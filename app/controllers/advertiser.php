<?php
declare(strict_types=1);

function own_campaign(int $id): array
{
    $c = one('SELECT * FROM campaigns WHERE id = ? AND user_id = ?', [$id, uid()]);
    if (!$c) {
        abort(404, 'Kampagne nicht gefunden.');
    }
    return $c;
}

function campaign_locked(int $id): bool
{
    return (int) val("SELECT COUNT(*) FROM bookings WHERE campaign_id = ? AND status IN ('requested','accepted','paid')", [$id]) > 0;
}

function campaigns_index(): void
{
    $campaigns = all("SELECT c.*,
        (SELECT COUNT(*) FROM bookings b WHERE b.campaign_id = c.id) AS bookings_total,
        (SELECT COUNT(*) FROM bookings b WHERE b.campaign_id = c.id AND b.status IN ('requested','accepted','paid')) AS bookings_open,
        (SELECT COALESCE(SUM(total_amount),0) FROM bookings b WHERE b.campaign_id = c.id AND b.status IN ('paid','completed')) AS spent
        FROM campaigns c WHERE c.user_id = ? ORDER BY c.created_at DESC", [uid()]);
    view('advertiser/campaigns', ['title' => 'Meine Kampagnen', 'campaigns' => $campaigns]);
}

function campaign_form(): void
{
    $id = input_int('id');
    $campaign = $id ? own_campaign($id) : null;
    if ($campaign && campaign_locked((int) $campaign['id'])) {
        throw new UserError('Kampagnen mit offenen oder laufenden Buchungen können nicht bearbeitet werden. Legen Sie bei Bedarf eine neue Kampagne an.');
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        view('advertiser/campaign_form', ['title' => $campaign ? 'Kampagne bearbeiten' : 'Neue Kampagne', 'campaign' => $campaign]);
        return;
    }
    $name = input('name');
    $target = input('target_url');
    $alt = input('alt_text');
    if (!len_between($name, 2, 160)) throw new UserError('Bitte einen Kampagnennamen (2–160 Zeichen) angeben.');
    if (!valid_url($target)) throw new UserError('Bitte eine gültige Ziel-URL (http/https) angeben.');
    if (mb_strlen($alt) > 200) throw new UserError('Der Alternativtext ist zu lang (max. 200 Zeichen).');

    $hasFile = isset($_FILES['banner']) && ($_FILES['banner']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    if (!$campaign && !$hasFile) throw new UserError('Bitte ein Banner hochladen.');
    $banner = $hasFile ? Upload::banner($_FILES['banner']) : null;

    if ($campaign) {
        $b = $banner ?? ['file' => $campaign['banner_file'], 'mime' => $campaign['banner_mime'], 'width' => $campaign['banner_width'],
            'height' => $campaign['banner_height'], 'size' => $campaign['banner_size']];
        q("UPDATE campaigns SET name = ?, target_url = ?, alt_text = ?, banner_file = ?, banner_mime = ?, banner_width = ?, banner_height = ?,
           banner_size = ?, status = 'pending', admin_note = NULL WHERE id = ?",
            [$name, $target, $alt, $b['file'], $b['mime'], $b['width'], $b['height'], $b['size'], $campaign['id']]);
        if ($banner) {
            Upload::delete($campaign['banner_file']);
        }
        audit('campaign.updated', 'campaign', (int) $campaign['id']);
        flash('success', 'Kampagne gespeichert. Sie wird erneut von einem Administrator geprüft.');
    } else {
        q('INSERT INTO campaigns (user_id, name, target_url, alt_text, banner_file, banner_mime, banner_width, banner_height, banner_size)
           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [uid(), $name, $target, $alt, $banner['file'], $banner['mime'], $banner['width'], $banner['height'], $banner['size']]);
        audit('campaign.created', 'campaign', (int) db()->lastInsertId());
        flash('success', 'Kampagne angelegt (Banner ' . $banner['width'] . '×' . $banner['height'] . '). Nach der Freigabe können Sie passende Werbeplätze buchen.');
    }
    redirect('campaigns');
}

function campaign_delete(): void
{
    $c = own_campaign(input_int('id'));
    if ((int) val('SELECT COUNT(*) FROM bookings WHERE campaign_id = ?', [$c['id']]) > 0) {
        throw new UserError('Kampagnen mit Buchungen bleiben zur Nachvollziehbarkeit erhalten und können nicht gelöscht werden.');
    }
    q('DELETE FROM campaigns WHERE id = ?', [$c['id']]);
    Upload::delete($c['banner_file']);
    audit('campaign.deleted', 'campaign', (int) $c['id'], ['name' => $c['name']]);
    flash('success', 'Kampagne gelöscht.');
    redirect('campaigns');
}
