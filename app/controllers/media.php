<?php
declare(strict_types=1);

/** Bannervorschau innerhalb der Plattform (nur für Berechtigte). */
function media_banner(): void
{
    $c = one('SELECT * FROM campaigns WHERE id = ?', [input_int('id')]);
    $u = current_user();
    $allowed = $c && ($u['role'] === 'admin' || (int) $c['user_id'] === (int) $u['id']
        || (int) val('SELECT COUNT(*) FROM bookings WHERE campaign_id = ? AND publisher_id = ?', [$c['id'], $u['id']]) > 0);
    if (!$allowed) {
        http_response_code(404);
        exit;
    }
    Upload::output($c['banner_file'], $c['banner_mime']);
}

function tracking_booking(): ?array
{
    $t = $_GET['t'] ?? '';
    if (!is_string($t) || !preg_match('/^[a-f0-9]{32}$/', $t)) {
        return null;
    }
    return one('SELECT b.id, b.status, b.start_date, b.end_date, c.banner_file, c.banner_mime, c.target_url
        FROM bookings b JOIN campaigns c ON c.id = b.campaign_id WHERE b.serve_token = ?', [$t]);
}

function tracking_is_live(array $b): bool
{
    $today = today();
    return $b['status'] === 'paid' && $b['start_date'] <= $today && $b['end_date'] >= $today;
}

function tracking_count(int $bookingId, string $column): void
{
    $ua = strtolower((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
    if ($ua === '' || preg_match('/bot|crawl|spider|slurp|preview|monitor/', $ua)) {
        return; // einfache Bot-Filterung
    }
    $column = $column === 'clicks' ? 'clicks' : 'impressions';
    q("INSERT INTO booking_stats (booking_id, day, $column) VALUES (?, CURDATE(), 1) ON DUPLICATE KEY UPDATE $column = $column + 1", [$bookingId]);
}

/**
 * Auslieferung des Banners auf der Webseite des Betreibers (zählt Impressionen).
 * Ausserhalb der bezahlten Laufzeit wird ein transparentes 1×1-GIF ausgeliefert.
 */
function media_serve(): void
{
    $b = tracking_booking();
    header('Cross-Origin-Resource-Policy: cross-origin');
    if ($b && tracking_is_live($b)) {
        tracking_count((int) $b['id'], 'impressions');
        Upload::output($b['banner_file'], $b['banner_mime'], 'no-store');
    }
    header('Content-Type: image/gif');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
}

/** Klick-Weiterleitung zur Ziel-URL der Kampagne (zählt Klicks während der Laufzeit). */
function media_click(): void
{
    $b = tracking_booking();
    if (!$b || !in_array($b['status'], ['paid', 'completed'], true) || !valid_url($b['target_url'])) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Nicht gefunden';
        return;
    }
    if (tracking_is_live($b)) {
        tracking_count((int) $b['id'], 'clicks');
    }
    header('Cache-Control: no-store');
    header('Referrer-Policy: no-referrer-when-downgrade');
    header('Location: ' . $b['target_url'], true, 302);
}
