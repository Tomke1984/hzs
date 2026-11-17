<?php
// Kopieer naar config.php, of laat public/install.php dit bestand voor je aanmaken.
return [
    'app_name'      => 'HZS Clubapp',
    'timezone'      => 'Europe/Brussels',
    'debug'         => false,   // true toont PHP-fouten (enkel lokaal gebruiken)
    'cookie_secure' => false,   // over HTTPS zet de app het secure-cookie al zelf; true forceert het altijd

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

    // Optioneel: link naar de privacyverklaring van de club (onderaan elke pagina van de app).
    'privacy_url' => '',
    // Beveiliging: true verplicht beheerders en bestuur om tweestapsverificatie aan te zetten.
    // Weglaten = verplicht op de server, niet bij lokaal testen (localhost).
    'security' => ['require_2fa_admin' => true],
    // Onderdelen die voorlopig uit staan; zet op true om ze te gebruiken.
    // attendance = presentielijst bij trainingen, aanwezigheidsrapport en "aanwezigheid nog in te vullen".
    'features' => ['attendance' => false],
    // Trainersplanning competitie: hoeveel dagen vooraf een mail vertrekt als er nog een trainer ontbreekt, en of de app
    // dat zelf doet (hooguit één keer per uur, bij een bezoek). Met een cron-taak: auto_remind false + tools/coverage-reminders.php.
    'coverage' => ['remind_days' => 3, 'auto_remind' => true],
    // Back-up van de database: standaard automatisch hooguit één keer per dag (na een bezoek), 14 dagen bewaard.
    // Map leeg = naast het SQLite-bestand (map "backup"), bij MySQL storage/backup. Met cron: auto false + tools/backup.php.
    'backup' => ['auto' => true, 'dir' => '', 'keep_days' => 14],
    // Deellink weekplanning: dagen geldig. iCal-link: vervalt na zoveel dagen zonder gebruik.
    'links' => ['share_days' => 365, 'ical_idle_days' => 180],
    // Sleutel voor versleutelde velden (medische opmerking, 2FA-geheim): base64 van 32 bytes, bv. via
    //   php -r "echo base64_encode(random_bytes(32)), PHP_EOL;"
    // Leeg = de app maakt eenmalig storage/app.key aan. Bewaar een kopie van de sleutel veilig, APART van de back-ups:
    // zonder sleutel zijn die velden onleesbaar.
    'app_key' => '',
    // Map voor sessiebestanden (standaard storage/sessions; niet de gedeelde /tmp van de hosting).
    // 'session_path' => '/home/<account>/hzs-data/sessions',

    'db' => [
        // 'sqlite' = geen installatie nodig (lokaal testen), 'mysql' = shared hosting
        'driver' => 'sqlite',
        // Op een server: zet het bestand BUITEN de webmap, bv. '/home/<account>/hzs-data/clubapp.sqlite'.
        'path'   => __DIR__ . '/storage/data/clubapp.sqlite',

        // enkel voor mysql:
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'hzs_clubapp',
        'user' => 'hzs',
        'pass' => '',
    ],
];
