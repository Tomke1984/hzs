<?php
// Kopieer naar config.php, of laat public/install.php dit bestand voor je aanmaken.
return [
    'app_name'      => 'HZS Clubapp',
    'timezone'      => 'Europe/Brussels',
    'debug'         => false,   // true toont PHP-fouten (enkel lokaal gebruiken)
    'cookie_secure' => false,   // true zodra je via HTTPS draait

    // Basisadres van de app, gebruikt in links in e-mails (uitnodigingen, wachtwoord vergeten).
    // Laat leeg om het uit het verzoek af te leiden (enkel lokaal); zet het op de echte URL in productie.
    'app_url' => '',

    'mail' => [
        // 'log'  = er wordt niets verstuurd; mails komen in storage/mail/ (lokaal testen)
        // 'smtp' = echte mail via je mailserver (aanbevolen in productie)
        // 'mail' = PHP mail() van de server (eenvoudig op shared hosting)
        'driver'     => 'log',
        'from_email' => 'noreply@example.be',
        'from_name'  => 'HZS Clubapp',
        'smtp' => [
            'host'       => 'smtp.example.be',
            'port'       => 587,
            'encryption' => 'tls',   // 'tls' (STARTTLS, poort 587), 'ssl' (poort 465) of 'none'
            'user'       => '',
            'pass'       => '',
        ],
    ],

    // Optioneel: link naar de privacyverklaring (getoond op het inschrijfformulier) en een e-mailadres
    // dat een melding krijgt bij elke bevestigde inschrijving.
    'privacy_url' => '',
    // Statistieken: bezoek van ingelogde beheerders/bestuur op de website telt standaard niet mee. Zet op true om te testen.
    'stats' => ['count_staff' => false],
    'registration' => ['notify_email' => ''],
    // Naar welk adres gaan berichten van het contactformulier? Leeg = het adres bij 'registration' hierboven.
    'contact' => ['swimschool_email' => '', 'competition_email' => '', 'other_email' => ''],

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
