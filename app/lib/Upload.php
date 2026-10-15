<?php
declare(strict_types=1);

/**
 * Sichere Banner-Uploads:
 *  - nur echte HTTP-Uploads, Grössenlimit
 *  - MIME-Typ über finfo (Inhalt), nicht über Dateiname/Client-Angabe
 *  - getimagesize() muss ein gültiges Bild mit passendem Typ liefern
 *  - zufälliger Dateiname, Ablage ausserhalb des Webroots (storage/banners)
 *  - Auslieferung nur über den Controller mit festem Content-Type + nosniff
 */
final class Upload
{
    public static function dir(): string
    {
        return APP_ROOT . '/storage/banners';
    }

    /** @return array{file:string,mime:string,width:int,height:int,size:int} */
    public static function banner(?array $file): array
    {
        if (!$file || !isset($file['error']) || is_array($file['error'])) {
            throw new UserError('Bitte eine Bilddatei auswählen.');
        }
        if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
            throw new UserError('Die Datei ist zu gross.');
        }
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new UserError('Der Upload ist fehlgeschlagen (Fehlercode ' . (int) $file['error'] . ').');
        }
        $tmp = (string) $file['tmp_name'];
        if (!is_uploaded_file($tmp)) {
            throw new UserError('Ungültiger Upload.');
        }
        $size = (int) filesize($tmp);
        $max = (int) config('uploads.max_bytes', 1048576);
        if ($size <= 0 || $size > $max) {
            throw new UserError('Die Datei darf höchstens ' . round($max / 1024) . ' KB gross sein.');
        }

        $allowed = (array) config('uploads.allowed');
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp) ?: '';
        if (!isset($allowed[$mime])) {
            throw new UserError('Erlaubt sind nur PNG, JPG, GIF und WebP.');
        }
        $info = @getimagesize($tmp);
        if (!$info || ($info['mime'] ?? '') !== $mime || $info[0] < 1 || $info[1] < 1 || $info[0] > 2000 || $info[1] > 2000) {
            throw new UserError('Die Datei ist kein gültiges Bild (max. 2000×2000 Pixel).');
        }

        self::assertCleanImage($tmp, $mime, $size);

        $dir = self::dir();
        if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
            throw new RuntimeException('Upload-Verzeichnis kann nicht angelegt werden.');
        }
        $name = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
        $target = $dir . '/' . $name;
        if (!move_uploaded_file($tmp, $target)) {
            throw new RuntimeException('Datei konnte nicht gespeichert werden.');
        }
        @chmod($target, 0640);

        return ['file' => $name, 'mime' => $mime, 'width' => (int) $info[0], 'height' => (int) $info[1], 'size' => $size];
    }

    /**
     * Zusätzliche Tiefenverteidigung gegen Polyglott-Dateien (gültiges Bild + eingebetteter Code):
     *  - keine Bytes hinter dem formalen Bildende (PNG: IEND, GIF: Trailer, JPEG: EOI, WebP: RIFF-Länge)
     *  - kein erkennbarer PHP-/Skript-Code im Dateiinhalt
     */
    private static function assertCleanImage(string $path, string $mime, int $size): void
    {
        $data = (string) file_get_contents($path);
        $tail = rtrim(substr($data, -16), "\0");
        $clean = match ($mime) {
            'image/png'  => str_ends_with(substr($data, -12), "IEND\xAE\x42\x60\x82"),
            'image/gif'  => str_ends_with($tail, ';'),
            'image/jpeg' => str_ends_with($tail, "\xFF\xD9"),
            'image/webp' => strlen($data) >= 12 && (unpack('V', substr($data, 4, 4))[1] + 8) === $size,
            default      => false,
        };
        if (!$clean || preg_match('/<\?php|<\?=|<script|<html|<svg/i', $data)) {
            throw new UserError('Die Bilddatei enthält unzulässige Zusatzdaten. Bitte exportieren Sie das Banner neu.');
        }
    }

    public static function delete(string $name): void
    {
        if (preg_match('/^[a-f0-9]{32}\.(png|jpg|gif|webp)$/', $name)) {
            @unlink(self::dir() . '/' . $name);
        }
    }

    /** Liefert eine gespeicherte Bilddatei sicher aus. */
    public static function output(string $name, string $mime, string $cacheControl = 'private, max-age=300'): never
    {
        $path = self::dir() . '/' . $name;
        if (!preg_match('/^[a-f0-9]{32}\.(png|jpg|gif|webp)$/', $name) || !is_file($path)
            || !in_array($mime, array_keys((array) config('uploads.allowed')), true)) {
            http_response_code(404);
            exit;
        }
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($path));
        header('X-Content-Type-Options: nosniff');
        header("Content-Security-Policy: default-src 'none'; sandbox");
        header('Content-Disposition: inline; filename="banner.' . pathinfo($name, PATHINFO_EXTENSION) . '"');
        header('Cache-Control: ' . $cacheControl);
        readfile($path);
        exit;
    }
}
