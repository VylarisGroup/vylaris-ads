<?php
declare(strict_types=1);

/**
 * NUR FÜR TESTS. Wird per PHP-Ini geladen (php -d auto_prepend_file=tests/dev_overrides.php …) und lenkt die
 * Update-Prüfung auf die Simulatoren um. Ohne diese Ini-Einstellung gelten ausschliesslich die fest einkompilierten Werte –
 * weder die Weboberfläche noch config/config.php können sie ändern.
 */
define('VYAD_UPDATE_API_BASE', 'http://127.0.0.1:8082');
