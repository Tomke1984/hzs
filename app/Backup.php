<?php
/**
 * Dagelijkse back-up van de database.
 *
 * - SQLite: een consistente kopie met VACUUM INTO (of een kopie na een WAL-checkpoint), gecomprimeerd: hzs-JJJJMMDD-UUMMSS-mmm.sqlite.gz
 * - MySQL: een SQL-dump (CREATE TABLE + INSERT), gecomprimeerd: hzs-JJJJMMDD-UUMMSS-mmm.sql.gz (terug te zetten via phpMyAdmin)
 *
 * Standaard loopt ze automatisch, hooguit één keer per dag, na het antwoord op een verzoek (geen cron nodig).
 * Met cron: php tools/backup.php en in config.php 'backup' => ['auto' => false].
 * Map: config backup.dir, anders naast het SQLite-bestand (map "backup"), anders storage/backup. Bewaard: backup.keep_days (14).
 */
final class Backup
{
    public static function dir(): string
    {
        $dir = (string) config('backup.dir', '');
        if ($dir === '') {
            $db = config('db');
            $dir = (is_array($db) && ($db['driver'] ?? 'sqlite') === 'sqlite' && !empty($db['path']))
                ? dirname((string) $db['path']) . '/backup'
                : APP_ROOT . '/storage/backup';
        }
        return rtrim($dir, '/');
    }

    /** Maakt een back-up en ruimt oude op. Geeft het pad van het nieuwe bestand terug. */
    public static function run(): string
    {
        $dir = self::dir();
        if (!is_dir($dir) && !@mkdir($dir, 0700, true)) {
            throw new RuntimeException("Back-upmap $dir kan niet aangemaakt worden.");
        }
        if (!is_file("$dir/.htaccess")) {
            @file_put_contents("$dir/.htaccess", "Require all denied\n");
        }
        $stamp = date('Ymd-His') . sprintf('-%03d', (int) (microtime(true) * 1000) % 1000); // uniek, ook bij twee back-ups in dezelfde seconde
        if (Database::driver() === 'mysql') {
            $file = "$dir/hzs-$stamp.sql.gz";
            self::write($file, self::mysqlDump());
        } else {
            $file = "$dir/hzs-$stamp.sqlite.gz";
            $tmp = "$dir/.tmp-$stamp.sqlite";
            @unlink($tmp);
            try {
                Database::pdo()->exec('VACUUM INTO ' . Database::pdo()->quote($tmp));
            } catch (Throwable $e) {
                // oudere SQLite (< 3.27): eerst de WAL wegschrijven, dan kopiëren
                Database::pdo()->exec('PRAGMA wal_checkpoint(TRUNCATE)');
                if (!@copy((string) config('db.path'), $tmp)) {
                    throw new RuntimeException('Kopie van de database mislukt.');
                }
            }
            self::write($file, (string) file_get_contents($tmp));
            @unlink($tmp);
        }
        @chmod($file, 0600);
        self::prune($dir);
        Installer::setMeta('last_backup', now());
        return $file;
    }

    private static function write(string $file, string $data): void
    {
        $gz = gzencode($data, 6);
        if ($gz === false || file_put_contents($file, $gz) === false) {
            throw new RuntimeException("Back-up $file kan niet geschreven worden.");
        }
    }

    /** Verwijdert back-ups ouder dan backup.keep_days, maar laat altijd de 3 nieuwste staan. */
    private static function prune(string $dir): void
    {
        $keep = max(1, (int) config('backup.keep_days', 14));
        $files = self::files();
        foreach (array_slice($files, 3) as $f) {
            if (filemtime($f) < time() - $keep * 86400) {
                @unlink($f);
            }
        }
    }

    /** Bestaande back-ups, nieuwste eerst. @return string[] */
    public static function files(): array
    {
        $files = glob(self::dir() . '/hzs-*.{sqlite,sql}.gz', GLOB_BRACE) ?: [];
        usort($files, fn($a, $b) => filemtime($b) <=> filemtime($a));
        return $files;
    }

    /** Tijdstip van de laatste geslaagde back-up (of null). */
    public static function lastAt(): ?int
    {
        $v = Database::val("SELECT v FROM app_meta WHERE k = 'last_backup'");
        return $v ? (int) strtotime((string) $v) : null;
    }

    /** Beheerders krijgen een waarschuwing als de laatste back-up ouder is dan 2 dagen. */
    public static function isOverdue(): bool
    {
        $last = self::lastAt();
        return $last === null || $last < time() - 2 * 86400;
    }

    /** Hooguit één keer per dag, na het antwoord (zoals de herinneringen van de trainersplanning). */
    public static function auto(): void
    {
        if (!config('backup.auto', true)) {
            return;
        }
        $f = APP_ROOT . '/storage/backup-last-run';
        if (is_file($f) && time() - (int) @filemtime($f) < 86400) {
            return;
        }
        if (!@touch($f)) {
            return;
        }
        register_shutdown_function(function (): void {
            if (function_exists('fastcgi_finish_request')) {
                @fastcgi_finish_request();
            }
            try {
                self::run();
            } catch (Throwable $e) {
                error_log('Back-up mislukt: ' . $e->getMessage());
            }
        });
    }

    private static function mysqlDump(): string
    {
        $pdo = Database::pdo();
        $out = "-- HZS Clubapp back-up " . date('c') . "\nSET FOREIGN_KEY_CHECKS=0;\n";
        foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $t) {
            $create = $pdo->query("SHOW CREATE TABLE `$t`")->fetch(PDO::FETCH_NUM)[1];
            $out .= "\nDROP TABLE IF EXISTS `$t`;\n$create;\n";
            $rows = $pdo->query("SELECT * FROM `$t`", PDO::FETCH_ASSOC);
            foreach ($rows as $r) {
                $vals = array_map(fn($v) => $v === null ? 'NULL' : $pdo->quote((string) $v), array_values($r));
                $out .= "INSERT INTO `$t` (`" . implode('`,`', array_keys($r)) . '`) VALUES (' . implode(',', $vals) . ");\n";
            }
        }
        return $out . "SET FOREIGN_KEY_CHECKS=1;\n";
    }
}
