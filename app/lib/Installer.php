<?php
declare(strict_types=1);

/** Gemeinsame Logik für Web- und CLI-Installation. */
final class Installer
{
    /** Aktuelle Schemaversion. Neue Versionen werden in migrate() ergänzt und laufen automatisch beim nächsten Aufruf. */
    public const SCHEMA_VERSION = 3;

    public static function root(): string
    {
        return dirname(__DIR__, 2);
    }

    public static function lockFile(): string
    {
        return self::root() . '/storage/installed.lock';
    }

    public static function isInstalled(): bool
    {
        return is_file(self::lockFile());
    }

    /** @return array<string, bool> */
    public static function requirements(): array
    {
        $root = self::root();
        return [
            'PHP 8.2 oder neuer (' . PHP_VERSION . ')'      => PHP_VERSION_ID >= 80200,
            'PDO MySQL-Erweiterung'                         => extension_loaded('pdo_mysql'),
            'Fileinfo-Erweiterung (Upload-Prüfung)'         => extension_loaded('fileinfo'),
            'Mbstring-Erweiterung'                          => extension_loaded('mbstring'),
            'config/ beschreibbar'                          => is_writable($root . '/config') || is_file($root . '/config/config.php'),
            'storage/ beschreibbar'                         => is_writable($root . '/storage'),
            'storage/banners/ beschreibbar'                 => is_writable($root . '/storage/banners'),
        ];
    }

    public static function connect(array $db): PDO
    {
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $db['host'], (int) $db['port'], $db['name']);
        return new PDO($dsn, $db['user'], $db['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }

    public static function runSchema(PDO $pdo): void
    {
        $sql = (string) file_get_contents(self::root() . '/database/schema.sql');
        $sql = preg_replace('/^\s*--.*$/m', '', $sql);
        foreach (preg_split('/;\s*(\r?\n|$)/', $sql) as $statement) {
            if (trim($statement) !== '') {
                $pdo->exec($statement);
            }
        }
        $pdo->exec("INSERT IGNORE INTO settings (name, value) VALUES ('schema_version', '1')");
        self::migrate($pdo);
    }

    private static function hasColumn(PDO $pdo, string $table, string $column): bool
    {
        $st = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
        $st->execute([$table, $column]);
        return (int) $st->fetchColumn() > 0;
    }

    /** Wendet ausstehende Schemaänderungen an (idempotent, mit Sperre gegen parallele Läufe). */
    public static function migrate(PDO $pdo): void
    {
        $current = static fn (): int => (int) ($pdo->query("SELECT value FROM settings WHERE name = 'schema_version'")->fetchColumn() ?: 1);
        if ($current() >= self::SCHEMA_VERSION) {
            return;
        }
        $pdo->query("SELECT GET_LOCK('vylaris_migrate', 20)")->fetchColumn();
        try {
            $v = $current();
            if ($v < 2) {
                // 2: DSGVO – Einwilligungsnachweis, Löschung/Anonymisierung, Rechtstexte
                foreach (['consent_at' => 'DATETIME NULL', 'consent_version' => 'VARCHAR(20) NULL', 'deleted_at' => 'DATETIME NULL', 'anonymize_after' => 'DATE NULL'] as $col => $ddl) {
                    if (!self::hasColumn($pdo, 'users', $col)) {
                        $pdo->exec("ALTER TABLE users ADD COLUMN $col $ddl");
                    }
                }
                $pdo->exec("ALTER TABLE users MODIFY status ENUM('active','blocked','deleted') NOT NULL DEFAULT 'active'");
                $defaults = ['legal_version' => '2026-10-14', 'legal_name' => '', 'legal_address' => '', 'legal_email' => '', 'legal_phone' => '',
                    'legal_register' => '', 'legal_representative' => '', 'legal_hoster' => '',
                    'legal_privacy_custom' => '', 'legal_imprint_custom' => '', 'legal_terms_custom' => ''];
                $ins = $pdo->prepare('INSERT IGNORE INTO settings (name, value) VALUES (?, ?)');
                foreach ($defaults as $k => $val) {
                    $ins->execute([$k, $val]);
                }
                $v = 2;
            }
            if ($v < 3) {
                // 3: Doppelrolle – ein Konto kann Werbetreibender und Webseitenbetreiber zugleich sein
                $pdo->exec("ALTER TABLE users MODIFY role ENUM('advertiser','publisher','both','admin') NOT NULL");
                $v = 3;
            }
            $pdo->prepare("INSERT INTO settings (name, value) VALUES ('schema_version', ?) ON DUPLICATE KEY UPDATE value = VALUES(value)")->execute([(string) $v]);
        } finally {
            $pdo->query("SELECT RELEASE_LOCK('vylaris_migrate')")->fetchColumn();
        }
    }

    /** Schutz vor Übernahme: Ist bereits ein Administrator vorhanden, darf der Web-Installer nicht erneut laufen. */
    public static function hasAdmin(PDO $pdo): bool
    {
        try {
            return (bool) $pdo->query("SELECT 1 FROM users WHERE role = 'admin' LIMIT 1")->fetchColumn();
        } catch (PDOException) {
            return false; // Tabelle existiert noch nicht
        }
    }

    public static function createAdmin(PDO $pdo, string $name, string $email, string $password): void
    {
        $email = mb_strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Ungültige E-Mail-Adresse für den Administrator.');
        }
        if (strlen($password) < 10) {
            throw new InvalidArgumentException('Das Administrator-Passwort muss mindestens 10 Zeichen lang sein.');
        }
        if (mb_strlen(trim($name)) < 2) {
            throw new InvalidArgumentException('Bitte einen Namen für den Administrator angeben.');
        }
        $exists = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $exists->execute([$email]);
        if ($exists->fetchColumn()) {
            $pdo->prepare("UPDATE users SET role = 'admin', status = 'active', password_hash = ?, name = ? WHERE email = ?")
                ->execute([password_hash($password, PASSWORD_DEFAULT), trim($name), $email]);
            return;
        }
        $pdo->prepare("INSERT INTO users (email, password_hash, name, role) VALUES (?, ?, ?, 'admin')")
            ->execute([$email, password_hash($password, PASSWORD_DEFAULT), trim($name)]);
    }

    public static function writeConfig(array $db, string $url, string $timezone, array $licence = []): void
    {
        $config = require self::root() . '/config/config.example.php';
        $config['app']['url'] = rtrim($url, '/');
        $config['app']['timezone'] = $timezone;
        $config['app']['secret'] = bin2hex(random_bytes(32));
        $config['db'] = array_merge($config['db'], $db);
        foreach (['endpoint', 'public_key', 'product'] as $k) {
            if (!empty($licence[$k])) $config['integrations']['licence'][$k] = (string) $licence[$k];
        }
        $php = "<?php\n// Automatisch erzeugt vom Installer am " . date('Y-m-d H:i') . ". Siehe config.example.php für Erläuterungen.\nreturn "
            . var_export($config, true) . ";\n";
        $file = self::root() . '/config/config.php';
        if (file_put_contents($file, $php, LOCK_EX) === false) {
            throw new RuntimeException('config/config.php konnte nicht geschrieben werden.');
        }
        @chmod($file, 0640);
    }

    /** Verschlüsselt ein Geheimnis (z. B. GitHub-Token) für die Ablage in der Datenbank; Schlüssel aus app.secret. */
    public static function sealSecret(string $plain, string $secret): string
    {
        $key = hash('sha256', 'vyad-seal|' . $secret, true);
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        return 's1:' . base64_encode($nonce . sodium_crypto_secretbox($plain, $nonce, $key));
    }

    public static function openSecret(string $sealed, string $secret): string
    {
        if (!str_starts_with($sealed, 's1:')) return '';
        $raw = base64_decode(substr($sealed, 3), true);
        if ($raw === false || strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) return '';
        $key = hash('sha256', 'vyad-seal|' . $secret, true);
        $plain = sodium_crypto_secretbox_open(substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), $key);
        return $plain === false ? '' : $plain;
    }

    /** Speichert den Lizenzschlüssel (Prüfung erfolgt beim ersten Aufruf bzw. unter Administration → Lizenz). */
    public static function saveLicenceKey(PDO $pdo, string $key): void
    {
        $key = trim($key);
        if ($key === '') return;
        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9-]{4,63}$/', $key)) {
            throw new RuntimeException('Der Lizenzschlüssel hat ein ungültiges Format.');
        }
        $pdo->prepare('INSERT INTO settings (name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)')->execute(['licence_key', $key]);
    }

    /** Speichert Update-Repository und (verschlüsselt) das optionale Token. */
    public static function saveUpdateSource(PDO $pdo, string $repo, string $token, string $secret): void
    {
        $repo = trim($repo);
        if ($repo === '') return;
        if (!preg_match('#^[A-Za-z0-9_.-]{1,100}/[A-Za-z0-9_.-]{1,100}$#', $repo)) throw new RuntimeException('Das GitHub-Repository muss das Format "besitzer/name" haben.');
        $st = $pdo->prepare('INSERT INTO settings (name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)');
        $st->execute(['update_repo', $repo]);
        $token = trim($token);
        if ($token !== '') {
            if (!preg_match('/^[A-Za-z0-9_\-]{20,255}$/', $token)) throw new RuntimeException('Das GitHub-Token hat ein ungültiges Format.');
            $st->execute(['update_token', self::sealSecret($token, $secret)]);
        }
    }

    /** Prüft, ob ein öffentlicher Ed25519-Schlüssel (PEM) gültig ist. */
    public static function validPublicKey(string $pem): bool
    {
        $der = base64_decode(preg_replace('/-----[^-]+-----|\s+/', '', $pem), true);
        return $der !== false && strlen($der) === 44 && str_starts_with($der, hex2bin('302a300506032b6570032100'));
    }

    public static function lock(): void
    {
        file_put_contents(self::lockFile(), 'installed ' . date('c') . "\n");
    }
}
