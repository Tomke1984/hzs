<?php
/** Afbeeldingen uploaden naar public/uploads (alleen echte afbeeldingen, willekeurige bestandsnamen). */
final class Uploads
{
    private const TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
    public const MAX_BYTES = 5 * 1024 * 1024;

    public static function dir(): string
    {
        return APP_ROOT . '/public/uploads';
    }

    /** @return array{0:?string,1:?string} [pad, foutmelding] */
    public static function store(array $file): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return [null, null];
        }
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return [null, 'Uploaden mislukt (bestand te groot of onderbroken).'];
        }
        if ($file['size'] > self::MAX_BYTES) {
            return [null, 'De afbeelding is te groot (max. 5 MB).'];
        }
        $info = @getimagesize($file['tmp_name']);
        $ext = $info ? (self::TYPES[$info['mime']] ?? null) : null;
        if (!$ext) {
            return [null, 'Enkel afbeeldingen (jpg, png, gif of webp) zijn toegestaan.'];
        }
        if (!is_dir(self::dir()) && !@mkdir(self::dir(), 0775, true)) {
            return [null, 'De map public/uploads kan niet worden aangemaakt.'];
        }
        $name = bin2hex(random_bytes(8)) . '.' . $ext;
        if (!move_uploaded_file($file['tmp_name'], self::dir() . '/' . $name) && !@rename($file['tmp_name'], self::dir() . '/' . $name)) {
            return [null, 'De afbeelding kon niet worden opgeslagen.'];
        }
        return ['uploads/' . $name, null];
    }

    public static function delete(?string $path): void
    {
        if ($path && preg_match('#^uploads/[A-Za-z0-9._-]+$#', $path)) {
            @unlink(APP_ROOT . '/public/' . $path);
        }
    }
}
