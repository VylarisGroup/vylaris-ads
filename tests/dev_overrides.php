<?php
declare(strict_types=1);

/**
 * NUR FÜR TESTS. Wird per PHP-Ini geladen (php -d auto_prepend_file=tests/dev_overrides.php …) und lenkt Lizenz- und
 * Update-Prüfung auf die Simulatoren um. Ohne diese Ini-Einstellung gelten ausschliesslich die fest einkompilierten Werte –
 * weder die Weboberfläche noch config/config.php können sie ändern.
 */
$kp = sodium_crypto_sign_seed_keypair(str_repeat('t', 32)); // gleicher Schlüssel wie tests/fake_licence.php
define('VYAD_LICENCE_ENDPOINT', 'http://127.0.0.1:8081');
define('VYAD_LICENCE_PUBLIC_KEY', "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode(hex2bin('302a300506032b6570032100') . sodium_crypto_sign_publickey($kp)), 64, "\n") . "-----END PUBLIC KEY-----\n");
define('VYAD_UPDATE_API_BASE', 'http://127.0.0.1:8082');
