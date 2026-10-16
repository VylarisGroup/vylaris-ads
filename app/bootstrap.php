<?php
declare(strict_types=1);

// Gemeinsamer Einstiegspunkt für Web (public/index.php) und CLI (bin/*.php).

if (PHP_VERSION_ID < 80200) {
    http_response_code(500);
    exit('VylarisAdvertise benötigt PHP 8.2 oder neuer.');
}

define('APP_ROOT', dirname(__DIR__));
define('APP_VERSION', '1.5.0');

$configFile = APP_ROOT . '/config/config.php';
if (!is_file($configFile)) {
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, "config/config.php fehlt. Bitte zuerst installieren (siehe README).\n");
        exit(1);
    }
    header('Location: install.php');
    exit;
}

$GLOBALS['config'] = require $configFile;
date_default_timezone_set(config('app.timezone', 'Europe/Zurich'));
mb_internal_encoding('UTF-8');

if (config('app.debug')) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', APP_ROOT . '/storage/logs/php-error.log');
}

require __DIR__ . '/lib/helpers.php';
require __DIR__ . '/lib/Hooks.php';
require __DIR__ . '/lib/Licence.php';
require __DIR__ . '/lib/Upload.php';
require __DIR__ . '/lib/Booking.php';
require __DIR__ . '/lib/Billing.php';
require __DIR__ . '/lib/Installer.php';
require __DIR__ . '/lib/Updater.php';
require __DIR__ . '/lib/Legal.php';
require __DIR__ . '/lib/Privacy.php';

// Schemaänderungen nach einem Update automatisch einspielen
if ((int) setting('schema_version', '1') < Installer::SCHEMA_VERSION) {
    Installer::migrate(db());
    setting('__reset');
}

$listeners = config('integrations.listeners_file');
if ($listeners && is_file($listeners)) {
    require $listeners;
}

/**
 * Konfigurationswert per Punktnotation lesen, z. B. config('db.host').
 */
function config(string $key, mixed $default = null): mixed
{
    $value = $GLOBALS['config'];
    foreach (explode('.', $key) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return $default;
        }
        $value = $value[$part];
    }
    return $value;
}
