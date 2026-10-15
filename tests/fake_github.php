<?php
declare(strict_types=1);

/**
 * Minimaler GitHub-Simulator für die Update-Tests.
 *   php -S 127.0.0.1:8082 tests/fake_github.php
 * Steuerung über <tmp>/fakegh.json: { tag, body, zip (Pfad), token (erwartetes Bearer-Token oder leer), codeload_auth_seen }
 */
$STATE = sys_get_temp_dir() . '/fakegh.json';
$st = is_file($STATE) ? (json_decode((string) file_get_contents($STATE), true) ?: []) : [];
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$host = 'http://' . ($_SERVER['HTTP_HOST'] ?? '127.0.0.1:8082');

if (preg_match('#^/repos/([^/]+)/([^/]+)/releases/latest$#', (string) $path, $m)) {
    header('Content-Type: application/json');
    if (!empty($st['token']) && $auth !== 'Bearer ' . $st['token']) { http_response_code(404); echo '{"message":"Not Found"}'; exit; }
    if (empty($st['tag'])) { http_response_code(404); echo '{"message":"Not Found"}'; exit; }
    echo json_encode(['tag_name' => $st['tag'], 'name' => $st['tag'], 'body' => $st['body'] ?? '', 'html_url' => "$host/$m[1]/$m[2]/releases/tag/{$st['tag']}",
        'published_at' => gmdate('c'), 'zipball_url' => "$host/repos/$m[1]/$m[2]/zipball/{$st['tag']}"]);
    exit;
}
if (preg_match('#^/repos/([^/]+)/([^/]+)/zipball/(.+)$#', (string) $path, $m)) {
    if (!empty($st['token']) && $auth !== 'Bearer ' . $st['token']) { http_response_code(404); exit; }
    header('Location: ' . $host . '/codeload/' . $m[3] . '.zip', true, 302);
    exit;
}
if (preg_match('#^/codeload/(.+)\.zip$#', (string) $path)) {
    $st['codeload_auth_seen'] = $auth !== '';
    file_put_contents($STATE, json_encode($st));
    header('Content-Type: application/zip');
    readfile((string) ($st['zip'] ?? '/nonexistent'));
    exit;
}
http_response_code(404);
echo '{}';
