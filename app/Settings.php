<?php
/** Instellingen die de beheerder in de app aanpast (tabel settings, waarde als JSON). */
final class Settings
{
    private static ?array $all = null;

    public static function get(string $key, $default = null)
    {
        if (self::$all === null) {
            self::$all = [];
            try {
                foreach (Database::all('SELECT k, v FROM settings') as $r) {
                    self::$all[$r['k']] = json_decode((string) $r['v'], true);
                }
            } catch (Throwable $e) {
                // tabel bestaat nog niet (voor de migratie): niets ingesteld
            }
        }
        return array_key_exists($key, self::$all) ? self::$all[$key] : $default;
    }

    public static function set(string $key, $value): void
    {
        $json = json_encode($value, JSON_UNESCAPED_UNICODE);
        if (Database::val('SELECT COUNT(*) FROM settings WHERE k = ?', [$key])) {
            Database::exec('UPDATE settings SET v = ? WHERE k = ?', [$json, $key]);
        } else {
            Database::exec('INSERT INTO settings (k, v) VALUES (?, ?)', [$key, $json]);
        }
        self::$all = null;
    }

    public static function forget(string $key): void
    {
        Database::exec('DELETE FROM settings WHERE k = ?', [$key]);
        self::$all = null;
    }
}
