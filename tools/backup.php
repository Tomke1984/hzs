<?php
/**
 * Back-up van de database maken (zie app/Backup.php). De app doet dit standaard zelf, hooguit één keer per dag.
 * Met cron (bv. elke nacht): php tools/backup.php   en in config.php 'backup' => ['auto' => false].
 *
 * Gebruik (vanuit de projectmap):  php tools/backup.php [--list]
 *   --list  bestaande back-ups tonen
 *
 * Terugzetten (SQLite): app offline, gunzip het bestand en zet het in de plaats van het databasebestand (db.path),
 * verwijder eventuele -wal/-shm-bestanden ernaast. MySQL: importeer het .sql-bestand (na gunzip) in phpMyAdmin.
 */
if (PHP_SAPI !== 'cli') {
    exit("Enkel via de commandoregel te gebruiken.\n");
}
require dirname(__DIR__) . '/app/bootstrap.php';
if (config('db') === null) {
    fwrite(STDERR, "Geen config.php gevonden.\n");
    exit(1);
}
if (in_array('--list', $argv, true)) {
    foreach (Backup::files() as $f) {
        echo date('Y-m-d H:i', filemtime($f)) . '  ' . round(filesize($f) / 1024) . " kB  $f\n";
    }
    exit(0);
}
try {
    $file = Backup::run();
    echo "Back-up gemaakt: $file\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'Back-up mislukt: ' . $e->getMessage() . "\n");
    exit(1);
}
