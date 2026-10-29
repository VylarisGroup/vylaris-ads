<?php
declare(strict_types=1);

function auth_login(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        view('auth/login', ['title' => 'Anmelden']);
        return;
    }
    $email = mb_strtolower(input('email'));
    $password = (string) ($_POST['password'] ?? '');
    $max = (int) config('security.login_max_attempts', 5);
    $since = date('Y-m-d H:i:s', time() - (int) config('security.login_window_min', 15) * 60);

    if (random_int(1, 50) === 1) {
        q('DELETE FROM login_attempts WHERE attempted_at < NOW() - INTERVAL 1 DAY');
    }
    $byEmail = (int) val('SELECT COUNT(*) FROM login_attempts WHERE email = ? AND attempted_at > ?', [$email, $since]);
    $byIp = (int) val('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND attempted_at > ?', [client_ip(), $since]);
    if ($byEmail >= $max || $byIp >= $max * 3) {
        remember_input();
        flash('error', 'Zu viele fehlgeschlagene Anmeldeversuche. Bitte versuchen Sie es in ' . config('security.login_window_min', 15) . ' Minuten erneut.');
        redirect('login');
    }

    $user = $email !== '' ? one('SELECT * FROM users WHERE email = ?', [$email]) : null;
    // Gleiche Laufzeit für unbekannte Konten (verhindert Konto-Ermittlung über Zeitmessung)
    $ok = password_verify($password, $user['password_hash'] ?? '$2y$10$IIa7Y8byxWR.YsrHKdqEPexRv4o83dLTtPqQx4zgJGzZHivRDA/N.');
    if (!$user || !$ok) {
        q('INSERT INTO login_attempts (ip, email) VALUES (?, ?)', [client_ip(), mb_substr($email, 0, 190)]);
        remember_input();
        flash('error', 'E-Mail-Adresse oder Passwort ist falsch.');
        redirect('login');
    }
    if ($user['status'] !== 'active') {
        flash('error', 'Dieses Konto ist gesperrt. Bitte wenden Sie sich an den Administrator.');
        redirect('login');
    }
    if (!$user['email_verified_at']) {
        // Passwort stimmt, aber die Adresse ist noch nicht bestätigt: kein Zugang, Link erneut anfordern
        remember_input();
        flash('error', 'Bitte bestätigen Sie zuerst Ihre E-Mail-Adresse über den Link in der Bestätigungs-Mail.');
        redirect('verify.resend');
    }
    if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
        $user['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
        q('UPDATE users SET password_hash = ? WHERE id = ?', [$user['password_hash'], $user['id']]);
    }
    q('DELETE FROM login_attempts WHERE email = ?', [$email]);
    login_user($user);
    audit('user.login', 'user', (int) $user['id']);

    $intended = (string) ($_SESSION['intended'] ?? '');
    unset($_SESSION['intended']);
    if ($intended !== '' && preg_match('/^p=[a-z.]+(&[a-z_]+=[A-Za-z0-9_.%-]*)*$/', $intended)) {
        header('Location: index.php?' . $intended, true, 303);
        exit;
    }
    redirect('dashboard');
}

function auth_register(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        view('auth/register', ['title' => 'Registrieren']);
        return;
    }
    $name = input('name');
    $company = input('company');
    $email = mb_strtolower(input('email'));
    $role = input('role');
    $password = (string) ($_POST['password'] ?? '');

    $errors = [];
    if (!len_between($name, 2, 120)) $errors[] = 'Bitte einen Namen (2–120 Zeichen) angeben.';
    if (mb_strlen($company) > 160) $errors[] = 'Der Firmenname ist zu lang.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) $errors[] = 'Bitte eine gültige E-Mail-Adresse angeben.';
    if (!in_array($role, ['advertiser', 'publisher', 'both'], true)) $errors[] = 'Bitte eine Kontoart wählen.';
    if (strlen($password) < 10 || strlen($password) > 200) $errors[] = 'Das Passwort muss mindestens 10 Zeichen lang sein.';
    if ($password !== ($_POST['password2'] ?? null)) $errors[] = 'Die Passwörter stimmen nicht überein.';
    if (empty($_POST['terms'])) $errors[] = 'Bitte akzeptieren Sie die Nutzungsbedingungen.';
    if (!$errors && val('SELECT id FROM users WHERE email = ?', [$email])) $errors[] = 'Diese E-Mail-Adresse ist bereits registriert.';

    if ($errors) {
        remember_input();
        foreach ($errors as $err) flash('error', $err);
        redirect('register', input('wizard') === '1' ? ['wizard' => 1] : []);
    }
    q('INSERT INTO users (email, password_hash, name, company, role, consent_at, consent_version) VALUES (?, ?, ?, ?, ?, NOW(), ?)',
        [$email, password_hash($password, PASSWORD_DEFAULT), $name, $company ?: null, $role, Legal::version()]);
    $id = (int) db()->lastInsertId();
    $user = one('SELECT * FROM users WHERE id = ?', [$id]);
    audit('user.registered', 'user', $id, ['role' => $role]);
    Hooks::fire('user.registered', ['user_id' => $id, 'role' => $role]);
    // Neue Konten sind erst nach Bestätigung der E-Mail-Adresse nutzbar
    if (Verification::sendVerify($user)) {
        flash('success', 'Ihr Konto wurde erstellt. Wir haben einen Bestätigungslink an ' . $email . ' gesendet – bitte öffnen Sie ihn, danach können Sie sich anmelden.');
    } else {
        flash('info', 'Ihr Konto wurde erstellt. Der E-Mail-Versand ist auf dieser Installation noch nicht eingerichtet – ein Administrator muss Ihre Adresse bestätigen, bevor Sie sich anmelden können.');
    }
    redirect('login');
}

/** Bestätigungslink aus der E-Mail (Registrierung oder Adressänderung). */
function auth_verify(): void
{
    $t = Verification::consume(input('token'));
    if (!$t) {
        flash('error', 'Der Bestätigungslink ist ungültig, abgelaufen oder wurde bereits verwendet. Sie können unten einen neuen anfordern.');
        redirect(current_user() ? 'account' : 'verify.resend');
    }
    $user = one('SELECT * FROM users WHERE id = ?', [$t['user_id']]);
    if (!$user || $user['status'] === 'deleted') {
        flash('error', 'Das Konto existiert nicht mehr.');
        redirect('login');
    }
    if ($t['purpose'] === 'email_change') {
        if (val('SELECT id FROM users WHERE email = ? AND id <> ?', [$t['email'], $user['id']])) {
            flash('error', 'Diese E-Mail-Adresse wird inzwischen von einem anderen Konto verwendet.');
            redirect(current_user() ? 'account' : 'login');
        }
        q('UPDATE users SET email = ?, email_verified_at = NOW() WHERE id = ?', [$t['email'], $user['id']]);
        audit('user.email_changed', 'user', (int) $user['id']);
        flash('success', 'Ihre neue E-Mail-Adresse wurde bestätigt.');
        redirect(current_user() ? 'account' : 'login');
    }
    Verification::markVerified((int) $user['id']);
    audit('user.email_verified', 'user', (int) $user['id']);
    flash('success', 'Ihre E-Mail-Adresse wurde bestätigt. Sie können sich jetzt anmelden.');
    redirect(current_user() ? 'dashboard' : 'login');
}

/** Bestätigungslink erneut anfordern (antwortet immer gleich, um keine Konten preiszugeben). */
function auth_verify_resend(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        view('auth/verify', ['title' => 'E-Mail bestätigen']);
        return;
    }
    $email = mb_strtolower(input('email'));
    $max = 10;
    if ((int) val("SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND email LIKE 'verify:%' AND attempted_at > NOW() - INTERVAL 15 MINUTE", [client_ip()]) >= $max) {
        flash('error', 'Zu viele Anfragen. Bitte versuchen Sie es in einigen Minuten erneut.');
        redirect('verify.resend');
    }
    q('INSERT INTO login_attempts (ip, email) VALUES (?, ?)', [client_ip(), 'verify:' . mb_substr($email, 0, 180)]);
    if (!Mailer::configured()) {
        flash('error', 'Der E-Mail-Versand ist auf dieser Installation nicht eingerichtet. Bitte wenden Sie sich an den Administrator.');
        redirect('verify.resend');
    }
    $user = filter_var($email, FILTER_VALIDATE_EMAIL) ? one("SELECT * FROM users WHERE email = ? AND status = 'active' AND email_verified_at IS NULL", [$email]) : null;
    if ($user && Verification::canSend((int) $user['id'])) {
        Verification::sendVerify($user);
    }
    flash('success', 'Falls zu dieser Adresse ein unbestätigtes Konto existiert, wurde ein neuer Bestätigungslink gesendet.');
    redirect('login');
}

function auth_logout(): void
{
    audit('user.logout', 'user', uid());
    $_SESSION = [];
    session_regenerate_id(true);
    flash('success', 'Sie wurden abgemeldet.');
    redirect('login');
}

function auth_account(): void
{
    $user = current_user();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        view('auth/account', ['title' => 'Mein Konto', 'user' => $user, 'blockers' => Privacy::blockers($user)]);
        return;
    }
    $current = (string) ($_POST['current_password'] ?? '');

    if (input('action') === 'add_role') { // zweite Rolle zum bestehenden Konto hinzufügen
        $add = input('role');
        $ok = ($user['role'] === 'advertiser' && $add === 'publisher') || ($user['role'] === 'publisher' && $add === 'advertiser');
        if (!$ok) throw new UserError('Diese Rolle kann nicht hinzugefügt werden.');
        q("UPDATE users SET role = 'both' WHERE id = ? AND role = ?", [$user['id'], $user['role']]);
        audit('user.role_added', 'user', (int) $user['id'], ['role' => $add]);
        flash('success', $add === 'publisher' ? 'Ihr Konto kann jetzt auch Werbeplätze anbieten.' : 'Ihr Konto kann jetzt auch Werbeplätze buchen.');
        redirect('dashboard');
    }

    if (input('action') === 'password') {
        $new = (string) ($_POST['password'] ?? '');
        if (!password_verify($current, $user['password_hash'])) throw new UserError('Das aktuelle Passwort ist falsch.');
        if (strlen($new) < 10 || strlen($new) > 200) throw new UserError('Das neue Passwort muss mindestens 10 Zeichen lang sein.');
        if ($new !== ($_POST['password2'] ?? null)) throw new UserError('Die neuen Passwörter stimmen nicht überein.');
        q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), $user['id']]);
        login_user(one('SELECT * FROM users WHERE id = ?', [$user['id']])); // andere Sitzungen werden ungültig
        audit('user.password_changed', 'user', (int) $user['id']);
        flash('success', 'Ihr Passwort wurde geändert. Andere Sitzungen wurden abgemeldet.');
        redirect('account');
    }

    $name = input('name');
    $company = input('company');
    $email = mb_strtolower(input('email'));
    if (!len_between($name, 2, 120)) throw new UserError('Bitte einen Namen (2–120 Zeichen) angeben.');
    if (mb_strlen($company) > 160) throw new UserError('Der Firmenname ist zu lang.');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) throw new UserError('Bitte eine gültige E-Mail-Adresse angeben.');
    if ($email !== $user['email']) {
        if (!password_verify($current, $user['password_hash'])) throw new UserError('Zum Ändern der E-Mail-Adresse bitte das aktuelle Passwort angeben.');
        if (val('SELECT id FROM users WHERE email = ? AND id <> ?', [$email, $user['id']])) throw new UserError('Diese E-Mail-Adresse wird bereits verwendet.');
    }
    $payout = can_publish($user) ? input('payout_details') : (string) $user['payout_details'];
    if (mb_strlen($payout) > 500) throw new UserError('Die Auszahlungsdaten sind zu lang (max. 500 Zeichen).');
    $notify = !empty($_POST['notify_email']) ? 1 : 0;
    $emailChange = $email !== $user['email'];
    if ($emailChange && !Mailer::configured()) throw new UserError('Die E-Mail-Adresse kann derzeit nicht geändert werden, da der E-Mail-Versand nicht eingerichtet ist.');
    if ($emailChange && !Verification::canSend((int) $user['id'])) throw new UserError('Bitte warten Sie kurz, bevor Sie erneut einen Bestätigungslink anfordern.');
    q('UPDATE users SET name = ?, company = ?, payout_details = ?, notify_email = ? WHERE id = ?', [$name, $company ?: null, $payout !== '' ? $payout : null, $notify, $user['id']]);
    audit('user.profile_updated', 'user', (int) $user['id']);
    if ($emailChange) {
        // Die neue Adresse gilt erst nach Bestätigung; bis dahin bleibt die bisherige aktiv.
        Verification::sendEmailChange($user, $email);
        flash('success', 'Profil gespeichert. Zur Änderung der E-Mail-Adresse haben wir einen Link an ' . $email . ' gesendet – die Adresse ändert sich erst nach dem Bestätigen.');
    } else {
        flash('success', 'Ihr Profil wurde gespeichert.');
    }
    redirect('account');
}
