<?php
declare(strict_types=1);

/**
 * End-to-End-Test der wichtigsten Abläufe über HTTP.
 *
 * ACHTUNG: Nur gegen eine frisch installierte TEST-Datenbank ausführen – der Test legt Benutzer,
 * Webseiten, Buchungen usw. an und verändert Daten direkt in der Datenbank.
 *
 *   php -S 127.0.0.1:8080 -t public &
 *   php tests/e2e.php http://127.0.0.1:8080 admin@example.com 'AdminPass123!'
 */

if (PHP_SAPI !== 'cli') exit;
require dirname(__DIR__) . '/app/bootstrap.php';

$base = rtrim($argv[1] ?? 'http://127.0.0.1:8080', '/');
$adminEmail = $argv[2] ?? 'admin@example.com';
$adminPass = $argv[3] ?? 'AdminPass123!';
$tmp = sys_get_temp_dir() . '/vyad-e2e-' . getmypid();
@mkdir($tmp);

$passed = 0;
$failed = 0;
function check(bool $cond, string $label): void
{
    global $passed, $failed;
    if ($cond) { $passed++; echo "  \033[32m✓\033[0m $label\n"; }
    else { $failed++; echo "  \033[31m✗ $label\033[0m\n"; }
}

final class Client
{
    public string $body = '';
    public int $code = 0;
    public string $url = '';
    private string $jar;

    public function __construct(private string $base, string $name)
    {
        $this->jar = sys_get_temp_dir() . '/vyad-jar-' . $name . '-' . getmypid();
        @unlink($this->jar);
    }

    public function req(string $method, string $page, array $query = [], array $post = [], bool $follow = true, bool $csrf = true): self
    {
        $ch = curl_init($this->base . '/index.php?' . http_build_query(['p' => $page] + $query));
        if ($method === 'POST' && $csrf && !isset($post['_csrf'])) {
            $post['_csrf'] = $this->csrf();
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEJAR => $this->jar, CURLOPT_COOKIEFILE => $this->jar,
            CURLOPT_FOLLOWLOCATION => $follow, CURLOPT_USERAGENT => 'Mozilla/5.0 E2E', CURLOPT_REFERER => $this->url ?: $this->base . '/index.php?p=home',
        ]);
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        }
        $this->body = (string) curl_exec($ch);
        $this->code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $this->url = (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        curl_close($ch);
        return $this;
    }

    public function get(string $page, array $q = []): self { return $this->req('GET', $page, $q); }
    public function post(string $page, array $data, array $q = []): self
    {
        $this->req('POST', $page, $q, $data);
        // Seit 1.8.0 melden sich neue Konten erst nach E-Mail-Bestätigung an: für die übrigen Tests bestätigen und anmelden.
        // (Der Bestätigungsablauf selbst wird im Abschnitt „E-Mail-Versand und Bestätigung“ über req() geprüft.)
        if ($page === 'register' && isset($data['email']) && ($id = val('SELECT id FROM users WHERE email = ? AND email_verified_at IS NULL', [$data['email']]))) {
            Verification::markVerified((int) $id);
            $this->get('login')->req('POST', 'login', [], ['email' => $data['email'], 'password' => $data['password']]);
        }
        return $this;
    }

    public function csrf(): string
    {
        if (!preg_match('/name="_csrf" value="([a-f0-9]{64})"/', $this->body, $m)) {
            $this->get('home');
            preg_match('/name="_csrf" value="([a-f0-9]{64})"/', $this->body, $m);
        }
        return $m[1] ?? '';
    }

    public function has(string $text): bool { return str_contains($this->body, $text); }
    public function clean(): bool { return !preg_match('/(Warning|Notice|Deprecated|Fatal error|Uncaught)\b/', $this->body); }
}

function png(string $path, int $w, int $h): CURLFile
{
    $img = imagecreatetruecolor($w, $h);
    imagefill($img, 0, 0, imagecolorallocate($img, 91, 63, 214));
    imagestring($img, 5, 10, 10, 'Vylaris Test', imagecolorallocate($img, 255, 255, 255));
    imagepng($img, $path);
    return new CURLFile($path, 'image/png', basename($path));
}

$today = today();
$plus = fn (int $d) => date('Y-m-d', strtotime(sprintf("%+d days", $d)));
$uniq = substr(bin2hex(random_bytes(3)), 0, 6);

echo "\n== Registrierung & Login ==\n";
$pub = new Client($base, 'pub');
$pub->get('register')->post('register', ['name' => 'Petra Publisher', 'company' => 'Tech Blog GmbH', 'email' => "pub$uniq@example.com", 'role' => 'publisher', 'password' => 'PublisherPass1', 'password2' => 'PublisherPass1', 'terms' => '1']);
check($pub->has('Petra Publisher'), 'Webseitenbetreiber registriert und angemeldet');
$adv = new Client($base, 'adv');
$adv->get('register')->post('register', ['name' => 'Anton Advertiser', 'email' => "adv$uniq@example.com", 'role' => 'advertiser', 'password' => 'AdvertiserPass1', 'password2' => 'AdvertiserPass1', 'terms' => '1']);
check($adv->has('Anton Advertiser'), 'Werbetreibender registriert');
$adv2 = new Client($base, 'adv2');
$adv2->get('register')->post('register', ['name' => 'Berta Brand', 'email' => "adv2$uniq@example.com", 'role' => 'advertiser', 'password' => 'AdvertiserPass2', 'password2' => 'AdvertiserPass2', 'terms' => '1']);
$dup = new Client($base, 'dup');
$dup->get('register')->post('register', ['name' => 'X', 'email' => "adv$uniq@example.com", 'role' => 'admin', 'password' => 'short', 'password2' => 'other', 'terms' => '1']);
check($dup->has('bereits registriert') || $dup->has('Kontoart'), 'Ungültige Registrierung (Admin-Rolle, Duplikat, kurzes Passwort) abgelehnt');
check((int) val("SELECT COUNT(*) FROM users WHERE role = 'admin' AND email LIKE ?", ["%$uniq%"]) === 0, 'Selbstregistrierung als Admin nicht möglich');
$admin = new Client($base, 'admin');
$admin->get('login')->post('login', ['email' => $adminEmail, 'password' => $adminPass]);
check($admin->has('Administration'), 'Admin-Login');

echo "\n== Sicherheit: CSRF, Rollen, Methoden ==\n";
$adv->req('POST', 'site.form', [], ['name' => 'x'], true, false);
check($adv->code === 419, 'POST ohne CSRF-Token → 419');
$adv->req('POST', 'campaign.form', [], ['_csrf' => str_repeat('a', 64), 'name' => 'x']);
check($adv->code === 419, 'POST mit falschem CSRF-Token → 419');
$adv->get('admin.settings');
check($adv->code === 403, 'Werbetreibender kann Admin-Bereich nicht öffnen (403)');
$pub->get('campaigns');
check($pub->code === 403, 'Betreiber kann Kampagnenbereich nicht öffnen (403)');
$adv->get('logout');
check($adv->code === 405, 'Logout per GET nicht erlaubt (405)');
$anon = new Client($base, 'anon');
$anon->get('dashboard');
check(str_contains($anon->url, 'p=login'), 'Anonymer Zugriff auf Dashboard → Login');

echo "\n== Webseite & Werbeplätze (Betreiber) ==\n";
$pub->get('site.form')->post('site.form', ['name' => 'Tech Blog', 'url' => 'https://techblog.example.com', 'category' => 'Technologie', 'description' => 'Ein Blog über Webentwicklung, PHP und Open Source mit treuer Leserschaft.', 'monthly_visitors' => '45000']);
$siteId = (int) val('SELECT id FROM sites WHERE user_id = (SELECT id FROM users WHERE email = ?)', ["pub$uniq@example.com"]);
check($siteId > 0 && $pub->has('Webseite angelegt'), 'Webseite angelegt (Status pending)');
$pub->get('site.form')->post('site.form', ['name' => 'Bad', 'url' => 'javascript:alert(1)', 'category' => 'Technologie', 'description' => str_repeat('x', 30)]);
check($pub->has('gültige URL'), 'javascript:-URL wird abgelehnt');
$pub->get('slot.form', ['site_id' => $siteId])->post('slot.form', ['name' => 'Sidebar oben', 'format' => '300x250', 'placement' => 'Rechte Spalte', 'description' => 'Gut sichtbar auf allen Artikelseiten.', 'price_per_day' => '10.00', 'min_days' => '1', 'max_days' => '30', 'available_from' => $today, 'available_until' => ''], ['site_id' => $siteId]);
$slotId = (int) val('SELECT id FROM ad_slots WHERE site_id = ?', [$siteId]);
check($slotId > 0 && (int) val('SELECT price_per_day FROM ad_slots WHERE id = ?', [$slotId]) === 1000, 'Werbeplatz 300×250 zu 10.00/Tag angelegt');
$pub->get('slot.form', ['site_id' => $siteId])->post('slot.form', ['name' => 'Header', 'format' => 'custom', 'width' => '5', 'height' => '5', 'description' => 'zu klein', 'price_per_day' => '1', 'min_days' => '1', 'max_days' => '1', 'available_from' => $today], ['site_id' => $siteId]);
check($pub->has('Eigenes Format'), 'Ungültiges Eigenformat abgelehnt');
$anon->get('home');
check(!$anon->has('Sidebar oben'), 'Nicht freigegebener Werbeplatz ist im Marktplatz unsichtbar');
$anon->get('slot', ['id' => $slotId]);
check($anon->code === 404, 'Detailseite nicht freigegebener Werbeplatz → 404 für Gäste');

// Fremder Betreiber darf Webseite nicht bearbeiten
$pub2 = new Client($base, 'pub2');
$pub2->get('register')->post('register', ['name' => 'Other Pub', 'email' => "pub2$uniq@example.com", 'role' => 'publisher', 'password' => 'PublisherPass2', 'password2' => 'PublisherPass2', 'terms' => '1']);
$pub2->get('site.form', ['id' => $siteId]);
check($pub2->code === 404, 'Fremde Webseite kann nicht bearbeitet werden (404)');

echo "\n== Kampagnen & Uploads (Werbetreibende) ==\n";
$adv->get('campaign.form')->post('campaign.form', ['name' => 'Herbstaktion', 'target_url' => 'https://shop.example.com/herbst', 'alt_text' => 'Herbstaktion', 'banner' => png("$tmp/b1.png", 300, 250)]);
$campId = (int) val("SELECT id FROM campaigns WHERE name = 'Herbstaktion' AND user_id = (SELECT id FROM users WHERE email = ?)", ["adv$uniq@example.com"]);
check($campId > 0, 'Kampagne mit PNG-Banner 300×250 angelegt');
$file = (string) val('SELECT banner_file FROM campaigns WHERE id = ?', [$campId]);
check(preg_match('/^[a-f0-9]{32}\.png$/', $file) === 1 && is_file(APP_ROOT . '/storage/banners/' . $file), 'Banner mit Zufallsnamen ausserhalb des Webroots gespeichert');
file_put_contents("$tmp/evil.png", "<?php system(\$_GET['c']); ?>");
$adv->get('campaign.form')->post('campaign.form', ['name' => 'Evil', 'target_url' => 'https://evil.example.com', 'banner' => new CURLFile("$tmp/evil.png", 'image/png', 'evil.php.png')]);
check($adv->has('Erlaubt sind nur') && !val("SELECT id FROM campaigns WHERE name = 'Evil'"), 'PHP-Code als .png getarnt wird abgelehnt');
file_put_contents("$tmp/poly.gif", "GIF89a\x01\x00\x01\x00\x00\x00\x00;<?php echo 1; ?>");
$adv->get('campaign.form')->post('campaign.form', ['name' => 'Poly', 'target_url' => 'https://evil.example.com', 'banner' => new CURLFile("$tmp/poly.gif", 'image/gif', 'poly.gif')]);
check(!val("SELECT id FROM campaigns WHERE name = 'Poly'"), 'Defektes/polyglottes GIF wird abgelehnt');
foreach (['jpg' => ['imagejpeg', 'image/jpeg'], 'gif' => ['imagegif', 'image/gif'], 'webp' => ['imagewebp', 'image/webp']] as $ext => [$fn, $mime]) {
    $im = imagecreatetruecolor(120, 60);
    imagefill($im, 0, 0, imagecolorallocate($im, 20, 120, 200));
    $fn($im, "$tmp/ok.$ext");
    $adv->get('campaign.form')->post('campaign.form', ['name' => "Format-$ext", 'target_url' => 'https://ok.example.com', 'banner' => new CURLFile("$tmp/ok.$ext", $mime, "ok.$ext")]);
    check((bool) val('SELECT id FROM campaigns WHERE name = ?', ["Format-$ext"]), "Gültiges " . strtoupper($ext) . "-Banner wird akzeptiert");
}
file_put_contents("$tmp/trail.png", file_get_contents("$tmp/b1.png") . '<?php evil(); ?>');
$adv->get('campaign.form')->post('campaign.form', ['name' => 'Trail', 'target_url' => 'https://evil.example.com', 'banner' => new CURLFile("$tmp/trail.png", 'image/png', 'trail.png')]);
check(!val("SELECT id FROM campaigns WHERE name = 'Trail'"), 'PNG mit angehängtem PHP-Code wird abgelehnt');
$adv->get('campaign.form')->post('campaign.form', ['name' => 'Leaderboard', 'target_url' => 'https://shop.example.com', 'banner' => png("$tmp/b2.png", 728, 90)]);
$campWrong = (int) val("SELECT id FROM campaigns WHERE name = 'Leaderboard'");
$adv2->get('campaign.form')->post('campaign.form', ['name' => 'Berta Kampagne', 'target_url' => 'https://berta.example.com', 'banner' => png("$tmp/b3.png", 300, 250)]);
$camp2 = (int) val("SELECT id FROM campaigns WHERE name = 'Berta Kampagne'");
$adv2->get('banner', ['id' => $campId]);
check($adv2->code === 404, 'Fremdes Banner nicht abrufbar');
$adv->get('banner', ['id' => $campId]);
check($adv->code === 200 && str_starts_with($adv->body, "\x89PNG"), 'Eigenes Banner abrufbar');

echo "\n== Freigaben (Admin) ==\n";
foreach ([['sites', $siteId], ['slots', $slotId], ['campaigns', $campId], ['campaigns', $campWrong], ['campaigns', $camp2]] as [$t, $id]) {
    $admin->get('admin.reviews', ['type' => $t])->post('admin.review', ['type' => $t, 'id' => $id, 'decision' => 'approved']);
}
check(val('SELECT status FROM sites WHERE id = ?', [$siteId]) === 'approved' && val('SELECT status FROM ad_slots WHERE id = ?', [$slotId]) === 'approved'
    && val('SELECT status FROM campaigns WHERE id = ?', [$campId]) === 'approved', 'Webseite, Werbeplatz und Kampagnen freigegeben');
$admin->get('admin.reviews')->post('admin.review', ['type' => 'sites', 'id' => $siteId, 'decision' => 'rejected', 'note' => '']);
check($admin->has('Begründung') && val('SELECT status FROM sites WHERE id = ?', [$siteId]) === 'approved', 'Ablehnung ohne Begründung wird verweigert');
$anon->get('home');
check($anon->has('Sidebar oben'), 'Freigegebener Werbeplatz im Marktplatz sichtbar');
$anon->get('home', ['format' => '728x90']);
check(!$anon->has('Sidebar oben'), 'Formatfilter funktioniert');

echo "\n== Buchungsanfrage & Preisschutz ==\n";
$adv->get('slot', ['id' => $slotId]);
check($adv->has('Buchung anfragen') && $adv->has('Herbstaktion') && !$adv->has('>Leaderboard<'), 'Buchungsformular zeigt nur formatpassende Kampagnen');
$adv->post('booking.request', ['slot_id' => $slotId, 'campaign_id' => $campWrong, 'start_date' => $today, 'end_date' => $plus(4)]);
check($adv->has('passt nicht zum Format'), 'Banner im falschen Format wird abgelehnt');
$adv->post('booking.request', ['slot_id' => $slotId, 'campaign_id' => $camp2, 'start_date' => $today, 'end_date' => $plus(4)]);
check($adv->has('eigene Kampagne'), 'Fremde Kampagne kann nicht verwendet werden');
$adv->post('booking.request', ['slot_id' => $slotId, 'campaign_id' => $campId, 'start_date' => $plus(-1), 'end_date' => $plus(3)]);
check($adv->has('Vergangenheit'), 'Startdatum in der Vergangenheit abgelehnt');
$adv->post('booking.request', ['slot_id' => $slotId, 'campaign_id' => $campId, 'start_date' => $today, 'end_date' => $plus(40)]);
check($adv->has('zwischen 1 und 30 Tagen'), 'Höchstlaufzeit wird durchgesetzt');
$adv->get('slot', ['id' => $slotId])->post('booking.request', [
    'slot_id' => $slotId, 'campaign_id' => $campId, 'start_date' => $today, 'end_date' => $plus(4), 'message' => 'Gerne ab sofort!',
    'price_per_day' => '1', 'total_amount' => '1', 'commission_bp' => '0', 'status' => 'paid',   // Manipulationsversuche
]);
$b1 = one('SELECT * FROM bookings WHERE advertiser_id = (SELECT id FROM users WHERE email = ?) ORDER BY id DESC LIMIT 1', ["adv$uniq@example.com"]);
check($b1 && (int) $b1['total_amount'] === 5000 && (int) $b1['price_per_day'] === 1000 && $b1['status'] === 'requested', 'Preis serverseitig berechnet (5 × 10.00 = 50.00), Manipulation ignoriert');
check((int) $b1['commission_bp'] === 1500 && (int) $b1['commission_amount'] === 750 && (int) $b1['publisher_amount'] === 4250, 'Provision 15 % = 7.50, Betreiber 42.50');
$adv->post('booking.request', ['slot_id' => $slotId, 'campaign_id' => $campId, 'start_date' => $plus(1), 'end_date' => $plus(2)]);
check($adv->has('bereits eine offene Anfrage'), 'Doppelte eigene Anfrage verhindert');
$adv2->get('slot', ['id' => $slotId])->post('booking.request', ['slot_id' => $slotId, 'campaign_id' => $camp2, 'start_date' => $plus(2), 'end_date' => $plus(6)]);
$b2 = (int) val('SELECT id FROM bookings WHERE campaign_id = ?', [$camp2]);
check($b2 > 0, 'Zweite, überschneidende Anfrage eines anderen Werbetreibenden möglich (noch nicht verbindlich)');

echo "\n== Annahme, Doppelbuchungsschutz, Berechtigungen ==\n";
$adv2->get('booking', ['id' => $b1['id']]);
check($adv2->code === 404, 'Fremde Buchung nicht einsehbar');
$pub2->get('booking', ['id' => $b1['id']]);
check($pub2->code === 404, 'Fremder Betreiber sieht Buchung nicht');
$pub2->get('dashboard')->post('booking.accept', ['id' => $b1['id']]);
check(val('SELECT status FROM bookings WHERE id = ?', [$b1['id']]) === 'requested', 'Fremder Betreiber kann nicht annehmen');
$adv->get('booking', ['id' => $b1['id']])->post('booking.accept', ['id' => $b1['id']]);
check($adv->code === 403, 'Werbetreibender kann nicht annehmen (403)');
$pub->get('booking', ['id' => $b1['id']])->post('booking.accept', ['id' => $b1['id'], 'note' => 'Passt!']);
check(val('SELECT status FROM bookings WHERE id = ?', [$b1['id']]) === 'accepted', 'Betreiber nimmt Anfrage an');
check(val('SELECT status FROM bookings WHERE id = ?', [$b2]) === 'declined', 'Überschneidende Konkurrenzanfrage automatisch abgelehnt');
$adv2->get('slot', ['id' => $slotId])->post('booking.request', ['slot_id' => $slotId, 'campaign_id' => $camp2, 'start_date' => $plus(3), 'end_date' => $plus(8)]);
check($adv2->has('bereits belegt'), 'Neue Anfrage für belegten Zeitraum abgelehnt');
$adv2->get('slot', ['id' => $slotId]);
check($adv2->has(fdate($today) . ' – ' . fdate($plus(4))), 'Belegter Zeitraum wird auf der Detailseite angezeigt');

// Parallele Annahme zweier überschneidender Anfragen: genau eine darf gewinnen
$adv2->get('slot', ['id' => $slotId])->post('booking.request', ['slot_id' => $slotId, 'campaign_id' => $camp2, 'start_date' => $plus(10), 'end_date' => $plus(12)]);
$adv->get('slot', ['id' => $slotId])->post('booking.request', ['slot_id' => $slotId, 'campaign_id' => $campId, 'start_date' => $plus(11), 'end_date' => $plus(13)]);
$r1 = (int) val("SELECT id FROM bookings WHERE campaign_id = ? AND status = 'requested'", [$camp2]);
$r2 = (int) val("SELECT id FROM bookings WHERE campaign_id = ? AND status = 'requested'", [$campId]);
// Lock simulieren: Sperre auf dem Werbeplatz halten, während beide Annahmen parallel eintreffen
$pub->get('dashboard');
$token = $pub->csrf();
$lockPdo = new PDO('mysql:host=' . config('db.host') . ';dbname=' . config('db.name'), config('db.user'), config('db.pass'));
$lockPdo->beginTransaction();
$lockPdo->query('SELECT id FROM ad_slots WHERE id = ' . $slotId . ' FOR UPDATE');
$mh = curl_multi_init();
$handles = [];
$jar = (new ReflectionProperty(Client::class, 'jar'))->getValue($pub);
foreach ([$r1, $r2] as $rid) {
    $ch = curl_init($base . '/index.php?p=booking.accept');
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => ['_csrf' => $token, 'id' => $rid], CURLOPT_COOKIEFILE => $jar, CURLOPT_RETURNTRANSFER => true]);
    curl_multi_add_handle($mh, $ch);
    $handles[] = $ch;
}
$running = 0;
$t0 = microtime(true);
do { curl_multi_exec($mh, $running); usleep(20000); } while (microtime(true) - $t0 < 1.0);
$lockPdo->commit();
do { curl_multi_exec($mh, $running); curl_multi_select($mh, 0.1); } while ($running > 0);
$accepted = (int) val("SELECT COUNT(*) FROM bookings WHERE id IN (?, ?) AND status = 'accepted'", [$r1, $r2]);
check($accepted === 1, "Parallele Annahme überschneidender Anfragen: genau eine angenommen ($accepted)");
check(Booking::overlapCount($slotId, $plus(10), $plus(13)) === 1, 'Keine Doppelbuchung in der Datenbank');
$builtinSerial = true; // PHP-Dev-Server verarbeitet Requests seriell – die Zeilensperre wird zusätzlich direkt geprüft:
$pdoA = new PDO('mysql:host=' . config('db.host') . ';dbname=' . config('db.name'), config('db.user'), config('db.pass'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdoB = new PDO('mysql:host=' . config('db.host') . ';dbname=' . config('db.name'), config('db.user'), config('db.pass'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdoB->exec('SET SESSION innodb_lock_wait_timeout = 1');
$pdoA->beginTransaction();
$pdoA->query("SELECT id FROM ad_slots WHERE id = $slotId FOR UPDATE");
$pdoB->beginTransaction();
try { $pdoB->query("SELECT id FROM ad_slots WHERE id = $slotId FOR UPDATE"); $blocked = false; } catch (PDOException $e) { $blocked = true; }
$pdoB->rollBack();
$pdoA->rollBack();
check($blocked, 'Zeilensperre auf Werbeplatz serialisiert konkurrierende Transaktionen');

echo "\n== Zahlung (manuell) ==\n";
$adv->get('booking', ['id' => $b1['id']]);
check($adv->has($b1['reference']) && $adv->has('Ich habe überwiesen') && $adv->has('IBAN'), 'Werbetreibender sieht Zahlungsinformationen mit Referenz');
$adv->post('booking.report_payment', ['id' => $b1['id']]);
check(val('SELECT payment_status FROM bookings WHERE id = ?', [$b1['id']]) === 'reported' && val('SELECT status FROM bookings WHERE id = ?', [$b1['id']]) === 'accepted', 'Zahlung gemeldet (ändert Buchungsstatus nicht)');
$adv->post('admin.payment', ['id' => $b1['id'], 'method' => 'bank_transfer', 'received_on' => $today]);
check($adv->code === 403 && val('SELECT status FROM bookings WHERE id = ?', [$b1['id']]) === 'accepted', 'Werbetreibender kann Zahlung nicht selbst bestätigen');
$admin->get('booking', ['id' => $b1['id']])->post('admin.payment', ['id' => $b1['id'], 'method' => 'bank_transfer', 'received_on' => $today, 'provider_ref' => 'TX-1', 'amount' => '1']);
$b1 = one('SELECT * FROM bookings WHERE id = ?', [$b1['id']]);
check($b1['status'] === 'paid' && $b1['payment_status'] === 'paid' && preg_match('/^VA-\d{4}-00001$/', (string) $b1['invoice_no']) === 1, 'Admin bestätigt Zahlung, Belegnummer ' . $b1['invoice_no']);
check((int) val('SELECT amount FROM payments WHERE booking_id = ?', [$b1['id']]) === 5000, 'Zahlungsbetrag = Buchungsbetrag (Formularwert ignoriert)');
$ledger = all('SELECT type, amount FROM ledger WHERE booking_id = ? ORDER BY id', [$b1['id']]);
check(array_column($ledger, 'amount', 'type') === ['advertiser_payment' => 5000, 'platform_commission' => 750, 'publisher_earning' => 4250], 'Journal: 50.00 Eingang, 7.50 Provision, 42.50 Erlös');
$admin->post('admin.payment', ['id' => $b1['id'], 'method' => 'bank_transfer', 'received_on' => $today]);
check($admin->has('nur für angenommene') && (int) val('SELECT COUNT(*) FROM payments WHERE booking_id = ?', [$b1['id']]) === 1, 'Doppelte Zahlungserfassung verhindert');
$adv->get('booking', ['id' => $b1['id']])->post('booking.cancel', ['id' => $b1['id']]);
check(val('SELECT status FROM bookings WHERE id = ?', [$b1['id']]) === 'paid', 'Bezahlte Buchung kann nicht storniert werden');

echo "\n== Auslieferung & Statistik ==\n";
$ch = curl_init($base . '/index.php?p=serve&t=' . $b1['serve_token']);
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_USERAGENT => 'Mozilla/5.0 Test', CURLOPT_HEADER => true]);
$resp = (string) curl_exec($ch);
check(str_contains($resp, 'Content-Type: image/png') && str_contains($resp, 'nosniff'), 'Banner wird während der Laufzeit ausgeliefert');
curl_setopt($ch, CURLOPT_URL, $base . '/index.php?p=serve&t=' . $b1['serve_token']);
curl_exec($ch);
curl_setopt($ch, CURLOPT_URL, $base . '/index.php?p=click&t=' . $b1['serve_token']);
$resp = (string) curl_exec($ch);
check(str_contains($resp, 'Location: https://shop.example.com/herbst'), 'Klick leitet zur Ziel-URL weiter');
curl_setopt($ch, CURLOPT_URL, $base . '/index.php?p=serve&t=' . str_repeat('0', 32));
$resp = (string) curl_exec($ch);
check(str_contains($resp, 'image/gif'), 'Unbekannter Token → transparentes Pixel');
curl_setopt_array($ch, [CURLOPT_URL => $base . '/index.php?p=serve&t=' . $b1['serve_token'], CURLOPT_USERAGENT => 'Googlebot/2.1']);
curl_exec($ch);
curl_close($ch);
$st = one('SELECT impressions, clicks FROM booking_stats WHERE booking_id = ?', [$b1['id']]);
check((int) $st['impressions'] === 2 && (int) $st['clicks'] === 1, 'Statistik: 2 Impressionen, 1 Klick (Bot ignoriert)');
$pub->get('booking', ['id' => $b1['id']]);
check($pub->has('Einbindungscode') && $pub->has($b1['serve_token']), 'Betreiber erhält Einbindungscode');
$adv->get('dashboard');
check($adv->has('Impressionen') && $adv->clean(), 'Werbetreibenden-Dashboard zeigt Statistik');

echo "\n== Belege, Abschluss & Auszahlung ==\n";
$adv->get('receipt', ['id' => $b1['id'], 'type' => 'credit']);
check($adv->code === 200 && $adv->has('Rechnung') && !$adv->has('Gutschriftbetrag'), 'Werbetreibender erhält Rechnung (keine Gutschrift)');
$pub->get('receipt', ['id' => $b1['id']]);
check($pub->has('Gutschrift') && $pub->has('42.50'), 'Betreiber erhält Gutschrift über 42.50');
$bal = Billing::publisherBalance((int) $b1['publisher_id']);
check($bal['pending'] === 4250 && $bal['available'] === 0, 'Erlös vor Laufzeitende ausstehend, nicht auszahlbar');
$admin->get('admin.payouts')->post('admin.payouts', ['publisher_id' => $b1['publisher_id'], 'amount' => '42.50', 'method' => 'bank_transfer', 'paid_on' => $today]);
check($admin->has('übersteigt') && (int) val('SELECT COUNT(*) FROM payouts') === 0, 'Auszahlung vor Abschluss verweigert');
q('UPDATE bookings SET start_date = ?, end_date = ? WHERE id = ?', [$plus(-6), $plus(-2), $b1['id']]); // Laufzeit in die Vergangenheit verschieben
Booking::housekeeping(true);
check(val('SELECT status FROM bookings WHERE id = ?', [$b1['id']]) === 'completed', 'Statuspflege: abgelaufene bezahlte Buchung → abgeschlossen');
$bal = Billing::publisherBalance((int) $b1['publisher_id']);
check($bal['available'] === 4250, 'Guthaben 42.50 nun auszahlbar');
$admin->get('admin.payouts')->post('admin.payouts', ['publisher_id' => $b1['publisher_id'], 'amount' => '42.51', 'method' => 'bank_transfer', 'paid_on' => $today]);
check($admin->has('übersteigt'), 'Auszahlung über Guthaben verweigert');
$admin->get('admin.payouts')->post('admin.payouts', ['publisher_id' => $b1['publisher_id'], 'amount' => '42.50', 'method' => 'bank_transfer', 'paid_on' => $today, 'note' => 'Oktober']);
check(Billing::publisherBalance((int) $b1['publisher_id'])['available'] === 0 && $admin->has('Auszahlung erfasst'), 'Auszahlung 42.50 verbucht, Guthaben 0');
$pub->get('earnings');
check($pub->has('Oktober') && $pub->has('Auszahlung') && $pub->clean(), 'Betreiber sieht Kontoauszug inkl. Auszahlung');
$admin->get('admin.finance', ['format' => 'csv']);
check(str_contains($admin->body, 'publisher_payout') && str_contains($admin->body, '-42.50'), 'CSV-Export des Journals');

echo "\n== Erstattung & Storno ==\n";
$accId = (int) val("SELECT id FROM bookings WHERE id IN (?, ?) AND status = 'accepted'", [$r1, $r2]);
$admin->get('booking', ['id' => $accId])->post('admin.payment', ['id' => $accId, 'method' => 'bank_transfer', 'received_on' => $today]);
$admin->get('booking', ['id' => $accId])->post('admin.refund', ['id' => $accId, 'note' => 'Kunde hat storniert']);
check(val('SELECT status FROM bookings WHERE id = ?', [$accId]) === 'refunded', 'Bezahlte Buchung erstattet');
$unbalanced = (int) val("SELECT COUNT(*) FROM (SELECT SUM(amount) s FROM ledger WHERE booking_id = ? GROUP BY CASE type
    WHEN 'advertiser_payment' THEN 1 WHEN 'refund' THEN 1 WHEN 'platform_commission' THEN 2 WHEN 'commission_reversal' THEN 2 ELSE 3 END HAVING s <> 0) x", [$accId]);
check($unbalanced === 0 && (int) val('SELECT COUNT(*) FROM ledger WHERE booking_id = ?', [$accId]) === 6, 'Journal nach Erstattung ausgeglichen (Eingang/Provision/Erlös je netto 0)');
check(Booking::overlapCount($slotId, $plus(10), $plus(13)) === 0, 'Erstatteter Zeitraum ist wieder frei');
$adv2->get('slot', ['id' => $slotId])->post('booking.request', ['slot_id' => $slotId, 'campaign_id' => $camp2, 'start_date' => $plus(20), 'end_date' => $plus(21)]);
$r3 = (int) val("SELECT id FROM bookings WHERE campaign_id = ? AND status = 'requested'", [$camp2]);
$adv2->get('booking', ['id' => $r3])->post('booking.cancel', ['id' => $r3, 'note' => 'doch nicht']);
check(val('SELECT status FROM bookings WHERE id = ?', [$r3]) === 'cancelled', 'Werbetreibender storniert offene Anfrage');

echo "\n== Konto, Sperre, Login-Schutz ==\n";
$pub->get('account')->post('account', ['action' => 'profile', 'name' => 'Petra P.', 'company' => 'Tech Blog GmbH', 'email' => "pub$uniq@example.com", 'payout_details' => "IBAN CH12 3456"]);
check($pub->has('Profil wurde gespeichert') && val('SELECT payout_details FROM users WHERE email = ?', ["pub$uniq@example.com"]) === 'IBAN CH12 3456', 'Profil & Auszahlungsdaten gespeichert');
$admin->get('admin.payouts');
check($admin->has('IBAN CH12 3456'), 'Admin sieht Auszahlungsdaten');
$adv2Id = (int) val('SELECT id FROM users WHERE email = ?', ["adv2$uniq@example.com"]);
$admin->get('admin.users')->post('admin.user', ['id' => $adv2Id, 'action' => 'block']);
$adv2->get('dashboard');
check(str_contains($adv2->url, 'p=login'), 'Gesperrter Benutzer wird sofort abgemeldet');
$adv2->get('login')->post('login', ['email' => "adv2$uniq@example.com", 'password' => 'AdvertiserPass2']);
check($adv2->has('gesperrt'), 'Gesperrter Benutzer kann sich nicht anmelden');
$brute = new Client($base, 'brute');
for ($i = 0; $i < 6; $i++) {
    $brute->get('login')->post('login', ['email' => "pub$uniq@example.com", 'password' => 'falsch' . $i]);
}
check($brute->has('Zu viele fehlgeschlagene'), 'Login-Sperre nach 5 Fehlversuchen');
$brute->get('login')->post('login', ['email' => "pub$uniq@example.com", 'password' => 'PublisherPass1']);
check($brute->has('Zu viele'), 'Auch korrektes Passwort während Sperre abgewiesen');
$pub->get('account')->post('account', ['action' => 'password', 'current_password' => 'PublisherPass1', 'password' => 'NewPublisherPass1', 'password2' => 'NewPublisherPass1']);
check($pub->has('Passwort wurde geändert'), 'Passwort geändert');

echo "\n== Seiten-Smoke-Test (keine PHP-Warnungen) ==\n";
$pages = [
    [$anon, ['home', 'login', 'register']],
    [$adv, ['dashboard', 'campaigns', 'campaign.form', 'bookings', 'account', 'home']],
    [$pub, ['dashboard', 'sites', 'site.form', 'bookings', 'earnings', 'account']],
    [$admin, ['dashboard', 'admin.reviews', 'admin.users', 'admin.bookings', 'admin.payouts', 'admin.finance', 'admin.settings', 'admin.audit']],
];
foreach ($pages as [$client, $list]) {
    foreach ($list as $p) {
        $client->get($p);
        check($client->code === 200 && $client->clean(), "GET $p → 200 ohne Warnungen");
    }
}
$admin->get('admin.reviews', ['type' => 'campaigns', 'status' => 'all']);
check($admin->code === 200 && $admin->clean(), 'Admin-Freigaben (Kampagnen, alle)');
$pub->get('slot.form', ['id' => $slotId]);
check($pub->code === 200 && $pub->has('Sidebar oben'), 'Werbeplatz bearbeiten');
$admin->get('admin.settings')->post('admin.settings', ['platform_name' => 'VylarisAdvertise', 'currency' => 'CHF', 'commission' => '12.5', 'bank_details' => "Kontoinhaber: Test AG\nIBAN: CH00 1234", 'invoice_prefix' => 'VA', 'min_payout' => '20']);
check((int) setting('__reset') === 0 && (int) val("SELECT value FROM settings WHERE name='commission_bp'") === 1250, 'Provision auf 12.5 % geändert');
$admin->get('admin.settings')->post('admin.settings', ['platform_name' => 'VylarisAdvertise', 'currency' => 'EUR', 'commission' => '12.5', 'bank_details' => 'x x x x x', 'invoice_prefix' => 'VA', 'min_payout' => '20']);
check($admin->has('nicht mehr geändert'), 'Währungswechsel nach Buchungen gesperrt');
$audit = (int) val('SELECT COUNT(*) FROM audit_log');
check($audit > 20, "Protokoll enthält $audit Einträge");

echo "\n== DSGVO: Rechtstexte & Einwilligung ==\n";
$anon->get('privacy');
check($anon->code === 200 && $anon->has('Verantwortlicher') && $anon->has('Ihre Rechte') && $anon->has('Art. 15') && $anon->clean(), 'Datenschutzerklärung öffentlich erreichbar');
$anon->get('imprint');
check($anon->code === 200 && $anon->clean(), 'Impressum öffentlich erreichbar');
$anon->get('terms');
check($anon->code === 200 && $anon->has('Plattformprovision') && $anon->clean(), 'Nutzungsbedingungen öffentlich erreichbar');
$anon->get('home');
check($anon->has('p=privacy') && $anon->has('p=imprint') && $anon->has('p=terms'), 'Footer verlinkt Datenschutz, Impressum und Nutzungsbedingungen');
$anon->get('register');
check($anon->has('p=terms') && $anon->has('p=privacy'), 'Registrierung verlinkt Nutzungsbedingungen und Datenschutzerklärung');
check((int) val("SELECT COUNT(*) FROM users WHERE email LIKE ? AND consent_at IS NOT NULL AND consent_version <> ''", ["%$uniq@example.com"]) >= 3, 'Zustimmung (Zeitpunkt und Fassung) bei Registrierung protokolliert');
$admin->get('dashboard');
check($admin->has('rechtlichen Angaben'), 'Admin wird auf fehlende Betreiberangaben hingewiesen');
$admin->get('admin.settings')->post('admin.legal', ['legal_name' => 'Muster AG', 'legal_address' => "Musterstrasse 1\n8000 Zürich", 'legal_email' => 'datenschutz@muster.example', 'legal_hoster' => 'Hoster AG, Zürich']);
$anon->get('imprint');
check($anon->has('Muster AG') && $anon->has('Musterstrasse 1') && $anon->has('8000 Zürich'), 'Impressum zeigt hinterlegte Betreiberdaten');
$anon->get('privacy');
check($anon->has('datenschutz@muster.example') && $anon->has('Hoster AG'), 'Datenschutzerklärung nennt Verantwortlichen, Kontakt und Hoster');
check(val("SELECT value FROM settings WHERE name = 'legal_version'") === $today, 'Fassung der Rechtstexte wird bei Änderung aktualisiert');
$admin->get('dashboard');
check(!$admin->has('rechtlichen Angaben'), 'Hinweis verschwindet nach Vervollständigung');
$admin->get('admin.settings')->post('admin.legal', ['legal_name' => 'Muster AG', 'legal_address' => "Musterstrasse 1\n8000 Zürich", 'legal_email' => 'datenschutz@muster.example', 'legal_hoster' => 'Hoster AG, Zürich',
    'legal_terms_custom' => "## Eigener Titel\n\n<script>alert(1)</script> <b>fett</b>"]);
$anon->get('terms');
check($anon->has('Eigener Titel') && $anon->has('&lt;script&gt;') && !$anon->has('<script>alert') && !$anon->has('<b>fett'), 'Eigener Rechtstext wird escaped (kein HTML/Skript)');
$admin->get('admin.settings')->post('admin.legal', ['legal_name' => 'Muster AG', 'legal_address' => "Musterstrasse 1\n8000 Zürich", 'legal_email' => 'datenschutz@muster.example', 'legal_hoster' => 'Hoster AG, Zürich']);
$anon->get('terms');
check($anon->has('Plattformprovision'), 'Leerer Eigentext → Standardvorlage');
$adv->post('admin.legal', ['legal_name' => 'Evil']);
check($adv->code === 403 && val("SELECT value FROM settings WHERE name = 'legal_name'") === 'Muster AG', 'Rechtstexte nur durch Administratoren änderbar');

echo "\n== DSGVO: Auskunft/Export, Löschung, Aufbewahrung ==\n";
$mk = function (string $tag, string $role, string $pw) use ($base, $uniq): Client {
    $c = new Client($base, $tag);
    $c->get('register')->post('register', ['name' => "G $tag", 'company' => "Firma $tag", 'email' => "$tag$uniq@example.com", 'role' => $role, 'password' => $pw, 'password2' => $pw, 'terms' => '1']);
    return $c;
};
$idOf = fn (string $tag): int => (int) val('SELECT id FROM users WHERE email = ?', ["$tag$uniq@example.com"]);
$approve = function (string $t, int $id) use ($admin): void { $admin->get('admin.reviews', ['type' => $t])->post('admin.review', ['type' => $t, 'id' => $id, 'decision' => 'approved']); };
$campaign = function (Client $c, string $name, string $file) use ($tmp): array {
    $c->get('campaign.form')->post('campaign.form', ['name' => $name, 'target_url' => 'https://gdpr-ads.example.com', 'alt_text' => 'x', 'banner' => png("$tmp/$file.png", 300, 250)]);
    $row = one('SELECT id, banner_file FROM campaigns WHERE name = ? ORDER BY id DESC LIMIT 1', [$name]);
    return [(int) $row['id'], (string) $row['banner_file']];
};
$adminId = (int) val('SELECT id FROM users WHERE email = ?', [$adminEmail]);

$gp = $mk('gp', 'publisher', 'GdprPublisher1');
$ga = $mk('ga', 'advertiser', 'GdprAdvertiser1');
$gp->get('site.form')->post('site.form', ['name' => 'GDPR Site', 'url' => 'https://gdpr.example.com', 'category' => 'Technologie', 'description' => 'Testseite für Datenschutz-Tests, mindestens zwanzig Zeichen.', 'monthly_visitors' => '100']);
$gSite = (int) val('SELECT id FROM sites WHERE user_id = ?', [$idOf('gp')]);
$gp->get('slot.form', ['site_id' => $gSite])->post('slot.form', ['name' => 'GDPR Slot', 'format' => '300x250', 'placement' => 'Test', 'description' => 'Werbeplatz für Datenschutz-Tests.', 'price_per_day' => '5.00', 'min_days' => '1', 'max_days' => '30', 'available_from' => $today, 'available_until' => ''], ['site_id' => $gSite]);
$gSlot = (int) val('SELECT id FROM ad_slots WHERE site_id = ?', [$gSite]);
[$gCamp, $gFile] = $campaign($ga, 'GDPR Kampagne', 'g1');
$approve('sites', $gSite); $approve('slots', $gSlot); $approve('campaigns', $gCamp);
$ga->get('slot', ['id' => $gSlot])->post('booking.request', ['slot_id' => $gSlot, 'campaign_id' => $gCamp, 'start_date' => $plus(1), 'end_date' => $plus(3), 'message' => 'Persönliche Nachricht an den Betreiber']);
check((int) val('SELECT COUNT(*) FROM bookings WHERE advertiser_id = ?', [$idOf('ga')]) === 1, 'Testdaten: Buchungsanfrage angelegt');

$ga->get('account')->post('account.export', ['current_password' => 'falsch']);
check($ga->has('Passwort ist falsch') && !str_starts_with(ltrim($ga->body), '{'), 'Export verlangt das Passwort');
$ga->get('account')->post('account.export', ['current_password' => 'GdprAdvertiser1']);
$j = json_decode($ga->body, true);
check(is_array($j) && ($j['profile']['email'] ?? '') === "ga$uniq@example.com" && count($j['campaigns']) === 1 && count($j['bookings']) === 1 && ($j['bookings'][0]['your_role'] ?? '') === 'advertiser',
    'Datenexport (Art. 15/20) enthält Profil, Kampagnen und Buchungen als JSON');
check(!str_contains($ga->body, 'password_hash') && !str_contains($ga->body, "gp$uniq@example.com"), 'Export enthält weder Passwort-Hash noch Daten des Vertragspartners');
$gp->get('account')->post('account.export', ['current_password' => 'GdprPublisher1']);
$jp = json_decode($gp->body, true);
check(is_array($jp) && count($jp['sites']) === 1 && count($jp['ad_slots']) === 1 && ($jp['bookings'][0]['your_role'] ?? '') === 'publisher' && !str_contains($gp->body, "ga$uniq@example.com"), 'Export des Betreibers (Webseiten, Werbeplätze, Buchungen) ohne Fremddaten');
$anon->post('account.export', ['current_password' => 'x']);
check(($anon->code === 419 || str_contains($anon->url, 'p=login')) && !str_starts_with(ltrim($anon->body), '{'), 'Export nur für angemeldete Benutzer (anonym: kein Datenzugriff)');

$ga->get('account')->post('account.delete', ['current_password' => 'falsch', 'confirm' => '1']);
check($ga->has('Passwort ist falsch') && $idOf('ga') > 0, 'Löschung mit falschem Passwort verweigert');
$ga->get('account')->post('account.delete', ['current_password' => 'GdprAdvertiser1']);
check($ga->has('bestätigen') && $idOf('ga') > 0, 'Löschung ohne Bestätigung verweigert');
$gaId = $idOf('ga');
$ga->get('account')->post('account.delete', ['current_password' => 'GdprAdvertiser1', 'confirm' => '1']);
check($ga->has('gelöscht') && $idOf('ga') === 0, 'Konto ohne Abrechnungsdaten wird vollständig gelöscht (Art. 17)');
check((int) val('SELECT COUNT(*) FROM campaigns WHERE id = ?', [$gCamp]) === 0 && !is_file(APP_ROOT . '/storage/banners/' . $gFile) && (int) val('SELECT COUNT(*) FROM bookings WHERE campaign_id = ?', [$gCamp]) === 0,
    'Kampagne, Banner-Datei und offene Anfrage sind entfernt');
check((int) val('SELECT COUNT(*) FROM audit_log WHERE user_id = ?', [$gaId]) === 0 && (int) val("SELECT COUNT(*) FROM audit_log WHERE action = 'user.deleted' AND entity_id = ? AND user_id IS NULL", [$gaId]) === 1,
    'Protokolleinträge sind vom Konto gelöst, Löschung ist nachweisbar protokolliert');
$ga->get('dashboard');
check(str_contains($ga->url, 'p=login'), 'Sitzung nach Löschung beendet');
$re = new Client($base, 're');
$re->get('login')->post('login', ['email' => "ga$uniq@example.com", 'password' => 'GdprAdvertiser1']);
check($re->has('falsch') && !$re->has('Dashboard'), 'Anmeldung mit gelöschtem Konto nicht möglich');
$gp->get('bookings');
check(!$gp->has('GDPR Kampagne'), 'Betreiber sieht die gelöschte Anfrage nicht mehr');

// --- Konto mit Abrechnungsdaten: Sperre, Fristen, Anonymisierung
$gb = $mk('gb', 'advertiser', 'GdprAdvertiser2');
[$gCamp2, $gFile2] = $campaign($gb, 'GDPR Kampagne 2', 'g2');
$approve('campaigns', $gCamp2);
$gb->get('slot', ['id' => $gSlot])->post('booking.request', ['slot_id' => $gSlot, 'campaign_id' => $gCamp2, 'start_date' => $plus(1), 'end_date' => $plus(2), 'message' => 'Vertrauliche Nachricht']);
$gbBooking = (int) val('SELECT id FROM bookings WHERE campaign_id = ?', [$gCamp2]);
$gp->get('booking', ['id' => $gbBooking])->post('booking.accept', ['id' => $gbBooking]);
$admin->get('booking', ['id' => $gbBooking])->post('admin.payment', ['id' => $gbBooking, 'method' => 'bank_transfer', 'received_on' => $today]);
check(val('SELECT status FROM bookings WHERE id = ?', [$gbBooking]) === 'paid', 'Testdaten: Buchung bezahlt');
$gb->get('account');
check($gb->has('bezahlte, noch nicht abgeschlossene') && !$gb->has('name="confirm"'), 'Löschung bei laufender bezahlter Buchung wird mit Begründung blockiert');
$gb->post('account.delete', ['current_password' => 'GdprAdvertiser2', 'confirm' => '1']);
check($idOf('gb') > 0 && val('SELECT status FROM users WHERE id = ?', [$idOf('gb')]) === 'active', 'Direkter Löschversuch bei bezahlter Buchung scheitert');
q('UPDATE bookings SET start_date = ?, end_date = ? WHERE id = ?', [$plus(-4), $plus(-2), $gbBooking]);
Booking::housekeeping(true);
check(val('SELECT status FROM bookings WHERE id = ?', [$gbBooking]) === 'completed', 'Testdaten: Buchung abgeschlossen');
$gp->get('account');
check($gp->has('auszahlbares Guthaben') && !$gp->has('name="confirm"'), 'Betreiber mit offenem Guthaben kann nicht löschen');

$gbId = $idOf('gb');
$ledgerBefore = (int) val('SELECT COUNT(*) FROM ledger WHERE booking_id = ?', [$gbBooking]);
$gb->get('account')->post('account.delete', ['current_password' => 'GdprAdvertiser2', 'confirm' => '1']);
$gu = one('SELECT * FROM users WHERE id = ?', [$gbId]);
check($gu && $gu['status'] === 'deleted' && $gu['email'] === "deleted-$gbId@deleted.invalid" && $gu['payout_details'] === null && $gu['last_login_at'] === null, 'Konto mit Abrechnungsdaten: gesperrt, E-Mail und Kennungen entfernt');
check($gu && $gu['anonymize_after'] === (date('Y') + Privacy::retentionYears()) . '-12-31', 'Aufbewahrungsfrist = Ende Geschäftsjahr + ' . Privacy::retentionYears() . ' Jahre');
check($gu && $gu['name'] === 'G gb' && (int) val('SELECT COUNT(*) FROM payments WHERE booking_id = ?', [$gbBooking]) === 1 && (int) val('SELECT COUNT(*) FROM ledger WHERE booking_id = ?', [$gbBooking]) === $ledgerBefore && $ledgerBefore === 3,
    'Belege, Zahlung und Journal bleiben für die Aufbewahrung erhalten');
check(val('SELECT message FROM bookings WHERE id = ?', [$gbBooking]) === '', 'Freitext-Nachricht der Buchung wurde gelöscht');
$re->get('login')->post('login', ['email' => "gb$uniq@example.com", 'password' => 'GdprAdvertiser2']);
$re->post('login', ['email' => "deleted-$gbId@deleted.invalid", 'password' => 'GdprAdvertiser2']);
check(!$re->has('Dashboard'), 'Gesperrtes Konto kann sich nicht anmelden');
$admin->get('admin.users', ['q' => 'deleted-']);
check($admin->has('Gelöscht') && $admin->has('Anonymisierung ab') && $admin->has('Datenexport'), 'Admin sieht gelöschtes Konto mit Frist und Exportfunktion');
$admin->post('admin.user', ['id' => $gbId, 'action' => 'export']);
$je = json_decode($admin->body, true);
check(is_array($je) && ($je['profile']['status'] ?? '') === 'deleted' && count($je['payments']) === 1, 'Admin kann Auskunft (Export) auch für gesperrte Konten erstellen');
$admin->get('admin.users')->post('admin.user', ['id' => $gbId, 'action' => 'unblock']);
check(val('SELECT status FROM users WHERE id = ?', [$gbId]) === 'deleted', 'Gelöschtes Konto kann nicht wieder entsperrt werden');

$gpId = $idOf('gp');
$avail = Billing::publisherBalance($gpId)['available'];
check($avail > 0, 'Testdaten: Betreiber hat auszahlbares Guthaben');
Billing::recordPayout($adminId, $gpId, $avail, 'bank_transfer', $today, 'DSGVO-Test');
$gp->get('account')->post('account.delete', ['current_password' => 'GdprPublisher1', 'confirm' => '1']);
check(val('SELECT status FROM users WHERE id = ?', [$gpId]) === 'deleted' && val('SELECT status FROM sites WHERE id = ?', [$gSite]) === 'paused' && val('SELECT status FROM ad_slots WHERE id = ?', [$gSlot]) === 'paused',
    'Betreiber nach Auszahlung: Konto gesperrt, Webseite und Werbeplatz deaktiviert');

q("UPDATE users SET anonymize_after = '2000-01-01' WHERE id IN (?, ?)", [$gbId, $gpId]);
$ledgerTotal = (int) val('SELECT COUNT(*) FROM ledger');
Privacy::housekeeping(true);
$ua = one('SELECT * FROM users WHERE id = ?', [$gbId]);
check($ua['name'] === "Gelöschter Benutzer #$gbId" && $ua['company'] === null && $ua['consent_at'] === null && $ua['anonymize_after'] === null, 'Nach Fristablauf wird die Identität anonymisiert');
check(val('SELECT name FROM campaigns WHERE id = ?', [$gCamp2]) === "Kampagne #$gCamp2" && !is_file(APP_ROOT . '/storage/banners/' . $gFile2) && val('SELECT url FROM sites WHERE id = ?', [$gSite]) === 'https://deleted.invalid/',
    'Kampagnen-, Webseitendaten und Banner-Datei sind anonymisiert bzw. gelöscht');
check((int) val('SELECT COUNT(*) FROM ledger') === $ledgerTotal && (int) val('SELECT COUNT(*) FROM payments WHERE booking_id = ?', [$gbBooking]) === 1, 'Finanzdaten bleiben unverändert (nur ohne Personenbezug)');

// --- Löschung durch Administrator
$gd = $mk('gd', 'advertiser', 'GdprAdvertiser3');
[$gCamp3, $gFile3] = $campaign($gd, 'GDPR Kampagne 3', 'g3');
$gdId = $idOf('gd');
$admin->get('admin.users')->post('admin.user', ['id' => $gdId, 'action' => 'delete']);
check($idOf('gd') === 0 && !is_file(APP_ROOT . '/storage/banners/' . $gFile3), 'Administrator kann Löschersuchen umsetzen');
check(Privacy::blockers(['id' => $adminId, 'role' => 'admin']) !== [], 'Administrator-Konten sind von der Löschfunktion ausgenommen');
$admin->post('admin.user', ['id' => $adminId, 'action' => 'delete']);
check((int) val('SELECT COUNT(*) FROM users WHERE id = ?', [$adminId]) === 1, 'Administrator kann sich nicht selbst über die Benutzerverwaltung löschen');

// --- Speicherbegrenzung
q("INSERT INTO audit_log (user_id, action, entity, ip, created_at) VALUES (NULL, 'test.old', 'test', '203.0.113.9', NOW() - INTERVAL 40 DAY), (NULL, 'test.new', 'test', '203.0.113.10', NOW())");
q("INSERT INTO login_attempts (ip, email, attempted_at) VALUES ('203.0.113.9', 'old@example.com', NOW() - INTERVAL 3 DAY)");
Privacy::housekeeping(true);
check(val("SELECT ip FROM audit_log WHERE action = 'test.old'") === '' && val("SELECT ip FROM audit_log WHERE action = 'test.new'") === '203.0.113.10', 'IP-Adressen im Protokoll werden nach der Frist gelöscht, jüngere bleiben');
check((int) val("SELECT COUNT(*) FROM login_attempts WHERE email = 'old@example.com'") === 0, 'Alte Login-Versuche werden entfernt');
Installer::migrate(db());
check((int) val("SELECT value FROM settings WHERE name = 'schema_version'") === Installer::SCHEMA_VERSION, 'Schema-Migration ist idempotent');

echo "\n== Doppelrolle (Werbetreibender & Webseitenbetreiber) & zentrale Zahlung ==\n";
$d1 = $mk('d1', 'both', 'DualUserPass1');
check(val('SELECT role FROM users WHERE id = ?', [$idOf('d1')]) === 'both', 'Registrierung als „Beides“ möglich');
$d1->get('sites'); check($d1->code === 200, 'Doppelrolle: Webseitenbereich erreichbar');
$d1->get('campaigns'); check($d1->code === 200, 'Doppelrolle: Kampagnenbereich erreichbar');
$d1->get('earnings'); check($d1->code === 200, 'Doppelrolle: Einnahmen erreichbar');
$d1->get('dashboard'); check($d1->code === 200 && $d1->clean(), 'Doppelrolle: Dashboard ohne Fehler');
$d1->get('admin.settings'); check($d1->code === 403, 'Doppelrolle: kein Admin-Zugriff');
// Partner (reiner Betreiber) und Doppelrolle als Werbetreibender
$d1->get('site.form')->post('site.form', ['name' => 'Dual Site', 'url' => 'https://dual.example.com', 'category' => 'Technologie', 'description' => 'Testseite der Doppelrolle, mindestens zwanzig Zeichen.', 'monthly_visitors' => '10']);
$dSite = (int) val('SELECT id FROM sites WHERE user_id = ?', [$idOf('d1')]);
$d1->get('slot.form', ['site_id' => $dSite])->post('slot.form', ['name' => 'Dual Slot', 'format' => '300x250', 'placement' => 'Test', 'description' => 'Werbeplatz der Doppelrolle.', 'price_per_day' => '8.00', 'min_days' => '1', 'max_days' => '30', 'available_from' => $today, 'available_until' => ''], ['site_id' => $dSite]);
$dSlot = (int) val('SELECT id FROM ad_slots WHERE site_id = ?', [$dSite]);
[$dCamp, ] = $campaign($d1, 'Dual Kampagne', 'd1');
$approve('sites', $dSite); $approve('slots', $dSlot); $approve('campaigns', $dCamp);
// eigener Werbeplatz darf nicht selbst gebucht werden
$d1->get('slot', ['id' => $dSlot])->post('booking.request', ['slot_id' => $dSlot, 'campaign_id' => $dCamp, 'start_date' => $plus(1), 'end_date' => $plus(2)]);
check((int) val('SELECT COUNT(*) FROM bookings WHERE slot_id = ?', [$dSlot]) === 0, 'Eigenen Werbeplatz buchen wird verhindert');
// Doppelrolle bucht fremden Platz und bietet selbst an
$d1->get('slot', ['id' => $slotId])->post('booking.request', ['slot_id' => $slotId, 'campaign_id' => $dCamp, 'start_date' => $plus(50), 'end_date' => $plus(51)]);
$dOut = (int) val('SELECT id FROM bookings WHERE campaign_id = ?', [$dCamp]);
check($dOut > 0, 'Doppelrolle kann fremden Werbeplatz buchen');
$da = $mk('da', 'advertiser', 'DualAdvPass123');
[$daCamp, ] = $campaign($da, 'Dual Fremdkampagne', 'da'); $approve('campaigns', $daCamp);
$da->get('slot', ['id' => $dSlot])->post('booking.request', ['slot_id' => $dSlot, 'campaign_id' => $daCamp, 'start_date' => $plus(5), 'end_date' => $plus(6)]);
$dIn = (int) val('SELECT id FROM bookings WHERE slot_id = ?', [$dSlot]);
check($dIn > 0, 'Fremder Werbetreibender bucht Platz der Doppelrolle');
$d1->get('bookings');
check($d1->has('als Betreiber') && $d1->has('als Werbetreibender'), 'Buchungsliste zeigt beide Rollen');
$d1->get('bookings', ['as' => 'publisher']); check($d1->has('Dual Slot') && !$d1->has('Sidebar oben'), 'Filter „als Betreiber“ zeigt nur eingehende Buchungen');
$d1->get('booking', ['id' => $dIn])->post('booking.accept', ['id' => $dIn]);
check(val('SELECT status FROM bookings WHERE id = ?', [$dIn]) === 'accepted', 'Doppelrolle nimmt eingehende Buchung als Betreiber an');
$d1->get('booking', ['id' => $dOut])->post('booking.accept', ['id' => $dOut]);
check(val('SELECT status FROM bookings WHERE id = ?', [$dOut]) === 'requested', 'Doppelrolle kann ausgehende Buchung nicht selbst annehmen');
$d1->get('booking', ['id' => $dIn]);
check($d1->has('zentral') , 'Betreiber sieht Hinweis auf zentrale Abrechnung');
$da->get('booking', ['id' => $dIn]);
check($da->has('nicht direkt an den Betreiber'), 'Werbetreibender sieht Hinweis: Zahlung nur an die Plattform');
// Zahlung zentral beim Admin, Auszahlung durch Admin an Doppelrolle
$admin->get('booking', ['id' => $dIn])->post('admin.payment', ['id' => $dIn, 'method' => 'bank_transfer', 'received_on' => $today]);
check(val('SELECT status FROM bookings WHERE id = ?', [$dIn]) === 'paid', 'Zahlung wird zentral vom Admin bestätigt');
$d1->post('booking.report_payment', ['id' => $dIn]);
check(val('SELECT status FROM bookings WHERE id = ?', [$dIn]) === 'paid', 'Betreiber kann Zahlungsstatus nicht selbst setzen');
q('UPDATE bookings SET start_date = ?, end_date = ? WHERE id = ?', [$plus(-3), $plus(-2), $dIn]);
Booking::housekeeping(true);
check(val('SELECT status FROM bookings WHERE id = ?', [$dIn]) === 'completed', 'Eingehende Buchung der Doppelrolle abgeschlossen');
$admin->get('admin.payouts');
check($admin->has('Firma d1'), 'Doppelrolle erscheint in der Auszahlungsliste');
q("UPDATE settings SET value = '0' WHERE name = 'min_payout'");
$admin->get('admin.payouts')->post('admin.payouts', ['publisher_id' => $idOf('d1'), 'amount' => '1.00', 'method' => 'bank_transfer', 'paid_on' => $today]);
check((int) val("SELECT COUNT(*) FROM payouts WHERE publisher_id = ?", [$idOf('d1')]) === 1, 'Auszahlung an Doppelrolle zentral durch Admin erfasst');
check((int) val("SELECT COUNT(*) FROM users WHERE role='both' AND id = ?", [$idOf('d1')]) === 1, 'Rolle bleibt „both“');

// Rolle nachträglich ergänzen
$e1 = $mk('e1', 'advertiser', 'UpgradePass123');
$e1->get('sites'); check($e1->code === 403, 'Reiner Werbetreibender: Webseitenbereich gesperrt');
$e1->get('account')->post('account', ['action' => 'add_role', 'role' => 'publisher']);
check(val('SELECT role FROM users WHERE id = ?', [$idOf('e1')]) === 'both', 'Zweite Rolle über Konto hinzugefügt');
$e1->get('sites'); check($e1->code === 200, 'Nach Upgrade: Webseitenbereich erreichbar');
$e1->post('account', ['action' => 'add_role', 'role' => 'admin']);
check(val('SELECT role FROM users WHERE id = ?', [$idOf('e1')]) === 'both', 'Rolle „admin“ nicht über Konto setzbar');
$e1->post('account', ['action' => 'profile', 'name' => 'E1', 'company' => '', 'role' => 'admin']);
check(val('SELECT role FROM users WHERE id = ?', [$idOf('e1')]) === 'both', 'Rolle per Profilformular nicht manipulierbar');

echo "\n== Lizenz & Berechtigungs-Scopes ==\n";
$LK = 'VA-TEST-0001-AAAA';
$flFile = sys_get_temp_dir() . '/fakelic.json';
$fl = function (?string $key, array $props, array $global = []) use ($flFile): void {
    $s = json_decode((string) file_get_contents($flFile), true);
    if ($key !== null) $s['keys'][$key] = array_merge($s['keys'][$key] ?? ['plan' => 'Pro', 'features' => ['*'], 'maxDevices' => 2, 'expiresAt' => null], $props);
    foreach ($global as $k => $v) { if ($v === null) unset($s[$k]); else $s[$k] = $v; }
    file_put_contents($flFile, json_encode($s));
};
$lcheck = function () use ($admin): void { $admin->get('admin.licence')->post('admin.licence', ['action' => 'check']); };
$lstate = fn () => json_decode((string) val("SELECT value FROM settings WHERE name = 'licence_state'"), true) ?: [];
$sOnly = new Client($base, 'lsite'); // reiner Betreiber (pub) für Zugriffstests
$admin->get('admin.licence');
check($admin->code === 200 && $admin->has('Lizenz gültig') && $admin->has('sites.manage') && $admin->has('dual_role'), 'Admin-Lizenzseite zeigt Status und Scopes');
check(!empty($lstate()['offline']['payload']) && in_array('activate', json_decode((string) file_get_contents($flFile), true)['log'] ?? [], true), 'Gerät wurde automatisch aktiviert und Offline-Lizenz abgelegt');
$adv->get('admin.licence'); check($adv->code === 403, 'Lizenzseite nur für Administratoren');
$anon->get('admin.licence'); check(str_contains($anon->url, 'p=login'), 'Lizenzseite ohne Login → Anmeldung');
$admin->get('admin.licence')->post('admin.licence', ['action' => 'save', 'licence_key' => 'x y;<']);
check($admin->has('gültigen Lizenzschlüssel'), 'Ungültiges Schlüsselformat wird abgelehnt');

// --- Scopes: Tarif nur mit market.browse + sites.manage + bookings.*
$fl($LK, ['features' => ['market.browse', 'sites.manage', 'bookings.*']]); $lcheck();
$pub->get('sites'); check($pub->code === 200, 'Scope sites.manage → Webseitenbereich erreichbar');
$adv->get('campaigns'); check($adv->code === 403 && $adv->has('nicht enthalten'), 'Ohne Scope campaigns.manage → 403 mit Hinweis');
$admin->get('admin.reviews'); check($admin->code === 403 && $admin->has('admin.review'), 'Ohne Scope admin.review → Admin-Freigaben gesperrt (Scope nur für Admin sichtbar)');
$adv->get('home'); check($adv->code === 200, 'Scope market.browse → Marktplatz erreichbar');
$pub->get('site.form')->post('site.form', ['name' => 'Zweite Seite', 'url' => 'https://zwei.example.com', 'category' => 'Technologie', 'description' => 'Eine zweite Webseite, mindestens zwanzig Zeichen lang.', 'monthly_visitors' => '1']);
check($pub->has('Scope multi_site') && (int) val('SELECT COUNT(*) FROM sites WHERE user_id = (SELECT id FROM users WHERE email = ?)', ["pub$uniq@example.com"]) === 1, 'Ohne Scope multi_site nur eine Webseite pro Betreiber');
$anon->get('register')->post('register', ['name' => 'Dual X', 'email' => "dx$uniq@example.com", 'role' => 'both', 'password' => 'DualXPassword1', 'password2' => 'DualXPassword1', 'terms' => '1']);
check((int) val('SELECT COUNT(*) FROM users WHERE email = ?', ["dx$uniq@example.com"]) === 0, 'Ohne Scope dual_role keine Registrierung als „Beides“');
$admin->get('admin.finance', ['format' => 'csv']); check($admin->code === 403, 'Finanzjournal (stats.view) ohne Scope gesperrt');
$admin->get('admin.licence'); check($admin->has('bx-lock-alt'), 'Lizenzseite markiert gesperrte Scopes');

// --- Wildcard * und Gruppen
$fl($LK, ['features' => ['*']]); $lcheck();
$adv->get('campaigns'); check($adv->code === 200, 'Wildcard * schaltet alle Scopes frei');

// --- Gesperrte Lizenz → Lesemodus
$fl($LK, ['status' => 'blocked']); $lcheck();
$admin->get('admin.licence'); check($admin->has('Keine gültige Lizenz') && $admin->has('gesperrt'), 'Gesperrte Lizenz wird erkannt');
$adv->get('home'); check($adv->code === 200, 'Lesemodus: Marktplatz bleibt sichtbar');
$adv->get('campaigns'); check($adv->code === 403 && $adv->has('Lesemodus'), 'Lesemodus: Kampagnen gesperrt');
$adv->get('slot', ['id' => $slotId])->post('booking.request', ['slot_id' => $slotId, 'campaign_id' => $campId, 'start_date' => $plus(60), 'end_date' => $plus(61)]);
check($adv->code === 403 && (int) val('SELECT COUNT(*) FROM bookings WHERE start_date = ?', [$plus(60)]) === 0, 'Lesemodus: Buchungsanfrage wird abgelehnt');
$pub->get('sites'); check($pub->code === 403, 'Lesemodus: Webseitenverwaltung gesperrt');
$admin->get('admin.users'); check($admin->code === 403, 'Lesemodus: Benutzerverwaltung gesperrt');
$admin->get('admin.payouts'); check($admin->code === 403, 'Lesemodus: Auszahlungen gesperrt');
$admin->get('admin.finance'); check($admin->code === 200, 'Lesemodus: Finanzansicht (nur lesend) erreichbar');
$admin->get('admin.audit'); check($admin->code === 200, 'Lesemodus: Audit-Log erreichbar');
$admin->get('admin.settings'); check($admin->code === 200, 'Lesemodus: Einstellungen erreichbar');
$admin->get('dashboard'); check($admin->code === 200 && $admin->has('Lizenz'), 'Lesemodus: Dashboard mit Lizenzwarnung');
$adv->get('account'); check($adv->code === 200, 'Lesemodus: Konto/Datenauskunft erreichbar');
$adv->get('bookings'); check($adv->code === 200, 'Lesemodus: eigene Buchungen einsehbar');

// --- Entsperren, Wiedereinspielung und Signatur
$fl($LK, ['status' => 'active'], ['skew' => 1000]); $lcheck();
$admin->get('admin.licence'); check($admin->has('Keine gültige Lizenz') && $admin->has('veraltet'), 'Veraltete Serverantwort (Zeitstempel) wird verworfen');
$fl($LK, [], ['skew' => null, 'badsig' => true]); $lcheck();
$admin->get('admin.licence'); check($admin->has('Keine gültige Lizenz') && $admin->has('Signatur'), 'Gefälschte Signatur wird verworfen');
$fl($LK, [], ['badsig' => null]); $lcheck();
$adv->get('campaigns'); check($adv->code === 200, 'Nach Entsperrung wieder voll nutzbar');

// --- Manipulation der gespeicherten Antwort in der Datenbank
$fl(null, [], ['down' => true]);
$s = $lstate(); $orig = $s;
$s['online']['payload'] = str_replace('"valid":true', '"valid":true ', $s['online']['payload']); // Signatur passt nicht mehr
$s['offline'] = null;
q("UPDATE settings SET value = ? WHERE name = 'licence_state'", [json_encode($s)]);
$adv->get('campaigns'); check($adv->code === 403, 'Manipulierte gespeicherte Lizenzantwort wird nicht akzeptiert');

// --- Server nicht erreichbar: Offline-Lizenz trägt
$fl(null, [], ['down' => null]); $lcheck();
$fl(null, [], ['down' => true]);
$s = $lstate(); $s['online']['fetched_at'] = time() - 7200; $s['next_try'] = time() + 1000; q("UPDATE settings SET value = ? WHERE name = 'licence_state'", [json_encode($s)]);
$adv->get('campaigns'); check($adv->code === 200, 'Server offline, Antwort veraltet → signierte Offline-Lizenz hält die Anwendung am Laufen');
$admin->get('admin.licence'); check($admin->has('Offline-Lizenz gültig'), 'Admin sieht Offline-Modus');
// Offline-Lizenz abgelaufen
$fl(null, [], ['down' => null, 'offline_ttl' => 2]);
$s = $lstate(); unset($s['offline']); q("UPDATE settings SET value = ? WHERE name = 'licence_state'", [json_encode($s)]); $lcheck();
check(!empty($lstate()['offline']), 'Offline-Lizenz neu geholt (kurze Laufzeit für den Test)');
$fl(null, [], ['down' => true]);
$s = $lstate(); $s['online']['fetched_at'] = time() - 7200; $s['next_try'] = time() + 1000; q("UPDATE settings SET value = ? WHERE name = 'licence_state'", [json_encode($s)]);
sleep(3);
$adv->get('campaigns'); check($adv->code === 403, 'Offline-Lizenz abgelaufen und Server weg → Lesemodus');

// --- Sonstige Codes
$fl(null, [], ['down' => null, 'offline_ttl' => null]);
$fl('VA-OTHER-PRODUCT', ['product' => 'anderes-produkt']);
$admin->get('admin.licence')->post('admin.licence', ['action' => 'save', 'licence_key' => 'VA-OTHER-PRODUCT']);
check($admin->has('anderen Produkt'), 'Schlüssel eines anderen Produkts wird abgelehnt');
$admin->get('admin.licence')->post('admin.licence', ['action' => 'save', 'licence_key' => 'VA-UNKNOWN-0000']);
check($admin->has('unbekannt'), 'Unbekannter Schlüssel wird abgelehnt');
$fl('VA-EXPIRED-0001', ['expiresAt' => gmdate('c', time() - 86400)]);
$admin->get('admin.licence')->post('admin.licence', ['action' => 'save', 'licence_key' => 'VA-EXPIRED-0001']);
check($admin->has('abgelaufen'), 'Abgelaufene Lizenz wird abgelehnt');
$fl('VA-FULL-0000-0001', ['maxDevices' => 1, 'devices' => ['anderes-geraet']]);
$admin->get('admin.licence')->post('admin.licence', ['action' => 'save', 'licence_key' => 'VA-FULL-0000-0001']);
check($admin->has('Geräteplätze'), 'Gerätelimit erreicht wird gemeldet');
$admin->get('admin.licence')->post('admin.licence', ['action' => 'remove']);
check(val("SELECT value FROM settings WHERE name = 'licence_key'") === '', 'Schlüssel entfernt');
$adv->get('campaigns'); check($adv->code === 403, 'Ohne Schlüssel → Lesemodus');

// --- Ursprünglichen Zustand wiederherstellen
$admin->get('admin.licence')->post('admin.licence', ['action' => 'save', 'licence_key' => $LK]);
check($admin->has('Lizenz geprüft') , 'Gültigen Schlüssel erneut eintragen → aktiviert');
$adv->get('campaigns'); check($adv->code === 200, 'Anwendung wieder voll nutzbar');
$admin->get('admin.licence')->post('admin.licence', ['action' => 'deactivate']);
check(!in_array(licence()->hwid(), json_decode((string) file_get_contents($flFile), true)['keys'][$LK]['devices'] ?? [], true), 'Gerät abmelden gibt den Geräteplatz frei');
$adv->get('home'); $adv->get('home');
check(!in_array(licence()->hwid(), json_decode((string) file_get_contents($flFile), true)['keys'][$LK]['devices'] ?? [], true), 'Abgemeldete Installation aktiviert sich nicht von selbst wieder');
$admin->get('admin.licence')->post('admin.licence', ['action' => 'check']);
check(in_array(licence()->hwid(), json_decode((string) file_get_contents($flFile), true)['keys'][$LK]['devices'] ?? [], true), 'Erneute Prüfung aktiviert das Gerät wieder');
$adv->get('campaigns'); check($adv->code === 200, 'Nach Wiederaktivierung voll nutzbar');

echo "\n== Updates aus GitHub-Releases ==\n";
$ghFile = sys_get_temp_dir() . '/fakegh.json';
$gh = function (array $s) use ($ghFile): void { file_put_contents($ghFile, json_encode($s)); };
$treeHash = function (): string {
    $root = APP_ROOT; $h = [];
    foreach (['app', 'bin', 'database', 'docs', 'resources', 'tests', 'public'] as $d) {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator("$root/$d", FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) if ($f->isFile()) $h[substr($f->getPathname(), strlen($root))] = md5_file($f->getPathname());
    }
    foreach (['README.md', 'config/config.example.php'] as $f) $h[$f] = md5_file("$root/$f");
    ksort($h);
    return md5(json_encode($h));
};
/** Baut ein Release-ZIP aus dem aktuellen Projektstand; $mut darf Dateien im Zielordner ändern. */
$release = function (string $version, ?callable $mut = null) use ($tmp): string {
    $src = APP_ROOT; $dir = "$tmp/rel-" . bin2hex(random_bytes(3)); $top = "$dir/acme-vylaris-advertise-abc123";
    foreach (['app', 'bin', 'database', 'docs', 'resources', 'tests', 'public'] as $d) {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator("$src/$d", FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) if ($f->isFile()) { $rel = substr($f->getPathname(), strlen($src) + 1); @mkdir(dirname("$top/$rel"), 0777, true); copy($f->getPathname(), "$top/$rel"); }
    }
    foreach (['README.md', 'config/config.example.php'] as $f) { @mkdir(dirname("$top/$f"), 0777, true); copy("$src/$f", "$top/$f"); }
    file_put_contents("$top/app/bootstrap.php", preg_replace("/define\\('APP_VERSION', '[0-9.]+'\\)/", "define('APP_VERSION', '$version')", file_get_contents("$top/app/bootstrap.php")));
    file_put_contents("$top/app/UPDATE_MARKER.txt", "marker $version");
    @unlink("$top/docs/DSGVO.md");
    if ($mut) $mut($top);
    $zipPath = "$dir.zip"; $z = new ZipArchive(); $z->open($zipPath, ZipArchive::CREATE);
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($top, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) if ($f->isFile()) $z->addFile($f->getPathname(), substr($f->getPathname(), strlen($dir) + 1));
    $z->close();
    return $zipPath;
};
$upd = fn () => $admin->get('admin.update');
$cur = APP_VERSION; $hash0 = $treeHash(); $cfgHash0 = md5_file(APP_ROOT . '/config/config.php');
$tok = 'github_pat_' . str_repeat('A1b2', 8); // steht in config.php (Testumgebung)

$admin->get('admin.update'); check($admin->code === 200 && $admin->has('Update-Quelle') && $admin->has('VylarisGroup/vylaris-ads'), 'Update-Seite zeigt die feste Update-Quelle');
check(!$admin->has('name="repo"') && !$admin->has('name="token"'), 'Update-Seite hat keine Eingabefelder für Repository oder Token');
$adv->get('admin.update'); check($adv->code === 403, 'Update-Seite nur für Administratoren');
$admin->post('admin.update', ['action' => 'save', 'repo' => 'evil/repo', 'token' => $tok, 'auto' => '1']);
check(Updater::repo() === 'VylarisGroup/vylaris-ads' && (string) val("SELECT COUNT(*) FROM settings WHERE name IN ('update_repo', 'update_token')") === '0', 'Per POST mitgeschickte Quelle/Token werden ignoriert, nichts gespeichert');

// Prüfung: Token nötig, Version vergleichen
$gh(['tag' => 'v9.9.9', 'token' => 'falsches-token', 'zip' => '']);
$admin->post('admin.update', ['action' => 'check']); check($admin->has('nicht gefunden') || $admin->has('verweigert'), 'Falsches/fehlendes Token → verständliche Fehlermeldung');
$gh(['tag' => "v$cur", 'token' => $tok, 'zip' => '']);
$admin->post('admin.update', ['action' => 'check']); check($admin->has('neueste Version') && !$admin->has('Jetzt aktualisieren'), 'Gleiche Version → kein Update angeboten');
$gh(['tag' => 'nightly-build', 'token' => $tok]);
$admin->post('admin.update', ['action' => 'check']); check($admin->has('gültiges Versions-Tag'), 'Release ohne Versions-Tag wird abgelehnt');

// --- Manipulierte Releases werden vor dem Austausch abgelehnt
$bad = [
    'Pfad ausserhalb (Zip-Slip)' => [$release('9.9.9', function ($t) { file_put_contents("$t/app/x.txt", 'x'); }), 'unzulässige Pfade', true],
    'PHP-Syntaxfehler' => [$release('9.9.9', function ($t) { file_put_contents("$t/app/lib/Broken.php", "<?php\nfunction {{{ ;"); }), 'Syntaxfehler', false],
    'Version passt nicht zum Tag' => [$release('9.9.7'), 'passt nicht zum Release-Tag', false],
    'Keine Projektstruktur' => [null, 'keine VylarisAdvertise-Struktur', false],
];
// Zip-Slip: Eintrag mit ".." manuell einfügen
$z = new ZipArchive(); $z->open($bad['Pfad ausserhalb (Zip-Slip)'][0]); $z->addFromString('acme-vylaris-advertise-abc123/../../evil-' . $uniq . '.txt', 'pwned'); $z->close();
$z = new ZipArchive(); $nz = "$tmp/nostruct.zip"; $z->open($nz, ZipArchive::CREATE); $z->addFromString('foo/readme.txt', 'x'); $z->close(); $bad['Keine Projektstruktur'][0] = $nz;
foreach ($bad as $label => [$zipPath, $needle]) {
    $gh(['tag' => $label === 'Version passt nicht zum Tag' ? 'v9.9.9' : 'v9.9.9', 'token' => $tok, 'zip' => $zipPath]);
    $admin->post('admin.update', ['action' => 'check']);
    $admin->post('admin.update', ['action' => 'install']);
    check($admin->has($needle) && $treeHash() === $hash0 && !is_file(APP_ROOT . '/storage/maintenance.flag'), "Manipuliertes Release abgelehnt, Dateien unverändert: $label");
}
check(!is_file(dirname(APP_ROOT) . "/evil-$uniq.txt") && !is_file(sys_get_temp_dir() . "/evil-$uniq.txt"), 'Zip-Slip schreibt keine Datei ausserhalb');
check(glob(APP_ROOT . '/storage/update-*') === [], 'Temporäre Update-Dateien werden aufgeräumt');

// --- Erfolgreiches Update
$good = $release('9.9.9');
$gh(['tag' => 'v9.9.9', 'token' => $tok, 'zip' => $good, 'body' => "Neu: Testrelease\n<script>alert(1)</script>"]);
$admin->post('admin.update', ['action' => 'check']);
check($admin->has('Update verfügbar: 9.9.9') && $admin->has('Jetzt aktualisieren'), 'Neue Version wird angeboten');
check(!str_contains($admin->body, '<script>alert(1)</script>') && $admin->has('&lt;script&gt;'), 'Release-Hinweise werden maskiert angezeigt');
$admin->get('dashboard'); check($admin->has('Version 9.9.9 ist verfügbar'), 'Hinweisbanner für Administratoren');
$adv->get('dashboard'); check(!$adv->has('9.9.9 ist verfügbar'), 'Kein Update-Hinweis für andere Benutzer');
$admin->get('admin.update'); $admin->post('admin.update', ['action' => 'install']);
check($admin->has('Update installiert') && $admin->has('Prüfung bestanden'), 'Update über die Weboberfläche installiert');
check(preg_match("/'APP_VERSION', '9\\.9\\.9'/", (string) file_get_contents(APP_ROOT . '/app/bootstrap.php')) === 1, 'Neue Programmversion ist aktiv');
check(is_file(APP_ROOT . '/app/UPDATE_MARKER.txt') && !is_file(APP_ROOT . '/docs/DSGVO.md'), 'Neue Dateien hinzugefügt, verwaiste Dateien entfernt');
check(md5_file(APP_ROOT . '/config/config.php') === $cfgHash0, 'Konfiguration bleibt unverändert');
check(!is_file(APP_ROOT . '/storage/maintenance.flag'), 'Wartungsmodus wieder beendet');
check(json_decode((string) file_get_contents($ghFile), true)['codeload_auth_seen'] === false, 'Token wird nicht an den Download-Host weitergegeben');
check(count(glob(APP_ROOT . '/storage/backups/vylaris-advertise-*.zip')) === 1, 'Sicherung vor dem Update angelegt');
$admin->get('admin.update'); check($admin->has('v9.9.9') || $admin->has('9.9.9'), 'Seite zeigt die neue Version');
$anon->get('home'); check($anon->code === 200 && $anon->has('v9.9.9'), 'Anwendung läuft mit der neuen Version (Footer)');
check((int) val("SELECT COUNT(*) FROM audit_log WHERE action = 'update.installed'") === 1, 'Update im Audit-Log protokolliert');

// --- Wartungsmodus blockiert Zugriffe
file_put_contents(APP_ROOT . '/storage/maintenance.flag', (string) time());
$anon->get('home'); check($anon->code === 503 && $anon->has('Wartung'), 'Wartungsmodus liefert 503');
unlink(APP_ROOT . '/storage/maintenance.flag');
$anon->get('home'); check($anon->code === 200, 'Nach dem Wartungsmodus wieder erreichbar');

// --- Rollback
$admin->get('admin.update'); $admin->post('admin.update', ['action' => 'rollback']);
check($admin->has('Sicherung wiederhergestellt'), 'Rollback über die Weboberfläche');
check($treeHash() === $hash0, 'Nach dem Rollback sind alle Programmdateien byte-identisch zum Ausgangsstand');
check(!is_file(APP_ROOT . '/app/UPDATE_MARKER.txt') && is_file(APP_ROOT . '/docs/DSGVO.md'), 'Rollback entfernt neue und stellt gelöschte Dateien wieder her');
$admin->post('admin.update', ['action' => 'rollback', 'file' => '../../etc/passwd']); check($admin->has('Unbekannte Sicherung'), 'Rollback akzeptiert nur vorhandene Sicherungen');
@array_map('unlink', glob(APP_ROOT . '/storage/backups/vylaris-advertise-*.zip'));

echo "\n== Feste Lizenz- und Update-Quelle ==\n";
$cfgFile = APP_ROOT . '/config/config.php';
$cfgBackup = file_get_contents($cfgFile);
$cfgArr = require $cfgFile;
$cfgArr['integrations']['licence_provider'] = 'NullLicence';
$cfgArr['integrations']['licence'] = ['endpoint' => 'https://evil.example', 'public_key' => 'x', 'product' => 'x', 'free_sites' => 99];
$cfgArr['integrations']['update'] = array_merge($cfgArr['integrations']['update'] ?? [], ['repo' => 'evil/repo', 'api_base' => 'https://evil.example']);
$evil = "<?php\nreturn " . var_export($cfgArr, true) . ";\n";
file_put_contents($cfgFile, $evil);
$probe = 'require "' . APP_ROOT . '/app/bootstrap.php"; echo json_encode([licence()->endpoint(), licence()->product(), md5(licence()->publicKey()), Updater::repo(), Updater::apiBase(), licence()->freeSites(), get_class(licence())]);';
$out = json_decode((string) shell_exec('php -r ' . escapeshellarg($probe) . ' 2>&1'), true); // bewusst OHNE auto_prepend_file
file_put_contents($cfgFile, $cfgBackup);
$userKey = "-----BEGIN PUBLIC KEY-----\nMCowBQYDK2VwAyEAhV7L176abx5a05XciRbOOeFj6wkCnNiDrwEXw7KceiU=\n-----END PUBLIC KEY-----\n";
check(is_array($out) && $out[0] === 'https://licence.vylaris.ch' && $out[1] === 'vylaris-advertise', 'Lizenzserver und Produkt lassen sich über config.php nicht ändern');
check(is_array($out) && $out[2] === md5($userKey), 'Es gilt automatisch der vorgegebene öffentliche Schlüssel (config.php wirkungslos)');
check(is_array($out) && $out[3] === 'VylarisGroup/vylaris-ads' && $out[4] === 'https://api.github.com', 'Update-Quelle lässt sich über config.php nicht ändern');
check(is_array($out) && $out[5] === 1 && $out[6] === 'TrsLicence', 'Kein alternativer Lizenzanbieter über config.php einhängbar');
check(Updater::repo() === 'VylarisGroup/vylaris-ads' && !str_contains((string) file_get_contents(APP_ROOT . '/app/views/admin/licence.php'), 'name="endpoint"') && !str_contains((string) file_get_contents(APP_ROOT . '/app/views/admin/licence.php'), 'public_key'), 'Weboberfläche bietet keine Felder für Lizenzserver oder Schlüssel');
$admin->get('admin.licence')->post('admin.licence', ['action' => 'save', 'licence_key' => 'VA-TEST-0001-AAAA', 'endpoint' => 'https://evil.example', 'public_key' => 'x', 'product' => 'x']);
check(licence()->endpoint() === 'http://127.0.0.1:8081' && !str_contains((string) val("SELECT GROUP_CONCAT(name) FROM settings"), 'licence_endpoint'), 'Per POST mitgeschickte Lizenzserver-Angaben werden ignoriert');
check(PHP_VERSION_ID > 0 && str_contains((string) file_get_contents(APP_ROOT . '/app/lib/Licence.php'), 'MCowBQYDK2VwAyEAhV7L176abx5a05XciRbOOeFj6wkCnNiDrwEXw7KceiU='), 'Öffentlicher Schlüssel ist im Programm hinterlegt');

echo "\n== Änderungsprotokoll ==\n";
$adminId = (int) val("SELECT id FROM users WHERE email = ?", [$adminEmail]);
$ck = 'changelog_seen_u' . $adminId;
$ver = APP_VERSION;
$parsed = Changelog::parse("# Titel\n\n## [2.0.0] - 2026-01-02\n### Neu\n- **Fett** und `code` <script>alert(1)</script>\n  Fortsetzung\n### Behoben\n- Fix\n\n## [1.10.0]\n- ohne Abschnitt\n\n## [1.9.0] - 2025-12-31\n- älter\n");
check(count($parsed) === 3 && $parsed[0]['version'] === '2.0.0' && $parsed[1]['version'] === '1.10.0' && $parsed[2]['version'] === '1.9.0', 'Parser liest Versionen und sortiert numerisch (1.10.0 vor 1.9.0)');
check($parsed[0]['date'] === '2026-01-02' && $parsed[1]['date'] === '' && str_contains($parsed[0]['sections']['Neu'][0], 'Fortsetzung') && ($parsed[1]['sections']['Änderungen'][0] ?? '') === 'ohne Abschnitt', 'Parser: Datum optional, Fortsetzungszeilen, Standardabschnitt');
$html = Changelog::html($parsed);
check(!str_contains($html, '<script') && str_contains($html, '<strong>Fett</strong>') && str_contains($html, '<code>code</code>') && str_contains($html, '&lt;script&gt;'), 'Darstellung maskiert HTML, erlaubt nur fett und code');
check(count(Changelog::entries()) >= 9 && Changelog::entries()[0]['version'] === $ver, 'CHANGELOG.md enthält die installierte Version als neuesten Eintrag');
check(str_contains(Changelog::markdownFor($ver), '### Neu'), 'Release-Hinweise lassen sich aus CHANGELOG.md erzeugen');

q("DELETE FROM settings WHERE name = ?", [$ck]);
$admin->get('dashboard'); check($admin->code === 200 && $admin->has('data-changelog') && $admin->has('Neu in Version ' . $ver), 'Erster Aufruf ohne Stand: Änderungsfenster mit der aktuellen Version');
check(!$admin->has('Version 1.4.0') , 'Ohne gespeicherten Stand wird nicht die ganze Historie gezeigt');
$adv->get('dashboard'); check(!$adv->has('data-changelog'), 'Nicht-Administratoren sehen das Änderungsfenster nie');
$adv->get('admin.changelog'); check($adv->code === 403, 'Änderungsprotokoll-Seite nur für Administratoren');
$anon->post('admin.changelog', []); check(in_array($anon->code, [302, 403, 419], true) && !$anon->has('data-changelog'), 'Anonymes POST auf das Änderungsprotokoll wird abgewiesen');
$admin->get('dashboard'); check($admin->has('data-changelog'), 'Fenster erscheint erneut, solange es nicht bestätigt wurde');
$admin->post('admin.changelog', []); $admin->get('dashboard');
check(val("SELECT value FROM settings WHERE name = ?", [$ck]) === $ver && !$admin->has('data-changelog'), 'Bestätigen merkt sich die Version, danach kein Fenster mehr');

set_setting($ck, '1.4.0');
$admin->get('dashboard');
check($admin->has('data-changelog') && $admin->has('Version 1.5.0') && $admin->has('Version ' . $ver) && !$admin->has('Version 1.4.0') && !$admin->has('Version 1.3.1'), 'Nach einem Update: nur Einträge seit der zuletzt gesehenen Version');
$admin->get('admin.changelog'); check($admin->code === 200 && !$admin->has('data-changelog') && $admin->has('Version 1.0.0') && $admin->has('Version 1.4.0'), 'Gesamtes Änderungsprotokoll mit Historie');
check(val("SELECT value FROM settings WHERE name = ?", [$ck]) === $ver, 'Das Öffnen der Seite gilt als gelesen');
$admin->get('dashboard'); check(!$admin->has('data-changelog'), 'Nach dem Lesen kein Fenster mehr');

set_setting($ck, '99.0.0');
$admin->get('dashboard'); check(!$admin->has('data-changelog'), 'Neuerer gespeicherter Stand (z. B. nach Rollback) zeigt nichts an');
q("DELETE FROM settings WHERE name = ?", [$ck]);
$other = (int) val("SELECT COUNT(*) FROM settings WHERE name LIKE 'changelog_seen_u%' AND name <> ?", [$ck]);
check($other === 0, 'Gesehen-Stand wird nur für den eigenen Administrator geführt');
$routes = require APP_ROOT . '/app/routes.php';
check(isset($routes['admin.changelog']) && !isset($routes['admin.changelog'][4]) && $routes['admin.changelog'][3] === 'admin', 'Änderungsprotokoll ist lizenzunabhängig (auch im Lesemodus) und nur für Administratoren');


echo "\n== Seitenleiste & Assistenten ==\n";
$wu = substr(bin2hex(random_bytes(3)), 0, 6);
$wp = new Client($base, 'wp');
$wp->get('register', ['wizard' => 1]);
check($wp->has('data-wizard') && $wp->has('data-step="Kontoart"') && $wp->has('Alle Felder auf einer Seite'), 'Registrierung: Assistent-Variante verfügbar');
$wp->get('register'); check(!$wp->has('data-wizard') && $wp->has('Schritt für Schritt registrieren'), 'Registrierung: klassisches Formular bleibt Standard');
$wp->post('register', ['wizard' => '1', 'name' => 'W', 'email' => 'kaputt', 'role' => 'publisher', 'password' => 'x', 'password2' => 'y']);
check(str_contains($wp->url, 'wizard=1') && $wp->has('data-wizard'), 'Registrierung: Fehler bringen den Benutzer in den Assistenten zurück');
$wp->post('register', ['wizard' => '1', 'name' => 'Wiebke Wizard', 'email' => "wp$wu@example.com", 'role' => 'publisher', 'password' => 'WizardPass123', 'password2' => 'WizardPass123', 'terms' => '1']);
check($wp->has('Wiebke Wizard'), 'Registrierung über den Assistenten funktioniert');
$wa = new Client($base, 'wa');
$wa->get('register')->post('register', ['name' => 'Walter Werber', 'email' => "wa$wu@example.com", 'role' => 'advertiser', 'password' => 'WerberPass1234', 'password2' => 'WerberPass1234', 'terms' => '1']);

$wp->get('dashboard');
check($wp->has('<aside') && $wp->has('data-nav-group="publish"') && $wp->has('Webseite einrichten') && !$wp->has('data-nav-group="manage"') && !$wp->has('data-nav-group="advertise"'), 'Seitenleiste (Betreiber): eigene Gruppen, keine Admin-/Werbe-Gruppen');
$wa->get('dashboard');
check($wa->has('data-nav-group="advertise"') && $wa->has('Kampagne erstellen') && $wa->has('Werbeplatz buchen') && !$wa->has('data-nav-group="publish"'), 'Seitenleiste (Werbetreibender): Werbe-Gruppe mit Assistenten');
$admin->get('dashboard');
check($admin->has('data-nav-group="manage"') && $admin->has('data-nav-group="system"') && $admin->has('Einrichtungsassistent') && $admin->has('Änderungsprotokoll'), 'Seitenleiste (Admin): Gruppen Prüfung, Finanzen, Verwaltung, System');
$wp->get('sites'); check(preg_match('/<details class="group" data-nav-group="publish" open data-active/', $wp->body) === 1, 'Aktive Gruppe ist beim Laden aufgeklappt');
$wp->get('dashboard'); check(preg_match('/data-nav-group="publish" open/', $wp->body) === 0 && $wp->has('aria-current="page"'), 'Inaktive Gruppen sind eingeklappt, aktive Seite ist markiert');
$anon->get('home'); check(!$anon->has('<aside') && $anon->has('Registrieren'), 'Gäste sehen keine Seitenleiste, aber Anmelden/Registrieren');

// Zugriff
$wa->get('wizard.site'); check($wa->code === 403, 'Betreiber-Assistent nur für Betreiber (403)');
$wp->get('wizard.campaign'); check($wp->code === 403, 'Kampagnen-Assistent nur für Werbetreibende (403)');
$wp->get('wizard.booking'); check($wp->code === 403, 'Buchungs-Assistent nur für Werbetreibende (403)');
$wp->get('wizard.setup'); check($wp->code === 403, 'Einrichtungsassistent nur für Administratoren (403)');
$anon->get('wizard.site'); check(str_contains($anon->url, 'p=login'), 'Assistenten erfordern Anmeldung');
foreach (['site.form' => 'wp', 'campaign.form' => 'wa'] as $page => $who) { $c = $$who; $c->get($page); check($c->code === 200 && $c->has('<form'), "Klassisches Formular $page bleibt erreichbar"); }

// Betreiber-Assistent: Webseite + Werbeplatz atomar
$wp->get('wizard.site');
check($wp->code === 200 && $wp->clean() && substr_count($wp->body, 'data-step=') === 4 && $wp->has('Klassisches Formular') && $wp->has('data-summary-list'), 'Betreiber-Assistent: 4 Schritte inkl. Zusammenfassung');
$slotFields = ['slot_name' => 'Sidebar', 'slot_format' => '300x250', 'slot_placement' => 'rechts', 'slot_description' => 'Sichtbar auf allen Seiten.', 'slot_price_per_day' => '12.50', 'slot_min_days' => '3', 'slot_max_days' => '30', 'slot_available_from' => $today, 'slot_available_until' => ''];
$siteFields = ['site_name' => 'Wizard Blog', 'site_url' => 'https://wizard.example.com', 'site_category' => 'Technologie', 'site_monthly_visitors' => '1200', 'site_description' => 'Ein Blog über Assistenten und Benutzerführung.'];
$before = [(int) val('SELECT COUNT(*) FROM sites'), (int) val('SELECT COUNT(*) FROM ad_slots')];
$wp->post('wizard.site', $siteFields + array_merge($slotFields, ['slot_price_per_day' => '']));
check($wp->has('Tagespreis') && (int) val('SELECT COUNT(*) FROM sites') === $before[0] && (int) val('SELECT COUNT(*) FROM ad_slots') === $before[1], 'Assistent: ungültiger Werbeplatz → es wird auch keine Webseite angelegt (alles oder nichts)');
check($wp->has('value="Wizard Blog"') && $wp->has('data-start="last"'), 'Assistent: Eingaben bleiben nach Fehler erhalten, Start bei der Zusammenfassung');
$wp->post('wizard.site', array_merge($siteFields, ['site_url' => 'javascript:alert(1)']) + $slotFields);
check($wp->has('gültige URL') && (int) val('SELECT COUNT(*) FROM sites') === $before[0], 'Assistent: Webseiten-Validierung identisch zum Formular (URL-Schema)');
$wp->post('wizard.site', $siteFields + $slotFields);
check($wp->has('Webseite und Werbeplatz angelegt') && (int) val('SELECT COUNT(*) FROM sites') === $before[0] + 1 && (int) val('SELECT COUNT(*) FROM ad_slots') === $before[1] + 1, 'Assistent legt Webseite und Werbeplatz gemeinsam an');
$newSite = one("SELECT * FROM sites WHERE name = 'Wizard Blog'");
$newSlot = one('SELECT * FROM ad_slots WHERE site_id = ?', [$newSite['id']]);
check($newSite['status'] === 'pending' && (int) $newSite['monthly_visitors'] === 1200 && $newSlot['status'] === 'pending' && (int) $newSlot['price_per_day'] === 1250 && $newSlot['format'] === '300x250', 'Gespeicherte Werte und Status „in Prüfung“ stimmen');
check((int) val("SELECT COUNT(*) FROM audit_log WHERE action = 'wizard.completed'") >= 1 && (int) val("SELECT COUNT(*) FROM audit_log WHERE action = 'site.created' AND entity_id = ?", [$newSite['id']]) === 1, 'Assistent wird im Protokoll festgehalten');
$wp->post('wizard.site', array_merge($siteFields, ['site_name' => 'Zweiter Blog']) + $slotFields);
$limited = !licence()->hasFeature('multi_site');
check($limited ? ($wp->has('höchstens') && (int) val("SELECT COUNT(*) FROM sites WHERE name = 'Zweiter Blog'") === 0) : ((int) val("SELECT COUNT(*) FROM sites WHERE name = 'Zweiter Blog'") === 1), 'Assistent beachtet das Webseiten-Limit der Lizenz (Scope multi_site)');
$wp->get('wizard.site', ['site_id' => $newSite['id']]);
check($wp->code === 200 && substr_count($wp->body, 'data-step=') === 3 && $wp->has('name="site_id"') && !$wp->has('name="site_name"'), 'Werbeplatz-Assistent für bestehende Webseite: ohne Webseiten-Schritt');
$wp->post('wizard.site', ['site_id' => $newSite['id']] + array_merge($slotFields, ['slot_name' => 'Footer', 'slot_format' => 'custom', 'slot_width' => '640', 'slot_height' => '120']));
check($wp->has('Werbeplatz angelegt') && (string) val("SELECT format FROM ad_slots WHERE name = 'Footer'") === '640x120', 'Zusätzlicher Werbeplatz über den Assistenten (eigenes Format)');
$wp->get('wizard.site', ['site_id' => $siteId]); check($wp->code === 404, 'Assistent: fremde Webseite → 404');

// Kampagnen-Assistent
$wa->get('wizard.campaign');
check($wa->code === 200 && $wa->clean() && $wa->has('action="index.php?p=campaign.form"') && $wa->has('enctype="multipart/form-data"') && $wa->has('data-banner-input') && $wa->has('name="_csrf"'), 'Kampagnen-Assistent sendet an das klassische Formular (inkl. Upload und CSRF)');
$wa->post('campaign.form', ['name' => 'Wizard Kampagne', 'target_url' => 'https://example.com/aktion', 'alt_text' => 'Aktion', 'banner' => png("$tmp/wiz.png", 300, 250)]);
check($wa->has('Kampagne angelegt') && (int) val("SELECT COUNT(*) FROM campaigns WHERE name = 'Wizard Kampagne'") === 1, 'Kampagne über den Assistenten-Weg angelegt');

// Buchungs-Assistent
$wa->get('wizard.booking');
check($wa->code === 200 && $wa->clean() && $wa->has('data-slot-select') && $wa->has('data-campaign-select') && $wa->has('action="index.php?p=booking.request"') && substr_count($wa->body, 'data-step=') === 4, 'Buchungs-Assistent: Werbeplatz, Kampagne, Zeitraum, Prüfen');
check(preg_match('/<option value="\d+"[^>]*data-w="\d+" data-h="\d+" data-price="\d+"/s', $wa->body) === 1, 'Buchungs-Assistent listet buchbare Werbeplätze mit Format und Preis');
check(!str_contains($wa->body, 'Wizard Blog – Sidebar'), 'Noch nicht freigegebene Werbeplätze erscheinen nicht');
$wp->get('wizard.booking'); 
q("UPDATE sites SET status = 'approved' WHERE id = ?", [$newSite['id']]); q("UPDATE ad_slots SET status = 'approved' WHERE id = ?", [$newSlot['id']]);
$wa->get('wizard.booking', ['slot_id' => $newSlot['id']]);
check($wa->has('Wizard Blog – Sidebar') && preg_match('/value="' . $newSlot['id'] . '" selected/', $wa->body) === 1 && $wa->has('data-start="1"'), 'Freigegebener Werbeplatz erscheint; Vorauswahl über slot_id springt zum Kampagnen-Schritt');
q("UPDATE campaigns SET status = 'approved' WHERE name = 'Wizard Kampagne'");
$wcid = (int) val("SELECT id FROM campaigns WHERE name = 'Wizard Kampagne'");
$wa->get('wizard.booking', ['slot_id' => $newSlot['id']]); check($wa->has('value="' . $wcid . '"') , 'Freigegebene Kampagne erscheint im Assistenten');
$wa->post('booking.request', ['slot_id' => $newSlot['id'], 'campaign_id' => $wcid, 'start_date' => $plus(2), 'end_date' => $plus(8), 'message' => 'Per Assistent']);
check($wa->has('Buchungsanfrage wurde gesendet') && (string) val("SELECT total_amount FROM bookings WHERE slot_id = ? ORDER BY id DESC LIMIT 1", [$newSlot['id']]) === (string) (7 * 1250), 'Buchung über den Assistenten-Weg: Preis serverseitig berechnet (7 Tage × 12.50)');
$wa->post('booking.request', ['slot_id' => $newSlot['id'], 'campaign_id' => $wcid, 'start_date' => $plus(4), 'end_date' => $plus(10), 'message' => '']);
check($wa->has('belegt') || $wa->has('bereits'), 'Doppelbuchungsschutz gilt auch für den Assistenten');

// Einrichtungsassistent (Admin)
$admin->get('wizard.setup');
check($admin->code === 200 && $admin->clean() && substr_count($admin->body, 'data-step=') === 5 && $admin->has('name="legal_hoster"'), 'Einrichtungsassistent: Plattform, Provision, Zahlung, Rechtliches, Prüfen');
$nameBefore = setting('platform_name'); $bpBefore = (string) val("SELECT value FROM settings WHERE name = 'commission_bp'");
$good = ['platform_name' => 'Wizard Plattform', 'currency' => (string) val("SELECT value FROM settings WHERE name = 'currency'"), 'invoice_prefix' => 'WZ', 'commission' => '12.5', 'min_payout' => '50.00', 'bank_details' => "Testbank\nIBAN CH00 0000 0000 0000 0000 0", 'legal_name' => 'Wizard AG', 'legal_email' => 'info@wizard.example', 'legal_address' => "Weg 1\n8000 Zürich", 'legal_phone' => '', 'legal_hoster' => 'Hoster AG, Bern'];
$admin->post('wizard.setup', array_merge($good, ['commission' => '77']));
check($admin->has('Provision') && (string) val("SELECT value FROM settings WHERE name = 'platform_name'") === $nameBefore, 'Einrichtungsassistent: Fehler → nichts wird gespeichert (alles oder nichts)');
$admin->post('wizard.setup', array_merge($good, ['legal_email' => 'keine-mail']));
check($admin->has('E-Mail') && (string) val("SELECT value FROM settings WHERE name = 'platform_name'") === $nameBefore, 'Einrichtungsassistent: ungültige Rechtsangabe verwirft auch die Plattform-Einstellungen');
$admin->post('wizard.setup', $good);
check((string) val("SELECT value FROM settings WHERE name = 'platform_name'") === 'Wizard Plattform' && (string) val("SELECT value FROM settings WHERE name = 'commission_bp'") === '1250' && (string) val("SELECT value FROM settings WHERE name = 'legal_name'") === 'Wizard AG', 'Einrichtungsassistent speichert Plattform, Provision und Betreiberangaben');
set_setting('legal_terms_custom', 'Eigener Text bleibt'); set_setting('legal_representative', 'Vorstand X');
$admin->post('wizard.setup', $good);
check(setting('legal_terms_custom') === 'Eigener Text bleibt' && (string) val("SELECT value FROM settings WHERE name = 'legal_representative'") === 'Vorstand X', 'Einrichtungsassistent lässt nicht abgefragte Rechtsfelder und eigene Texte unberührt');
set_setting('platform_name', $nameBefore); set_setting('commission_bp', $bpBefore); set_setting('legal_terms_custom', ''); set_setting('legal_representative', '');

echo "\n== Assistenten-Oberfläche (statisch) ==\n";
$js = (string) file_get_contents(APP_ROOT . '/public/assets/app.js');
check(str_contains($js, 'form[data-wizard]') && str_contains($js, 'data-nav-group') && str_contains($js, 'localStorage'), 'JavaScript: Assistenten-Logik und Merken der Menüzustände vorhanden');
check(!preg_match('/\bblob:|eval\(|new Function|innerHTML\s*=\s*[^`\'"]*(value|name)/', $js . ''), 'JavaScript: keine blob-URLs/eval; Benutzereingaben werden per textContent ausgegeben');
foreach (['wizard/site', 'wizard/campaign', 'wizard/booking', 'wizard/setup'] as $v) {
    $src = (string) file_get_contents(APP_ROOT . "/app/views/$v.php");
    check(str_contains($src, 'csrf_field()') && str_contains($src, 'data-wizard') && !str_contains($src, 'onclick=') && !str_contains($src, '<script'), "Assistenten-Vorlage $v: CSRF, keine Inline-Skripte");
}

echo "\n== E-Mail-Versand (SMTP) und Bestätigung ==\n";
// Benötigt: php tests/fake_smtp.php 2525 /tmp/vyad-smtp.jsonl (läuft im Hintergrund)
$smtpFile = '/tmp/vyad-smtp.jsonl';
$mails = function () use ($smtpFile): array { return array_map(fn ($l) => json_decode($l, true), array_filter(explode("\n", (string) @file_get_contents($smtpFile)))); };
$waitMail = function (int $n) use ($mails): array { for ($i = 0; $i < 50 && count($mails()) < $n; $i++) usleep(100000); return $mails(); };
@unlink($smtpFile); @unlink($smtpFile . '.reject');
$mu = substr(bin2hex(random_bytes(3)), 0, 6);
$reg = fn (Client $c, string $tag) => $c->get('register')->req('POST', 'register', [], ['name' => "Mail $tag", 'email' => "m$tag$mu@example.com", 'role' => 'advertiser', 'password' => 'MailTestPass123', 'password2' => 'MailTestPass123', 'terms' => '1']);
$uid = fn (string $tag): int => (int) val('SELECT id FROM users WHERE email = ?', ["m$tag$mu@example.com"]);

// a) Ohne eingerichteten Versand
check(!Mailer::configured(), 'Ausgangslage: E-Mail-Versand nicht eingerichtet');
$admin->get('dashboard'); check($admin->has('Jetzt SMTP einrichten'), 'Admin sieht Hinweis auf fehlende SMTP-Einrichtung');
$ma = new Client($base, 'ma');
$reg($ma, 'a');
check(val('SELECT email_verified_at FROM users WHERE email = ?', ["ma$mu@example.com"]) === null && !$ma->has('Abmelden'), 'Registrierung ohne SMTP: Konto unbestätigt und nicht angemeldet');
check($ma->has('Administrator'), 'Registrierung ohne SMTP: Nutzer wird auf die Bestätigung durch den Administrator hingewiesen');
$ma->get('login')->req('POST', 'login', [], ['email' => "ma$mu@example.com", 'password' => 'MailTestPass123']);
check(!$ma->has('Abmelden') && (int) val('SELECT COUNT(*) FROM mail_queue') === 0, 'Unbestätigtes Konto kann sich nicht anmelden; keine Mail in der Warteschlange');
$admin->get('admin.users'); check($admin->has('E-Mail unbestätigt') && $admin->has('Manuell bestätigen'), 'Benutzerliste zeigt unbestätigte Konten mit manueller Bestätigung');
$admin->post('admin.user', ['id' => $uid('a'), 'action' => 'verify']);
check(val('SELECT email_verified_at FROM users WHERE id = ?', [$uid('a')]) !== null, 'Administrator kann die Adresse manuell bestätigen');
$ma->get('login')->req('POST', 'login', [], ['email' => "ma$mu@example.com", 'password' => 'MailTestPass123']);
check($ma->has('Abmelden') || $ma->has('Mail a'), 'Nach manueller Bestätigung ist die Anmeldung möglich');

// b) Einrichtung im Admin-UI
$anon->get('admin.mail'); check(!$anon->has('SMTP-Server'), 'E-Mail-Seite: ohne Anmeldung nicht erreichbar');
$ma->get('admin.mail'); check($ma->code === 403, 'E-Mail-Seite: Nichtadministratoren erhalten 403');
$admin->get('admin.mail'); check($admin->code === 200 && $admin->has('SMTP-Server') && $admin->clean(), 'E-Mail-Seite für Administratoren erreichbar');
$cfg = ['action' => 'save', 'host' => '127.0.0.1', 'port' => '2525', 'secure' => 'none', 'user' => 'mailuser', 'pass' => 'MailPass42', 'from' => 'noreply@example.com', 'from_name' => 'Test Plattform', 'enabled' => '1'];
$admin->post('admin.mail', array_merge($cfg, ['host' => 'bad host!']));
check(!Mailer::configured() && $admin->has('Hostname'), 'Ungültiger SMTP-Server wird abgelehnt');
$admin->post('admin.mail', array_merge($cfg, ['host' => 'smtp.example.com']));
check(!Mailer::configured() && $admin->has('nicht unverschlüsselt'), 'Zugangsdaten ohne Verschlüsselung werden für Fremdserver abgelehnt');
$admin->post('admin.mail', $cfg); setting('__reset');
check(Mailer::configured() && setting('mail_pass') !== '' && !str_contains((string) val("SELECT GROUP_CONCAT(value) FROM settings WHERE name LIKE 'mail_%'"), 'MailPass42'), 'SMTP gespeichert; Passwort nur verschlüsselt in der Datenbank');
$admin->get('admin.mail'); check(!$admin->has('MailPass42') && $admin->has('unverändert lassen'), 'Passwort wird in der Oberfläche nie angezeigt');
$admin->post('admin.mail', array_merge($cfg, ['pass' => '', 'from_name' => 'Test Plattform 2'])); setting('__reset');
check(Mailer::config()['from_name'] === 'Test Plattform 2' && Mailer::config()['has_pass'], 'Leeres Passwortfeld lässt das gespeicherte Passwort unverändert');
$admin->post('admin.mail', ['action' => 'test', 'to' => 'empfaenger@example.com']);
$m = $waitMail(1);
check(count($m) === 1 && $m[0]['to'] === 'empfaenger@example.com' && str_contains($m[0]['subject'], 'Test-Mail') && $m[0]['from'] === 'noreply@example.com', 'Test-Mail wird über SMTP (inkl. Anmeldung) zugestellt');
$admin->post('admin.mail', array_merge($cfg, ['pass' => 'falsch']));
$admin->post('admin.mail', ['action' => 'test', 'to' => 'empfaenger@example.com']);
check($admin->has('fehlgeschlagen') && count($mails()) === 1, 'Falsches SMTP-Passwort: Test-Mail meldet Fehler');
$admin->post('admin.mail', $cfg);

// c) Registrierung mit Bestätigung
$mb = new Client($base, 'mb');
$reg($mb, 'b');
check(val('SELECT email_verified_at FROM users WHERE id = ?', [$uid('b')]) === null && !$mb->has('Abmelden'), 'Neue Registrierung: unbestätigt, nicht angemeldet');
$m = $waitMail(2);
$last = end($m) ?: [];
check(($last['to'] ?? '') === "mb$mu@example.com" && preg_match('/token=([a-f0-9]{64})/', (string) ($last['body'] ?? ''), $tm) === 1, 'Bestätigungsmail mit Link wird zugestellt');
$token = $tm[1] ?? '';
check((string) val('SELECT body FROM mail_queue WHERE user_id = ? ORDER BY id DESC LIMIT 1', [$uid('b')]) === '' && (string) val('SELECT status FROM mail_queue WHERE user_id = ? ORDER BY id DESC LIMIT 1', [$uid('b')]) === 'sent', 'Nach dem Versand ist der Mailtext aus der Warteschlange entfernt');
check($token !== '' && (int) val('SELECT COUNT(*) FROM email_tokens WHERE token_hash = ?', [$token]) === 0 && (int) val('SELECT COUNT(*) FROM email_tokens WHERE token_hash = ?', [hash('sha256', $token)]) === 1, 'Token wird nur als Hash gespeichert');
$mb->get('login')->req('POST', 'login', [], ['email' => "mb$mu@example.com", 'password' => 'MailTestPass123']);
check(!$mb->has('Abmelden') && $mb->has('bestätig'), 'Anmeldung vor der Bestätigung wird verweigert');
$mb->get('verify', ['token' => str_repeat('a', 64)]);
check(val('SELECT email_verified_at FROM users WHERE id = ?', [$uid('b')]) === null, 'Ungültiger Token bestätigt nichts');
$mb->get('verify', ['token' => $token]);
check(val('SELECT email_verified_at FROM users WHERE id = ?', [$uid('b')]) !== null, 'Gültiger Link bestätigt die Adresse');
$mb->get('login')->req('POST', 'login', [], ['email' => "mb$mu@example.com", 'password' => 'MailTestPass123']);
check($mb->has('Abmelden') || $mb->has('Mail b'), 'Nach der Bestätigung ist die Anmeldung möglich');
$before = val('SELECT email_verified_at FROM users WHERE id = ?', [$uid('b')]);
$mc = new Client($base, 'mc'); $mc->get('verify', ['token' => $token]);
check(val('SELECT used_at FROM email_tokens WHERE token_hash = ?', [hash('sha256', $token)]) !== null && !$mc->has('Abmelden'), 'Token ist nur einmal verwendbar und meldet niemanden an');
q('UPDATE email_tokens SET used_at = NULL, expires_at = NOW() - INTERVAL 1 MINUTE WHERE token_hash = ?', [hash('sha256', $token)]);
$mc->get('verify', ['token' => $token]);
check(val('SELECT used_at FROM email_tokens WHERE token_hash = ?', [hash('sha256', $token)]) === null, 'Abgelaufener Token wird nicht eingelöst');

// d) Erneut senden, Rate-Limit, keine Konto-Enumeration
$md = new Client($base, 'md');
$reg($md, 'd'); $waitMail(3);
$n = count($mails());
$md->post('verify.resend', ['email' => "md$mu@example.com"]);
check(count($mails()) === $n, 'Erneutes Senden innerhalb von 60 Sekunden wird gebremst');
q('UPDATE email_tokens SET created_at = NOW() - INTERVAL 2 MINUTE WHERE user_id = ?', [$uid('d')]);
$md->post('verify.resend', ['email' => "md$mu@example.com"]);
$m = $waitMail($n + 1);
check(count($m) === $n + 1, 'Bestätigungslink kann erneut angefordert werden');
$r1 = (new Client($base, 'e1'))->post('verify.resend', ['email' => "md$mu@example.com"]); $r2 = (new Client($base, 'e2'))->post('verify.resend', ['email' => "gibtesnicht$mu@example.com"]);
check(preg_replace('/\s+/', ' ', strip_tags($r1->body)) === preg_replace('/\s+/', ' ', strip_tags($r2->body)) || ($r1->has('bestätigung') === $r2->has('bestätigung')), 'Antwort verrät nicht, ob ein Konto existiert');

// e) Zustellfehler: Wiederholung
file_put_contents($smtpFile . '.reject', '1');
$me2 = new Client($base, 'me2'); $reg($me2, 'f');
usleep(800000);
$qid = (int) val('SELECT id FROM mail_queue WHERE user_id = ? ORDER BY id DESC LIMIT 1', [$uid('f')]);
check($qid > 0 && (int) val('SELECT attempts FROM mail_queue WHERE id = ?', [$qid]) >= 1 && (string) val('SELECT status FROM mail_queue WHERE id = ?', [$qid]) === 'pending' && str_contains((string) val('SELECT last_error FROM mail_queue WHERE id = ?', [$qid]), '550'), 'Zustellfehler: Mail bleibt in der Warteschlange mit Fehlermeldung und Wiederholung');
@unlink($smtpFile . '.reject'); setting('__reset');
q('UPDATE mail_queue SET next_try_at = NOW() WHERE id = ?', [$qid]);
$r = Mailer::flush(5);
check($r['sent'] >= 1 && (string) val('SELECT status FROM mail_queue WHERE id = ?', [$qid]) === 'sent', 'Wiederholung stellt die Mail zu, sobald der Server wieder annimmt');
$admin->get('admin.mail'); check($admin->has('Warteschlange') && $admin->clean(), 'Warteschlange in der Administration sichtbar');

// f) Konto: Benachrichtigungen und E-Mail-Änderung
$mb->get('account'); check($mb->has('name="notify_email"') && $mb->has('Bestätigt am'), 'Konto: Bestätigungsstatus und Benachrichtigungs-Schalter');
$mb->post('account', ['action' => 'profile', 'name' => 'Mail b', 'company' => '', 'email' => "mb$mu@example.com", 'payout_details' => '', 'current_password' => 'MailTestPass123']);
check((int) val('SELECT notify_email FROM users WHERE id = ?', [$uid('b')]) === 0, 'Benachrichtigungen lassen sich im Konto abschalten');
$n = count($mails());
q('UPDATE email_tokens SET created_at = NOW() - INTERVAL 2 MINUTE WHERE user_id = ?', [$uid('b')]);
$mb->post('account', ['action' => 'profile', 'name' => 'Mail b', 'company' => '', 'email' => "mb-neu$mu@example.com", 'payout_details' => '', 'current_password' => 'MailTestPass123', 'notify_email' => '1']);
check((string) val('SELECT email FROM users WHERE id = ?', [$uid('b')]) === "mb$mu@example.com", 'E-Mail-Änderung: Adresse bleibt bis zur Bestätigung unverändert');
$m = $waitMail($n + 1); $last = end($m) ?: [];
check(($last['to'] ?? '') === "mb-neu$mu@example.com" && preg_match('/token=([a-f0-9]{64})/', (string) ($last['body'] ?? ''), $tm2) === 1, 'E-Mail-Änderung: Link geht an die neue Adresse');
$mb->get('verify', ['token' => $tm2[1] ?? '']);
check((string) val('SELECT email FROM users WHERE email = ?', ["mb-neu$mu@example.com"]) === "mb-neu$mu@example.com" && (int) val('SELECT COUNT(*) FROM users WHERE email = ?', ["mb$mu@example.com"]) === 0, 'E-Mail-Änderung wird nach Klick auf den Link wirksam');

// g) Datenschutz
$cnt = (int) val('SELECT COUNT(*) FROM mail_queue WHERE user_id = ?', [$uid('d')]);
Privacy::deleteAccount($uid('d'));
check($cnt > 0 && (int) val('SELECT COUNT(*) FROM mail_queue WHERE user_id = ?', [$uid('d') ?: 0]) === 0 && (int) val('SELECT COUNT(*) FROM mail_queue WHERE to_email = ?', ["md$mu@example.com"]) === 0, 'Kontolöschung entfernt Warteschlange und Tokens des Kontos');
q("INSERT INTO mail_queue (to_email, subject, body, status, sent_at, created_at) VALUES ('alt@example.com', 'alt', '', 'sent', NOW() - INTERVAL 40 DAY, NOW() - INTERVAL 40 DAY)");
Privacy::housekeeping(true);
check((int) val("SELECT COUNT(*) FROM mail_queue WHERE to_email = 'alt@example.com'") === 0, 'Bereinigung löscht zugestellte Mails nach 30 Tagen');
check(Notify::class !== '' && str_contains((string) file_get_contents(APP_ROOT . '/bin/cron.php'), 'Mailer::flush'), 'Cronjob stellt die Warteschlange zu');

echo "\nErgebnis: $passed bestanden, $failed fehlgeschlagen\n";
array_map('unlink', glob("$tmp/*") ?: []);
@rmdir($tmp);
exit($failed > 0 ? 1 : 0);
