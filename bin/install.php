<?php
declare(strict_types=1);

// CLI-Installation (Alternative zum Web-Installer), z. B. per SSH:
//   cp config/config.example.php config/config.php   # Zugangsdaten eintragen
//   php bin/install.php --admin-email=admin@example.com --admin-name="Admin" --admin-password="geheimes-passwort" --licence-key=VA-XXXX-XXXX-XXXX [--update-repo=konto/repo [--update-token=...]]
// Der öffentliche Lizenzschlüssel (Ed25519, PEM) gehört in config/config.php → integrations.licence.public_key.

if (PHP_SAPI !== 'cli') {
    exit('Nur über die Kommandozeile ausführbar.');
}
require dirname(__DIR__) . '/app/lib/Installer.php';

$opt = getopt('', ['admin-email:', 'admin-name:', 'admin-password:', 'licence-key:', 'update-repo:', 'update-token:']);
$configFile = Installer::root() . '/config/config.php';
if (!is_file($configFile)) {
    fwrite(STDERR, "config/config.php fehlt. Bitte config/config.example.php kopieren und anpassen.\n");
    exit(1);
}
$config = require $configFile;
if (str_contains((string) ($config['app']['secret'] ?? ''), 'CHANGE_ME')) {
    fwrite(STDERR, "Bitte in config/config.php einen zufälligen Wert für app.secret setzen, z. B.: " . bin2hex(random_bytes(32)) . "\n");
    exit(1);
}
foreach (Installer::requirements() as $label => $ok) {
    echo ($ok ? '[ok]   ' : '[FEHLT] ') . $label . "\n";
}
try {
    $pdo = Installer::connect($config['db']);
    Installer::runSchema($pdo);
    echo "Datenbankschema eingerichtet.\n";
    if (isset($opt['admin-email'], $opt['admin-password'])) {
        Installer::createAdmin($pdo, (string) ($opt['admin-name'] ?? 'Administrator'), (string) $opt['admin-email'], (string) $opt['admin-password']);
        echo "Administrator {$opt['admin-email']} angelegt.\n";
    } else {
        echo "Hinweis: Kein Administrator angelegt (Optionen --admin-email und --admin-password).\n";
    }
    if (!empty($opt['licence-key'])) {
        Installer::saveLicenceKey($pdo, (string) $opt['licence-key']);
        echo "Lizenzschlüssel gespeichert (öffentlicher Schlüssel: integrations.licence.public_key in config/config.php).\n";
    } else {
        echo "Hinweis: Kein Lizenzschlüssel angegeben (--licence-key) – ohne Lizenz läuft die Anwendung im Lesemodus. Eintrag später unter Administration → Lizenz.\n";
    }
    if (!empty($opt['update-repo'])) {
        Installer::saveUpdateSource($pdo, (string) $opt['update-repo'], (string) ($opt['update-token'] ?? ''), (string) $config['app']['secret']);
        echo "Update-Quelle gespeichert: {$opt['update-repo']}\n";
    }
    Installer::lock();
    echo "Fertig. Web-Installer ist gesperrt; public/install.php kann gelöscht werden.\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'Fehler: ' . $e->getMessage() . "\n");
    exit(1);
}
