<?php
// VylarisAdvertise – Konfiguration
// Kopieren nach config/config.php (wird vom Web-Installer automatisch erzeugt).
// Dieses Verzeichnis darf NICHT im öffentlichen Webroot liegen.

return [
    'app' => [
        'name'     => 'VylarisAdvertise',
        // Öffentliche Basis-URL ohne abschliessenden Slash, z. B. https://ads.example.com
        'url'      => 'http://localhost:8080',
        'timezone' => 'Europe/Zurich',
        'debug'    => false,
        // Zufälliger geheimer Schlüssel (mind. 32 Zeichen)
        'secret'   => 'CHANGE_ME_TO_A_LONG_RANDOM_STRING',
    ],
    'db' => [
        'host'     => 'localhost',
        'port'     => 3306,
        'name'     => 'vylaris_advertise',
        'user'     => 'vylaris',
        'pass'     => '',
        'charset'  => 'utf8mb4',
    ],
    'session' => [
        'name'         => 'vyad_sess',
        'idle_minutes' => 60,
    ],
    'uploads' => [
        'max_bytes'   => 1024 * 1024,           // 1 MB pro Banner
        'allowed'     => ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/gif' => 'gif', 'image/webp' => 'webp'],
    ],
    'security' => [
        'login_max_attempts' => 5,     // pro E-Mail bzw. 3x pro IP
        'login_window_min'   => 15,
    ],
    // Datenschutz (DSGVO)
    'privacy' => [
        // Aufbewahrungsfrist für buchhalterisch relevante Daten nach Ende des Geschäftsjahres (CH: 10 Jahre, DE: 8 Jahre)
        'retention_years' => 10,
        // IP-Adressen im Protokoll (audit_log) werden nach dieser Anzahl Tage gelöscht
        'log_ip_days'     => 30,
    ],
    // Optionale Integrationen (VylarisGroup-Ökosystem). Ohne Eintrag läuft alles eigenständig.
    'integrations' => [
        // Lizenzprüfung gegen TRS License (Pflicht). Der Lizenzschlüssel wird im Installer bzw. unter Administration → Lizenz eingetragen.
        'licence_provider' => 'TrsLicence',
        'licence'          => [
            'endpoint'   => 'https://licence.vylaris.ch',
            'product'    => 'vylaris-advertise',   // Produktkennung auf dem Lizenzserver
            // Öffentlicher Ed25519-Schlüssel (Lizenz-Panel → Einstellungen). Bewusst hier fest hinterlegt, nicht vom Server geladen.
            'public_key' => "-----BEGIN PUBLIC KEY-----\nMCowBQYDK2VwAyEAhV7L176abx5a05XciRbOOeFj6wkCnNiDrwEXw7KceiU=\n-----END PUBLIC KEY-----\n",
            // Webseiten pro Betreiber, wenn die Lizenz den Scope "multi_site" nicht enthält
            'free_sites' => 1,
        ],
        // Selbst-Update aus GitHub-Releases (Einrichtung auch unter Administration → Updates)
        'update' => [
            'repo'        => 'VylarisGroup/vylaris-ads',  // Update-Quelle (GitHub-Releases); Token für private Repos wird verschlüsselt in der Datenbank abgelegt
            'api_base'    => 'https://api.github.com',
            'check_hours' => 24,                          // automatische Prüfung (nur Hinweis, keine Installation)
            'webroot'     => null,                        // Verzeichnis, aus dem der Webserver ausliefert (Standard: public/ der Anwendung)
        ],
        // Event-Listener: Datei, die Hooks::on(...) aufruft (optional)
        'listeners_file'   => null,
    ],
];
