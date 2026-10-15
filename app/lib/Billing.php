<?php
declare(strict_types=1);

/**
 * Abrechnung: Journal (ledger), Guthaben der Webseitenbetreiber, Auszahlungen, Kennzahlen.
 */
final class Billing
{
    public static function ledger(?int $bookingId, ?int $payoutId, ?int $userId, string $type, int $amount, string $currency, string $description, ?int $by): void
    {
        q('INSERT INTO ledger (booking_id, payout_id, user_id, type, amount, currency, description, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$bookingId, $payoutId, $userId, $type, $amount, $currency, mb_substr($description, 0, 255), $by]);
    }

    /**
     * Guthaben eines Webseitenbetreibers.
     *  pending   = Erlöse aus bezahlten, noch laufenden/geplanten Buchungen
     *  earned    = Erlöse aus abgeschlossenen Buchungen
     *  paid_out  = bereits ausgezahlt
     *  available = earned - paid_out (auszahlbar)
     */
    public static function publisherBalance(int $publisherId): array
    {
        $pending = (int) val("SELECT COALESCE(SUM(publisher_amount),0) FROM bookings WHERE publisher_id = ? AND status = 'paid'", [$publisherId]);
        $earned = (int) val("SELECT COALESCE(SUM(publisher_amount),0) FROM bookings WHERE publisher_id = ? AND status = 'completed'", [$publisherId]);
        $paidOut = (int) val('SELECT COALESCE(SUM(amount),0) FROM payouts WHERE publisher_id = ?', [$publisherId]);
        return ['pending' => $pending, 'earned' => $earned, 'paid_out' => $paidOut, 'available' => $earned - $paidOut];
    }

    public static function recordPayout(int $adminId, int $publisherId, int $amount, string $method, string $paidOn, string $note): int
    {
        $id = tx(function () use ($adminId, $publisherId, $amount, $method, $paidOn, $note) {
            // Sperre auf den Benutzer verhindert parallele Doppel-Auszahlungen
            $pub = one("SELECT id, role FROM users WHERE id = ? FOR UPDATE", [$publisherId]);
            if (!$pub || !role_allows($pub['role'], 'publisher')) {
                throw new UserError('Webseitenbetreiber nicht gefunden.');
            }
            if ($amount <= 0) {
                throw new UserError('Bitte einen gültigen Betrag angeben.');
            }
            if (!parse_date($paidOn) || $paidOn > today()) {
                throw new UserError('Bitte ein gültiges Auszahlungsdatum angeben.');
            }
            if (!in_array($method, ['bank_transfer', 'cash', 'other'], true)) {
                throw new UserError('Ungültige Auszahlungsart.');
            }
            $balance = self::publisherBalance($publisherId);
            if ($amount > $balance['available']) {
                throw new UserError('Der Betrag übersteigt das auszahlbare Guthaben von ' . money($balance['available']) . '.');
            }
            $min = (int) setting('min_payout', '0');
            if ($amount < $min && $amount !== $balance['available']) {
                throw new UserError('Teilauszahlungen müssen mindestens ' . money($min) . ' betragen (oder dem gesamten Guthaben entsprechen).');
            }
            $currency = setting('currency', 'CHF');
            q('INSERT INTO payouts (publisher_id, amount, currency, method, note, paid_on, recorded_by) VALUES (?, ?, ?, ?, ?, ?, ?)',
                [$publisherId, $amount, $currency, $method, mb_substr($note, 0, 500), $paidOn, $adminId]);
            $id = (int) db()->lastInsertId();
            self::ledger(null, $id, $publisherId, 'publisher_payout', -$amount, $currency, 'Auszahlung #' . $id, $adminId);
            audit('payout.recorded', 'payout', $id, ['publisher_id' => $publisherId, 'amount' => $amount]);
            return $id;
        });
        Hooks::fire('payout.recorded', ['payout_id' => $id, 'publisher_id' => $publisherId]);
        return $id;
    }

    /** Kennzahlen für das Admin-Dashboard. */
    public static function platformSummary(): array
    {
        $r = one("SELECT
            COALESCE(SUM(CASE WHEN status IN ('paid','completed') THEN total_amount END),0) AS gross,
            COALESCE(SUM(CASE WHEN status IN ('paid','completed') THEN commission_amount END),0) AS commission,
            COALESCE(SUM(CASE WHEN status IN ('paid','completed') THEN publisher_amount END),0) AS publisher_share,
            COALESCE(SUM(CASE WHEN status = 'accepted' THEN total_amount END),0) AS receivables,
            SUM(status = 'requested') AS open_requests,
            SUM(status = 'accepted') AS awaiting_payment,
            SUM(status = 'accepted' AND payment_status = 'reported') AS reported_payments,
            SUM(status = 'paid' AND start_date <= CURDATE() AND end_date >= CURDATE()) AS live
            FROM bookings");
        $r['paid_out'] = (int) val('SELECT COALESCE(SUM(amount),0) FROM payouts');
        $r['payable'] = (int) val("SELECT COALESCE(SUM(publisher_amount),0) FROM bookings WHERE status = 'completed'") - $r['paid_out'];
        return array_map(fn ($v) => (int) $v, $r);
    }

    /** Umsatz der letzten n Monate (nach Zahlungseingang). */
    public static function monthly(int $months = 6, ?int $publisherId = null, ?int $advertiserId = null): array
    {
        $where = "status IN ('paid','completed') AND paid_at >= ?";
        $params = [date('Y-m-01', strtotime('-' . ($months - 1) . ' months'))];
        if ($publisherId) {
            $where .= ' AND publisher_id = ?';
            $params[] = $publisherId;
        }
        if ($advertiserId) {
            $where .= ' AND advertiser_id = ?';
            $params[] = $advertiserId;
        }
        $rows = all("SELECT DATE_FORMAT(paid_at, '%Y-%m') AS ym, SUM(total_amount) AS gross, SUM(commission_amount) AS commission,
                     SUM(publisher_amount) AS publisher FROM bookings WHERE $where GROUP BY ym", $params);
        $byMonth = array_column($rows, null, 'ym');
        $out = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $ym = date('Y-m', strtotime(date('Y-m-01') . " -$i months"));
            $out[] = [
                'ym' => $ym,
                'gross' => (int) ($byMonth[$ym]['gross'] ?? 0),
                'commission' => (int) ($byMonth[$ym]['commission'] ?? 0),
                'publisher' => (int) ($byMonth[$ym]['publisher'] ?? 0),
            ];
        }
        return $out;
    }

    /** Impressionen/Klicks je Buchung (Gesamtsummen). */
    public static function statsFor(array $bookingIds): array
    {
        if (!$bookingIds) {
            return [];
        }
        $in = implode(',', array_fill(0, count($bookingIds), '?'));
        $rows = all("SELECT booking_id, SUM(impressions) AS impressions, SUM(clicks) AS clicks FROM booking_stats WHERE booking_id IN ($in) GROUP BY booking_id",
            array_values($bookingIds));
        return array_column($rows, null, 'booking_id');
    }
}
