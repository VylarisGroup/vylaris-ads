<?php
declare(strict_types=1);

// Front-Controller. Liegt die Anwendung nicht direkt über dem Webroot,
// diesen Pfad anpassen (siehe README, Abschnitt "Shared Hosting").
$appRoot = dirname(__DIR__);

require $appRoot . '/app/bootstrap.php';

$routes = require APP_ROOT . '/app/routes.php';
$page = $_GET['p'] ?? 'home';
$route = is_string($page) ? ($routes[$page] ?? null) : null;

if (Updater::maintenanceActive()) { // Update läuft: kurze Unterbrechung
    http_response_code(503);
    header('Retry-After: 60');
    exit('<!doctype html><meta charset="utf-8"><title>Wartung</title><body style="font-family:sans-serif;max-width:32rem;margin:4rem auto;padding:0 1rem"><h1>Wartungsarbeiten</h1><p>Die Anwendung wird gerade aktualisiert. Bitte in einer Minute erneut versuchen.</p>');
}

try {
    if ($route && $route[3] === 'tracking') {
        require APP_ROOT . '/app/controllers/' . $route[0] . '.php';
        ($route[1])();
        exit;
    }

    send_security_headers();
    start_session();

    if (!$route) {
        abort(404, 'Die angeforderte Seite existiert nicht.');
    }
    [$file, $handler, $methods, $access] = $route;

    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if (!in_array($method, $methods, true)) {
        header('Allow: ' . implode(', ', $methods));
        abort(405);
    }
    if ($method === 'POST' && !csrf_valid()) {
        abort(419, 'Das Formular ist abgelaufen oder ungültig. Bitte laden Sie die Seite neu und versuchen Sie es erneut.');
    }

    $user = current_user();
    if ($access === 'guest' && $user) {
        redirect('dashboard');
    }
    if (!in_array($access, ['public', 'guest'], true)) {
        if (!$user) {
            if ($method === 'GET') {
                $_SESSION['intended'] = $_SERVER['QUERY_STRING'] ?? '';
            }
            flash('info', 'Bitte melden Sie sich an.');
            redirect('login');
        }
        if ($access !== 'auth' && !role_allows($user['role'], $access)) {
            abort(403, 'Für diesen Bereich fehlt Ihnen die Berechtigung.');
        }
    }

    if ($user && $user['role'] === 'admin') {
        Updater::checkIfDue(); // nur Hinweis auf neue Versionen, es wird nichts installiert
    }

    if ($user) {
        Booking::housekeeping();
        Privacy::housekeeping();
    }

    require APP_ROOT . '/app/controllers/' . $file . '.php';
    $handler();
} catch (UserError $e) {
    flash('error', $e->getMessage());
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        remember_input();
    }
    back();
} catch (Throwable $e) {
    error_log('[VylarisAdvertise] ' . $e::class . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) {
        http_response_code(500);
    }
    $detail = config('app.debug') ? '<pre>' . htmlspecialchars((string) $e) . '</pre>' : '';
    echo '<!doctype html><meta charset="utf-8"><title>Serverfehler</title><body>'
        . '<h1>Serverfehler</h1><p>Es ist ein unerwarteter Fehler aufgetreten. Bitte versuchen Sie es später erneut.</p>' . $detail;
}
