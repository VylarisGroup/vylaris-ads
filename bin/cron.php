<?php
declare(strict_types=1);

// Statuspflege per Cronjob (optional, empfohlen täglich kurz nach Mitternacht):
//   5 0 * * * php /pfad/zu/vylaris-advertise/bin/cron.php
// Ohne Cronjob läuft die Statuspflege automatisch bei Seitenaufrufen angemeldeter Benutzer.

if (PHP_SAPI !== 'cli') {
    exit('Nur über die Kommandozeile ausführbar.');
}
require dirname(__DIR__) . '/app/bootstrap.php';

Booking::housekeeping(true);
Privacy::housekeeping(true);
Updater::checkIfDue();
Mailer::flush(50);
if (licence() instanceof TrsLicence) { licence()->refreshIfDue(); }
echo date('c') . " Statuspflege und Datenschutz-Bereinigung ausgeführt.\n";
