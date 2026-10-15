<?php
declare(strict_types=1);

/**
 * Selbst-Update aus GitHub-Releases (öffentlich oder privat mit Lesetoken).
 *
 * Ablauf: neuestes Release lesen (Tag vX.Y.Z) → ZIP laden → sicher entpacken → prüfen (Struktur, Version, PHP-Syntax)
 * → Sicherung der betroffenen Dateien → Wartungsmodus → Dateien austauschen (Konfiguration, storage/ und
 * Uploads bleiben unberührt) → bei Fehler automatische Wiederherstellung. Datenbankänderungen spielt
 * Installer::migrate() beim nächsten Aufruf ein. Eine manuelle oder automatische Wiederherstellung ist über
 * "Rollback" bzw. bin/update.php möglich.
 *
 * Vertrauensmodell: HTTPS zu GitHub + Zugriff auf das Repository. Wer Releases im Repository veröffentlichen kann,
 * kann Code in alle Installationen einspielen – Repository-Zugriff (2FA, Branch-/Tag-Schutz) entsprechend sichern.
 */
final class Updater
{
    /** Vollständig gespiegelte Verzeichnisse (verwaiste Dateien werden entfernt). */
    private const MIRRORED = ['app', 'bin', 'database', 'docs', 'resources', 'tests'];
    /** Zusätzliche Einzeldateien im Projektstamm. */
    private const FILES = ['README.md', 'LICENSE', 'CHANGELOG.md', 'config/config.example.php'];
    private const MAX_ZIP = 60 * 1024 * 1024;
    private const MAX_UNPACKED = 200 * 1024 * 1024;
    private const MAX_ENTRIES = 6000;
    private const KEEP_BACKUPS = 3;

    // ----- Konfiguration -------------------------------------------------------------------------------------

    public static function repo(): string
    {
        $r = trim(setting('update_repo'));
        return $r !== '' ? $r : trim((string) config('integrations.update.repo', ''));
    }

    public static function validRepo(string $repo): bool
    {
        return (bool) preg_match('#^[A-Za-z0-9_.-]{1,100}/[A-Za-z0-9_.-]{1,100}$#', $repo);
    }

    public static function configured(): bool { return self::validRepo(self::repo()); }

    public static function apiBase(): string { return rtrim((string) config('integrations.update.api_base', 'https://api.github.com'), '/'); }

    /** Verzeichnis, aus dem der Webserver ausliefert (Standard: public/ der Anwendung). */
    public static function webroot(): string
    {
        $w = (string) config('integrations.update.webroot', '');
        return rtrim($w !== '' ? $w : APP_ROOT . '/public', '/\\');
    }

    public static function token(): string
    {
        $v = setting('update_token');
        return $v === '' ? '' : Installer::openSecret($v, (string) config('app.secret'));
    }

    public static function saveSettings(string $repo, ?string $token, bool $removeToken): void
    {
        if ($repo !== '' && !self::validRepo($repo)) throw new UserError('Bitte das Repository im Format "besitzer/name" angeben.');
        set_setting('update_repo', $repo);
        if ($removeToken) {
            set_setting('update_token', '');
        } elseif ($token !== null && $token !== '') {
            if (!preg_match('/^[A-Za-z0-9_\-]{20,255}$/', $token)) throw new UserError('Das Token hat ein ungültiges Format.');
            set_setting('update_token', Installer::sealSecret($token, (string) config('app.secret')));
        }
        $st = self::state();
        unset($st['latest'], $st['error']);
        $st['next_check'] = 0;
        self::saveState($st);
    }

    // ----- Status --------------------------------------------------------------------------------------------

    public static function state(): array
    {
        $j = json_decode(setting('update_state', '{}'), true);
        return is_array($j) ? $j : [];
    }

    private static function saveState(array $st): void
    {
        set_setting('update_state', json_encode($st, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /** Neuere Version, falls bekannt (aus der letzten Prüfung): [version, notes, url, published]. */
    public static function available(): ?array
    {
        $l = self::state()['latest'] ?? null;
        return is_array($l) && version_compare((string) ($l['version'] ?? '0'), APP_VERSION, '>') ? $l : null;
    }

    /** Tägliche automatische Prüfung (nur Hinweis, installiert wird nichts). Wirft nie. */
    public static function checkIfDue(): void
    {
        try {
            if (!self::configured() || setting('update_auto', '1') !== '1') return;
            if ((int) (self::state()['next_check'] ?? 0) > time()) return;
            self::check();
        } catch (Throwable $e) {
            error_log('[VylarisAdvertise] Update-Prüfung: ' . $e->getMessage());
        }
    }

    /** Fragt das neueste Release ab und speichert das Ergebnis. */
    public static function check(): array
    {
        if (!self::configured()) throw new UserError('Es ist kein GitHub-Repository hinterlegt.');
        $st = self::state();
        $st['next_check'] = time() + 3600; // bei Fehlern frühestens in einer Stunde erneut
        $st['checked_at'] = time();
        self::saveState($st);
        try {
            $res = self::http(self::apiBase() . '/repos/' . self::repo() . '/releases/latest', null, true);
        } catch (RuntimeException $e) {
            $st['error'] = $e->getMessage();
            self::saveState($st);
            throw new UserError($e->getMessage());
        }
        $j = json_decode($res, true);
        $tag = is_array($j) ? (string) ($j['tag_name'] ?? '') : '';
        if (!preg_match('/^v?(\d+\.\d+\.\d+)$/', $tag, $m) || empty($j['zipball_url'])) {
            $st['error'] = 'Das neueste Release hat kein gültiges Versions-Tag (erwartet: vX.Y.Z).';
            unset($st['latest']);
            self::saveState($st);
            throw new UserError($st['error']);
        }
        $st['error'] = '';
        $st['latest'] = [
            'version' => $m[1], 'tag' => $tag, 'zip' => (string) $j['zipball_url'],
            'notes' => mb_substr((string) ($j['body'] ?? ''), 0, 4000), 'url' => (string) ($j['html_url'] ?? ''), 'published' => (string) ($j['published_at'] ?? ''),
        ];
        $st['next_check'] = time() + max(1, (int) config('integrations.update.check_hours', 24)) * 3600;
        self::saveState($st);
        return $st['latest'];
    }

    /** Voraussetzungen für ein Update (Bezeichnung => erfüllt). */
    public static function requirements(): array
    {
        $dirs = [];
        foreach (self::MIRRORED as $d) $dirs[] = APP_ROOT . '/' . $d;
        $writable = true;
        foreach (array_merge($dirs, [APP_ROOT . '/config', self::webroot()]) as $d) {
            if (is_dir($d) && !is_writable($d)) $writable = false;
        }
        return [
            'PHP-Erweiterung zip (ZipArchive)' => class_exists('ZipArchive'),
            'PHP-Erweiterung curl' => function_exists('curl_init'),
            'Programmverzeichnisse beschreibbar' => $writable,
            'storage/ beschreibbar' => is_writable(APP_ROOT . '/storage'),
        ];
    }

    /** @return list<array{file: string, name: string, size: int, time: int}> */
    public static function backups(): array
    {
        $out = [];
        foreach (glob(APP_ROOT . '/storage/backups/vylaris-advertise-*.zip') ?: [] as $f) {
            $out[] = ['file' => basename($f), 'name' => preg_replace('/^vylaris-advertise-|\.zip$/', '', basename($f)), 'size' => (int) filesize($f), 'time' => (int) filemtime($f)];
        }
        usort($out, fn ($a, $b) => $b['time'] <=> $a['time'] ?: strcmp($b['file'], $a['file']));
        return $out;
    }

    public static function maintenanceActive(): bool
    {
        $f = APP_ROOT . '/storage/maintenance.flag';
        return is_file($f) && filemtime($f) > time() - 900;
    }

    // ----- Installation --------------------------------------------------------------------------------------

    /** @return list<string> Protokollzeilen */
    public static function install(): array
    {
        if (!self::configured()) throw new UserError('Es ist kein GitHub-Repository hinterlegt.');
        foreach (self::requirements() as $label => $ok) {
            if (!$ok) throw new UserError('Voraussetzung nicht erfüllt: ' . $label . '.');
        }
        @set_time_limit(300);
        @ignore_user_abort(true);
        $lockFile = APP_ROOT . '/storage/update.lock';
        $lock = fopen($lockFile, 'c');
        if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) throw new UserError('Es läuft bereits ein Update.');

        $log = [];
        $work = APP_ROOT . '/storage/update-' . bin2hex(random_bytes(4));
        $flag = APP_ROOT . '/storage/maintenance.flag';
        $backup = null;
        $applied = false;
        try {
            $latest = self::check();
            $version = $latest['version'];
            if (!version_compare($version, APP_VERSION, '>')) throw new UserError('Es ist bereits die neueste Version (' . APP_VERSION . ') installiert.');
            $log[] = 'Neue Version ' . $version . ' gefunden (installiert: ' . APP_VERSION . ').';

            mkdir($work, 0700, true);
            $zip = $work . '/release.zip';
            self::http($latest['zip'], $zip, true);
            $log[] = 'Release heruntergeladen (' . number_format((int) filesize($zip) / 1024, 0, ',', "'") . ' KB).';

            $new = $work . '/new';
            self::extract($zip, $new);
            @unlink($zip);
            $log[] = 'Archiv sicher entpackt.';
            $files = self::verifyTree($new, $version);
            $log[] = 'Prüfung bestanden: Struktur, Version ' . $version . ', PHP-Syntax von ' . count(self::phpFiles($files)) . ' Dateien.';

            $plan = self::plan($new, $files);
            self::preflight($plan);
            $backup = self::backup($plan);
            $log[] = 'Sicherung erstellt: ' . basename($backup) . '.';

            file_put_contents($flag, (string) time());
            $log[] = 'Wartungsmodus aktiviert.';
            self::apply($plan, $backup);
            $applied = true;
            $log[] = 'Dateien ausgetauscht (' . count($plan['copy']) . ' aktualisiert, ' . count($plan['orphans']) . ' entfernt).';
            if (function_exists('opcache_reset')) @opcache_reset();

            $st = self::state();
            $st['installed'] = ['from' => APP_VERSION, 'to' => $version, 'at' => time(), 'backup' => basename($backup)];
            self::saveState($st);
            audit('update.installed', 'system', null, ['from' => APP_VERSION, 'to' => $version, 'backup' => basename($backup)]);
            $log[] = 'Fertig. Datenbankänderungen werden beim nächsten Seitenaufruf automatisch eingespielt.';
            self::pruneBackups();
            return $log;
        } catch (UserError $e) {
            throw $e;
        } catch (Throwable $e) {
            error_log('[VylarisAdvertise] Update fehlgeschlagen: ' . $e->getMessage());
            throw new UserError('Update fehlgeschlagen und zurückgesetzt: ' . $e->getMessage());
        } finally {
            @unlink($flag);
            self::rmTree($work);
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /** Stellt den Stand der letzten (oder angegebenen) Sicherung wieder her. */
    public static function rollback(?string $file = null): string
    {
        $all = self::backups();
        if (!$all) throw new UserError('Es ist keine Sicherung vorhanden.');
        $name = $file ?? $all[0]['file'];
        if (!preg_match('/^vylaris-advertise-[0-9A-Za-z._-]+\.zip$/', $name) || !in_array($name, array_column($all, 'file'), true)) throw new UserError('Unbekannte Sicherung.');
        $zip = APP_ROOT . '/storage/backups/' . $name;
        $lock = fopen(APP_ROOT . '/storage/update.lock', 'c');
        if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) throw new UserError('Es läuft bereits ein Update.');
        $flag = APP_ROOT . '/storage/maintenance.flag';
        try {
            file_put_contents($flag, (string) time());
            $ver = self::restore($zip);
            if (function_exists('opcache_reset')) @opcache_reset();
            $st = self::state();
            $st['installed'] = ['from' => APP_VERSION, 'to' => $ver, 'at' => time(), 'backup' => $name, 'rollback' => true];
            self::saveState($st);
            audit('update.rollback', 'system', null, ['from' => APP_VERSION, 'to' => $ver]);
            return $ver;
        } finally {
            @unlink($flag);
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    // ----- Bausteine -----------------------------------------------------------------------------------------

    /** Lädt eine URL (GET). Ohne $saveTo wird der Inhalt zurückgegeben. Weiterleitungen nur zu GitHub-Hosts, Token nur an den API-Host. */
    private static function http(string $url, ?string $saveTo, bool $auth): string
    {
        $apiHost = (string) parse_url(self::apiBase(), PHP_URL_HOST);
        $allowed = array_unique([$apiHost, 'github.com', 'codeload.github.com', 'objects.githubusercontent.com', 'api.github.com']);
        $token = $auth ? self::token() : '';
        for ($hop = 0; $hop < 4; $hop++) {
            $p = parse_url($url);
            $host = (string) ($p['host'] ?? '');
            $local = in_array($host, ['127.0.0.1', 'localhost', '::1'], true);
            if (!in_array($host, $allowed, true) || (($p['scheme'] ?? '') !== 'https' && !(($p['scheme'] ?? '') === 'http' && $local))) {
                throw new RuntimeException('Ungültige oder nicht erlaubte Adresse für den Download.');
            }
            $headers = ['Accept: ' . ($saveTo ? 'application/vnd.github+json, application/octet-stream' : 'application/vnd.github+json'), 'X-GitHub-Api-Version: 2022-11-28'];
            if ($token !== "" && $hop === 0 && $host === $apiHost) $headers[] = 'Authorization: Bearer ' . $token;
            $fh = $saveTo ? fopen($saveTo, 'wb') : null;
            $loc = '';
            $body = '';
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_HTTPHEADER => $headers, CURLOPT_FOLLOWLOCATION => false, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => $saveTo ? 120 : 10,
                CURLOPT_USERAGENT => 'VylarisAdvertise/' . APP_VERSION, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_PROTOCOLS => CURLPROTO_HTTPS | CURLPROTO_HTTP,
                CURLOPT_HEADERFUNCTION => function ($c, $h) use (&$loc) { if (stripos($h, 'location:') === 0) $loc = trim(substr($h, 9)); return strlen($h); },
                CURLOPT_NOPROGRESS => false,
                CURLOPT_PROGRESSFUNCTION => fn ($c, $dt, $d) => $d > self::MAX_ZIP ? 1 : 0,
            ]);
            if ($fh) { curl_setopt($ch, CURLOPT_FILE, $fh); } else { curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($c, $d) use (&$body) { $body .= $d; return strlen($body) > 2_000_000 ? 0 : strlen($d); }); }
            $ok = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $err = curl_error($ch);
            curl_close($ch);
            if ($fh) fclose($fh);
            if ($code >= 300 && $code < 400 && $loc !== '') {
                $url = str_starts_with($loc, 'http') ? $loc : (($p['scheme'] ?? 'https') . '://' . $host . (isset($p['port']) ? ':' . $p['port'] : '') . $loc);
                continue;
            }
            if ($ok === false) throw new RuntimeException('Verbindung zu GitHub fehlgeschlagen: ' . $err);
            if ($code === 401 || $code === 403) throw new RuntimeException('GitHub verweigert den Zugriff (HTTP ' . $code . '). Bei privaten Repositories ein Token mit Lesezugriff hinterlegen; ggf. Rate-Limit erreicht.');
            if ($code === 404) throw new RuntimeException('Repository oder Release nicht gefunden (HTTP 404). Bei privaten Repositories fehlt ggf. das Token.');
            if ($code !== 200) throw new RuntimeException('GitHub antwortete mit HTTP ' . $code . '.');
            return $saveTo ? '' : $body;
        }
        throw new RuntimeException('Zu viele Weiterleitungen.');
    }

    /** Entpackt das ZIP nach $dest, ohne Pfadtricks. Das Wurzelverzeichnis (mit app/bootstrap.php) wird auf $dest abgebildet. */
    private static function extract(string $zipFile, string $dest): void
    {
        $zip = new ZipArchive();
        if ($zip->open($zipFile) !== true) throw new RuntimeException('Das Archiv ist ungültig.');
        if ($zip->numFiles < 1 || $zip->numFiles > self::MAX_ENTRIES) throw new RuntimeException('Das Archiv hat eine unerwartete Grösse.');
        $names = [];
        $total = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $st = $zip->statIndex($i);
            $n = (string) $st['name'];
            if ($n === '' || str_contains($n, "\0") || str_contains($n, '\\') || str_starts_with($n, '/') || preg_match('#(^|/)\.\.(/|$)#', $n) || preg_match('#^[A-Za-z]:#', $n)) {
                throw new RuntimeException('Das Archiv enthält unzulässige Pfade.');
            }
            $zip->getExternalAttributesIndex($i, $opsys, $attr);
            if ($opsys === ZipArchive::OPSYS_UNIX && (($attr >> 16) & 0170000) === 0120000) throw new RuntimeException('Das Archiv enthält symbolische Links.');
            $total += (int) $st['size'];
            if ($total > self::MAX_UNPACKED) throw new RuntimeException('Das Archiv ist entpackt zu gross.');
            $names[$i] = $n;
        }
        $prefix = null;
        foreach ($names as $n) {
            if (preg_match('#^(.*?)app/bootstrap\.php$#', $n, $m) && ($prefix === null || strlen($m[1]) < strlen($prefix))) $prefix = $m[1];
        }
        if ($prefix === null) throw new RuntimeException('Das Release enthält keine VylarisAdvertise-Struktur (app/bootstrap.php fehlt).');
        foreach ($names as $i => $n) {
            if (!str_starts_with($n, $prefix) || str_ends_with($n, '/')) continue;
            $rel = substr($n, strlen($prefix));
            if ($rel === '' || str_starts_with($rel, '.git/') || str_starts_with($rel, '.github/')) continue;
            $target = $dest . '/' . $rel;
            if (!is_dir(dirname($target))) mkdir(dirname($target), 0700, true);
            $in = $zip->getStream($n);
            if (!$in) throw new RuntimeException('Datei im Archiv nicht lesbar: ' . $rel);
            $out = fopen($target, 'wb');
            stream_copy_to_stream($in, $out);
            fclose($in);
            fclose($out);
        }
        $zip->close();
    }

    /** @return list<string> relative Dateipfade der neuen Version (nur verwaltete Bereiche) */
    private static function verifyTree(string $new, string $version): array
    {
        foreach (['app/bootstrap.php', 'public/index.php', 'database/schema.sql', 'config/config.example.php', 'app/routes.php'] as $must) {
            if (!is_file($new . '/' . $must)) throw new RuntimeException('Im Release fehlt ' . $must . '.');
        }
        if (!preg_match("/define\\('APP_VERSION', '([0-9.]+)'\\)/", (string) file_get_contents($new . '/app/bootstrap.php'), $m) || $m[1] !== $version) {
            throw new RuntimeException('Die Version im Code (' . ($m[1] ?? '?') . ') passt nicht zum Release-Tag (' . $version . ').');
        }
        $files = [];
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($new, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if ($f->isFile()) $files[] = ltrim(str_replace('\\', '/', substr($f->getPathname(), strlen($new))), '/');
        }
        $files = array_values(array_filter($files, fn ($rel) => self::managed($rel)));
        sort($files);
        foreach (self::phpFiles($files) as $rel) {
            try {
                token_get_all((string) file_get_contents($new . '/' . $rel), TOKEN_PARSE);
            } catch (ParseError $e) {
                throw new RuntimeException('PHP-Syntaxfehler im Release (' . $rel . ', Zeile ' . $e->getLine() . '): ' . $e->getMessage());
            }
        }
        return $files;
    }

    private static function phpFiles(array $files): array
    {
        return array_values(array_filter($files, fn ($f) => str_ends_with($f, '.php')));
    }

    private static function managed(string $rel): bool
    {
        if (in_array($rel, self::FILES, true)) return true;
        $top = explode('/', $rel, 2)[0];
        if ($top === 'public') return $rel !== 'public/config.php';
        return in_array($top, self::MIRRORED, true);
    }

    private static function target(string $rel): string
    {
        return str_starts_with($rel, 'public/') ? self::webroot() . '/' . substr($rel, 7) : APP_ROOT . '/' . $rel;
    }

    /** @return array{new: string, copy: list<string>, orphans: list<string>, existing: list<string>} */
    private static function plan(string $new, array $files): array
    {
        $existing = [];
        foreach (self::MIRRORED as $d) {
            if (!is_dir(APP_ROOT . '/' . $d)) continue;
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(APP_ROOT . '/' . $d, FilesystemIterator::SKIP_DOTS));
            foreach ($it as $f) {
                if ($f->isFile()) $existing[] = $d . '/' . ltrim(str_replace('\\', '/', substr($f->getPathname(), strlen(APP_ROOT . '/' . $d))), '/');
            }
        }
        foreach (self::FILES as $f) if (is_file(APP_ROOT . '/' . $f)) $existing[] = $f;
        $pub = self::webroot();
        foreach ($files as $rel) if (str_starts_with($rel, 'public/') && is_file(self::target($rel))) $existing[] = $rel;
        $orphans = array_values(array_diff(array_filter($existing, fn ($e) => !str_starts_with($e, 'public/')), $files));
        sort($existing);
        return ['new' => $new, 'copy' => $files, 'orphans' => $orphans, 'existing' => array_values(array_unique($existing))];
    }

    private static function preflight(array $plan): void
    {
        foreach ($plan['copy'] as $rel) {
            $t = self::target($rel);
            $dir = $t;
            while (!file_exists($dir) && dirname($dir) !== $dir) $dir = dirname($dir);
            if (is_file($t) ? !is_writable($t) && !is_writable(dirname($t)) : !is_writable($dir)) {
                throw new UserError('Keine Schreibrechte für ' . $rel . '. Bitte Dateirechte prüfen.');
            }
        }
    }

    private static function backup(array $plan): string
    {
        $dir = APP_ROOT . '/storage/backups';
        if (!is_dir($dir)) mkdir($dir, 0750, true);
        if (!is_file($dir . '/.htaccess')) file_put_contents($dir . '/.htaccess', "Require all denied\n");
        $file = $dir . '/vylaris-advertise-' . APP_VERSION . '-' . date('Ymd-His') . '.zip';
        $zip = new ZipArchive();
        if ($zip->open($file, ZipArchive::CREATE | ZipArchive::EXCL) !== true) throw new RuntimeException('Sicherung konnte nicht angelegt werden.');
        foreach ($plan['existing'] as $rel) {
            $zip->addFile(self::target($rel), $rel);
        }
        $zip->addFromString('__manifest.json', json_encode(['version' => APP_VERSION, 'created' => date('c'), 'files' => $plan['existing']], JSON_UNESCAPED_SLASHES));
        if (!$zip->close() || !is_file($file)) throw new RuntimeException('Sicherung konnte nicht geschrieben werden.');
        @chmod($file, 0640);
        return $file;
    }

    private static function apply(array $plan, string $backup): void
    {
        $done = [];
        try {
            foreach ($plan['copy'] as $rel) {
                $t = self::target($rel);
                if (!is_dir(dirname($t))) mkdir(dirname($t), 0755, true);
                $tmp = $t . '.upd' . bin2hex(random_bytes(3));
                if (!copy($plan['new'] . '/' . $rel, $tmp)) throw new RuntimeException('Datei konnte nicht geschrieben werden: ' . $rel);
                @chmod($tmp, 0644);
                if (!rename($tmp, $t)) { @unlink($tmp); throw new RuntimeException('Datei konnte nicht ersetzt werden: ' . $rel); }
                $done[] = $rel;
            }
            foreach ($plan['orphans'] as $rel) {
                $t = self::target($rel);
                if (is_file($t) && !unlink($t)) throw new RuntimeException('Veraltete Datei konnte nicht entfernt werden: ' . $rel);
            }
        } catch (Throwable $e) {
            try { self::restore($backup, $plan['copy']); } catch (Throwable $r) { error_log('[VylarisAdvertise] Wiederherstellung fehlgeschlagen: ' . $r->getMessage()); }
            throw $e;
        }
    }

    /** Stellt eine Sicherung her; Dateien, die es damals nicht gab (nur in $extra), werden entfernt. Liefert die Version der Sicherung. */
    private static function restore(string $zipFile, array $extra = []): string
    {
        $zip = new ZipArchive();
        if ($zip->open($zipFile) !== true) throw new RuntimeException('Die Sicherung ist nicht lesbar.');
        $man = json_decode((string) $zip->getFromName('__manifest.json'), true);
        if (!is_array($man) || !is_array($man['files'] ?? null)) throw new RuntimeException('Die Sicherung ist unvollständig.');
        $keep = array_flip($man['files']);
        foreach ($man['files'] as $rel) {
            if (!is_string($rel) || preg_match('#(^|/)\.\.(/|$)#', $rel) || str_starts_with($rel, '/') || !self::managed($rel)) throw new RuntimeException('Die Sicherung enthält unzulässige Pfade.');
            $data = $zip->getFromName($rel);
            if ($data === false) throw new RuntimeException('Datei fehlt in der Sicherung: ' . $rel);
            $t = self::target($rel);
            if (!is_dir(dirname($t))) mkdir(dirname($t), 0755, true);
            $tmp = $t . '.upd' . bin2hex(random_bytes(3));
            file_put_contents($tmp, $data);
            @chmod($tmp, 0644);
            rename($tmp, $t);
        }
        // Dateien entfernen, die erst durch das Update hinzugekommen sind
        $added = $extra;
        if (!$extra) {
            foreach (self::MIRRORED as $d) {
                if (!is_dir(APP_ROOT . '/' . $d)) continue;
                $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(APP_ROOT . '/' . $d, FilesystemIterator::SKIP_DOTS));
                foreach ($it as $f) if ($f->isFile()) $added[] = $d . '/' . ltrim(str_replace('\\', '/', substr($f->getPathname(), strlen(APP_ROOT . '/' . $d))), '/');
            }
        }
        foreach ($added as $rel) {
            if (!isset($keep[$rel]) && !str_starts_with($rel, 'public/') && self::managed($rel) && is_file(self::target($rel))) @unlink(self::target($rel));
        }
        $zip->close();
        return (string) ($man['version'] ?? '?');
    }

    private static function pruneBackups(): void
    {
        foreach (array_slice(self::backups(), self::KEEP_BACKUPS) as $b) {
            @unlink(APP_ROOT . '/storage/backups/' . $b['file']);
        }
    }

    private static function rmTree(string $dir): void
    {
        if (!is_dir($dir)) return;
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $f) { $f->isDir() && !$f->isLink() ? @rmdir($f->getPathname()) : @unlink($f->getPathname()); }
        @rmdir($dir);
    }
}
