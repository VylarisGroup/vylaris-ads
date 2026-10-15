<?php
declare(strict_types=1);

/**
 * Lizenzprüfung gegen TRS License (https://licence.vylaris.ch, API v1).
 *
 * Ablauf: Schlüssel im Admin-Bereich (oder Installer) eintragen → Gerät wird automatisch aktiviert →
 * alle ~15 Minuten Heartbeat (bei Seitenaufrufen und per bin/cron.php). Jede Serverantwort ist mit Ed25519
 * signiert und wird gegen den fest in config/config.php hinterlegten öffentlichen Schlüssel geprüft.
 * Zusätzlich wird eine signierte Offline-Lizenz abgelegt: Ist der Server nicht erreichbar, läuft die Anwendung
 * damit bis zu deren Ablauf weiter. Eine definitive Ablehnung (gesperrt, abgelaufen …) wirkt sofort.
 *
 * Berechtigungs-Scopes = die "features" des Tarifs auf dem Lizenzserver (siehe licence_scopes()).
 */
interface LicenceProvider
{
    /** Ist die Installation lizenziert? */
    public function isValid(): bool;

    /** Ist ein Feature (Scope) freigeschaltet? */
    public function hasFeature(string $feature): bool;

    /** Darf die Funktion hinter dem Scope genutzt werden (inkl. Lesemodus ohne gültige Lizenz)? */
    public function allows(string $scope): bool;

    /** Kurzer Statustext für das Admin-Dashboard. */
    public function status(): string;
}

/** Alle Scopes der Anwendung: Name => [Bezeichnung, nur lesend (bleibt ohne gültige Lizenz im Lesemodus nutzbar)] */
function licence_scopes(): array
{
    return [
        // Basis
        'market.browse'    => ['Marktplatz und Werbeplätze ansehen', true],
        'sites.manage'     => ['Webseiten und Werbeplätze verwalten (Betreiber)', false],
        'campaigns.manage' => ['Kampagnen und Banner verwalten (Werbetreibende)', false],
        'bookings.manage'  => ['Buchungen anfragen, annehmen, ablehnen, stornieren, Zahlung melden', false],
        'admin.review'     => ['Webseiten, Werbeplätze und Kampagnen freigeben', false],
        'payments.manage'  => ['Zahlungseingänge bestätigen und erstatten', false],
        'payouts.manage'   => ['Auszahlungen an Betreiber erfassen', false],
        // Erweitert
        'users.manage'     => ['Benutzerverwaltung (sperren, Rollen, Passwörter)', false],
        'export.csv'       => ['CSV-Export des Finanzjournals', true],
        'stats.view'       => ['Einnahmen- und Finanzstatistiken ansehen', true],
        'legal.edit'       => ['Impressum und Datenschutzangaben bearbeiten', false],
        'audit.view'       => ['Audit-Log ansehen', true],
        'multi_site'       => ['Mehr als eine Webseite pro Betreiber', false],
        'dual_role'        => ['Konten mit beiden Rollen (Werbetreibender & Betreiber)', false],
    ];
}

final class TrsLicence implements LicenceProvider
{
    private const TTL = 3600;          // so lange gilt eine positive Online-Antwort ohne Neuprüfung
    private const REFRESH = 900;       // Heartbeat-Intervall
    private const RETRY_OK_NEG = 600;  // erneute Prüfung nach definitiver Ablehnung
    private const RETRY_FAIL = 300;    // erneuter Versuch nach Netzwerkfehler
    private const SKEW = 300;          // erlaubte Zeitabweichung der Serverantwort (Sekunden)

    private ?array $eval = null;

    public function __construct(private array $cfg = [])
    {
    }

    // ----- Konfiguration -------------------------------------------------------------------------------------

    public function endpoint(): string { return rtrim((string) ($this->cfg['endpoint'] ?? 'https://licence.vylaris.ch'), '/'); }
    public function product(): string { return (string) ($this->cfg['product'] ?? 'vylaris-advertise'); }
    public function freeSites(): int { return max(1, (int) ($this->cfg['free_sites'] ?? 1)); }
    public function key(): string { return trim(setting('licence_key')); }

    /** Stabile Geräte-ID dieser Installation (aus dem geheimen Schlüssel der Konfiguration abgeleitet). */
    public function hwid(): string
    {
        return 'va-' . substr(hash('sha256', 'vylaris-advertise|' . (string) config('app.secret')), 0, 32);
    }

    // ----- Öffentliche Prüfung -------------------------------------------------------------------------------

    public function isValid(): bool { return $this->evaluate()['valid']; }

    public function hasFeature(string $feature): bool
    {
        $e = $this->evaluate();
        if (!$e['valid']) return false;
        foreach ($e['features'] as $f) {
            if ($f === '*' || $f === $feature || (str_ends_with($f, '.*') && str_starts_with($feature, substr($f, 0, -1)))) {
                return true;
            }
        }
        return false;
    }

    public function allows(string $scope): bool
    {
        if ($this->isValid()) return $this->hasFeature($scope);
        return (licence_scopes()[$scope][1] ?? false) === true; // Lesemodus
    }

    public function status(): string
    {
        $e = $this->evaluate();
        return $e['valid']
            ? 'Lizenz gültig' . ($e['plan'] !== '' ? ' (' . $e['plan'] . ')' : '') . ($e['mode'] === 'offline' ? ' – Offline-Lizenz' : '')
            : 'Keine gültige Lizenz – ' . self::describe($e['code']) . ' (Lesemodus)';
    }

    public static function describe(string $code): string
    {
        return [
            'OK' => 'Lizenz gültig', 'OFFLINE' => 'Offline-Lizenz gültig',
            'NO_KEY' => 'Es ist kein Lizenzschlüssel hinterlegt', 'NO_PUBLIC_KEY' => 'Der öffentliche Lizenzschlüssel fehlt in config/config.php',
            'NOT_CHECKED' => 'Die Lizenz wurde noch nicht geprüft bzw. die Installation ist abgemeldet', 'UNREACHABLE' => 'Der Lizenzserver ist nicht erreichbar und keine gültige Offline-Lizenz vorhanden',
            'NOT_FOUND' => 'Der Lizenzschlüssel ist unbekannt', 'BLOCKED' => 'Die Lizenz ist gesperrt', 'EXPIRED' => 'Die Lizenz ist abgelaufen',
            'WRONG_PRODUCT' => 'Der Schlüssel gehört zu einem anderen Produkt', 'DEVICE_LIMIT' => 'Alle Geräteplätze der Lizenz sind belegt',
            'NOT_ACTIVATED' => 'Die Installation ist nicht aktiviert', 'BAD_SIGNATURE' => 'Die Serverantwort hat keine gültige Signatur',
        ][$code] ?? $code;
    }

    /** Ergebnis der lokalen Bewertung (ohne Netzwerkzugriff), pro Anfrage zwischengespeichert. */
    public function evaluate(): array
    {
        if ($this->eval !== null) return $this->eval;
        $r = ['valid' => false, 'code' => 'NOT_CHECKED', 'mode' => 'none', 'plan' => '', 'features' => [], 'expires' => null, 'license' => null, 'checked' => null, 'valid_until' => null];
        $key = $this->key();
        if ($key === '') { $r['code'] = 'NO_KEY'; return $this->eval = $r; }
        if ($this->pubKey() === null) { $r['code'] = 'NO_PUBLIC_KEY'; return $this->eval = $r; }
        $st = $this->state();
        if (($st['key'] ?? '') !== hash('sha256', $key)) return $this->eval = $r;

        $now = time();
        // 1. Online-Antwort (Signatur wird bei jeder Auswertung erneut geprüft → Manipulation der DB wirkungslos)
        $on = $st['online'] ?? null;
        if (is_array($on) && ($d = $this->decode($on['payload'] ?? '', $on['signature'] ?? '')) !== null) {
            $r['checked'] = (int) ($on['fetched_at'] ?? 0);
            $lic = is_array($d['license'] ?? null) ? $d['license'] : null;
            if (empty($d['valid'])) {
                $r['code'] = (string) ($d['code'] ?? 'NOT_FOUND');
                $r['license'] = $lic;
                return $this->eval = $r;       // definitive Ablehnung gilt sofort, Offline-Datei zählt nicht
            }
            if ($lic !== null && ($lic['product'] ?? '') !== $this->product()) {
                $r['code'] = 'WRONG_PRODUCT';
                return $this->eval = $r;
            }
            $exp = isset($lic['expiresAt']) ? strtotime((string) $lic['expiresAt']) : null;
            if ($exp !== null && $exp !== false && $exp < $now) {
                $r['code'] = 'EXPIRED';
                return $this->eval = $r;
            }
            if ($now - (int) ($on['fetched_at'] ?? 0) <= self::TTL) {
                return $this->eval = $this->fill($r, 'online', 'OK', $lic, (array) ($lic['features'] ?? []));
            }
        }
        // 2. Offline-Lizenz (Server nicht erreichbar oder Antwort zu alt)
        $off = $st['offline'] ?? null;
        if (is_array($off) && ($d = $this->decode($off['payload'] ?? '', $off['signature'] ?? '')) !== null) {
            $until = strtotime((string) ($d['validUntil'] ?? '')) ?: 0;
            $lexp = isset($d['licenseExpiresAt']) ? strtotime((string) $d['licenseExpiresAt']) : null;
            if (($d['type'] ?? '') === 'offline' && ($d['key'] ?? '') === $key && ($d['hwid'] ?? '') === $this->hwid()
                && ($d['product'] ?? '') === $this->product() && $until > $now && ($lexp === null || $lexp === false || $lexp > $now)) {
                $r['valid_until'] = $until;
                return $this->eval = $this->fill($r, 'offline', 'OFFLINE', ['plan' => $d['plan'] ?? '', 'expiresAt' => $d['licenseExpiresAt'] ?? null], (array) ($d['features'] ?? []));
            }
        }
        $r['code'] = isset($st['online']) ? 'UNREACHABLE' : 'NOT_CHECKED';
        return $this->eval = $r;
    }

    private function fill(array $r, string $mode, string $code, ?array $lic, array $features): array
    {
        $r['valid'] = true;
        $r['mode'] = $mode;
        $r['code'] = $code;
        $r['license'] = $lic;
        $r['plan'] = (string) ($lic['plan'] ?? '');
        $r['expires'] = $lic['expiresAt'] ?? null;
        $r['features'] = array_values(array_filter(array_map('strval', $features), fn ($f) => $f !== ''));
        return $r;
    }

    /** Letzter Netzwerkfehler (für die Admin-Anzeige). */
    public function lastError(): string { return (string) ($this->state()['error'] ?? ''); }

    // ----- Abgleich mit dem Server ---------------------------------------------------------------------------

    /** Heartbeat, wenn fällig (billig, wenn nichts zu tun ist). Wirft nie. */
    public function refreshIfDue(): void
    {
        try {
            if ($this->key() === '' || $this->pubKey() === null) return;
            $st = $this->state();
            if (!empty($st['paused'])) return; // bewusst abgemeldet: erst wieder nach manueller Prüfung
            if (($st['key'] ?? '') === hash('sha256', $this->key()) && (int) ($st['next_try'] ?? 0) > time()) return;
            $this->refresh();
        } catch (Throwable $e) {
            error_log('[VylarisAdvertise] Lizenzprüfung: ' . $e->getMessage());
        }
    }

    /** Prüft den Schlüssel beim Server (aktiviert das Gerät bei Bedarf) und holt die Offline-Lizenz. */
    public function refresh(): string
    {
        $key = $this->key();
        $st = $this->state();
        if (($st['key'] ?? '') !== hash('sha256', $key)) {
            $st = ['key' => hash('sha256', $key)];
        }
        unset($st['paused']);
        $st['next_try'] = time() + self::RETRY_FAIL; // Sperre gegen parallele Anfragen und bei Netzwerkfehlern
        $this->saveState($st);

        $a = $this->call('heartbeat');
        $d = $a ? $this->decode((string) ($a['payload'] ?? ''), (string) ($a['signature'] ?? ''), true) : null;
        if ($d !== null && ($d['code'] ?? '') === 'NOT_ACTIVATED') {
            $a = $this->call('activate');
            $d = $a ? $this->decode((string) ($a['payload'] ?? ''), (string) ($a['signature'] ?? ''), true) : null;
        }
        $st = $this->state();
        if ($d === null) {
            $st['error'] = $a === null ? ($this->netError ?: 'Keine Antwort vom Lizenzserver.') : 'Die Antwort des Lizenzservers hat keine gültige Signatur oder ist veraltet.';
            $this->saveState($st);
            return 'UNREACHABLE';
        }
        $st['error'] = '';
        $st['online'] = ['payload' => $a['payload'], 'signature' => $a['signature'], 'fetched_at' => time()];
        if (!empty($d['valid'])) {
            $st['next_try'] = time() + self::REFRESH;
            $off = $st['offline'] ?? null;
            if (!is_array($off) || (int) ($off['fetched_at'] ?? 0) < time() - 86400) {
                $o = $this->call('offline');
                if ($o && !empty($o['valid']) && ($od = $this->decode((string) ($o['payload'] ?? ''), (string) ($o['signature'] ?? ''))) !== null && ($od['type'] ?? '') === 'offline') {
                    $st['offline'] = ['payload' => $o['payload'], 'signature' => $o['signature'], 'fetched_at' => time()];
                }
            }
        } else {
            $st['next_try'] = time() + self::RETRY_OK_NEG;
            unset($st['offline']);
        }
        $this->saveState($st);
        $this->eval = null;
        return (string) ($d['code'] ?? '');
    }

    /** Gerät beim Server abmelden (Platz wird frei) und lokalen Zustand löschen. */
    public function deactivate(): bool
    {
        $a = $this->call('deactivate');
        $ok = $a !== null;
        $this->saveState(['key' => hash('sha256', $this->key()), 'paused' => true]);
        $this->eval = null;
        return $ok;
    }

    public function reset(): void
    {
        $this->saveState([]);
        $this->eval = null;
    }

    // ----- Intern --------------------------------------------------------------------------------------------

    private string $netError = '';

    private function call(string $action): ?array
    {
        $this->netError = '';
        $ep = $this->endpoint();
        $p = parse_url($ep);
        $local = in_array($p['host'] ?? '', ['127.0.0.1', 'localhost', '::1', '[::1]'], true);
        if (($p['scheme'] ?? '') !== 'https' && !(($p['scheme'] ?? '') === 'http' && $local)) {
            $this->netError = 'Der Lizenzserver muss per HTTPS erreichbar sein.';
            return null;
        }
        $body = ['key' => $this->key(), 'hwid' => $this->hwid(), 'name' => mb_substr((string) setting('platform_name', 'VylarisAdvertise') . ' @ ' . ((string) parse_url((string) config('app.url'), PHP_URL_HOST)), 0, 80), 'product' => $this->product()];
        $ch = curl_init($ep . '/api/v1/' . $action);
        curl_setopt_array($ch, [
            CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode($body), CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
            CURLOPT_CONNECTTIMEOUT => 3, CURLOPT_TIMEOUT => 5, CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS | CURLPROTO_HTTP, CURLOPT_USERAGENT => 'VylarisAdvertise/' . APP_VERSION,
            CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2, CURLOPT_MAXFILESIZE => 262144,
        ]);
        $raw = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($raw === false || $code !== 200) {
            $this->netError = $raw === false ? 'Verbindung zum Lizenzserver fehlgeschlagen: ' . $err : 'Lizenzserver antwortete mit HTTP ' . $code . '.';
            return null;
        }
        $j = json_decode((string) $raw, true);
        if (!is_array($j)) {
            $this->netError = 'Ungültige Antwort des Lizenzservers.';
            return null;
        }
        return $j;
    }

    /** Roher 32-Byte-Ed25519-Schlüssel aus dem PEM in der Konfiguration. */
    private function pubKey(): ?string
    {
        $pem = (string) ($this->cfg['public_key'] ?? '');
        if ($pem === '' || !function_exists('sodium_crypto_sign_verify_detached')) return null;
        $der = base64_decode(preg_replace('/-----[^-]+-----|\s+/', '', $pem), true);
        // SubjectPublicKeyInfo für Ed25519: 12 Byte Präfix + 32 Byte Schlüssel
        if ($der === false || strlen($der) !== 44 || !str_starts_with($der, hex2bin('302a300506032b6570032100'))) return null;
        return substr($der, 12);
    }

    /** Prüft die Signatur und liefert die Nutzdaten; $fresh prüft zusätzlich den Zeitstempel (Wiedereinspielschutz). */
    private function decode(string $payload, string $signature, bool $fresh = false): ?array
    {
        $pk = $this->pubKey();
        $sig = base64_decode($signature, true);
        if ($pk === null || $payload === '' || $sig === false || strlen($sig) !== SODIUM_CRYPTO_SIGN_BYTES) return null;
        if (!sodium_crypto_sign_verify_detached($sig, $payload, $pk)) return null;
        $d = json_decode($payload, true);
        if (!is_array($d)) return null;
        if ($fresh && abs(time() - (int) ($d['ts'] ?? 0)) > self::SKEW) return null;
        return $d;
    }

    private function state(): array
    {
        $j = json_decode(setting('licence_state', '{}'), true);
        return is_array($j) ? $j : [];
    }

    private function saveState(array $st): void
    {
        set_setting('licence_state', json_encode($st, JSON_UNESCAPED_SLASHES));
        $this->eval = null;
    }
}

function licence(): LicenceProvider
{
    static $provider = null;
    if ($provider === null) {
        $class = (string) config('integrations.licence_provider', 'TrsLicence');
        $cfg = (array) config('integrations.licence', []);
        $provider = ($class !== 'TrsLicence' && class_exists($class) && is_subclass_of($class, LicenceProvider::class))
            ? new $class($cfg) : new TrsLicence($cfg);
    }
    return $provider;
}

/** Bricht mit Fehlermeldung ab, wenn der Scope nicht freigeschaltet ist (für Prüfungen innerhalb von Aktionen). */
function require_scope(string $scope): void
{
    if (!licence()->allows($scope)) {
        throw new UserError('Diese Funktion ist durch die Lizenz nicht freigeschaltet (' . $scope . ').');
    }
}
