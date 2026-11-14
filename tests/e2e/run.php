<?php
/**
 * End-to-end tests van de Clubapp.
 * Gebruik (vanuit de projectmap):  php tests/e2e/run.php [suite ...]
 *   zonder argument: alle suites in deze map (smoke, v12_trainersplanning, ...), elk in een verse tijdelijke kopie
 *   met SQLite en demodata, tegen `php -S`. KEEP=1 bewaart de tijdelijke map (database, mails, serverlog).
 */
if (PHP_SAPI !== 'cli') {
    exit;
}
require __DIR__ . '/lib.php';

if (($argv[1] ?? '') === '--one') {
    $suite = basename((string) ($argv[2] ?? ''));
    T::setup();
    require __DIR__ . "/$suite.php";
    exit(T::report());
}

$suites = array_slice($argv, 1);
if (!$suites) {
    $suites = array_values(array_diff(array_map(fn($f) => basename($f, '.php'), glob(__DIR__ . '/*.php')), ['run', 'lib']));
    sort($suites);
}
$code = 0;
foreach ($suites as $s) {
    echo "== $s ==\n";
    passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' --one ' . escapeshellarg(basename($s, '.php')), $rc);
    $code = max($code, $rc);
}
echo $code ? "\nEr zijn fouten.\n" : "\nAlle suites geslaagd.\n";
exit($code);
