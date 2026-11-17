<?php
/**
 * Genummerde migraties in db/migrations/NNN_naam.php. Elk bestand geeft een functie terug: function (): void.
 *
 * - Ze lopen in volgorde van de bestandsnaam, één keer per database (tabel schema_migrations).
 * - Schrijf ze idempotent (met de helpers hieronder): een nieuwe installatie krijgt het volledige schema uit
 *   db/schema.sql en voert de migraties daarna ook uit; ze mogen dan niets stuk maken.
 * - db/schema.sql blijft altijd het volledige, actuele schema voor een nieuwe installatie.
 */
final class Migrator
{
    /** @return string[] namen (zonder .php), gesorteerd */
    public static function available(): array
    {
        $names = array_map(fn($f) => basename($f, '.php'), glob(APP_ROOT . '/db/migrations/[0-9][0-9][0-9]_*.php') ?: []);
        sort($names, SORT_STRING);
        return $names;
    }

    public static function latest(): string
    {
        $all = self::available();
        return $all ? end($all) : '';
    }

    /** Voert de migraties uit die nog niet liepen. @return string[] uitgevoerde namen */
    public static function run(): array
    {
        $done = array_column(Database::all('SELECT name FROM schema_migrations'), 'name');
        $ran = [];
        foreach (self::available() as $name) {
            if (in_array($name, $done, true)) {
                continue;
            }
            $fn = require APP_ROOT . "/db/migrations/$name.php";
            $fn();
            Database::exec('INSERT INTO schema_migrations (name, applied_at) VALUES (?, ?)', [$name, now()]);
            $ran[] = $name;
        }
        return $ran;
    }

    /* ---------- helpers voor migraties (SQLite en MySQL) ---------- */

    private static function t(string $table): string
    {
        return $table === 'groups' ? '`groups`' : $table;
    }

    public static function hasColumn(string $table, string $column): bool
    {
        try {
            Database::val('SELECT ' . $column . ' FROM ' . self::t($table) . ' LIMIT 1');
            return true;
        } catch (Throwable $e) {
            return false;
        }
    }

    public static function addColumn(string $table, string $column, string $definition): void
    {
        if (!self::hasColumn($table, $column)) {
            Database::exec('ALTER TABLE ' . self::t($table) . " ADD COLUMN $column $definition");
        }
    }

    /**
     * Verwijdert een kolom als dat kan. SQLite (vanaf 3.35) weigert kolommen die in een index of foreign key zitten;
     * dan wordt de kolom enkel leeggemaakt ($nullable) en blijft ze staan. @return bool verwijderd
     */
    public static function dropColumn(string $table, string $column, bool $nullable = true): bool
    {
        if (!self::hasColumn($table, $column)) {
            return true;
        }
        try {
            Database::pdo()->exec('ALTER TABLE ' . self::t($table) . " DROP COLUMN $column");
            return true;
        } catch (Throwable $e) {
            if ($nullable) {
                try {
                    Database::exec('UPDATE ' . self::t($table) . " SET $column = NULL");
                } catch (Throwable $e2) {
                }
            }
            return false;
        }
    }
}
