<?php
/**
 * Voegt voorbeelddata toe aan een bestaande installatie (groepen, leden, activiteiten en
 * demo-accounts trainer@/zwemmer@/ouder@demo.local, wachtwoord Demo1234!).
 *
 * Gebruik (vanuit de projectmap):  php tools/seed-demo.php
 * Weigert te draaien als er al groepen of leden bestaan, zodat echte gegevens veilig blijven.
 */
if (PHP_SAPI !== 'cli') {
    exit("Enkel via de commandoregel te gebruiken.\n");
}

require dirname(__DIR__) . '/app/bootstrap.php';

if (config('db') === null) {
    fwrite(STDERR, "Geen config.php gevonden. Voer eerst public/install.php uit.\n");
    exit(1);
}

try {
    $groups = (int) Database::val('SELECT COUNT(*) FROM `groups`');
    $members = (int) Database::val('SELECT COUNT(*) FROM members');
    $demoUsers = (int) Database::val("SELECT COUNT(*) FROM users WHERE email LIKE '%@demo.local'");
} catch (Throwable $e) {
    fwrite(STDERR, "De database is nog niet geïnstalleerd. Voer eerst public/install.php uit.\n");
    exit(1);
}

if ($groups > 0 || $members > 0 || $demoUsers > 0) {
    fwrite(STDERR, "Niet toegevoegd: er bestaan al groepen ($groups), leden ($members) of demo-accounts ($demoUsers).\n");
    fwrite(STDERR, "Wil je opnieuw beginnen? Verwijder config.php en storage/data/clubapp.sqlite en doorloop install.php.\n");
    exit(1);
}

try {
    Installer::seedDemo();
} catch (Throwable $e) {
    fwrite(STDERR, 'Mislukt: ' . $e->getMessage() . "\n");
    exit(1);
}

echo "Voorbeelddata toegevoegd.\n";
echo "Demo-accounts (wachtwoord voor allemaal: Demo1234!):\n";
echo "  trainer@demo.local  zwemmer@demo.local  ouder@demo.local\n";
