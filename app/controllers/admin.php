<?php
declare(strict_types=1);

require_once __DIR__ . '/bookings.php';

const REVIEW_TYPES = ['sites' => 'Webseiten', 'slots' => 'Werbeplätze', 'campaigns' => 'Kampagnen'];

function admin_reviews(): void
{
    $type = isset(REVIEW_TYPES[input('type')]) ? input('type') : 'sites';
    $status = in_array(input('status'), ['pending', 'approved', 'rejected', 'paused', 'all'], true) ? input('status') : 'pending';
    $params = [];
    $filter = '';
    $alias = ['sites' => 'si', 'slots' => 's', 'campaigns' => 'c'][$type];
    if ($status !== 'all') {
        $filter = " WHERE $alias.status = ?";
        $params[] = $status;
    }
    $sql = match ($type) {
        'sites' => "SELECT si.*, u.name AS owner_name, u.email AS owner_email FROM sites si JOIN users u ON u.id = si.user_id$filter ORDER BY si.updated_at DESC",
        'slots' => "SELECT s.*, si.name AS site_name, si.url AS site_url, si.status AS site_status, u.name AS owner_name
                    FROM ad_slots s JOIN sites si ON si.id = s.site_id JOIN users u ON u.id = si.user_id$filter ORDER BY s.updated_at DESC",
        'campaigns' => "SELECT c.*, u.name AS owner_name, u.email AS owner_email FROM campaigns c JOIN users u ON u.id = c.user_id$filter ORDER BY c.updated_at DESC",
    };
    $counts = one("SELECT (SELECT COUNT(*) FROM sites WHERE status='pending') AS sites,
        (SELECT COUNT(*) FROM ad_slots WHERE status='pending') AS slots, (SELECT COUNT(*) FROM campaigns WHERE status='pending') AS campaigns");
    view('admin/reviews', ['title' => 'Freigaben', 'type' => $type, 'status' => $status, 'result' => paginate($sql, $params, 20), 'counts' => $counts]);
}

function admin_review(): void
{
    $type = input('type');
    $table = ['sites' => 'sites', 'slots' => 'ad_slots', 'campaigns' => 'campaigns'][$type] ?? null;
    $decision = input('decision');
    $note = input('note');
    $id = input_int('id');
    if (!$table || !in_array($decision, ['approved', 'rejected'], true)) {
        throw new UserError('Ungültige Aktion.');
    }
    if ($decision === 'rejected' && !len_between($note, 3, 500)) {
        throw new UserError('Bitte bei einer Ablehnung eine Begründung (3–500 Zeichen) angeben.');
    }
    if (!val("SELECT id FROM $table WHERE id = ?", [$id])) {
        throw new UserError('Eintrag nicht gefunden.');
    }
    q("UPDATE $table SET status = ?, admin_note = ? WHERE id = ?", [$decision, $note !== '' ? mb_substr($note, 0, 500) : null, $id]);
    audit('review.' . $decision, rtrim($type, 's'), $id, ['note' => $note]);
    Hooks::fire('entity.reviewed', ['type' => $type, 'id' => $id, 'decision' => $decision]);
    flash('success', REVIEW_TYPES[$type] . '-Eintrag #' . $id . ' wurde ' . ($decision === 'approved' ? 'freigegeben.' : 'abgelehnt.'));
    back('admin.reviews');
}

function admin_users(): void
{
    $where = '1=1';
    $params = [];
    if (isset(ROLE_LABELS[input('role')])) {
        // Konten mit beiden Rollen erscheinen in beiden Filtern
        $where .= in_array(input('role'), ['advertiser', 'publisher'], true) ? " AND role IN (?, 'both')" : ' AND role = ?';
        $params[] = input('role');
    }
    if (input('q') !== '') {
        $where .= ' AND (email LIKE ? OR name LIKE ? OR company LIKE ?)';
        $like = '%' . addcslashes(input('q'), '%_\\') . '%';
        array_push($params, $like, $like, $like);
    }
    $result = paginate("SELECT u.*,
        (SELECT COUNT(*) FROM sites WHERE user_id = u.id) AS sites,
        (SELECT COUNT(*) FROM campaigns WHERE user_id = u.id) AS campaigns,
        (SELECT COUNT(*) FROM bookings WHERE advertiser_id = u.id OR publisher_id = u.id) AS bookings
        FROM users u WHERE $where ORDER BY u.created_at DESC", $params, 25);
    view('admin/users', ['title' => 'Benutzer', 'result' => $result]);
}

function admin_user(): void
{
    $target = one('SELECT * FROM users WHERE id = ?', [input_int('id')]);
    if (!$target) throw new UserError('Benutzer nicht gefunden.');
    $self = (int) $target['id'] === uid();
    $action = input('action');
    if ($target['status'] === 'deleted' && $action !== 'export') {
        throw new UserError('Dieses Konto ist gelöscht.');
    }

    switch ($action) {
        case 'export': // Auskunftsersuchen (Art. 15 DSGVO) im Auftrag des Betroffenen
            Privacy::sendExport((int) $target['id']);
        case 'delete': // Löschersuchen (Art. 17 DSGVO)
            if ($self) throw new UserError('Bitte löschen Sie Ihr eigenes Konto unter „Mein Konto“.');
            $mode = Privacy::deleteAccount((int) $target['id'], uid());
            flash('success', $mode === 'deleted' ? 'Konto vollständig gelöscht.' : 'Konto gesperrt und bereinigt. Name/Firma werden bis Ende der Aufbewahrungsfrist für die Belege vorgehalten und danach anonymisiert.');
            redirect('admin.users');
        case 'block':
        case 'unblock':
            if ($self) throw new UserError('Sie können Ihr eigenes Konto nicht sperren.');
            q('UPDATE users SET status = ? WHERE id = ?', [$action === 'block' ? 'blocked' : 'active', $target['id']]);
            flash('success', $action === 'block' ? 'Konto gesperrt. Aktive Sitzungen werden beim nächsten Seitenaufruf beendet.' : 'Konto entsperrt.');
            break;
        case 'role':
            $role = input('role');
            if ($self) throw new UserError('Sie können Ihre eigene Rolle nicht ändern.');
            if (!isset(ROLE_LABELS[$role])) throw new UserError('Ungültige Rolle.');
            $linked = (int) val('SELECT (SELECT COUNT(*) FROM sites WHERE user_id = ?) + (SELECT COUNT(*) FROM campaigns WHERE user_id = ?)
                + (SELECT COUNT(*) FROM bookings WHERE advertiser_id = ? OR publisher_id = ?)', array_fill(0, 4, $target['id']));
            if ($role === 'both') require_scope('dual_role');
            $addsRole = $role === 'both' && in_array($target['role'], ['advertiser', 'publisher'], true); // Erweiterung zu „beides“ ist immer möglich
            if ($linked > 0 && !$addsRole) throw new UserError('Bei Konten mit Webseiten, Kampagnen oder Buchungen kann die Rolle nur zu „Werbetreibender & Webseitenbetreiber“ erweitert werden.');
            q('UPDATE users SET role = ? WHERE id = ?', [$role, $target['id']]);
            flash('success', 'Rolle geändert: ' . ROLE_LABELS[$role] . '.');
            break;
        case 'password':
            $pw = (string) ($_POST['password'] ?? '');
            if (strlen($pw) < 10 || strlen($pw) > 200) throw new UserError('Das neue Passwort muss mindestens 10 Zeichen lang sein.');
            q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($pw, PASSWORD_DEFAULT), $target['id']]);
            q('DELETE FROM login_attempts WHERE email = ?', [$target['email']]);
            flash('success', 'Passwort gesetzt. Bestehende Sitzungen des Benutzers wurden beendet.');
            break;
        default:
            throw new UserError('Ungültige Aktion.');
    }
    audit('admin.user_' . $action, 'user', (int) $target['id'], $action === 'role' ? ['role' => input('role')] : []);
    back('admin.users');
}

function admin_bookings(): void
{
    $where = '1=1';
    $params = [];
    $status = input('status');
    if (isset(STATUS_LABELS[$status]) && in_array($status, ['requested', 'accepted', 'paid', 'completed', 'declined', 'cancelled', 'expired', 'refunded'], true)) {
        $where .= ' AND b.status = ?';
        $params[] = $status;
    }
    if (input('payment') === 'reported') {
        $where .= " AND b.payment_status = 'reported'";
    }
    if (input('q') !== '') {
        $where .= ' AND (b.reference LIKE ? OR b.invoice_no LIKE ? OR a.email LIKE ? OR p.email LIKE ? OR si.name LIKE ?)';
        $like = '%' . addcslashes(input('q'), '%_\\') . '%';
        array_push($params, $like, $like, $like, $like, $like);
    }
    $result = paginate(BOOKING_SELECT . " WHERE $where ORDER BY b.created_at DESC, b.id DESC", $params, 25);
    view('admin/bookings', ['title' => 'Buchungen & Zahlungen', 'result' => $result, 'status' => $status]);
}

function admin_payment(): void
{
    $id = input_int('id');
    Booking::confirmPayment(uid(), $id, input('method'), input('provider_ref'), input('received_on'), input('note'));
    flash('success', 'Zahlungseingang erfasst. Die Buchung ist bezahlt und der Beleg wurde erstellt.');
    redirect('booking', ['id' => $id]);
}

function admin_refund(): void
{
    $id = input_int('id');
    Booking::refund(uid(), $id, input('note'));
    flash('success', 'Buchung erstattet. Die Buchungen im Journal wurden storniert.');
    redirect('booking', ['id' => $id]);
}

function admin_payouts(): void
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $amount = parse_money(input('amount'));
        if ($amount === null) throw new UserError('Bitte einen gültigen Betrag angeben.');
        Billing::recordPayout(uid(), input_int('publisher_id'), $amount, input('method'), input('paid_on'), input('note'));
        flash('success', 'Auszahlung erfasst.');
        redirect('admin.payouts');
    }
    $publishers = all("SELECT u.id, u.name, u.company, u.email, u.payout_details,
        COALESCE((SELECT SUM(publisher_amount) FROM bookings WHERE publisher_id = u.id AND status = 'completed'),0) AS earned,
        COALESCE((SELECT SUM(publisher_amount) FROM bookings WHERE publisher_id = u.id AND status = 'paid'),0) AS pending,
        COALESCE((SELECT SUM(amount) FROM payouts WHERE publisher_id = u.id),0) AS paid_out
        FROM users u WHERE u.role IN ('publisher','both') ORDER BY u.name");
    foreach ($publishers as &$p) {
        $p['available'] = (int) $p['earned'] - (int) $p['paid_out'];
    }
    unset($p);
    usort($publishers, fn ($a, $b) => $b['available'] <=> $a['available']);
    $history = paginate('SELECT po.*, u.name, u.email FROM payouts po JOIN users u ON u.id = po.publisher_id ORDER BY po.paid_on DESC, po.id DESC', [], 20);
    view('admin/payouts', ['title' => 'Auszahlungen', 'publishers' => $publishers, 'history' => $history]);
}

function admin_finance(): void
{
    $types = ['advertiser_payment', 'platform_commission', 'publisher_earning', 'publisher_payout', 'refund', 'commission_reversal', 'earning_reversal'];
    $where = '1=1';
    $params = [];
    if (in_array(input('type'), $types, true)) {
        $where .= ' AND l.type = ?';
        $params[] = input('type');
    }
    $sql = "SELECT l.*, u.name AS user_name, b.reference FROM ledger l LEFT JOIN users u ON u.id = l.user_id
            LEFT JOIN bookings b ON b.id = l.booking_id WHERE $where ORDER BY l.id DESC";

    if (input('format') === 'csv') {
        require_scope('export.csv');
        audit('finance.export', 'ledger');
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="journal-' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['ID', 'Datum', 'Typ', 'Buchung', 'Benutzer', 'Beschreibung', 'Betrag', 'Währung'], ';');
        foreach (q($sql, $params) as $r) {
            $row = [$r['id'], $r['created_at'], $r['type'], $r['reference'], $r['user_name'], $r['description'],
                number_format($r['amount'] / 100, 2, '.', ''), $r['currency']];
            // CSV-Injection verhindern (Formeln in Tabellenkalkulationen)
            $row = array_map(fn ($v) => is_string($v) && preg_match('/^[=+\-@\t\r]/', $v) && !is_numeric($v) ? "'" . $v : $v, $row);
            fputcsv($out, $row, ';');
        }
        fclose($out);
        exit;
    }
    view('admin/finance', [
        'title' => 'Finanzen & Journal', 'summary' => Billing::platformSummary(), 'monthly' => Billing::monthly(12),
        'ledger' => paginate($sql, $params, 40), 'types' => $types,
    ]);
}

function admin_settings(): void
{
    $hasBookings = (int) val('SELECT COUNT(*) FROM bookings') > 0;
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        view('admin/settings', ['title' => 'Einstellungen', 'hasBookings' => $hasBookings]);
        return;
    }
    $name = input('platform_name');
    $currency = strtoupper(input('currency'));
    $commission = input('commission');
    $bank = input('bank_details');
    $prefix = strtoupper(input('invoice_prefix'));
    $minPayout = parse_money(input('min_payout'));

    if (!len_between($name, 2, 60)) throw new UserError('Bitte einen Plattformnamen (2–60 Zeichen) angeben.');
    if (!preg_match('/^[A-Z]{3}$/', $currency)) throw new UserError('Die Währung muss ein dreistelliger ISO-Code sein (z. B. CHF, EUR).');
    if ($hasBookings && $currency !== setting('currency')) throw new UserError('Die Währung kann nicht mehr geändert werden, da bereits Buchungen existieren.');
    $c = str_replace(',', '.', $commission);
    if (!preg_match('/^\d{1,2}(\.\d{1,2})?$/', $c) || (float) $c > 50) throw new UserError('Die Provision muss zwischen 0 und 50 % liegen.');
    if (!len_between($bank, 5, 1000)) throw new UserError('Bitte die Zahlungsinformationen (Bankverbindung) angeben.');
    if (!preg_match('/^[A-Z0-9]{2,8}$/', $prefix)) throw new UserError('Das Belegpräfix muss aus 2–8 Buchstaben/Ziffern bestehen.');
    if ($minPayout === null) throw new UserError('Bitte einen gültigen Mindestbetrag für Auszahlungen angeben.');

    $old = (int) setting('commission_bp');
    $bp = (int) round((float) $c * 100);
    set_setting('platform_name', $name);
    set_setting('currency', $currency);
    set_setting('commission_bp', (string) $bp);
    set_setting('bank_details', $bank);
    set_setting('invoice_prefix', $prefix);
    set_setting('min_payout', (string) $minPayout);
    audit('settings.updated', 'settings', null, ['commission_bp' => ['from' => $old, 'to' => $bp], 'currency' => $currency]);
    flash('success', 'Einstellungen gespeichert. Eine geänderte Provision gilt nur für neue Buchungsanfragen.');
    redirect('admin.settings');
}

function admin_legal(): void
{
    $limits = ['name' => 160, 'address' => 500, 'email' => 190, 'phone' => 40, 'register' => 500, 'representative' => 190, 'hoster' => 300];
    $new = [];
    foreach ($limits as $f => $max) {
        $v = trim(str_replace("\r", '', (string) ($_POST['legal_' . $f] ?? '')));
        if (mb_strlen($v) > $max) throw new UserError("Das Feld „$f“ ist zu lang (max. $max Zeichen).");
        $new['legal_' . $f] = $v;
    }
    if ($new['legal_email'] !== '' && !filter_var($new['legal_email'], FILTER_VALIDATE_EMAIL)) throw new UserError('Bitte eine gültige Kontakt-E-Mail-Adresse angeben.');
    foreach (array_keys(Legal::PAGES) as $page) {
        $v = trim(str_replace("\r", '', (string) ($_POST['legal_' . $page . '_custom'] ?? '')));
        if (mb_strlen($v) > 30000) throw new UserError('Ein eigener Rechtstext ist zu lang (max. 30\'000 Zeichen).');
        $new['legal_' . $page . '_custom'] = $v;
    }
    $changed = false;
    foreach ($new as $k => $v) {
        if ($v !== str_replace("\r", '', setting($k))) {
            $changed = true;
            set_setting($k, $v);
        }
    }
    if ($changed) {
        set_setting('legal_version', date('Y-m-d'));
    }
    audit('settings.legal_updated', 'settings', null, ['changed' => $changed]);
    flash('success', $changed ? 'Rechtliche Angaben gespeichert (Fassung ' . date('d.m.Y') . ').' : 'Keine Änderungen.');
    redirect('admin.settings');
}

function admin_audit(): void
{
    $params = [];
    $where = '1=1';
    if (input('entity') !== '' && preg_match('/^[a-z]+$/', input('entity'))) {
        $where .= ' AND a.entity = ?';
        $params[] = input('entity');
        if (input_int('entity_id') > 0) {
            $where .= ' AND a.entity_id = ?';
            $params[] = input_int('entity_id');
        }
    }
    $result = paginate("SELECT a.*, u.name AS user_name, u.email AS user_email FROM audit_log a LEFT JOIN users u ON u.id = a.user_id
        WHERE $where ORDER BY a.id DESC", $params, 50);
    view('admin/audit', ['title' => 'Protokoll', 'result' => $result]);
}

function admin_licence(): void
{
    $lic = licence();
    if (!$lic instanceof TrsLicence) {
        view('admin/licence', ['title' => 'Lizenz', 'lic' => $lic, 'trs' => false]);
        return;
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        switch (input('action')) {
            case 'save':
                $key = input('licence_key');
                if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9-]{4,63}$/', $key)) throw new UserError('Bitte einen gültigen Lizenzschlüssel eingeben (z. B. VA-XXXX-XXXX-XXXX).');
                if ($key !== $lic->key()) {
                    set_setting('licence_key', $key);
                    $lic->reset();
                }
                $code = $lic->refresh();
                audit('licence.key_saved', 'licence', null, ['code' => $code]);
                flash($lic->isValid() ? 'success' : 'error', $lic->isValid() ? 'Lizenz geprüft und aktiviert.' : 'Lizenz nicht gültig: ' . TrsLicence::describe($lic->evaluate()['code']) . '.');
                break;
            case 'check':
                if ($lic->key() === '') throw new UserError('Bitte zuerst einen Lizenzschlüssel eintragen.');
                $code = $lic->refresh();
                audit('licence.checked', 'licence', null, ['code' => $code]);
                flash($lic->isValid() ? 'success' : 'error', $lic->isValid() ? 'Lizenz ist gültig.' : 'Lizenz nicht gültig: ' . TrsLicence::describe($lic->evaluate()['code']) . '.');
                break;
            case 'deactivate':
                $ok = $lic->deactivate();
                audit('licence.deactivated', 'licence', null, ['server_reached' => $ok]);
                flash($ok ? 'success' : 'info', $ok ? 'Installation abgemeldet, der Geräteplatz ist wieder frei.' : 'Lokal zurückgesetzt; der Lizenzserver war nicht erreichbar. Gerät ggf. im Lizenz-Panel zurücksetzen.');
                break;
            case 'remove':
                $lic->deactivate();
                set_setting('licence_key', '');
                audit('licence.removed', 'licence');
                flash('info', 'Lizenzschlüssel entfernt. Die Anwendung läuft im Lesemodus.');
                break;
            default:
                throw new UserError('Unbekannte Aktion.');
        }
        redirect('admin.licence');
    }
    view('admin/licence', ['title' => 'Lizenz', 'lic' => $lic, 'trs' => true]);
}

function admin_update(): void
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        switch (input('action')) {
            case 'save':
                Updater::saveSettings(input('repo'), (string) ($_POST['token'] ?? ''), !empty($_POST['remove_token']));
                set_setting('update_auto', !empty($_POST['auto']) ? '1' : '0');
                audit('update.settings', 'system', null, ['repo' => input('repo')]);
                flash('success', 'Update-Einstellungen gespeichert.');
                break;
            case 'check':
                $l = Updater::check();
                flash('success', version_compare($l['version'], APP_VERSION, '>') ? 'Neue Version ' . $l['version'] . ' verfügbar.' : 'Sie nutzen die neueste Version (' . APP_VERSION . ').');
                break;
            case 'install':
                $_SESSION['update_log'] = Updater::install();
                flash('success', 'Update installiert.');
                break;
            case 'rollback':
                $v = Updater::rollback(input('file') !== '' ? input('file') : null);
                $_SESSION['update_log'] = ['Auf Version ' . $v . ' zurückgesetzt.'];
                flash('success', 'Sicherung wiederhergestellt (Version ' . $v . ').');
                break;
            default:
                throw new UserError('Unbekannte Aktion.');
        }
        redirect('admin.update');
    }
    $log = $_SESSION['update_log'] ?? [];
    unset($_SESSION['update_log']);
    view('admin/update', ['title' => 'Updates', 'log' => $log]);
}
