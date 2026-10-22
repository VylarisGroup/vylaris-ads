<?php
declare(strict_types=1);

/**
 * E-Mail-Versand per SMTP (eigener Client ohne Abhängigkeiten) mit Warteschlange.
 *
 *  - Einrichtung im Admin-UI (Administration → System → E-Mail); die Werte liegen in `settings`,
 *    das SMTP-Passwort verschlüsselt (libsodium, Schlüssel aus app.secret).
 *  - Verschlüsselung: "tls" (implizit, meist Port 465), "starttls" (meist 587) oder "none" (nur ohne Zugangsdaten
 *    bzw. nur für localhost). Zertifikate werden immer geprüft.
 *  - Mails werden in `mail_queue` abgelegt und nach der Antwort an den Browser zugestellt (fastcgi_finish_request),
 *    bei Fehlern mehrfach mit wachsendem Abstand wiederholt (Cronjob bin/cron.php oder Admin-UI).
 *  - Nach erfolgreichem Versand wird der Nachrichtentext aus der Warteschlange gelöscht (Datenminimierung).
 */
final class Mailer
{
    public const MAX_ATTEMPTS = 5;
    private const BACKOFF_MIN = [1 => 5, 2 => 30, 3 => 120, 4 => 360];
    public const SECURE_MODES = ['starttls' => 'STARTTLS (Port 587)', 'tls' => 'SSL/TLS (Port 465)', 'none' => 'Unverschlüsselt (nur localhost)'];

    private static bool $flushScheduled = false;

    // ----- Konfiguration -------------------------------------------------------------------------------------

    /** @return array{enabled: bool, host: string, port: int, secure: string, user: string, from: string, from_name: string, has_pass: bool} */
    public static function config(): array
    {
        return [
            'enabled' => setting('mail_enabled') === '1',
            'host' => trim(setting('mail_host')),
            'port' => (int) (setting('mail_port', '587') ?: 587),
            'secure' => isset(self::SECURE_MODES[setting('mail_secure')]) ? setting('mail_secure') : 'starttls',
            'user' => setting('mail_user'),
            'from' => trim(setting('mail_from')),
            'from_name' => setting('mail_from_name'),
            'has_pass' => setting('mail_pass') !== '',
        ];
    }

    public static function configured(): bool
    {
        $c = self::config();
        return $c['enabled'] && $c['host'] !== '' && $c['port'] > 0 && filter_var($c['from'], FILTER_VALIDATE_EMAIL) !== false;
    }

    /** Prüft und speichert die Einstellungen (aus dem Admin-UI). Ein leeres Passwort lässt das bisherige unverändert. */
    public static function saveConfig(array $in): void
    {
        $host = trim((string) ($in['host'] ?? ''));
        $port = (int) ($in['port'] ?? 0);
        $secure = (string) ($in['secure'] ?? '');
        $user = trim((string) ($in['user'] ?? ''));
        $pass = (string) ($in['pass'] ?? '');
        $from = trim((string) ($in['from'] ?? ''));
        $fromName = trim(str_replace(["\r", "\n"], ' ', (string) ($in['from_name'] ?? '')));
        $enabled = !empty($in['enabled']);

        if (!preg_match('/^[A-Za-z0-9]([A-Za-z0-9.-]{0,188}[A-Za-z0-9])?$/', $host)) throw new UserError('Bitte einen gültigen SMTP-Server (Hostname oder IPv4-Adresse) angeben.');
        if ($port < 1 || $port > 65535) throw new UserError('Der Port muss zwischen 1 und 65535 liegen.');
        if (!isset(self::SECURE_MODES[$secure])) throw new UserError('Bitte eine Verschlüsselung wählen.');
        if (mb_strlen($user) > 190 || preg_match('/[\r\n]/', $user)) throw new UserError('Der Benutzername ist ungültig.');
        if (strlen($pass) > 300 || preg_match('/[\r\n]/', $pass)) throw new UserError('Das Passwort ist ungültig.');
        if (!filter_var($from, FILTER_VALIDATE_EMAIL) || mb_strlen($from) > 190) throw new UserError('Bitte eine gültige Absenderadresse angeben.');
        if (mb_strlen($fromName) > 80) throw new UserError('Der Absendername ist zu lang (max. 80 Zeichen).');
        $hasPass = $pass !== '' || setting('mail_pass') !== '';
        if ($secure === 'none' && ($user !== '' || $hasPass) && !self::isLoopback($host)) {
            throw new UserError('Zugangsdaten werden nicht unverschlüsselt übertragen. Bitte STARTTLS oder SSL/TLS wählen.');
        }

        set_setting('mail_host', $host);
        set_setting('mail_port', (string) $port);
        set_setting('mail_secure', $secure);
        set_setting('mail_user', $user);
        if ($pass !== '') set_setting('mail_pass', self::encrypt($pass));
        if ($user === '') set_setting('mail_pass', ''); // ohne Benutzer keine Anmeldung, kein Passwort behalten
        set_setting('mail_from', $from);
        set_setting('mail_from_name', $fromName);
        set_setting('mail_enabled', $enabled ? '1' : '0');
    }

    private static function isLoopback(string $host): bool
    {
        return in_array(strtolower($host), ['localhost', '127.0.0.1', '::1'], true) || str_starts_with($host, '127.');
    }

    private static function key(): string
    {
        return sodium_crypto_generichash('vyad-mail-v1|' . (string) config('app.secret'), '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
    }

    private static function encrypt(string $plain): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        return base64_encode($nonce . sodium_crypto_secretbox($plain, $nonce, self::key()));
    }

    private static function password(): string
    {
        $raw = base64_decode(setting('mail_pass'), true);
        if ($raw === false || strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) return '';
        $plain = sodium_crypto_secretbox_open(substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), self::key());
        return $plain === false ? '' : $plain;
    }

    // ----- Warteschlange ---------------------------------------------------------------------------------------

    /** Legt eine Nachricht in die Warteschlange (nur wenn der Versand eingerichtet ist). Gibt die ID oder null zurück. */
    public static function queue(string $toEmail, string $toName, string $subject, string $body, ?int $userId = null): ?int
    {
        $toEmail = trim($toEmail);
        if (!self::configured() || !filter_var($toEmail, FILTER_VALIDATE_EMAIL) || str_ends_with($toEmail, '.invalid')) {
            return null;
        }
        q('INSERT INTO mail_queue (user_id, to_email, to_name, subject, body) VALUES (?, ?, ?, ?, ?)',
            [$userId, mb_substr($toEmail, 0, 190), mb_substr(str_replace(["\r", "\n"], ' ', $toName), 0, 120), mb_substr(str_replace(["\r", "\n"], ' ', $subject), 0, 200), $body]);
        $id = (int) db()->lastInsertId();
        self::scheduleFlush();
        return $id;
    }

    /** Stellt nach Abschluss der Anfrage zu (die Antwort an den Browser wird vorher abgeschickt, soweit möglich). */
    private static function scheduleFlush(): void
    {
        if (self::$flushScheduled) return;
        self::$flushScheduled = true;
        register_shutdown_function(static function (): void {
            if (function_exists('fastcgi_finish_request')) @fastcgi_finish_request();
            try {
                self::flush(5);
            } catch (Throwable $e) {
                error_log('[VylarisAdvertise] Mail-Zustellung: ' . $e->getMessage());
            }
        });
    }

    /**
     * Stellt fällige Nachrichten zu. Bricht beim ersten Fehler ab (kein Warten auf mehrere Zeitüberschreitungen).
     * @return array{sent: int, failed: int}
     */
    public static function flush(int $limit = 5): array
    {
        $res = ['sent' => 0, 'failed' => 0];
        if (!self::configured()) return $res;
        q("UPDATE mail_queue SET status = 'failed', last_error = 'Zu alt, nicht mehr zugestellt' WHERE status = 'pending' AND created_at < NOW() - INTERVAL 2 DAY");
        $rows = all("SELECT * FROM mail_queue WHERE (status = 'pending' AND next_try_at <= NOW())
            OR (status = 'sending' AND claimed_at < NOW() - INTERVAL 5 MINUTE) ORDER BY id LIMIT " . max(1, $limit));
        foreach ($rows as $m) {
            // Beanspruchen: nur ein Prozess stellt eine Nachricht zu
            $claim = q("UPDATE mail_queue SET status = 'sending', claimed_at = NOW(), attempts = attempts + 1 WHERE id = ?
                AND (status = 'pending' OR (status = 'sending' AND claimed_at < NOW() - INTERVAL 5 MINUTE))", [$m['id']]);
            if ($claim->rowCount() !== 1) continue;
            $attempts = (int) $m['attempts'] + 1;
            try {
                self::send($m['to_email'], $m['to_name'], $m['subject'], $m['body'], 8);
                q("UPDATE mail_queue SET status = 'sent', sent_at = NOW(), body = '', last_error = NULL WHERE id = ?", [$m['id']]);
                $res['sent']++;
            } catch (Throwable $e) {
                $final = $attempts >= self::MAX_ATTEMPTS;
                $wait = self::BACKOFF_MIN[$attempts] ?? 360;
                q("UPDATE mail_queue SET status = ?, last_error = ?, next_try_at = NOW() + INTERVAL $wait MINUTE WHERE id = ?",
                    [$final ? 'failed' : 'pending', mb_substr($e->getMessage(), 0, 300), $m['id']]);
                $res['failed']++;
                break;
            }
        }
        return $res;
    }

    /** @return array{pending: int, failed: int, sent_24h: int} */
    public static function stats(): array
    {
        return [
            'pending' => (int) val("SELECT COUNT(*) FROM mail_queue WHERE status IN ('pending','sending')"),
            'failed' => (int) val("SELECT COUNT(*) FROM mail_queue WHERE status = 'failed'"),
            'sent_24h' => (int) val("SELECT COUNT(*) FROM mail_queue WHERE status = 'sent' AND sent_at > NOW() - INTERVAL 1 DAY"),
        ];
    }

    // ----- SMTP --------------------------------------------------------------------------------------------------

    /** Sendet sofort (wirft bei Fehlern UserError-taugliche RuntimeException). */
    public static function send(string $toEmail, string $toName, string $subject, string $body, int $timeout = 10): void
    {
        if (!self::configured()) throw new RuntimeException('Der E-Mail-Versand ist nicht eingerichtet.');
        $c = self::config();
        if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Ungültige Empfängeradresse.');
        $message = self::build($c['from'], $c['from_name'], $toEmail, $toName, $subject, $body);
        self::deliver($c, self::password(), $c['from'], $toEmail, $message, $timeout);
    }

    private static function header(string $s): string
    {
        return trim(str_replace(["\r", "\n", "\0"], ' ', $s));
    }

    private static function encodeHeader(string $s): string
    {
        $s = self::header($s);
        return preg_match('/^[\x20-\x7E]*$/', $s) ? $s : mb_encode_mimeheader($s, 'UTF-8', 'B', "\r\n");
    }

    private static function address(string $email, string $name): string
    {
        $email = self::header($email);
        $name = self::header($name);
        if ($name === '') return '<' . $email . '>';
        $enc = preg_match('/^[\x20-\x7E]*$/', $name) ? '"' . addcslashes($name, '"\\') . '"' : mb_encode_mimeheader($name, 'UTF-8', 'B', "\r\n");
        return $enc . ' <' . $email . '>';
    }

    private static function build(string $from, string $fromName, string $to, string $toName, string $subject, string $body): string
    {
        $host = (string) parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost';
        $h = [
            'Date: ' . date('r'),
            'From: ' . self::address($from, $fromName),
            'To: ' . self::address($to, $toName),
            'Subject: ' . self::encodeHeader($subject),
            'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . preg_replace('/[^A-Za-z0-9.-]/', '', $host) . '>',
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
            'Auto-Submitted: auto-generated',
            'X-Auto-Response-Suppress: All',
        ];
        $body = str_replace(["\r\n", "\r"], "\n", $body);
        return implode("\r\n", $h) . "\r\n\r\n" . chunk_split(base64_encode(str_replace("\n", "\r\n", $body)), 76, "\r\n");
    }

    /** @param resource $fp */
    private static function reply($fp): array
    {
        $lines = [];
        while (($l = fgets($fp, 1024)) !== false) {
            $lines[] = rtrim($l);
            if (strlen($l) < 4 || $l[3] !== '-') break;
        }
        if (!$lines) {
            $meta = stream_get_meta_data($fp);
            throw new RuntimeException(!empty($meta['timed_out']) ? 'Zeitüberschreitung: der Mailserver antwortet nicht.' : 'Der Mailserver hat die Verbindung beendet.');
        }
        return [(int) substr($lines[0], 0, 3), $lines];
    }

    /** @param resource $fp @param list<int> $ok */
    private static function cmd($fp, string $line, array $ok, bool $secret = false): array
    {
        fwrite($fp, $line . "\r\n");
        [$code, $lines] = self::reply($fp);
        if (!in_array($code, $ok, true)) {
            $what = $secret ? 'Anmeldung' : strtok($line, ' ');
            throw new RuntimeException('Mailserver lehnt ab (' . $what . '): ' . mb_substr(implode(' ', $lines), 0, 200));
        }
        return $lines;
    }

    private static function deliver(array $c, string $pass, string $from, string $to, string $message, int $timeout): void
    {
        $host = $c['host'];
        $ssl = ['verify_peer' => true, 'verify_peer_name' => true, 'peer_name' => $host, 'SNI_enabled' => true, 'allow_self_signed' => false];
        if (defined('VYAD_SMTP_CAFILE')) $ssl['cafile'] = (string) VYAD_SMTP_CAFILE; // nur für Tests (tests/dev_overrides.php)
        $ctx = stream_context_create(['ssl' => $ssl]);
        $scheme = $c['secure'] === 'tls' ? 'tls' : 'tcp';
        $fp = @stream_socket_client($scheme . '://' . $host . ':' . $c['port'], $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $ctx);
        if (!$fp) {
            throw new RuntimeException('Keine Verbindung zu ' . $host . ':' . $c['port'] . ' (' . ($errstr !== '' ? $errstr : 'Fehler ' . $errno) . ').');
        }
        stream_set_timeout($fp, $timeout);
        try {
            [$code, $greet] = self::reply($fp);
            if ($code !== 220) throw new RuntimeException('Unerwartete Begrüssung des Mailservers: ' . mb_substr(implode(' ', $greet), 0, 120));
            $me = preg_replace('/[^A-Za-z0-9.-]/', '', (string) parse_url((string) config('app.url'), PHP_URL_HOST)) ?: 'localhost';
            $caps = self::cmd($fp, 'EHLO ' . $me, [250]);
            if ($c['secure'] === 'starttls') {
                if (!preg_grep('/\bSTARTTLS\b/i', $caps)) throw new RuntimeException('Der Mailserver bietet kein STARTTLS an.');
                self::cmd($fp, 'STARTTLS', [220]);
                if (!@stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new RuntimeException('TLS-Verbindung fehlgeschlagen (Zertifikat des Mailservers ungültig oder nicht vertrauenswürdig).');
                }
                $caps = self::cmd($fp, 'EHLO ' . $me, [250]);
            } elseif ($c['secure'] === 'none' && $c['user'] !== '' && !self::isLoopback($host)) {
                throw new RuntimeException('Unverschlüsselte Anmeldung ist nur für localhost erlaubt.');
            }
            if ($c['user'] !== '') {
                $auth = strtoupper(implode(' ', $caps));
                if (str_contains($auth, 'AUTH') && str_contains($auth, 'PLAIN')) {
                    self::cmd($fp, 'AUTH PLAIN ' . base64_encode("\0" . $c['user'] . "\0" . $pass), [235], true);
                } elseif (str_contains($auth, 'AUTH') && str_contains($auth, 'LOGIN')) {
                    self::cmd($fp, 'AUTH LOGIN', [334], true);
                    self::cmd($fp, base64_encode($c['user']), [334], true);
                    self::cmd($fp, base64_encode($pass), [235], true);
                } else {
                    throw new RuntimeException('Der Mailserver bietet keine unterstützte Anmeldung (PLAIN/LOGIN) an.');
                }
            }
            self::cmd($fp, 'MAIL FROM:<' . self::header($from) . '>', [250]);
            self::cmd($fp, 'RCPT TO:<' . self::header($to) . '>', [250, 251]);
            self::cmd($fp, 'DATA', [354]);
            fwrite($fp, preg_replace('/^\./m', '..', $message) . ".\r\n");
            [$code, $lines] = self::reply($fp);
            if ($code !== 250) throw new RuntimeException('Mailserver lehnt die Nachricht ab: ' . mb_substr(implode(' ', $lines), 0, 200));
            @fwrite($fp, "QUIT\r\n");
        } finally {
            @fclose($fp);
        }
    }
}
