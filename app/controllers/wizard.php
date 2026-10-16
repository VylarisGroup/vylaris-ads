<?php
declare(strict_types=1);

// Assistenten: führen Schritt für Schritt durch das Anlegen. Die Eingaben werden mit derselben Logik geprüft und
// gespeichert wie in den klassischen Formularen (diese bleiben unverändert erreichbar).

require_once __DIR__ . '/publisher.php';
require_once __DIR__ . '/admin.php';
require_once __DIR__ . '/market.php'; // MARKET_BASE_SQL

/** true, wenn die Seite nach einem Fehler mit den zuletzt eingegebenen Werten neu aufgebaut wird. */
function wizard_has_old(): bool
{
    return !empty($_SESSION['old']);
}

/** Webseite (neu oder bestehend) + Werbeplatz in einem Durchgang. */
function wizard_site(): void
{
    $siteId = input_int('site_id');
    $site = $siteId ? own_site($siteId) : null;

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        view('wizard/site', [
            'title' => $site ? 'Werbeplatz hinzufügen' : 'Webseite einrichten',
            'site' => $site,
            'hasOld' => wizard_has_old(),
        ]);
        return;
    }

    // Erst alles prüfen, dann in einer Transaktion speichern (entweder beides oder nichts)
    $siteIn = $site ? null : site_input('site_');
    if (!$site) site_check_limit();
    $slotIn = slot_input('slot_');
    $slotId = tx(function () use ($site, $siteIn, $slotIn) {
        $sid = $site ? (int) $site['id'] : site_create($siteIn);
        return slot_create($sid, $slotIn);
    });
    audit('wizard.completed', 'slot', $slotId, ['wizard' => 'site']);
    flash('success', $site
        ? 'Werbeplatz angelegt. Er wird nach der Freigabe durch einen Administrator buchbar.'
        : 'Webseite und Werbeplatz angelegt. Nach der Freigabe durch einen Administrator ist der Werbeplatz buchbar.');
    redirect('sites');
}

/** Kampagne mit Banner: die Daten gehen an das klassische Formular (campaign.form). */
function wizard_campaign(): void
{
    view('wizard/campaign', ['title' => 'Kampagne erstellen', 'hasOld' => wizard_has_old()]);
}

/** Werbeplatz buchen: Platz → Kampagne → Zeitraum → Prüfen; die Daten gehen an booking.request. */
function wizard_booking(): void
{
    $slots = all("SELECT s.id, s.name, s.width, s.height, s.price_per_day, s.min_days, s.max_days, s.available_from, s.available_until,
            si.name AS site_name, si.category
        " . MARKET_BASE_SQL . ' AND si.user_id <> ? ORDER BY si.name, s.name LIMIT 300', [uid()]);
    $campaigns = all("SELECT id, name, banner_width, banner_height FROM campaigns WHERE user_id = ? AND status = 'approved' ORDER BY name", [uid()]);
    $pending = (int) val("SELECT COUNT(*) FROM campaigns WHERE user_id = ? AND status = 'pending'", [uid()]);
    view('wizard/booking', [
        'title' => 'Werbeplatz buchen',
        'slots' => $slots,
        'campaigns' => $campaigns,
        'pendingCampaigns' => $pending,
        'preselect' => input_int('slot_id'),
        'hasOld' => wizard_has_old(),
    ]);
}

/** Einrichtungsassistent für Administratoren: Plattform, Provision, Zahlung, Rechtliches. */
function wizard_setup(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        view('wizard/setup', [
            'title' => 'Einrichtungsassistent',
            'hasBookings' => (int) val('SELECT COUNT(*) FROM bookings') > 0,
            'hasOld' => wizard_has_old(),
        ]);
        return;
    }
    tx(function () {
        settings_apply();
        legal_apply(['name', 'email', 'address', 'phone', 'hoster']);
    });
    audit('wizard.completed', 'settings', null, ['wizard' => 'setup']);
    flash('success', 'Die Einrichtung wurde gespeichert. Weitere Angaben finden Sie unter Einstellungen.');
    redirect('dashboard');
}
