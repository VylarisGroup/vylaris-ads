<?php
declare(strict_types=1);

function own_site(int $id): array
{
    $site = one('SELECT * FROM sites WHERE id = ? AND user_id = ?', [$id, uid()]);
    if (!$site) {
        abort(404, 'Webseite nicht gefunden.');
    }
    return $site;
}

function own_slot(int $id): array
{
    $slot = one('SELECT s.*, si.name AS site_name FROM ad_slots s JOIN sites si ON si.id = s.site_id WHERE s.id = ? AND si.user_id = ?', [$id, uid()]);
    if (!$slot) {
        abort(404, 'Werbeplatz nicht gefunden.');
    }
    return $slot;
}

function sites_index(): void
{
    $sites = all('SELECT * FROM sites WHERE user_id = ? ORDER BY created_at DESC', [uid()]);
    $slots = all("SELECT s.*, (SELECT COUNT(*) FROM bookings b WHERE b.slot_id = s.id AND b.status IN ('paid','completed')) AS sold
        FROM ad_slots s JOIN sites si ON si.id = s.site_id WHERE si.user_id = ? ORDER BY s.created_at", [uid()]);
    $bySite = [];
    foreach ($slots as $s) {
        $bySite[$s['site_id']][] = $s;
    }
    view('publisher/sites', ['title' => 'Meine Webseiten', 'sites' => $sites, 'slotsBySite' => $bySite]);
}

/** Liest und prüft die Webseiten-Felder aus $_POST (mit Feldnamen-Präfix $p; gemeinsam für Formular und Assistent). */
function site_input(string $p = ''): array
{
    $name = input($p . 'name');
    $url = input($p . 'url');
    $category = input($p . 'category');
    $description = input($p . 'description');
    $visitors = input($p . 'monthly_visitors') === '' ? 0 : input_int($p . 'monthly_visitors', -1);

    if (!len_between($name, 2, 160)) throw new UserError('Bitte einen Namen (2–160 Zeichen) angeben.');
    if (!valid_url($url) || strlen($url) > 255) throw new UserError('Bitte eine gültige URL (http/https) angeben.');
    if (!in_array($category, SITE_CATEGORIES, true)) throw new UserError('Bitte eine Kategorie wählen.');
    if (!len_between($description, 20, 3000)) throw new UserError('Bitte eine Beschreibung mit 20–3000 Zeichen angeben.');
    if ($visitors < 0 || $visitors > 2000000000) throw new UserError('Bitte eine gültige Besucherzahl angeben.');
    return ['name' => $name, 'url' => $url, 'category' => $category, 'description' => $description, 'visitors' => $visitors];
}

function site_check_limit(): void
{
    if (!licence()->hasFeature('multi_site')
        && (int) val('SELECT COUNT(*) FROM sites WHERE user_id = ?', [uid()]) >= licence()->freeSites()) {
        throw new UserError('Ihre Lizenz erlaubt höchstens ' . licence()->freeSites() . ' Webseite(n) pro Betreiber (Scope multi_site).');
    }
}

function site_create(array $in): int
{
    q('INSERT INTO sites (user_id, name, url, category, description, monthly_visitors) VALUES (?, ?, ?, ?, ?, ?)',
        [uid(), $in['name'], $in['url'], $in['category'], $in['description'], $in['visitors']]);
    $id = (int) db()->lastInsertId();
    audit('site.created', 'site', $id);
    return $id;
}

function site_form(): void
{
    $id = input_int('id');
    $site = $id ? own_site($id) : null;
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        view('publisher/site_form', ['title' => $site ? 'Webseite bearbeiten' : 'Webseite hinzufügen', 'site' => $site]);
        return;
    }
    $in = site_input();
    if (!$site) site_check_limit();
    if ($site) {
        // Änderungen werden erneut geprüft; bestehende Buchungen bleiben unberührt.
        q("UPDATE sites SET name = ?, url = ?, category = ?, description = ?, monthly_visitors = ?, status = 'pending', admin_note = NULL WHERE id = ?",
            [$in['name'], $in['url'], $in['category'], $in['description'], $in['visitors'], $site['id']]);
        audit('site.updated', 'site', (int) $site['id']);
        flash('success', 'Webseite gespeichert. Sie wird erneut von einem Administrator geprüft.');
    } else {
        site_create($in);
        flash('success', 'Webseite angelegt. Nach der Freigabe durch einen Administrator sind Ihre Werbeplätze buchbar. Sie können bereits jetzt Werbeplätze anlegen.');
    }
    redirect('sites');
}

function site_toggle(): void
{
    $site = own_site(input_int('id'));
    $new = match ($site['status']) { 'approved' => 'paused', 'paused' => 'approved', default => null };
    if (!$new) throw new UserError('Nur freigegebene Webseiten können pausiert bzw. fortgesetzt werden.');
    q('UPDATE sites SET status = ? WHERE id = ?', [$new, $site['id']]);
    audit('site.' . ($new === 'paused' ? 'paused' : 'resumed'), 'site', (int) $site['id']);
    flash('success', $new === 'paused' ? 'Webseite pausiert – keine neuen Buchungen möglich.' : 'Webseite wieder aktiv.');
    redirect('sites');
}

function site_delete(): void
{
    $site = own_site(input_int('id'));
    if ((int) val('SELECT COUNT(*) FROM bookings b JOIN ad_slots s ON s.id = b.slot_id WHERE s.site_id = ?', [$site['id']]) > 0) {
        throw new UserError('Webseiten mit Buchungen können aus Gründen der Nachvollziehbarkeit nicht gelöscht werden. Bitte pausieren Sie die Webseite stattdessen.');
    }
    tx(function () use ($site) {
        q('DELETE FROM ad_slots WHERE site_id = ?', [$site['id']]);
        q('DELETE FROM sites WHERE id = ?', [$site['id']]);
    });
    audit('site.deleted', 'site', (int) $site['id'], ['name' => $site['name']]);
    flash('success', 'Webseite gelöscht.');
    redirect('sites');
}

/** Liest und prüft die Werbeplatz-Felder aus $_POST (gemeinsam für Formular und Assistent). */
function slot_input(string $p = ''): array
{
    $name = input($p . 'name');
    $format = input($p . 'format');
    $placement = input($p . 'placement');
    $description = input($p . 'description');
    $price = parse_money(input($p . 'price_per_day'));
    $minDays = input_int($p . 'min_days', 0);
    $maxDays = input_int($p . 'max_days', 0);
    $from = parse_date(input($p . 'available_from'));
    $untilRaw = input($p . 'available_until');
    $until = $untilRaw === '' ? null : parse_date($untilRaw);

    if (!len_between($name, 2, 160)) throw new UserError('Bitte einen Namen (2–160 Zeichen) angeben.');
    if ($format === 'custom') {
        $w = input_int($p . 'width');
        $h = input_int($p . 'height');
        if ($w < 10 || $w > 2000 || $h < 10 || $h > 2000) throw new UserError('Eigenes Format: Breite und Höhe müssen zwischen 10 und 2000 Pixel liegen.');
        $format = $w . 'x' . $h;
    } elseif (isset(AD_FORMATS[$format])) {
        [$w, $h] = array_map('intval', explode('x', $format));
    } else {
        throw new UserError('Bitte ein Format wählen.');
    }
    if (mb_strlen($placement) > 160) throw new UserError('Die Platzierung ist zu lang.');
    if (!len_between($description, 10, 3000)) throw new UserError('Bitte eine Beschreibung mit 10–3000 Zeichen angeben.');
    if ($price === null || $price < 50 || $price > 10000000) throw new UserError('Bitte einen Tagespreis zwischen 0.50 und 100\'000 angeben.');
    if ($minDays < 1 || $minDays > 365 || $maxDays < $minDays || $maxDays > 365) throw new UserError('Mindest- und Höchstlaufzeit müssen zwischen 1 und 365 Tagen liegen (Minimum ≤ Maximum).');
    if (!$from) throw new UserError('Bitte ein gültiges Verfügbarkeitsdatum angeben.');
    if ($untilRaw !== '' && (!$until || $until < $from)) throw new UserError('Das Ende der Verfügbarkeit muss nach dem Beginn liegen.');
    return ['name' => $name, 'format' => $format, 'w' => $w, 'h' => $h, 'placement' => $placement, 'description' => $description,
        'price' => $price, 'min' => $minDays, 'max' => $maxDays, 'from' => $from, 'until' => $until];
}

function slot_create(int $siteId, array $in): int
{
    q('INSERT INTO ad_slots (site_id, name, format, width, height, placement, description, price_per_day, min_days, max_days, available_from, available_until)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [$siteId, $in['name'], $in['format'], $in['w'], $in['h'], $in['placement'], $in['description'], $in['price'], $in['min'], $in['max'], $in['from'], $in['until']]);
    $id = (int) db()->lastInsertId();
    audit('slot.created', 'slot', $id, ['price_per_day' => $in['price']]);
    return $id;
}

function slot_form(): void
{
    $id = input_int('id');
    $slot = $id ? own_slot($id) : null;
    $site = own_site($slot ? (int) $slot['site_id'] : input_int('site_id'));
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        view('publisher/slot_form', ['title' => $slot ? 'Werbeplatz bearbeiten' : 'Werbeplatz anlegen', 'slot' => $slot, 'site' => $site]);
        return;
    }
    $in = slot_input();
    ['name' => $name, 'format' => $format, 'w' => $w, 'h' => $h, 'placement' => $placement, 'description' => $description,
     'price' => $price, 'min' => $minDays, 'max' => $maxDays, 'from' => $from, 'until' => $until] = $in;

    if ($slot) {
        $openBookings = (int) val("SELECT COUNT(*) FROM bookings WHERE slot_id = ? AND status IN ('requested','accepted','paid')", [$slot['id']]);
        if ($openBookings > 0 && ((int) $slot['width'] !== $w || (int) $slot['height'] !== $h)) {
            throw new UserError('Das Format kann nicht geändert werden, solange offene oder laufende Buchungen bestehen.');
        }
        q("UPDATE ad_slots SET name = ?, format = ?, width = ?, height = ?, placement = ?, description = ?, price_per_day = ?, min_days = ?, max_days = ?,
           available_from = ?, available_until = ?, status = 'pending', admin_note = NULL WHERE id = ?",
            [$name, $format, $w, $h, $placement, $description, $price, $minDays, $maxDays, $from, $until, $slot['id']]);
        audit('slot.updated', 'slot', (int) $slot['id'], ['price_per_day' => $price]);
        flash('success', 'Werbeplatz gespeichert. Er wird erneut geprüft; bestehende Buchungen behalten ihren Preis.');
    } else {
        slot_create((int) $site['id'], $in);
        flash('success', 'Werbeplatz angelegt. Er wird nach der Freigabe durch einen Administrator buchbar.');
    }
    redirect('sites');
}

function slot_toggle(): void
{
    $slot = own_slot(input_int('id'));
    $new = match ($slot['status']) { 'approved' => 'paused', 'paused' => 'approved', default => null };
    if (!$new) throw new UserError('Nur freigegebene Werbeplätze können pausiert bzw. fortgesetzt werden.');
    q('UPDATE ad_slots SET status = ? WHERE id = ?', [$new, $slot['id']]);
    audit('slot.' . ($new === 'paused' ? 'paused' : 'resumed'), 'slot', (int) $slot['id']);
    flash('success', $new === 'paused' ? 'Werbeplatz pausiert.' : 'Werbeplatz wieder buchbar.');
    redirect('sites');
}

function slot_delete(): void
{
    $slot = own_slot(input_int('id'));
    if ((int) val('SELECT COUNT(*) FROM bookings WHERE slot_id = ?', [$slot['id']]) > 0) {
        throw new UserError('Werbeplätze mit Buchungen können nicht gelöscht werden. Bitte pausieren Sie den Werbeplatz stattdessen.');
    }
    q('DELETE FROM ad_slots WHERE id = ?', [$slot['id']]);
    audit('slot.deleted', 'slot', (int) $slot['id'], ['name' => $slot['name']]);
    flash('success', 'Werbeplatz gelöscht.');
    redirect('sites');
}

function earnings_index(): void
{
    $balance = Billing::publisherBalance(uid());
    $ledger = paginate('SELECT * FROM ledger WHERE user_id = ? ORDER BY id DESC', [uid()], 30);
    $payouts = all('SELECT * FROM payouts WHERE publisher_id = ? ORDER BY paid_on DESC, id DESC', [uid()]);
    view('publisher/earnings', [
        'title' => 'Einnahmen & Abrechnung',
        'balance' => $balance,
        'ledger' => $ledger,
        'payouts' => $payouts,
        'monthly' => Billing::monthly(6, uid()),
    ]);
}
