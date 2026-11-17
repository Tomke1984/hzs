<?php
/**
 * Oude tabellen van verwijderde onderdelen (website, lidgeld, inschrijvingen, zwemtest, ...) bekijken, exporteren
 * en wissen. De app gebruikt ze niet meer, maar in een database van vóór v0.13 kunnen ze nog persoonsgegevens
 * bevatten. Wissen is een AVG-beslissing van het bestuur: exporteer eerst als je iets wilt bewaren.
 *
 * Gebruik (vanuit de projectmap):
 *   php tools/legacy-data.php                  oude tabellen en aantal rijen tonen
 *   php tools/legacy-data.php --export         alles exporteren naar <back-upmap>/legacy-JJJJMMDD-UUMMSS.json.gz
 *   php tools/legacy-data.php --export --drop  exporteren én daarna de tabellen verwijderen (enkel samen met --export)
 */
if (PHP_SAPI !== 'cli') {
    exit("Enkel via de commandoregel te gebruiken.\n");
}
require dirname(__DIR__) . '/app/bootstrap.php';
if (config('db') === null) {
    fwrite(STDERR, "Geen config.php gevonden.\n");
    exit(1);
}
preg_match_all('/CREATE TABLE IF NOT EXISTS `?([a-z_]+)`?/', (string) file_get_contents(APP_ROOT . '/db/schema.sql'), $m);
$current = $m[1];
$all = Database::driver() === 'mysql'
    ? Database::pdo()->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN)
    : array_column(Database::all("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'"), 'name');
$legacy = array_values(array_diff($all, $current));
sort($legacy);
if (!$legacy) {
    echo "Geen oude tabellen gevonden.\n";
    exit(0);
}
$q = fn($t) => Database::driver() === 'mysql' ? "`$t`" : "\"$t\"";
foreach ($legacy as $t) {
    echo str_pad($t, 30) . (int) Database::val('SELECT COUNT(*) FROM ' . $q($t)) . " rijen\n";
}
$export = in_array('--export', $argv, true);
if (in_array('--drop', $argv, true) && !$export) {
    fwrite(STDERR, "--drop enkel samen met --export (eerst bewaren, dan wissen).\n");
    exit(1);
}
if ($export) {
    $data = [];
    foreach ($legacy as $t) {
        $data[$t] = Database::all('SELECT * FROM ' . $q($t));
    }
    $dir = Backup::dir();
    if (!is_dir($dir) && !@mkdir($dir, 0700, true)) {
        fwrite(STDERR, "Map $dir kan niet aangemaakt worden.\n");
        exit(1);
    }
    $file = $dir . '/legacy-' . date('Ymd-His') . '.json.gz';
    if (file_put_contents($file, gzencode(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), 6)) === false) {
        fwrite(STDERR, "Export mislukt.\n");
        exit(1);
    }
    @chmod($file, 0600);
    echo "Geëxporteerd naar $file\n";
}
if ($export && in_array('--drop', $argv, true)) {
    Database::pdo()->exec(Database::driver() === 'mysql' ? 'SET FOREIGN_KEY_CHECKS=0' : 'PRAGMA foreign_keys = OFF');
    foreach ($legacy as $t) {
        Database::pdo()->exec('DROP TABLE ' . $q($t));
    }
    if (Database::driver() === 'mysql') {
        Database::pdo()->exec('SET FOREIGN_KEY_CHECKS=1');
    } else {
        Database::pdo()->exec('PRAGMA foreign_keys = ON');
        Database::pdo()->exec('VACUUM'); // vrijgekomen ruimte (en de oude gegevens) echt uit het bestand
    }
    audit('LEGACY_DROP', 'database', null, implode(',', $legacy));
    echo count($legacy) . " oude tabellen verwijderd.\n";
}
