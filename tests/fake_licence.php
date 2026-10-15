<?php
declare(strict_types=1);

/**
 * Minimaler Lizenzserver-Simulator für die Tests (nachgebaut nach der TRS-License-API-Dokumentation).
 *
 *   php -S 127.0.0.1:8081 tests/fake_licence.php
 *   php tests/fake_licence.php pem          # öffentlicher Test-Schlüssel (für config.php)
 *
 * Zustand/Steuerung über die JSON-Datei in $STATE (vom Test geschrieben):
 *   keys:  { "<KEY>": { status: active|blocked, product, plan, features[], expiresAt|null, maxDevices, devices: [hwid…] } }
 *   down:  true → HTTP 503 | skew: Sekunden Zeitabweichung von ts | badsig: true → kaputte Signatur
 */
$STATE = sys_get_temp_dir() . '/fakelic.json';
$kp = sodium_crypto_sign_seed_keypair(str_repeat('t', 32));
$sk = sodium_crypto_sign_secretkey($kp);
$pem = "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode(hex2bin('302a300506032b6570032100') . sodium_crypto_sign_publickey($kp)), 64, "\n") . "-----END PUBLIC KEY-----\n";

if (PHP_SAPI === 'cli' && ($argv[1] ?? '') === 'pem') { echo $pem; exit; }

$st = is_file($STATE) ? (json_decode((string) file_get_contents($STATE), true) ?: []) : [];
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
header('Content-Type: application/json');
if (!empty($st['down'])) { http_response_code(503); echo '{"message":"down"}'; exit; }
if ($path === '/api/v1/public-key') { echo json_encode(['publicKey' => $pem]); exit; }
if (!preg_match('#^/api/v1/(validate|activate|heartbeat|deactivate|offline)$#', (string) $path, $m) || ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { http_response_code(404); echo '{}'; exit; }
$action = $m[1];
$in = json_decode((string) file_get_contents('php://input'), true) ?: [];
$key = (string) ($in['key'] ?? ''); $hwid = (string) ($in['hwid'] ?? ''); $product = (string) ($in['product'] ?? '');
$st['log'][] = $action;

$sign = function (string $payload) use ($sk, $st): string {
    $sig = base64_encode(sodium_crypto_sign_detached($payload, $sk));
    return !empty($st['badsig']) ? strrev($sig) : $sig;
};
$iso = fn ($t) => $t === null ? null : gmdate('Y-m-d\TH:i:s.000\Z', is_int($t) ? $t : strtotime((string) $t));
$lic = &$st['keys'][$key];
$code = 'OK';
if (!isset($lic)) { $code = 'NOT_FOUND'; unset($st['keys'][$key]); $lic = null; }
elseif ($product !== '' && $product !== ($lic['product'] ?? 'vylaris-advertise')) { $code = 'WRONG_PRODUCT'; }
elseif (($lic['status'] ?? 'active') === 'blocked') { $code = 'BLOCKED'; }
elseif (!empty($lic['expiresAt']) && strtotime($lic['expiresAt']) < time()) { $code = 'EXPIRED'; }
elseif ($action !== 'validate' && $hwid === '') { $code = 'HWID_REQUIRED'; }
else {
    $dev = $lic['devices'] ?? [];
    $has = in_array($hwid, $dev, true);
    if ($action === 'activate' && !$has) {
        if (count($dev) >= ($lic['maxDevices'] ?? 1)) $code = 'DEVICE_LIMIT'; else { $dev[] = $hwid; $has = true; }
    } elseif ($action === 'deactivate') { $dev = array_values(array_diff($dev, [$hwid])); }
    elseif (in_array($action, ['heartbeat', 'offline'], true) || ($action === 'validate' && $hwid !== '')) { if (!$has) $code = 'NOT_ACTIVATED'; }
    $lic['devices'] = $dev;
}
$valid = $code === 'OK';
$skew = (int) ($st['skew'] ?? 0);
if ($action === 'offline') {
    if (!$valid) { echo json_encode(['valid' => false, 'code' => $code]); file_put_contents($STATE, json_encode($st)); exit; }
    $until = time() + (int) ($st['offline_ttl'] ?? 30 * 86400);
    if (!empty($lic['expiresAt'])) $until = min($until, strtotime($lic['expiresAt']));
    $payload = json_encode(['v' => 1, 'type' => 'offline', 'key' => $key, 'product' => $lic['product'] ?? 'vylaris-advertise', 'plan' => $lic['plan'] ?? 'Pro', 'features' => $lic['features'] ?? [], 'hwid' => $hwid, 'issuedAt' => $iso(time()), 'validUntil' => $iso($until), 'licenseExpiresAt' => $iso($lic['expiresAt'] ?? null)]);
    $out = ['valid' => true, 'code' => 'OK', 'format' => 'trs-license-offline-v1', 'payload' => $payload, 'signature' => $sign($payload)];
} else {
    $license = $lic ? ['key' => $key, 'product' => $lic['product'] ?? 'vylaris-advertise', 'productName' => 'VylarisAdvertise', 'plan' => $lic['plan'] ?? 'Pro', 'trial' => false,
        'expiresAt' => $iso($lic['expiresAt'] ?? null), 'maxDevices' => $lic['maxDevices'] ?? 1, 'devices' => count($lic['devices'] ?? []), 'features' => $lic['features'] ?? []] : null;
    if ($code === 'WRONG_PRODUCT') $license = null;
    $payload = json_encode(['valid' => $valid, 'code' => $code, 'license' => $license, 'ts' => time() + $skew]);
    $out = ['valid' => $valid, 'code' => $code, 'license' => $license, 'payload' => $payload, 'signature' => $sign($payload)];
}
unset($lic);
file_put_contents($STATE, json_encode($st));
echo json_encode($out);
