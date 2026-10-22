<?php
declare(strict_types=1);

/**
 * Datenschutz (DSGVO): Auskunft/Übertragbarkeit (Art. 15, 20), Löschung (Art. 17), Speicherbegrenzung (Art. 5 Abs. 1 lit. e).
 *
 * Löschkonzept:
 *  - Konten ohne buchhalterisch relevante Vorgänge werden vollständig gelöscht.
 *  - Konten mit bezahlten/abgeschlossenen/erstatteten Buchungen oder Auszahlungen werden sofort gesperrt und bereinigt
 *    (E-Mail, Passwort, Auszahlungsdaten, Nachrichten, Kennungen); Name/Firma bleiben nur für die gesetzliche Aufbewahrung
 *    der Belege erhalten (Art. 17 Abs. 3 lit. b) und werden nach Ablauf der Frist anonymisiert.
 */
final class Privacy
{
    public static function retentionYears(): int
    {
        return max(1, (int) config('privacy.retention_years', 10));
    }

    // ------------------------------------------------------------------ Auskunft / Export

    /** Alle zu einem Benutzer gespeicherten Daten (ohne Passwort-Hash und ohne personenbezogene Daten von Vertragspartnern). */
    public static function export(int $userId): array
    {
        $u = one('SELECT id, email, name, company, role, status, payout_details, external_source, external_id, consent_at, consent_version,
                  last_login_at, created_at, updated_at FROM users WHERE id = ?', [$userId]);
        if (!$u) {
            throw new UserError('Benutzer nicht gefunden.');
        }
        $bookingCols = 'b.id, b.reference, b.campaign_id, b.slot_id, b.start_date, b.end_date, b.days, b.price_per_day, b.total_amount,
            b.commission_bp, b.commission_amount, b.publisher_amount, b.currency, b.status, b.payment_status, b.invoice_no, b.message,
            b.decision_note, b.created_at, b.decided_at, b.paid_at';
        $asAdv = all("SELECT 'advertiser' AS your_role, $bookingCols FROM bookings b WHERE b.advertiser_id = ? ORDER BY b.id", [$userId]);
        $asPub = all("SELECT 'publisher' AS your_role, $bookingCols FROM bookings b WHERE b.publisher_id = ? ORDER BY b.id", [$userId]);

        return [
            'meta' => [
                'platform'     => setting('platform_name'),
                'generated_at' => date('c'),
                'user_id'      => (int) $userId,
                'format'       => 'JSON, UTF-8. Beträge in Cent. Enthält Ihre Daten nach Art. 15 und 20 DSGVO. Personenbezogene Daten von Vertragspartnern sind aus Gründen des Schutzes Dritter nicht enthalten.',
            ],
            'profile'   => $u,
            'sites'     => all('SELECT id, name, url, category, description, monthly_visitors, status, admin_note, created_at FROM sites WHERE user_id = ? ORDER BY id', [$userId]),
            'ad_slots'  => all('SELECT s.id, s.site_id, s.name, s.format, s.placement, s.description, s.pricing_model, s.price_per_day, s.min_days, s.max_days,
                                s.available_from, s.available_until, s.status, s.admin_note, s.created_at
                                FROM ad_slots s JOIN sites si ON si.id = s.site_id WHERE si.user_id = ? ORDER BY s.id', [$userId]),
            'campaigns' => all('SELECT id, name, target_url, alt_text, banner_mime, banner_width, banner_height, banner_size, status, admin_note, created_at
                                FROM campaigns WHERE user_id = ? ORDER BY id', [$userId]),
            'bookings'  => array_merge($asAdv, $asPub),
            'payments'  => all('SELECT p.id, p.booking_id, p.method, p.provider_ref, p.amount, p.currency, p.note, p.received_on, p.created_at
                                FROM payments p JOIN bookings b ON b.id = p.booking_id WHERE b.advertiser_id = ? ORDER BY p.id', [$userId]),
            'payouts'   => all('SELECT id, amount, currency, method, note, paid_on, created_at FROM payouts WHERE publisher_id = ? ORDER BY id', [$userId]),
            'ledger'    => all('SELECT id, booking_id, payout_id, type, amount, currency, description, created_at FROM ledger WHERE user_id = ? ORDER BY id', [$userId]),
            'audit_log' => all('SELECT id, action, entity, entity_id, details, ip, created_at FROM audit_log WHERE user_id = ? ORDER BY id', [$userId]),
        ];
    }

    /** Sendet den Export als Datei-Download und beendet die Anfrage. */
    public static function sendExport(int $userId): never
    {
        $json = json_encode(self::export($userId), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        audit('privacy.export', 'user', $userId);
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="vylaris-daten-' . $userId . '-' . date('Ymd') . '.json"');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store');
        header('Content-Length: ' . strlen($json));
        echo $json;
        exit;
    }

    // ------------------------------------------------------------------ Löschung

    /** @return list<string> Gründe, die eine Löschung aktuell verhindern (leer = möglich). */
    public static function blockers(array $user): array
    {
        $id = (int) $user['id'];
        $out = [];
        if ($user['role'] === 'admin') {
            $out[] = 'Administrator-Konten können nicht über diese Funktion gelöscht werden.';
        }
        $paid = (int) val("SELECT COUNT(*) FROM bookings WHERE (advertiser_id = ? OR publisher_id = ?) AND status = 'paid'", [$id, $id]);
        if ($paid > 0) {
            $out[] = "Es bestehen $paid bezahlte, noch nicht abgeschlossene Buchung(en). Bitte warten Sie das Laufzeitende ab oder wenden Sie sich für eine Erstattung an den Administrator.";
        }
        if (can_publish($user)) {
            $balance = Billing::publisherBalance($id);
            if ($balance['available'] > 0) {
                $out[] = 'Es besteht ein auszahlbares Guthaben von ' . money($balance['available']) . '. Bitte lassen Sie sich dieses zuerst vom Administrator auszahlen.';
            }
        }
        return $out;
    }

    /** Hat das Konto buchhalterisch relevante Vorgänge (Aufbewahrungspflicht)? */
    private static function hasAccountingRecords(int $id): bool
    {
        return (int) val("SELECT COUNT(*) FROM bookings WHERE (advertiser_id = ? OR publisher_id = ?) AND status IN ('paid','completed','refunded')", [$id, $id]) > 0
            || (int) val('SELECT COUNT(*) FROM payouts WHERE publisher_id = ?', [$id]) > 0;
    }

    /**
     * Löscht bzw. sperrt das Konto. Offene unbezahlte Anfragen/Buchungen werden storniert.
     * @return 'deleted'|'retained' deleted = vollständig gelöscht, retained = gesperrt, Identität bis Fristende aufbewahrt
     */
    public static function deleteAccount(int $userId, ?int $actorId = null): string
    {
        $files = [];
        $mode = tx(function () use ($userId, &$files): string {
            $u = one('SELECT * FROM users WHERE id = ? FOR UPDATE', [$userId]);
            if (!$u || $u['status'] === 'deleted') {
                throw new UserError('Konto nicht gefunden oder bereits gelöscht.');
            }
            if ($blockers = self::blockers($u)) {
                throw new UserError(implode(' ', $blockers));
            }
            // Unbezahlte offene Vorgänge beenden (es ist kein Geld im Spiel)
            q("UPDATE bookings SET status = 'cancelled', decision_note = 'Konto eines Vertragspartners wurde gelöscht.'
               WHERE (advertiser_id = ? OR publisher_id = ?) AND status IN ('requested','accepted')", [$userId, $userId]);
            // Nicht buchhalterische Buchungen entfernen
            $ids = "SELECT id FROM (SELECT id FROM bookings WHERE (advertiser_id = ? OR publisher_id = ?) AND status IN ('declined','cancelled','expired')) x";
            q("DELETE FROM booking_stats WHERE booking_id IN ($ids)", [$userId, $userId]);
            q("DELETE FROM bookings WHERE (advertiser_id = ? OR publisher_id = ?) AND status IN ('declined','cancelled','expired')", [$userId, $userId]);

            // Kampagnen/Werbeplätze/Webseiten ohne verbleibende Buchung entfernen
            foreach (all('SELECT banner_file FROM campaigns WHERE user_id = ? AND id NOT IN (SELECT campaign_id FROM bookings)', [$userId]) as $c) {
                $files[] = $c['banner_file'];
            }
            q('DELETE FROM campaigns WHERE user_id = ? AND id NOT IN (SELECT campaign_id FROM bookings)', [$userId]);
            q('DELETE FROM ad_slots WHERE site_id IN (SELECT id FROM (SELECT id FROM sites WHERE user_id = ?) x) AND id NOT IN (SELECT slot_id FROM bookings)', [$userId]);
            q('DELETE FROM sites WHERE user_id = ? AND id NOT IN (SELECT site_id FROM ad_slots)', [$userId]);
            q("UPDATE ad_slots SET status = 'paused' WHERE site_id IN (SELECT id FROM (SELECT id FROM sites WHERE user_id = ?) x)", [$userId]);
            q("UPDATE sites SET status = 'paused' WHERE user_id = ?", [$userId]);
            q('DELETE FROM login_attempts WHERE email = ?', [$u['email']]);
            q('DELETE FROM mail_queue WHERE user_id = ? OR to_email = ?', [$userId, $u['email']]);
            q('DELETE FROM email_tokens WHERE user_id = ?', [$userId]);

            if (!self::hasAccountingRecords($userId)) {
                q('UPDATE audit_log SET user_id = NULL, ip = \'\' WHERE user_id = ?', [$userId]);
                q('DELETE FROM users WHERE id = ?', [$userId]);
                return 'deleted';
            }

            // Aufbewahrung: Fristende = Ende des Geschäftsjahres der letzten Buchung/Auszahlung + Aufbewahrungsjahre
            $last = max(
                (string) val("SELECT COALESCE(MAX(GREATEST(end_date, DATE(COALESCE(paid_at, end_date)))), '1970-01-01') FROM bookings WHERE advertiser_id = ? OR publisher_id = ?", [$userId, $userId]),
                (string) val("SELECT COALESCE(MAX(paid_on), '1970-01-01') FROM payouts WHERE publisher_id = ?", [$userId]),
                date('Y-m-d')
            );
            $until = ((int) substr($last, 0, 4) + self::retentionYears()) . '-12-31';
            q("UPDATE bookings SET message = '' WHERE advertiser_id = ?", [$userId]);
            q("UPDATE users SET email = ?, password_hash = ?, status = 'deleted', payout_details = NULL, external_source = NULL, external_id = NULL,
               last_login_at = NULL, deleted_at = NOW(), anonymize_after = ? WHERE id = ?",
                ['deleted-' . $userId . '@deleted.invalid', password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT), $until, $userId]);
            q("UPDATE audit_log SET ip = '' WHERE user_id = ?", [$userId]);
            return 'retained';
        });

        foreach ($files as $f) {
            Upload::delete((string) $f);
        }
        // Ohne Verknüpfung zum gelöschten Konto protokollieren (bei Selbstlöschung existiert der Benutzer ggf. nicht mehr)
        q('INSERT INTO audit_log (user_id, action, entity, entity_id, details, ip) VALUES (?, ?, ?, ?, ?, ?)', [
            $actorId !== null && $actorId !== $userId ? $actorId : null, 'user.deleted', 'user', $userId,
            json_encode(['mode' => $mode, 'by' => $actorId === null || $actorId === $userId ? 'self' : 'admin']), PHP_SAPI === 'cli' ? 'cli' : client_ip(),
        ]);
        Hooks::fire('user.deleted', ['user_id' => $userId, 'mode' => $mode]);
        return $mode;
    }

    /** Anonymisiert die Identität eines gesperrten Kontos nach Ablauf der Aufbewahrungsfrist. */
    public static function anonymize(int $userId): void
    {
        $files = [];
        tx(function () use ($userId, &$files) {
            $u = one("SELECT id FROM users WHERE id = ? AND status = 'deleted' FOR UPDATE", [$userId]);
            if (!$u) {
                return;
            }
            q("UPDATE users SET name = CONCAT('Gelöschter Benutzer #', id), company = NULL, consent_at = NULL, consent_version = NULL, anonymize_after = NULL WHERE id = ?", [$userId]);
            foreach (all('SELECT banner_file FROM campaigns WHERE user_id = ?', [$userId]) as $c) {
                $files[] = $c['banner_file'];
            }
            q("UPDATE campaigns SET name = CONCAT('Kampagne #', id), target_url = 'https://deleted.invalid/', alt_text = '' WHERE user_id = ?", [$userId]);
            q("UPDATE sites SET name = CONCAT('Webseite #', id), url = 'https://deleted.invalid/', description = '', admin_note = NULL WHERE user_id = ?", [$userId]);
            q("UPDATE ad_slots SET description = '', placement = '', admin_note = NULL WHERE site_id IN (SELECT id FROM (SELECT id FROM sites WHERE user_id = ?) x)", [$userId]);
            q('UPDATE audit_log SET details = NULL WHERE user_id = ?', [$userId]);
        });
        foreach ($files as $f) {
            Upload::delete((string) $f);
        }
        q('INSERT INTO audit_log (user_id, action, entity, entity_id, ip) VALUES (NULL, ?, ?, ?, ?)', ['user.anonymized', 'user', $userId, PHP_SAPI === 'cli' ? 'cli' : client_ip()]);
    }

    // ------------------------------------------------------------------ Speicherbegrenzung

    /** Täglich: IP-Adressen im Protokoll löschen, Login-Versuche bereinigen, Fristen auswerten. */
    public static function housekeeping(bool $force = false): void
    {
        if (!$force && (int) setting('privacy_hk_at', '0') > time() - 86400) {
            return;
        }
        set_setting('privacy_hk_at', (string) time());
        $days = max(1, (int) config('privacy.log_ip_days', 30));
        q("UPDATE audit_log SET ip = '' WHERE ip <> '' AND ip <> 'cli' AND created_at < NOW() - INTERVAL $days DAY");
        q('DELETE FROM login_attempts WHERE attempted_at < NOW() - INTERVAL 1 DAY');
        q("DELETE FROM mail_queue WHERE status = 'sent' AND sent_at < NOW() - INTERVAL 30 DAY");
        q("DELETE FROM mail_queue WHERE status = 'failed' AND created_at < NOW() - INTERVAL 14 DAY");
        q('DELETE FROM email_tokens WHERE expires_at < NOW() - INTERVAL 7 DAY');
        foreach (all("SELECT id FROM users WHERE status = 'deleted' AND anonymize_after IS NOT NULL AND anonymize_after < CURDATE()") as $row) {
            self::anonymize((int) $row['id']);
        }
    }
}
