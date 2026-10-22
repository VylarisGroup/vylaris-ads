<?php
declare(strict_types=1);

/**
 * NUR FÜR TESTS: minimaler SMTP-Server (unverschlüsselt, AUTH PLAIN: user "mailuser" / Passwort "MailPass42").
 *   php tests/fake_smtp.php 2525 /tmp/vyad-smtp.jsonl &
 * Empfangene Mails werden zeilenweise als JSON (from, to, subject, body) angehängt.
 * Existiert die Datei <ausgabe>.reject, antwortet der Server auf RCPT mit 550 (Test für Fehler/Wiederholung).
 */
$port = (int) ($argv[1] ?? 2525);
$out = $argv[2] ?? '/tmp/vyad-smtp.jsonl';
$srv = stream_socket_server("tcp://127.0.0.1:$port", $errno, $err) or exit("Fehler: $err\n");
while (true) {
    $c = @stream_socket_accept($srv, 3600);
    if (!$c) continue;
    stream_set_timeout($c, 10);
    $w = fn (string $s) => fwrite($c, $s . "\r\n");
    $w('220 fake-smtp ready');
    $from = $to = '';
    $authed = false;
    while (($l = fgets($c, 8192)) !== false) {
        $l = rtrim($l);
        $u = strtoupper($l);
        if (str_starts_with($u, 'EHLO')) { fwrite($c, "250-fake-smtp\r\n250 AUTH PLAIN\r\n"); }
        elseif (str_starts_with($u, 'AUTH PLAIN')) {
            $p = explode("\0", (string) base64_decode(substr($l, 11)));
            if (($p[1] ?? '') === 'mailuser' && ($p[2] ?? '') === 'MailPass42') { $authed = true; $w('235 ok'); } else { $w('535 bad credentials'); }
        }
        elseif (str_starts_with($u, 'MAIL FROM:')) { $from = trim(substr($l, 10), ' <>'); $w($authed ? '250 ok' : '530 auth required'); }
        elseif (str_starts_with($u, 'RCPT TO:')) { $to = trim(substr($l, 8), ' <>'); clearstatcache(); $w(is_file($out . '.reject') ? '550 rejected' : '250 ok'); }
        elseif ($u === 'DATA') {
            $w('354 go');
            $data = '';
            while (($d = fgets($c, 8192)) !== false && rtrim($d) !== '.') $data .= $d;
            [$head, $body] = array_pad(explode("\r\n\r\n", $data, 2), 2, '');
            preg_match('/^Subject: (.*)$/mi', $head, $m);
            $subject = $m[1] ?? '';
            if (str_starts_with($subject, '=?')) $subject = mb_decode_mimeheader($subject);
            file_put_contents($out, json_encode(['from' => $from, 'to' => $to, 'subject' => $subject, 'body' => (string) base64_decode(str_replace("\r\n", '', $body))], JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);
            $w('250 queued');
        }
        elseif ($u === 'QUIT') { $w('221 bye'); break; }
        else { $w('250 ok'); }
    }
    fclose($c);
}
