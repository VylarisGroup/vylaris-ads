<?php
declare(strict_types=1);

/**
 * Buchungslogik inkl. Verfügbarkeitsprüfung, Statusübergängen und Zahlungsbestätigung.
 *
 * Schutz gegen Doppelbuchungen: Jede Operation, die einen Zeitraum belegt, sperrt zuerst die
 * Zeile des Werbeplatzes (SELECT … FOR UPDATE) und prüft danach Überschneidungen. Parallele
 * Anfragen für denselben Werbeplatz werden dadurch serialisiert.
 *
 * Schutz gegen Preismanipulation: Preise, Provision und Beträge werden ausschliesslich serverseitig
 * aus der Datenbank berechnet und beim Anlegen der Buchung als Snapshot gespeichert.
 * Statusänderungen erfolgen nur über die Methoden dieser Klasse mit Prüfung des Ausgangsstatus.
 */
final class Booking
{
    /** Status, die einen Zeitraum belegen. */
    public const BLOCKING = ['accepted', 'paid', 'completed'];

    public const MAX_LEAD_DAYS = 365;

    // -----------------------------------------------------------------------
    // Abfragen
    // -----------------------------------------------------------------------

    public static function overlapCount(int $slotId, string $start, string $end, ?int $excludeId = null): int
    {
        return (int) val(
            "SELECT COUNT(*) FROM bookings WHERE slot_id = ? AND status IN ('accepted','paid','completed')
             AND start_date <= ? AND end_date >= ? AND id <> ?",
            [$slotId, $end, $start, $excludeId ?? 0]
        );
    }

    /** Belegte Zeiträume ab heute (für Kalender/Anzeige). */
    public static function bookedRanges(int $slotId): array
    {
        return all(
            "SELECT start_date, end_date, status FROM bookings WHERE slot_id = ? AND status IN ('accepted','paid','completed')
             AND end_date >= CURDATE() ORDER BY start_date",
            [$slotId]
        );
    }

    /**
     * Berechnet Laufzeit und Preis serverseitig und prüft die Regeln des Werbeplatzes.
     * @return array{days:int,price_per_day:int,total:int,commission_bp:int,commission:int,publisher_amount:int}
     */
    public static function quote(array $slot, string $start, string $end): array
    {
        $s = parse_date($start);
        $e = parse_date($end);
        if (!$s || !$e) {
            throw new UserError('Bitte gültige Start- und Enddaten angeben.');
        }
        if ($e < $s) {
            throw new UserError('Das Enddatum muss nach dem Startdatum liegen.');
        }
        if ($s < today()) {
            throw new UserError('Der Startzeitpunkt darf nicht in der Vergangenheit liegen.');
        }
        if ($s > date('Y-m-d', strtotime('+' . self::MAX_LEAD_DAYS . ' days'))) {
            throw new UserError('Buchungen sind höchstens ' . self::MAX_LEAD_DAYS . ' Tage im Voraus möglich.');
        }
        if ($s < $slot['available_from']) {
            throw new UserError('Der Werbeplatz ist erst ab ' . fdate($slot['available_from']) . ' verfügbar.');
        }
        if (!empty($slot['available_until']) && $e > $slot['available_until']) {
            throw new UserError('Der Werbeplatz ist nur bis ' . fdate($slot['available_until']) . ' verfügbar.');
        }
        $days = (int) (new DateTimeImmutable($s))->diff(new DateTimeImmutable($e))->days + 1;
        if ($days < (int) $slot['min_days'] || $days > (int) $slot['max_days']) {
            throw new UserError(sprintf('Die Laufzeit muss zwischen %d und %d Tagen liegen.', $slot['min_days'], $slot['max_days']));
        }
        $ppd = (int) $slot['price_per_day'];
        $total = $ppd * $days;
        $bp = max(0, min(10000, (int) setting('commission_bp', '1500')));
        $commission = intdiv($total * $bp + 5000, 10000);
        return [
            'days' => $days, 'price_per_day' => $ppd, 'total' => $total,
            'commission_bp' => $bp, 'commission' => $commission, 'publisher_amount' => $total - $commission,
        ];
    }

    /** Laufphase einer bezahlten Buchung. */
    public static function phase(array $b): string
    {
        if ($b['status'] !== 'paid') {
            return $b['status'];
        }
        $t = today();
        if ($t < $b['start_date']) {
            return 'scheduled';
        }
        return $t > $b['end_date'] ? 'completed' : 'live';
    }

    // -----------------------------------------------------------------------
    // Übergänge
    // -----------------------------------------------------------------------

    private static function lockSlot(int $slotId): array
    {
        $slot = one(
            'SELECT s.*, si.user_id AS publisher_id, si.status AS site_status, u.status AS publisher_status
             FROM ad_slots s JOIN sites si ON si.id = s.site_id JOIN users u ON u.id = si.user_id
             WHERE s.id = ? FOR UPDATE',
            [$slotId]
        );
        if (!$slot) {
            throw new UserError('Werbeplatz nicht gefunden.');
        }
        return $slot;
    }

    private static function lockBooking(int $bookingId): array
    {
        $slotId = (int) val('SELECT slot_id FROM bookings WHERE id = ?', [$bookingId]);
        if (!$slotId) {
            throw new UserError('Buchung nicht gefunden.');
        }
        self::lockSlot($slotId); // stets zuerst Werbeplatz, dann Buchung sperren (Deadlock-Vermeidung)
        return one('SELECT * FROM bookings WHERE id = ? FOR UPDATE', [$bookingId]);
    }

    public static function request(int $advertiserId, int $slotId, int $campaignId, string $start, string $end, string $message): int
    {
        $id = tx(function () use ($advertiserId, $slotId, $campaignId, $start, $end, $message) {
            $slot = self::lockSlot($slotId);
            if ($slot['status'] !== 'approved' || $slot['site_status'] !== 'approved' || $slot['publisher_status'] !== 'active') {
                throw new UserError('Dieser Werbeplatz ist derzeit nicht buchbar.');
            }
            if ((int) $slot['publisher_id'] === $advertiserId) {
                throw new UserError('Eigene Werbeplätze können nicht gebucht werden.');
            }
            $campaign = one('SELECT * FROM campaigns WHERE id = ? AND user_id = ?', [$campaignId, $advertiserId]);
            if (!$campaign) {
                throw new UserError('Bitte eine eigene Kampagne auswählen.');
            }
            if ($campaign['status'] !== 'approved') {
                throw new UserError('Die Kampagne muss zuerst von einem Administrator freigegeben werden.');
            }
            if ((int) $campaign['banner_width'] !== (int) $slot['width'] || (int) $campaign['banner_height'] !== (int) $slot['height']) {
                throw new UserError(sprintf('Das Banner (%d×%d) passt nicht zum Format des Werbeplatzes (%d×%d).',
                    $campaign['banner_width'], $campaign['banner_height'], $slot['width'], $slot['height']));
            }
            $quote = self::quote($slot, $start, $end);
            if (self::overlapCount($slotId, $start, $end) > 0) {
                throw new UserError('Der gewählte Zeitraum ist bereits belegt.');
            }
            $dup = (int) val(
                "SELECT COUNT(*) FROM bookings WHERE slot_id = ? AND advertiser_id = ? AND status = 'requested'
                 AND start_date <= ? AND end_date >= ?",
                [$slotId, $advertiserId, $end, $start]
            );
            if ($dup > 0) {
                throw new UserError('Sie haben für diesen Zeitraum bereits eine offene Anfrage gestellt.');
            }
            q(
                'INSERT INTO bookings (campaign_id, slot_id, advertiser_id, publisher_id, start_date, end_date, days, pricing_model,
                 price_per_day, total_amount, commission_bp, commission_amount, publisher_amount, currency, message)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$campaignId, $slotId, $advertiserId, $slot['publisher_id'], $start, $end, $quote['days'], $slot['pricing_model'],
                 $quote['price_per_day'], $quote['total'], $quote['commission_bp'], $quote['commission'], $quote['publisher_amount'],
                 setting('currency', 'CHF'), mb_substr($message, 0, 1000)]
            );
            $id = (int) db()->lastInsertId();
            $ref = strtoupper(setting('invoice_prefix', 'VA')) . '-B' . str_pad((string) $id, 6, '0', STR_PAD_LEFT);
            q('UPDATE bookings SET reference = ? WHERE id = ?', [$ref, $id]);
            audit('booking.requested', 'booking', $id, ['total' => $quote['total'], 'start' => $start, 'end' => $end]);
            return $id;
        });
        Hooks::fire('booking.requested', ['booking_id' => $id]);
        return $id;
    }

    public static function accept(int $publisherId, int $bookingId, string $note = ''): void
    {
        $declined = tx(function () use ($publisherId, $bookingId, $note) {
            $b = self::lockBooking($bookingId);
            if ((int) $b['publisher_id'] !== $publisherId) {
                throw new UserError('Buchung nicht gefunden.');
            }
            if ($b['status'] !== 'requested') {
                throw new UserError('Nur offene Anfragen können angenommen werden.');
            }
            if ($b['start_date'] < today()) {
                throw new UserError('Der Startzeitpunkt dieser Anfrage liegt bereits in der Vergangenheit.');
            }
            if (self::overlapCount((int) $b['slot_id'], $b['start_date'], $b['end_date'], $bookingId) > 0) {
                throw new UserError('Der Zeitraum ist inzwischen durch eine andere Buchung belegt.');
            }
            q("UPDATE bookings SET status = 'accepted', decided_at = NOW(), decision_note = ? WHERE id = ? AND status = 'requested'",
                [mb_substr($note, 0, 500) ?: null, $bookingId]);
            // Konkurrierende offene Anfragen für denselben Zeitraum automatisch ablehnen
            $others = all(
                "SELECT id FROM bookings WHERE slot_id = ? AND status = 'requested' AND id <> ? AND start_date <= ? AND end_date >= ?",
                [$b['slot_id'], $bookingId, $b['end_date'], $b['start_date']]
            );
            foreach ($others as $o) {
                q("UPDATE bookings SET status = 'declined', decided_at = NOW(), decision_note = 'Zeitraum wurde anderweitig vergeben.' WHERE id = ?", [$o['id']]);
                audit('booking.auto_declined', 'booking', (int) $o['id'], ['because' => $bookingId]);
            }
            audit('booking.accepted', 'booking', $bookingId);
            return array_column($others, 'id');
        });
        Hooks::fire('booking.accepted', ['booking_id' => $bookingId]);
        foreach ($declined as $id) {
            Hooks::fire('booking.declined', ['booking_id' => (int) $id, 'auto' => true]);
        }
    }

    public static function decline(int $publisherId, int $bookingId, string $note): void
    {
        tx(function () use ($publisherId, $bookingId, $note) {
            $b = self::lockBooking($bookingId);
            if ((int) $b['publisher_id'] !== $publisherId) {
                throw new UserError('Buchung nicht gefunden.');
            }
            if ($b['status'] !== 'requested') {
                throw new UserError('Nur offene Anfragen können abgelehnt werden.');
            }
            q("UPDATE bookings SET status = 'declined', decided_at = NOW(), decision_note = ? WHERE id = ?", [mb_substr($note, 0, 500) ?: null, $bookingId]);
            audit('booking.declined', 'booking', $bookingId, ['note' => $note]);
        });
        Hooks::fire('booking.declined', ['booking_id' => $bookingId]);
    }

    /** Stornierung unbezahlter Buchungen durch Werbetreibenden, Betreiber oder Admin. */
    public static function cancel(array $actor, int $bookingId, string $note = ''): void
    {
        tx(function () use ($actor, $bookingId, $note) {
            $b = self::lockBooking($bookingId);
            // Die Berechtigung ergibt sich aus der Beziehung zur Buchung (ein Konto kann beide Rollen haben)
            $isAdv = (int) $b['advertiser_id'] === (int) $actor['id'];
            $isPub = (int) $b['publisher_id'] === (int) $actor['id'];
            $allowed = $actor['role'] === 'admin' || $isAdv || ($isPub && $b['status'] === 'accepted');
            if (!$allowed) {
                throw new UserError('Diese Buchung kann von Ihnen nicht storniert werden.');
            }
            if (!in_array($b['status'], ['requested', 'accepted'], true)) {
                throw new UserError('Nur unbezahlte Buchungen können storniert werden. Bezahlte Buchungen erstattet der Administrator.');
            }
            q("UPDATE bookings SET status = 'cancelled', decision_note = ? WHERE id = ? AND status IN ('requested','accepted')",
                [mb_substr($note, 0, 500) ?: null, $bookingId]);
            audit('booking.cancelled', 'booking', $bookingId, ['by_role' => $actor['role'] === 'admin' ? 'admin' : ($isAdv ? 'advertiser' : 'publisher'), 'note' => $note]);
        });
        Hooks::fire('booking.cancelled', ['booking_id' => $bookingId]);
    }

    /** Werbetreibender meldet, dass er überwiesen hat (rein informativ). */
    public static function reportPayment(int $advertiserId, int $bookingId): void
    {
        $n = q("UPDATE bookings SET payment_status = 'reported' WHERE id = ? AND advertiser_id = ? AND status = 'accepted' AND payment_status = 'unpaid'",
            [$bookingId, $advertiserId])->rowCount();
        if ($n !== 1) {
            throw new UserError('Für diese Buchung kann keine Zahlung gemeldet werden.');
        }
        audit('booking.payment_reported', 'booking', $bookingId);
    }

    /**
     * Admin bestätigt den Zahlungseingang. Der Betrag ist immer der gespeicherte Buchungsbetrag –
     * er kann nicht über das Formular verändert werden.
     */
    public static function confirmPayment(int $adminId, int $bookingId, string $method, string $providerRef, string $receivedOn, string $note): void
    {
        tx(function () use ($adminId, $bookingId, $method, $providerRef, $receivedOn, $note) {
            $b = self::lockBooking($bookingId);
            if ($b['status'] !== 'accepted') {
                throw new UserError('Zahlungen können nur für angenommene, unbezahlte Buchungen erfasst werden.');
            }
            if ($b['end_date'] < today()) {
                throw new UserError('Die Laufzeit dieser Buchung ist bereits abgelaufen.');
            }
            if (!parse_date($receivedOn) || $receivedOn > today()) {
                throw new UserError('Bitte ein gültiges Eingangsdatum (nicht in der Zukunft) angeben.');
            }
            if (!in_array($method, ['bank_transfer', 'cash', 'other'], true)) {
                throw new UserError('Ungültige Zahlungsart.');
            }
            if (self::overlapCount((int) $b['slot_id'], $b['start_date'], $b['end_date'], $bookingId) > 0) {
                throw new UserError('Konflikt: Der Zeitraum ist durch eine andere Buchung belegt.');
            }
            // Fortlaufende Belegnummer – Zähler wird gesperrt, damit keine Nummer doppelt vergeben wird
            $counter = (int) val("SELECT value FROM settings WHERE name = 'invoice_counter' FOR UPDATE") + 1;
            q("UPDATE settings SET value = ? WHERE name = 'invoice_counter'", [(string) $counter]);
            $invoice = strtoupper(setting('invoice_prefix', 'VA')) . '-' . date('Y') . '-' . str_pad((string) $counter, 5, '0', STR_PAD_LEFT);

            q("UPDATE bookings SET status = 'paid', payment_status = 'paid', paid_at = NOW(), invoice_no = ?, serve_token = ?
               WHERE id = ? AND status = 'accepted'", [$invoice, bin2hex(random_bytes(16)), $bookingId]);
            q('INSERT INTO payments (booking_id, method, provider_ref, amount, currency, note, received_on, recorded_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [$bookingId, $method, $providerRef !== '' ? mb_substr($providerRef, 0, 120) : null, $b['total_amount'], $b['currency'],
                 mb_substr($note, 0, 500), $receivedOn, $adminId]);
            $ref = $b['reference'];
            Billing::ledger($bookingId, null, (int) $b['advertiser_id'], 'advertiser_payment', (int) $b['total_amount'], $b['currency'], "Zahlungseingang $ref ($invoice)", $adminId);
            Billing::ledger($bookingId, null, null, 'platform_commission', (int) $b['commission_amount'], $b['currency'], "Plattformprovision $ref (" . percent_bp($b['commission_bp']) . ')', $adminId);
            Billing::ledger($bookingId, null, (int) $b['publisher_id'], 'publisher_earning', (int) $b['publisher_amount'], $b['currency'], "Erlös Betreiber $ref", $adminId);
            audit('booking.paid', 'booking', $bookingId, ['amount' => $b['total_amount'], 'method' => $method, 'invoice' => $invoice]);
        });
        Hooks::fire('booking.paid', ['booking_id' => $bookingId]);
    }

    /** Erstattung einer bezahlten, noch nicht abgeschlossenen Buchung (Admin). */
    public static function refund(int $adminId, int $bookingId, string $note): void
    {
        tx(function () use ($adminId, $bookingId, $note) {
            $b = self::lockBooking($bookingId);
            if ($b['status'] !== 'paid') {
                throw new UserError('Nur bezahlte, noch nicht abgeschlossene Buchungen können erstattet werden.');
            }
            if (trim($note) === '') {
                throw new UserError('Bitte einen Grund für die Erstattung angeben.');
            }
            q("UPDATE bookings SET status = 'refunded', payment_status = 'refunded', decision_note = ? WHERE id = ? AND status = 'paid'",
                [mb_substr($note, 0, 500), $bookingId]);
            q('INSERT INTO payments (booking_id, method, amount, currency, note, received_on, recorded_by) VALUES (?, ?, ?, ?, ?, CURDATE(), ?)',
                [$bookingId, 'refund', -(int) $b['total_amount'], $b['currency'], mb_substr($note, 0, 500), $adminId]);
            $ref = $b['reference'];
            Billing::ledger($bookingId, null, (int) $b['advertiser_id'], 'refund', -(int) $b['total_amount'], $b['currency'], "Erstattung $ref", $adminId);
            Billing::ledger($bookingId, null, null, 'commission_reversal', -(int) $b['commission_amount'], $b['currency'], "Storno Provision $ref", $adminId);
            Billing::ledger($bookingId, null, (int) $b['publisher_id'], 'earning_reversal', -(int) $b['publisher_amount'], $b['currency'], "Storno Erlös $ref", $adminId);
            audit('booking.refunded', 'booking', $bookingId, ['note' => $note]);
        });
        Hooks::fire('booking.refunded', ['booking_id' => $bookingId]);
    }

    /**
     * Zeitgesteuerte Statuspflege (wird höchstens alle 5 Minuten bei Seitenaufrufen oder per Cron ausgeführt):
     *  - unbeantwortete Anfragen nach Startdatum -> expired
     *  - angenommene, unbezahlte Buchungen nach Enddatum -> expired
     *  - bezahlte Buchungen nach Enddatum -> completed (Erlös wird auszahlbar)
     */
    public static function housekeeping(bool $force = false): void
    {
        if (!$force && (int) setting('housekeeping_at', '0') > time() - 300) {
            return;
        }
        set_setting('housekeeping_at', (string) time());
        q("UPDATE bookings SET status = 'expired' WHERE status = 'requested' AND start_date < CURDATE()");
        q("UPDATE bookings SET status = 'expired' WHERE status = 'accepted' AND end_date < CURDATE()");
        $done = all("SELECT id FROM bookings WHERE status = 'paid' AND end_date < CURDATE()");
        foreach ($done as $row) {
            if (q("UPDATE bookings SET status = 'completed' WHERE id = ? AND status = 'paid'", [$row['id']])->rowCount() === 1) {
                Hooks::fire('booking.completed', ['booking_id' => (int) $row['id']]);
            }
        }
    }
}
