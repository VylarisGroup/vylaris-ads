<?php
declare(strict_types=1);

/** Bestätigung von E-Mail-Adressen (Registrierung und Adressänderung) über einmalige, zeitlich begrenzte Links. */
final class Verification
{
    public const TTL_HOURS = 48;
    public const MAX_PER_DAY = 5;

    /** Erzeugt einen neuen Token (frühere offene Tokens gleicher Art werden ungültig). Gespeichert wird nur der Hash. */
    private static function issue(int $userId, string $email, string $purpose): string
    {
        q('UPDATE email_tokens SET used_at = NOW() WHERE user_id = ? AND purpose = ? AND used_at IS NULL', [$userId, $purpose]);
        $token = bin2hex(random_bytes(32));
        q('INSERT INTO email_tokens (user_id, purpose, email, token_hash, expires_at) VALUES (?, ?, ?, ?, NOW() + INTERVAL ' . self::TTL_HOURS . ' HOUR)',
            [$userId, $purpose, $email, hash('sha256', $token)]);
        return $token;
    }

    /** Höchstens MAX_PER_DAY Mails pro Konto und Tag, mindestens 60 Sekunden Abstand. */
    public static function canSend(int $userId): bool
    {
        if ((int) val('SELECT COUNT(*) FROM email_tokens WHERE user_id = ? AND created_at > NOW() - INTERVAL 1 DAY', [$userId]) >= self::MAX_PER_DAY) return false;
        return (int) val('SELECT COUNT(*) FROM email_tokens WHERE user_id = ? AND created_at > NOW() - INTERVAL 60 SECOND', [$userId]) === 0;
    }

    /** Sendet den Bestätigungslink an die Adresse des Kontos. Gibt false zurück, wenn kein Mailversand eingerichtet ist. */
    public static function sendVerify(array $user): bool
    {
        if (!Mailer::configured()) return false;
        $token = self::issue((int) $user['id'], $user['email'], 'verify');
        $platform = setting('platform_name', 'VylarisAdvertise');
        return Mailer::queue($user['email'], $user['name'], 'E-Mail-Adresse bestätigen – ' . $platform,
            "Guten Tag {$user['name']}\n\nbitte bestätigen Sie Ihre E-Mail-Adresse, um Ihr Konto bei $platform zu aktivieren:\n\n"
            . abs_url('verify', ['token' => $token]) . "\n\nDer Link ist " . self::TTL_HOURS . " Stunden gültig. Falls Sie sich nicht registriert haben, können Sie diese Nachricht ignorieren.\n\n$platform",
            (int) $user['id']) !== null;
    }

    /** Sendet den Bestätigungslink an die NEUE Adresse; die Änderung wird erst nach Klick wirksam. */
    public static function sendEmailChange(array $user, string $newEmail): bool
    {
        if (!Mailer::configured()) return false;
        $token = self::issue((int) $user['id'], $newEmail, 'email_change');
        $platform = setting('platform_name', 'VylarisAdvertise');
        return Mailer::queue($newEmail, $user['name'], 'Neue E-Mail-Adresse bestätigen – ' . $platform,
            "Guten Tag {$user['name']}\n\nfür Ihr Konto bei $platform wurde diese E-Mail-Adresse als neue Adresse angegeben. Zum Bestätigen öffnen Sie bitte:\n\n"
            . abs_url('verify', ['token' => $token]) . "\n\nDer Link ist " . self::TTL_HOURS . " Stunden gültig. Haben Sie die Änderung nicht veranlasst, ignorieren Sie diese Nachricht – Ihre bisherige Adresse bleibt dann bestehen.\n\n$platform",
            (int) $user['id']) !== null;
    }

    /**
     * Löst einen Token ein (einmalig). Gibt Art, Konto und Adresse zurück oder null bei ungültigem/abgelaufenem/benutztem Token.
     * @return array{purpose: string, user_id: int, email: string}|null
     */
    public static function consume(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) return null;
        $row = one('SELECT * FROM email_tokens WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW()', [hash('sha256', $token)]);
        if (!$row) return null;
        if (q('UPDATE email_tokens SET used_at = NOW() WHERE id = ? AND used_at IS NULL', [$row['id']])->rowCount() !== 1) return null;
        return ['purpose' => $row['purpose'], 'user_id' => (int) $row['user_id'], 'email' => $row['email']];
    }

    /** Markiert die Adresse des Kontos als bestätigt (z. B. per Administrator). */
    public static function markVerified(int $userId): void
    {
        q('UPDATE users SET email_verified_at = COALESCE(email_verified_at, NOW()) WHERE id = ?', [$userId]);
        q('UPDATE email_tokens SET used_at = NOW() WHERE user_id = ? AND purpose = \'verify\' AND used_at IS NULL', [$userId]);
    }
}
