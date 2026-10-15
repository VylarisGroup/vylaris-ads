<?php
declare(strict_types=1);

const MARKET_BASE_SQL = "FROM ad_slots s
    JOIN sites si ON si.id = s.site_id
    JOIN users u ON u.id = si.user_id
    WHERE s.status = 'approved' AND si.status = 'approved' AND u.status = 'active'
      AND (s.available_until IS NULL OR s.available_until >= CURDATE())";

function market_index(): void
{
    $where = '';
    $params = [];
    $q = input('q');
    $format = input('format');
    $category = input('category');
    $maxPrice = input('max_price') !== '' ? parse_money(input('max_price')) : null;

    if ($q !== '') {
        $where .= ' AND (s.name LIKE ? OR si.name LIKE ? OR s.description LIKE ? OR si.description LIKE ?)';
        $like = '%' . addcslashes($q, '%_\\') . '%';
        array_push($params, $like, $like, $like, $like);
    }
    if (isset(AD_FORMATS[$format])) {
        $where .= ' AND s.format = ?';
        $params[] = $format;
    }
    if (in_array($category, SITE_CATEGORIES, true)) {
        $where .= ' AND si.category = ?';
        $params[] = $category;
    }
    if ($maxPrice !== null) {
        $where .= ' AND s.price_per_day <= ?';
        $params[] = $maxPrice;
    }
    $order = match (input('sort')) {
        'price_asc'  => 's.price_per_day ASC',
        'price_desc' => 's.price_per_day DESC',
        'visitors'   => 'si.monthly_visitors DESC',
        default      => 's.created_at DESC',
    };
    $result = paginate("SELECT s.*, si.name AS site_name, si.url AS site_url, si.category, si.monthly_visitors
        " . MARKET_BASE_SQL . $where . " ORDER BY $order, s.id DESC", $params, 12);

    view('market/index', ['title' => 'Werbeplätze entdecken', 'result' => $result]);
}

function market_slot(): void
{
    $id = input_int('id');
    $slot = one("SELECT s.*, si.name AS site_name, si.url AS site_url, si.category, si.description AS site_description,
        si.monthly_visitors, si.status AS site_status, si.user_id AS publisher_id, u.status AS publisher_status
        FROM ad_slots s JOIN sites si ON si.id = s.site_id JOIN users u ON u.id = si.user_id WHERE s.id = ?", [$id]);
    if (!$slot) {
        abort(404, 'Werbeplatz nicht gefunden.');
    }
    $bookable = $slot['status'] === 'approved' && $slot['site_status'] === 'approved' && $slot['publisher_status'] === 'active';
    $isOwner = uid() === (int) $slot['publisher_id'];
    if (!$bookable && !$isOwner && !has_role('admin')) {
        abort(404, 'Dieser Werbeplatz ist derzeit nicht verfügbar.');
    }

    $campaigns = [];
    if (has_role('advertiser')) {
        $campaigns = all("SELECT id, name, banner_width, banner_height FROM campaigns WHERE user_id = ? AND status = 'approved'
            AND banner_width = ? AND banner_height = ? ORDER BY name", [uid(), $slot['width'], $slot['height']]);
    }
    $commissionBp = (int) setting('commission_bp', '1500');
    view('market/slot', [
        'title' => $slot['name'] . ' – ' . $slot['site_name'],
        'slot' => $slot,
        'bookable' => $bookable,
        'ranges' => Booking::bookedRanges($id),
        'campaigns' => $campaigns,
        'commissionBp' => $commissionBp,
    ]);
}
