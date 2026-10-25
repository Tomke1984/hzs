<?php
// Kopieer naar config.php, of laat public/install.php dit bestand voor je aanmaken.
return [
    'app_name'      => 'HZS Clubapp',
    'timezone'      => 'Europe/Brussels',
    'debug'         => false,   // true toont PHP-fouten (enkel lokaal gebruiken)
    'cookie_secure' => false,   // true zodra je via HTTPS draait

    'db' => [
        // 'sqlite' = geen installatie nodig (lokaal testen), 'mysql' = shared hosting
        'driver' => 'sqlite',
        'path'   => __DIR__ . '/storage/data/clubapp.sqlite',

        // enkel voor mysql:
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'hzs_clubapp',
        'user' => 'hzs',
        'pass' => '',
    ],
];
