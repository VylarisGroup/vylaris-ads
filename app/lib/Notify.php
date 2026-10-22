<?php
declare(strict_types=1);

/**
 * E-Mail-Benachrichtigungen zu Ereignissen (über den Hooks-Bus). Jede Nachricht enthält nur Referenz, Status und einen Link –
 * keine Zahlungs- oder Bankdaten. Empfänger können Benachrichtigungen unter „Mein Konto“ abbestellen (users.notify_email).
 * Nachrichten werden nur in die Warteschlange gelegt; die Zustellung erfolgt nach der Anfrage (siehe Mailer).
 */
final class Notify
{
    public static function register(): void
    {
        foreach (['requested', 'accepted', 'declined', 'cancelled', 'paid', 'refunded'] as $e) {
            Hooks::on('booking.' . $e, static fn (array $p) => self::booking((int) ($p['booking_id'] ?? 0), $e));
        }
        Hooks::on('entity.reviewed', static fn (array $p) => self::reviewed((string) ($p['type'] ?? ''), (int) ($p['id'] ?? 0), (string) ($p['decision'] ?? '')));
    }

    /** Sendet an ein Konto, sofern aktiv, bestätigt und nicht abbestellt. */
    private static function to(int $userId, string $subject, string $text): void
    {
        $u = one("SELECT id, email, name FROM users WHERE id = ? AND status = 'active' AND notify_email = 1 AND email_verified_at IS NOT NULL", [$userId]);
        if (!$u) return;
        $platform = setting('platform_name', 'VylarisAdvertise');
        Mailer::queue($u['email'], $u['name'], $subject . ' – ' . $platform,
            "Guten Tag {$u['name']}\n\n$text\n\n$platform\n\nSie erhalten diese Nachricht, weil Benachrichtigungen aktiviert sind. Einstellen unter: " . abs_url('account'), (int) $u['id']);
    }

    private static function booking(int $id, string $event): void
    {
        if (!Mailer::configured()) return;
        $b = one('SELECT b.reference, b.advertiser_id, b.publisher_id, b.start_date, b.end_date, s.name AS slot_name, si.name AS site_name
            FROM bookings b JOIN ad_slots s ON s.id = b.slot_id JOIN sites si ON si.id = s.site_id WHERE b.id = ?', [$id]);
        if (!$b) return;
        $what = $b['reference'] . ' – ' . $b['site_name'] . ', ' . $b['slot_name'] . ' (' . fdate($b['start_date']) . ' bis ' . fdate($b['end_date']) . ')';
        $link = "\n\nDetails: " . abs_url('booking', ['id' => $id]);
        switch ($event) {
            case 'requested':
                self::to((int) $b['publisher_id'], 'Neue Buchungsanfrage ' . $b['reference'], "für Ihren Werbeplatz ist eine neue Buchungsanfrage eingegangen:\n\n$what\n\nBitte nehmen Sie sie an oder lehnen Sie sie ab." . $link);
                break;
            case 'accepted':
                self::to((int) $b['advertiser_id'], 'Buchung angenommen ' . $b['reference'], "Ihre Buchungsanfrage wurde angenommen:\n\n$what\n\nDie Zahlungsinformationen finden Sie bei der Buchung." . $link);
                break;
            case 'declined':
                self::to((int) $b['advertiser_id'], 'Buchung abgelehnt ' . $b['reference'], "Ihre Buchungsanfrage wurde leider nicht angenommen:\n\n$what" . $link);
                break;
            case 'cancelled':
                $t = "Die folgende Buchung wurde storniert:\n\n$what" . $link;
                self::to((int) $b['advertiser_id'], 'Buchung storniert ' . $b['reference'], $t);
                self::to((int) $b['publisher_id'], 'Buchung storniert ' . $b['reference'], $t);
                break;
            case 'paid':
                self::to((int) $b['advertiser_id'], 'Zahlung bestätigt ' . $b['reference'], "Ihre Zahlung ist eingegangen, die Buchung ist bestätigt:\n\n$what" . $link);
                self::to((int) $b['publisher_id'], 'Buchung bestätigt ' . $b['reference'], "Die Zahlung für die folgende Buchung ist eingegangen, die Anzeige kann zum Starttermin geschaltet werden:\n\n$what" . $link);
                break;
            case 'refunded':
                self::to((int) $b['advertiser_id'], 'Erstattung ' . $b['reference'], "Zu der folgenden Buchung wurde eine Erstattung erfasst:\n\n$what" . $link);
                break;
        }
    }

    private static function reviewed(string $type, int $id, string $decision): void
    {
        if (!Mailer::configured()) return;
        $row = match ($type) {
            'sites' => one('SELECT user_id, name, admin_note FROM sites WHERE id = ?', [$id]),
            'slots' => one('SELECT si.user_id, s.name, s.admin_note FROM ad_slots s JOIN sites si ON si.id = s.site_id WHERE s.id = ?', [$id]),
            'campaigns' => one('SELECT user_id, name, admin_note FROM campaigns WHERE id = ?', [$id]),
            default => null,
        };
        if (!$row) return;
        $label = ['sites' => 'Webseite', 'slots' => 'Werbeplatz', 'campaigns' => 'Kampagne'][$type];
        $page = ['sites' => 'sites', 'slots' => 'sites', 'campaigns' => 'campaigns'][$type];
        if ($decision === 'approved') {
            self::to((int) $row['user_id'], "$label freigegeben", "Ihr(e) $label „{$row['name']}“ wurde freigegeben.\n\nÜbersicht: " . abs_url($page));
        } elseif ($decision === 'rejected') {
            self::to((int) $row['user_id'], "$label abgelehnt", "Ihr(e) $label „{$row['name']}“ wurde abgelehnt.\n\nBegründung: " . ($row['admin_note'] ?: '–') . "\n\nSie können die Angaben anpassen und erneut einreichen: " . abs_url($page));
        }
    }
}
