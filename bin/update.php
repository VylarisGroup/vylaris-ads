<?php
declare(strict_types=1);

// Update-Werkzeug für die Kommandozeile (auch Notfall-Rollback, falls die Weboberfläche nicht mehr läuft):
//   php bin/update.php check        # neueste Version im GitHub-Repository abfragen
//   php bin/update.php install      # neueste Version installieren (vorher Datenbank sichern!)
//   php bin/update.php rollback     # letzte Sicherung wiederherstellen

if (PHP_SAPI !== 'cli') {
    exit('Nur über die Kommandozeile ausführbar.');
}
require dirname(__DIR__) . '/app/bootstrap.php';

try {
    switch ($argv[1] ?? '') {
        case 'check':
            $l = Updater::check();
            echo 'Installiert: ' . APP_VERSION . ', neuestes Release: ' . $l['version'] . (version_compare($l['version'], APP_VERSION, '>') ? " → Update verfügbar\n" : " (aktuell)\n");
            break;
        case 'install':
            echo implode("\n", Updater::install()) . "\n";
            break;
        case 'rollback':
            echo 'Zurückgesetzt auf Version ' . Updater::rollback($argv[2] ?? null) . "\n";
            break;
        default:
            echo "Verwendung: php bin/update.php check|install|rollback [sicherung.zip]\n";
            exit(1);
    }
} catch (Throwable $e) {
    fwrite(STDERR, 'Fehler: ' . $e->getMessage() . "\n");
    exit(1);
}
