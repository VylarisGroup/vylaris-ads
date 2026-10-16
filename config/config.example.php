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
        // Lizenzprüfung (TRS License): Server, Produkt und öffentlicher Schlüssel sind fest im Programm hinterlegt und nicht konfigurierbar.
        // Selbst-Update aus GitHub-Releases (Administration → Updates). Die Update-Quelle (Repository) ist fest im Programm hinterlegt
        // und nicht konfigurierbar.
        'update' => [
            'token'       => '',                          // nur falls das Release-Repository privat ist: Token mit Lesezugriff (Contents: read)
            'check_hours' => 24,                          // automatische Prüfung (nur Hinweis, keine Installation)
            'webroot'     => null,                        // Verzeichnis, aus dem der Webserver ausliefert (Standard: public/ der Anwendung)
        ],
        // Event-Listener: Datei, die Hooks::on(...) aufruft (optional)
        'listeners_file'   => null,
    ],
];
