<?php
declare(strict_types=1);

// ---------------------------------------------------------------------------
// Datenbank
// ---------------------------------------------------------------------------

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            config('db.host'), (int) config('db.port', 3306), config('db.name'), config('db.charset', 'utf8mb4')
        );
        $pdo = new PDO($dsn, config('db.user'), config('db.pass'), [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        // Datenbank-Zeitzone an PHP angleichen (CURRENT_TIMESTAMP, CURDATE())
        $pdo->exec("SET time_zone = '" . date('P') . "'");
    }
    return $pdo;
}

function q(string $sql, array $params = []): PDOStatement
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

function one(string $sql, array $params = []): ?array
{
    $row = q($sql, $params)->fetch();
    return $row === false ? null : $row;
}

function all(string $sql, array $params = []): array
{
    return q($sql, $params)->fetchAll();
}

function val(string $sql, array $params = []): mixed
{
    $v = q($sql, $params)->fetchColumn();
    return $v === false ? null : $v;
}

/** Führt $fn in einer Transaktion aus; bei Exception wird zurückgerollt. */
function tx(callable $fn): mixed
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $result = $fn($pdo);
        $pdo->commit();
        return $result;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/** Fachlicher Fehler, dessen Meldung dem Benutzer angezeigt werden darf. */
final class UserError extends RuntimeException {}

// ---------------------------------------------------------------------------
// Einstellungen
// ---------------------------------------------------------------------------

function setting(string $name, string $default = ''): string
{
    static $cache = null;
    if ($cache === null || $name === '__reset') {
        $cache = [];
        foreach (all('SELECT name, value FROM settings') as $r) {
            $cache[$r['name']] = $r['value'];
        }
    }
    return $cache[$name] ?? $default;
}

function set_setting(string $name, string $value): void
{
    q('INSERT INTO settings (name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)', [$name, $value]);
    setting('__reset');
}

// ---------------------------------------------------------------------------
// HTTP / Ausgabe
// ---------------------------------------------------------------------------

function e(mixed $v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $page, array $params = []): string
{
    $query = http_build_query(['p' => $page] + $params);
    return 'index.php?' . $query;
}

function abs_url(string $page, array $params = []): string
{
    return rtrim((string) config('app.url'), '/') . '/' . url($page, $params);
}

function redirect(string $page, array $params = []): never
{
    header('Location: ' . url($page, $params), true, 303);
    exit;
}

function back(string $fallback = 'dashboard'): never
{
    $ref = $_SERVER['HTTP_REFERER'] ?? '';
    // Nur relative Rücksprünge auf die eigene Anwendung zulassen
    $base = rtrim((string) config('app.url'), '/');
    if ($ref !== '' && str_starts_with($ref, $base . '/index.php?')) {
        header('Location: ' . substr($ref, strlen($base) + 1), true, 303);
        exit;
    }
    redirect($fallback);
}

function abort(int $code, string $message = ''): never
{
    http_response_code($code);
    $titles = [403 => 'Kein Zugriff', 404 => 'Nicht gefunden', 405 => 'Methode nicht erlaubt', 419 => 'Sitzung abgelaufen', 429 => 'Zu viele Anfragen', 500 => 'Serverfehler'];
    view('error', ['code' => $code, 'title' => $titles[$code] ?? 'Fehler', 'message' => $message]);
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $message];
}

function take_flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

function old(string $key, mixed $default = ''): string
{
    return (string) ($_SESSION['old'][$key] ?? $default);
}

function remember_input(): void
{
    $data = $_POST;
    unset($data['password'], $data['password2'], $data['_csrf'], $data['current_password']);
    $_SESSION['old'] = $data;
}

/** Rendert eine View innerhalb des Layouts. */
function view(string $template, array $data = [], string $layout = 'layout'): void
{
    $data['__template'] = APP_ROOT . '/app/views/' . $template . '.php';
    extract($data, EXTR_SKIP);
    if ($layout === '') {
        require $__template;
    } else {
        require APP_ROOT . '/app/views/' . $layout . '.php';
    }
    unset($_SESSION['old']);
}

function partial(string $name, array $data = []): void
{
    extract($data, EXTR_SKIP);
    require APP_ROOT . '/app/views/partials/' . $name . '.php';
}

function client_ip(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
}

function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? '') === '443')
        || str_starts_with((string) config('app.url'), 'https://');
}

function csp_nonce(): string
{
    static $n = null;
    return $n ??= base64_encode(random_bytes(16));
}

function send_security_headers(): void
{
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self'; font-src 'self'; "
        . "script-src 'self' 'nonce-" . csp_nonce() . "'; form-action 'self'; frame-ancestors 'none'; base-uri 'self'; object-src 'none'");
    if (is_https()) {
        header('Strict-Transport-Security: max-age=31536000');
    }
}

// ---------------------------------------------------------------------------
// Eingaben
// ---------------------------------------------------------------------------

function input(string $key, string $default = ''): string
{
    $v = $_POST[$key] ?? $_GET[$key] ?? $default;
    return is_string($v) ? trim($v) : $default;
}

function input_int(string $key, int $default = 0): int
{
    $v = input($key);
    return preg_match('/^-?\d{1,10}$/', $v) ? (int) $v : $default;
}

/** "12.50", "12,50", "1'200" -> Cent. Gibt null bei ungültiger Eingabe zurück. */
function parse_money(string $v): ?int
{
    $v = str_replace(["'", ' ', "\u{2019}"], '', trim($v));
    $v = str_replace(',', '.', $v);
    if (!preg_match('/^\d{1,7}(\.\d{1,2})?$/', $v)) {
        return null;
    }
    return (int) round(((float) $v) * 100);
}

function parse_date(string $v): ?string
{
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $v);
    return ($d && $d->format('Y-m-d') === $v) ? $v : null;
}

function valid_url(string $v): bool
{
    if (strlen($v) > 500 || !filter_var($v, FILTER_VALIDATE_URL)) {
        return false;
    }
    $scheme = strtolower((string) parse_url($v, PHP_URL_SCHEME));
    return in_array($scheme, ['http', 'https'], true) && parse_url($v, PHP_URL_HOST);
}

function len_between(string $v, int $min, int $max): bool
{
    $l = mb_strlen($v);
    return $l >= $min && $l <= $max;
}

// ---------------------------------------------------------------------------
// Formatierung
// ---------------------------------------------------------------------------

function money(int|string|null $cents, ?string $currency = null): string
{
    $currency ??= setting('currency', 'CHF');
    $cents = (int) $cents;
    $sign = $cents < 0 ? '-' : '';
    return $currency . ' ' . $sign . number_format(abs($cents) / 100, 2, '.', "'");
}

function money_input(int|string|null $cents): string
{
    return number_format(((int) $cents) / 100, 2, '.', '');
}

function percent_bp(int|string $bp): string
{
    return rtrim(rtrim(number_format(((int) $bp) / 100, 2, '.', ''), '0'), '.') . ' %';
}

function fdate(?string $d, bool $time = false): string
{
    if (!$d) {
        return '–';
    }
    $ts = strtotime($d);
    return $ts ? date($time ? 'd.m.Y H:i' : 'd.m.Y', $ts) : '–';
}

function today(): string
{
    return date('Y-m-d');
}

const STATUS_LABELS = [
    'pending' => ['In Prüfung', 'amber'], 'approved' => ['Freigegeben', 'green'], 'rejected' => ['Abgelehnt', 'red'],
    'paused' => ['Pausiert', 'slate'], 'requested' => ['Angefragt', 'amber'], 'accepted' => ['Angenommen – Zahlung offen', 'blue'],
    'declined' => ['Abgelehnt', 'red'], 'cancelled' => ['Storniert', 'slate'], 'expired' => ['Abgelaufen', 'slate'],
    'paid' => ['Bezahlt', 'green'], 'completed' => ['Abgeschlossen', 'indigo'], 'refunded' => ['Erstattet', 'red'],
    'unpaid' => ['Offen', 'amber'], 'reported' => ['Zahlung gemeldet', 'blue'], 'active' => ['Aktiv', 'green'],
    'blocked' => ['Gesperrt', 'red'], 'deleted' => ['Gelöscht', 'slate'], 'live' => ['Läuft', 'green'], 'scheduled' => ['Geplant', 'blue'],
];

function badge(string $status): string
{
    [$label, $color] = STATUS_LABELS[$status] ?? [$status, 'slate'];
    $classes = [
        'amber' => 'bg-amber-100 text-amber-800', 'green' => 'bg-emerald-100 text-emerald-800', 'red' => 'bg-rose-100 text-rose-800',
        'slate' => 'bg-slate-100 text-slate-700', 'blue' => 'bg-sky-100 text-sky-800', 'indigo' => 'bg-indigo-100 text-indigo-800',
    ][$color];
    return '<span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ' . $classes . '">' . e($label) . '</span>';
}

const ROLE_LABELS = ['advertiser' => 'Werbetreibender', 'publisher' => 'Webseitenbetreiber', 'both' => 'Werbetreibender & Webseitenbetreiber', 'admin' => 'Administrator'];

const AD_FORMATS = [
    '728x90'   => 'Leaderboard (728×90)',
    '970x250'  => 'Billboard (970×250)',
    '300x250'  => 'Medium Rectangle (300×250)',
    '336x280'  => 'Large Rectangle (336×280)',
    '300x600'  => 'Half Page (300×600)',
    '160x600'  => 'Wide Skyscraper (160×600)',
    '468x60'   => 'Banner (468×60)',
    '320x50'   => 'Mobile Banner (320×50)',
    '320x100'  => 'Large Mobile Banner (320×100)',
];

const SITE_CATEGORIES = ['Technologie', 'Gaming', 'Business & Finanzen', 'Lifestyle', 'Reisen', 'Sport', 'Nachrichten', 'Bildung', 'Gesundheit', 'Entertainment', 'Open Source', 'Sonstiges'];

// ---------------------------------------------------------------------------
// Sitzung, Authentifizierung, CSRF
// ---------------------------------------------------------------------------

function start_session(): void
{
    session_name((string) config('session.name', 'vyad_sess'));
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    // Sitzungen in eigenem Verzeichnis ausserhalb des Webroots (nicht im gemeinsam genutzten /tmp)
    $sessDir = APP_ROOT . '/storage/sessions';
    if ((is_dir($sessDir) || @mkdir($sessDir, 0750, true)) && is_writable($sessDir)) {
        session_save_path($sessDir);
        ini_set('session.gc_probability', '1');
        ini_set('session.gc_divisor', '100');
        ini_set('session.gc_maxlifetime', (string) ((int) config('session.idle_minutes', 60) * 60 + 600));
    }
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_start();

    $idle = (int) config('session.idle_minutes', 60) * 60;
    if (isset($_SESSION['uid'], $_SESSION['last_seen']) && time() - $_SESSION['last_seen'] > $idle) {
        $_SESSION = [];
        session_regenerate_id(true);
        flash('info', 'Ihre Sitzung ist abgelaufen. Bitte melden Sie sich erneut an.');
    }
    $_SESSION['last_seen'] = time();
}

function csrf_token(): string
{
    return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_valid(): bool
{
    $sent = $_POST['_csrf'] ?? '';
    return is_string($sent) && isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $sent);
}

/** Fingerabdruck des Passwort-Hashes: Passwortänderung beendet andere Sitzungen. */
function password_fingerprint(string $hash): string
{
    return hash_hmac('sha256', $hash, (string) config('app.secret'));
}

function current_user(): ?array
{
    static $user = false;
    if ($user !== false) {
        return $user;
    }
    $user = null;
    if (!empty($_SESSION['uid'])) {
        $row = one('SELECT * FROM users WHERE id = ?', [(int) $_SESSION['uid']]);
        if ($row && $row['status'] === 'active' && hash_equals((string) ($_SESSION['pwfp'] ?? ''), password_fingerprint($row['password_hash']))) {
            $user = $row;
        } else {
            unset($_SESSION['uid'], $_SESSION['pwfp']);
        }
    }
    return $user;
}

function uid(): int
{
    return (int) (current_user()['id'] ?? 0);
}

/** Erfüllt die Kontorolle die geforderte Rolle? 'both' (Werbetreibender und Webseitenbetreiber) erfüllt beide. */
function role_allows(string $role, string $access): bool
{
    return $role === $access || ($role === 'both' && ($access === 'advertiser' || $access === 'publisher'));
}

function has_role(string ...$roles): bool
{
    $u = current_user();
    if ($u === null) {
        return false;
    }
    foreach ($roles as $r) {
        if (role_allows($u['role'], $r)) {
            return true;
        }
    }
    return false;
}

function can_advertise(?array $u): bool
{
    return $u !== null && role_allows($u['role'], 'advertiser');
}

function can_publish(?array $u): bool
{
    return $u !== null && role_allows($u['role'], 'publisher');
}

function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['uid'] = (int) $user['id'];
    $_SESSION['pwfp'] = password_fingerprint($user['password_hash']);
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
    q('UPDATE users SET last_login_at = NOW() WHERE id = ?', [$user['id']]);
}

function audit(string $action, string $entity, ?int $entityId = null, array $details = []): void
{
    q('INSERT INTO audit_log (user_id, action, entity, entity_id, details, ip) VALUES (?, ?, ?, ?, ?, ?)', [
        uid() ?: null, $action, $entity, $entityId,
        $details ? json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
        PHP_SAPI === 'cli' ? 'cli' : client_ip(),
    ]);
}

// ---------------------------------------------------------------------------
// Listen / Paginierung
// ---------------------------------------------------------------------------

/** Liefert ['rows' => [...], 'page' => n, 'pages' => n, 'total' => n] */
function paginate(string $sql, array $params, int $perPage = 25): array
{
    $total = (int) val('SELECT COUNT(*) FROM (' . $sql . ') t', $params);
    $pages = max(1, (int) ceil($total / $perPage));
    $page = min(max(1, input_int('page', 1)), $pages);
    $offset = ($page - 1) * $perPage;
    $rows = all($sql . ' LIMIT ' . $perPage . ' OFFSET ' . $offset, $params);
    return ['rows' => $rows, 'page' => $page, 'pages' => $pages, 'total' => $total];
}
