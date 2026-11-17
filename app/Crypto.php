<?php
/**
 * Versleuteling van gevoelige velden in de database (medische opmerking, TOTP-geheim) met libsodium (XSalsa20-Poly1305).
 *
 * Sleutel: 'app_key' in config.php (base64, 32 bytes). Ontbreekt die, dan maakt de app eenmalig storage/app.key aan.
 * Wie de database lekt maar niet de sleutel, kan deze velden niet lezen. Bewaar de sleutel daarom NIET naast de
 * back-ups, maar wel veilig (zonder sleutel zijn de versleutelde velden in een back-up onleesbaar).
 *
 * Formaat in de database: "enc1:" + base64(nonce || ciphertext). Waarden zonder dat voorvoegsel (oude, nog niet
 * gemigreerde gegevens) worden ongewijzigd teruggegeven.
 */
final class Crypto
{
    private const PREFIX = 'enc1:';
    private static ?string $key = null;

    public static function key(): string
    {
        if (self::$key !== null) {
            return self::$key;
        }
        $cfg = (string) config('app_key', '');
        if ($cfg !== '') {
            $k = base64_decode($cfg, true);
            if ($k === false || strlen($k) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
                throw new RuntimeException("'app_key' in config.php is ongeldig (verwacht: base64 van 32 bytes).");
            }
            return self::$key = $k;
        }
        $file = APP_ROOT . '/storage/app.key';
        if (!is_file($file)) {
            $new = base64_encode(sodium_crypto_secretbox_keygen());
            // 'x' = enkel aanmaken als het nog niet bestaat (twee gelijktijdige verzoeken overschrijven elkaar niet)
            $h = @fopen($file, 'x');
            if ($h) {
                fwrite($h, $new);
                fclose($h);
                @chmod($file, 0600);
            }
        }
        $k = base64_decode(trim((string) @file_get_contents($file)), true);
        if ($k === false || strlen($k) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            throw new RuntimeException('Geen geldige sleutel in storage/app.key.');
        }
        return self::$key = $k;
    }

    /** Nieuwe sleutel als base64, voor 'app_key' in config.php. */
    public static function newKey(): string
    {
        return base64_encode(sodium_crypto_secretbox_keygen());
    }

    public static function isEncrypted(?string $v): bool
    {
        return $v !== null && str_starts_with($v, self::PREFIX);
    }

    /** null en '' blijven ongewijzigd (dan valt er niets te beschermen). */
    public static function encrypt(?string $plain): ?string
    {
        if ($plain === null || $plain === '' || self::isEncrypted($plain)) {
            return $plain;
        }
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        return self::PREFIX . base64_encode($nonce . sodium_crypto_secretbox($plain, $nonce, self::key()));
    }

    public static function decrypt(?string $stored): ?string
    {
        if (!self::isEncrypted($stored)) {
            return $stored;
        }
        $raw = base64_decode(substr((string) $stored, strlen(self::PREFIX)), true);
        if ($raw === false || strlen($raw) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + SODIUM_CRYPTO_SECRETBOX_MACBYTES) {
            return null;
        }
        $plain = sodium_crypto_secretbox_open(
            substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
            substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
            self::key()
        );
        if ($plain === false) {
            error_log('Crypto: ontsleutelen mislukt (verkeerde sleutel?)');
            return null;
        }
        return $plain;
    }
}
