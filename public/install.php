<?php
declare(strict_types=1);

// Web-Installer. Nach erfolgreicher Installation gesperrt (storage/installed.lock).
// Empfehlung: Datei nach der Installation löschen.

$appRoot = dirname(__DIR__);
require $appRoot . '/app/lib/Installer.php';

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'self'; style-src 'self'; font-src 'self'; img-src 'self' data:; form-action 'self'; frame-ancestors 'none'");
session_name('vyad_install');
session_start(['cookie_httponly' => true, 'cookie_samesite' => 'Strict']);
$_SESSION['csrf'] ??= bin2hex(random_bytes(32));

$h = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$errors = [];
$done = false;
$locked = Installer::isInstalled();
$hasConfig = is_file($appRoot . '/config/config.php');
$reqs = Installer::requirements();
$reqOk = !in_array(false, $reqs, true);

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$guessUrl = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/\\');
$f = [
    'db_host' => 'localhost', 'db_port' => '3306', 'db_name' => '', 'db_user' => '', 'app_url' => $guessUrl,
    'timezone' => 'Europe/Zurich', 'admin_name' => '', 'admin_email' => '', 'update_repo' => '', 'licence_key' => '', 'licence_endpoint' => 'https://licence.vylaris.ch',
];

if (!$locked && $_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($f as $k => $_) {
        $f[$k] = trim((string) ($_POST[$k] ?? ''));
    }
    if (!hash_equals($_SESSION['csrf'], (string) ($_POST['_csrf'] ?? ''))) {
        $errors[] = 'Ungültiges Formular. Bitte Seite neu laden.';
    } elseif (!$reqOk) {
        $errors[] = 'Bitte zuerst alle Systemanforderungen erfüllen.';
    } else {
        $db = ['host' => $f['db_host'], 'port' => (int) $f['db_port'], 'name' => $f['db_name'], 'user' => $f['db_user'], 'pass' => (string) ($_POST['db_pass'] ?? '')];
        if ($hasConfig) {
            $existing = require $appRoot . '/config/config.php';
            $db = $existing['db'];
        }
        if (!filter_var($f['app_url'], FILTER_VALIDATE_URL)) $errors[] = 'Bitte eine gültige Basis-URL angeben.';
        if (!in_array($f['timezone'], timezone_identifiers_list(), true)) $errors[] = 'Ungültige Zeitzone.';
        if ((string) ($_POST['admin_password'] ?? '') !== (string) ($_POST['admin_password2'] ?? '')) $errors[] = 'Die Administrator-Passwörter stimmen nicht überein.';
        $pub = trim((string) ($_POST['licence_public_key'] ?? ''));
        if (!$hasConfig && $pub !== '' && !Installer::validPublicKey($pub)) $errors[] = 'Der öffentliche Lizenzschlüssel ist kein gültiger Ed25519-Schlüssel (PEM).';
        if ($f['licence_key'] !== '' && !preg_match('/^[A-Za-z0-9][A-Za-z0-9-]{4,63}$/', $f['licence_key'])) $errors[] = 'Der Lizenzschlüssel hat ein ungültiges Format.';
        $updToken = trim((string) ($_POST['update_token'] ?? ''));
        if ($f['update_repo'] !== '' && !preg_match('#^[A-Za-z0-9_.-]{1,100}/[A-Za-z0-9_.-]{1,100}$#', $f['update_repo'])) $errors[] = 'Das GitHub-Repository muss das Format "besitzer/name" haben.';
        if ($updToken !== '' && !preg_match('/^[A-Za-z0-9_\-]{20,255}$/', $updToken)) $errors[] = 'Das GitHub-Token hat ein ungültiges Format.';
        if (!$errors) {
            try {
                $pdo = Installer::connect($db);
                if (Installer::hasAdmin($pdo)) {
                    throw new RuntimeException('In dieser Datenbank existiert bereits ein Administrator. Der Installer wird nicht erneut ausgeführt.');
                }
                Installer::runSchema($pdo);
                Installer::createAdmin($pdo, $f['admin_name'], $f['admin_email'], (string) ($_POST['admin_password'] ?? ''));
                if (!$hasConfig) {
                    Installer::writeConfig($db, $f['app_url'], $f['timezone'], ['public_key' => $pub, 'endpoint' => $f['licence_endpoint']]);
                }
                Installer::saveLicenceKey($pdo, $f['licence_key']);
                $secret = (string) (($hasConfig ? ($existing['app']['secret'] ?? '') : (require $appRoot . '/config/config.php')['app']['secret']));
                Installer::saveUpdateSource($pdo, $f['update_repo'], $updToken, $secret);
                Installer::lock();
                $done = true;
                $_SESSION = [];
            } catch (PDOException $e) {
                $errors[] = 'Datenbankfehler: ' . $e->getMessage();
            } catch (Throwable $e) {
                $errors[] = $e->getMessage();
            }
        }
    }
}
?><!doctype html>
<html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Installation · VylarisAdvertise</title><link rel="stylesheet" href="assets/app.css"><link rel="stylesheet" href="assets/boxicons/css/boxicons.min.css"></head>
<body class="min-h-screen bg-slate-50 py-10 text-slate-800">
<div class="mx-auto max-w-2xl px-4">
  <div class="mb-6 flex items-center gap-2 text-xl font-semibold"><span class="grid h-9 w-9 place-items-center rounded-lg bg-brand-600 text-white"><i class="bx bx-broadcast"></i></span> VylarisAdvertise · Installation</div>

  <?php if ($locked && !$done): ?>
    <div class="card p-6"><p class="font-medium">Die Anwendung ist bereits installiert.</p>
      <p class="mt-2 text-sm text-slate-600">Aus Sicherheitsgründen ist der Installer gesperrt. Löschen Sie <code>public/install.php</code> vom Server. Für eine Neuinstallation entfernen Sie <code>storage/installed.lock</code>.</p>
      <a class="btn-primary mt-4" href="index.php">Zur Anwendung</a></div>
  <?php elseif ($done): ?>
    <div class="card p-6"><p class="flex items-center gap-2 text-lg font-semibold text-emerald-700"><i class="bx bx-check-circle"></i> Installation abgeschlossen</p>
      <ul class="mt-3 list-disc space-y-1 pl-5 text-sm text-slate-600">
        <li>Bitte löschen Sie jetzt <code>public/install.php</code> vom Server.</li>
        <li>Melden Sie sich als Administrator an und hinterlegen Sie unter <em>Einstellungen</em> Bankverbindung und Provision.</li>
        <li>Optional: Cronjob für <code>bin/cron.php</code> einrichten (siehe README).</li>
      </ul>
      <a class="btn-primary mt-5" href="index.php?p=login">Zur Anmeldung</a></div>
  <?php else: ?>
    <div class="card mb-6 p-6">
      <h2 class="font-semibold">1. Systemanforderungen</h2>
      <ul class="mt-3 space-y-1 text-sm">
        <?php foreach ($reqs as $label => $ok): ?><li class="flex items-center gap-2 <?= $ok ? 'text-emerald-700' : 'text-rose-700' ?>"><i class="bx <?= $ok ? 'bx-check' : 'bx-x' ?>"></i><?= $h($label) ?></li><?php endforeach; ?>
      </ul>
    </div>
    <?php foreach ($errors as $err): ?><div class="mb-3 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800"><?= $h($err) ?></div><?php endforeach; ?>
    <form method="post" class="card space-y-5 p-6">
      <input type="hidden" name="_csrf" value="<?= $h($_SESSION['csrf']) ?>">
      <h2 class="font-semibold">2. Datenbank</h2>
      <?php if ($hasConfig): ?>
        <p class="rounded-lg bg-slate-50 p-3 text-sm text-slate-600">Es existiert bereits eine <code>config/config.php</code>. Deren Datenbankzugang wird verwendet.</p>
      <?php else: ?>
      <div class="grid gap-4 sm:grid-cols-3">
        <div class="sm:col-span-2"><label class="label">Host</label><input class="input" name="db_host" value="<?= $h($f['db_host']) ?>" required></div>
        <div><label class="label">Port</label><input class="input" name="db_port" value="<?= $h($f['db_port']) ?>" required></div>
        <div><label class="label">Datenbankname</label><input class="input" name="db_name" value="<?= $h($f['db_name']) ?>" required></div>
        <div><label class="label">Benutzer</label><input class="input" name="db_user" value="<?= $h($f['db_user']) ?>" required></div>
        <div><label class="label">Passwort</label><input class="input" type="password" name="db_pass"></div>
      </div>
      <?php endif; ?>
      <h2 class="font-semibold">3. Anwendung</h2>
      <div class="grid gap-4 sm:grid-cols-3">
        <div class="sm:col-span-2"><label class="label">Basis-URL (Ordner mit index.php)</label><input class="input" name="app_url" value="<?= $h($f['app_url']) ?>" required></div>
        <div><label class="label">Zeitzone</label><input class="input" name="timezone" value="<?= $h($f['timezone']) ?>" required></div>
      </div>
      <h2 class="font-semibold">4. Administrator</h2>
      <div class="grid gap-4 sm:grid-cols-2">
        <div><label class="label">Name</label><input class="input" name="admin_name" value="<?= $h($f['admin_name']) ?>" required></div>
        <div><label class="label">E-Mail</label><input class="input" type="email" name="admin_email" value="<?= $h($f['admin_email']) ?>" required></div>
        <div><label class="label">Passwort (min. 10 Zeichen)</label><input class="input" type="password" name="admin_password" minlength="10" required></div>
        <div><label class="label">Passwort wiederholen</label><input class="input" type="password" name="admin_password2" minlength="10" required></div>
      </div>
      <h2 class="font-semibold">5. Lizenz</h2>
      <p class="text-sm text-slate-600">VylarisAdvertise ist lizenzpflichtig. Ohne Lizenz läuft die Anwendung nur im Lesemodus; der Schlüssel kann später unter <em>Administration → Lizenz</em> eingetragen werden.</p>
      <div class="grid gap-4">
        <div><label class="label">Lizenzschlüssel</label><input class="input font-mono" name="licence_key" value="<?= $h($f['licence_key']) ?>" maxlength="64" autocomplete="off" placeholder="VA-XXXX-XXXX-XXXX"></div>
        <?php if (!$hasConfig): ?>
        <div><label class="label">Lizenzserver</label><input class="input" name="licence_endpoint" value="<?= $h($f['licence_endpoint']) ?>"></div>
        <div><label class="label">Öffentlicher Lizenzschlüssel (PEM; leer lassen = Standard-Schlüssel von licence.vylaris.ch)</label><textarea class="input font-mono text-xs" name="licence_public_key" rows="4" placeholder="-----BEGIN PUBLIC KEY-----"></textarea></div>
        <?php endif; ?>
      </div>
      <h2 class="font-semibold">6. Updates (optional)</h2>
      <p class="text-sm text-slate-600">Aktualisierung aus GitHub-Releases. Kann auch später unter <em>Administration → Updates</em> eingerichtet werden.</p>
      <div class="grid gap-4 sm:grid-cols-2">
        <div><label class="label">GitHub-Repository (besitzer/name)</label><input class="input font-mono" name="update_repo" value="<?= $h($f['update_repo']) ?>" maxlength="201" autocomplete="off"></div>
        <div><label class="label">Zugriffstoken (nur bei privatem Repository)</label><input class="input font-mono" type="password" name="update_token" maxlength="255" autocomplete="new-password"></div>
      </div>
      <button class="btn-primary" <?= $reqOk ? '' : 'disabled' ?>><i class="bx bx-rocket"></i> Installieren</button>
    </form>
  <?php endif; ?>
</div>
</body></html>
