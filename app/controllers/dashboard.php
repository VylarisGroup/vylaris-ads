<?php
declare(strict_types=1);

require_once __DIR__ . '/bookings.php';

function dashboard_index(): void
{
    $u = current_user();
    match ($u['role']) {
        'advertiser' => dashboard_advertiser($u),
        'publisher'  => dashboard_publisher($u),
        'both'       => dashboard_both($u),
        default      => dashboard_admin(),
    };
}

/** Konto mit beiden Rollen: Umschalter zwischen Werbetreibenden- und Betreiber-Ansicht (Auswahl wird in der Sitzung gemerkt). */
function dashboard_both(array $u): void
{
    $view = input('view');
    if (!in_array($view, ['advertiser', 'publisher'], true)) {
        $view = in_array($_SESSION['dash_view'] ?? '', ['advertiser', 'publisher'], true) ? $_SESSION['dash_view'] : 'advertiser';
    }
    $_SESSION['dash_view'] = $view;
    $view === 'publisher' ? dashboard_publisher($u, true) : dashboard_advertiser($u, true);
}

function dashboard_advertiser(array $u, bool $switch = false): void
{
    $id = (int) $u['id'];
    $kpi = one("SELECT
        COALESCE(SUM(CASE WHEN status IN ('paid','completed') THEN total_amount END),0) AS spent,
        COALESCE(SUM(CASE WHEN status = 'accepted' THEN total_amount END),0) AS due,
        SUM(status = 'requested') AS requested, SUM(status = 'accepted') AS accepted,
        SUM(status = 'paid' AND start_date <= CURDATE() AND end_date >= CURDATE()) AS live
        FROM bookings WHERE advertiser_id = ?", [$id]);
    $traffic = one('SELECT COALESCE(SUM(st.impressions),0) AS impressions, COALESCE(SUM(st.clicks),0) AS clicks
        FROM booking_stats st JOIN bookings b ON b.id = st.booking_id WHERE b.advertiser_id = ?', [$id]);
    $campaigns = one("SELECT COUNT(*) AS total, SUM(status='approved') AS approved, SUM(status='pending') AS pending, SUM(status='rejected') AS rejected
        FROM campaigns WHERE user_id = ?", [$id]);
    $todo = all(BOOKING_SELECT . " WHERE b.advertiser_id = ? AND b.status = 'accepted' ORDER BY b.start_date LIMIT 10", [$id]);
    $recent = all(BOOKING_SELECT . ' WHERE b.advertiser_id = ? ORDER BY b.created_at DESC LIMIT 8', [$id]);
    view('dashboard/advertiser', [
        'title' => 'Dashboard', 'switch' => $switch, 'kpi' => $kpi, 'traffic' => $traffic, 'campaigns' => $campaigns,
        'todo' => $todo, 'recent' => $recent, 'stats' => Billing::statsFor(array_column($recent, 'id')),
        'monthly' => Billing::monthly(6, null, $id),
    ]);
}

function dashboard_publisher(array $u, bool $switch = false): void
{
    $id = (int) $u['id'];
    $counts = one("SELECT
        (SELECT COUNT(*) FROM sites WHERE user_id = ?) AS sites,
        (SELECT COUNT(*) FROM sites WHERE user_id = ? AND status = 'pending') AS sites_pending,
        (SELECT COUNT(*) FROM ad_slots s JOIN sites si ON si.id = s.site_id WHERE si.user_id = ?) AS slots,
        (SELECT COUNT(*) FROM ad_slots s JOIN sites si ON si.id = s.site_id WHERE si.user_id = ? AND s.status = 'approved') AS slots_live",
        [$id, $id, $id, $id]);
    $kpi = one("SELECT SUM(status = 'requested') AS requested, SUM(status = 'accepted') AS accepted,
        SUM(status = 'paid' AND start_date <= CURDATE() AND end_date >= CURDATE()) AS live
        FROM bookings WHERE publisher_id = ?", [$id]);
    $traffic = one('SELECT COALESCE(SUM(st.impressions),0) AS impressions, COALESCE(SUM(st.clicks),0) AS clicks
        FROM booking_stats st JOIN bookings b ON b.id = st.booking_id WHERE b.publisher_id = ?', [$id]);
    $requests = all(BOOKING_SELECT . " WHERE b.publisher_id = ? AND b.status = 'requested' ORDER BY b.start_date LIMIT 10", [$id]);
    $running = all(BOOKING_SELECT . " WHERE b.publisher_id = ? AND b.status = 'paid' ORDER BY b.start_date LIMIT 10", [$id]);
    view('dashboard/publisher', [
        'title' => 'Dashboard', 'switch' => $switch, 'counts' => $counts, 'kpi' => $kpi, 'traffic' => $traffic,
        'requests' => $requests, 'running' => $running, 'stats' => Billing::statsFor(array_column($running, 'id')),
        'balance' => Billing::publisherBalance($id), 'monthly' => Billing::monthly(6, $id),
    ]);
}

function dashboard_admin(): void
{
    $reviews = one("SELECT
        (SELECT COUNT(*) FROM sites WHERE status = 'pending') AS sites,
        (SELECT COUNT(*) FROM ad_slots WHERE status = 'pending') AS slots,
        (SELECT COUNT(*) FROM campaigns WHERE status = 'pending') AS campaigns");
    $users = one("SELECT COUNT(*) AS total, SUM(role IN ('advertiser','both')) AS advertisers, SUM(role IN ('publisher','both')) AS publishers, SUM(status='blocked') AS blocked FROM users");
    $awaiting = all(BOOKING_SELECT . " WHERE b.status = 'accepted' ORDER BY b.payment_status = 'reported' DESC, b.start_date LIMIT 10");
    view('dashboard/admin', [
        'title' => 'Admin-Dashboard', 'summary' => Billing::platformSummary(), 'reviews' => $reviews, 'users' => $users,
        'awaiting' => $awaiting, 'monthly' => Billing::monthly(6)
    ]);
}
