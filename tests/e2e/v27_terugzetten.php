<?php
/**
 * v0.27: bestaande SQLite-database terugzetten via de installer (zonder SSH), met controle van de sleutel.
 */
T::autoRan();

// Een medische opmerking, zodat er versleutelde gegevens in de database staan
$mid = (int) T::val("SELECT id FROM members WHERE first_name = 'Emma'");
$m = T::all('SELECT * FROM members WHERE id = ?', [$mid])[0];
$admin = Browser::login('admin@demo.local');
$admin->get('/app/leden/' . $mid . '/wijzigen');
$admin->post('/app/member-save', ['id' => $mid, 'first_name' => $m['first_name'], 'last_name' => $m['last_name'], 'birth_date' => (string) $m['birth_date'],
    'email' => (string) $m['email'], 'phone' => (string) $m['phone'], 'status' => $m['status'],
    'emergency_name' => 'Papa', 'emergency_phone' => '0470 00 00 00', 'medical_note' => 'Allergie: pinda']);
T::ok(str_starts_with((string) T::val('SELECT medical_note FROM members WHERE id = ?', [$mid]), 'enc1:'), 'medische opmerking versleuteld');
$key = trim((string) file_get_contents(T::$dir . '/storage/app.key'));
$users = (int) T::val('SELECT COUNT(*) FROM users');

// Kopie van de database, zoals de gebruiker ze lokaal maakt (en als back-up van de app: .sqlite.gz)
$copy = sys_get_temp_dir() . '/hzs-import-' . getmypid() . '.sqlite';
@unlink($copy);
T::pdo()->exec("VACUUM INTO '$copy'");
$sqlite = (string) file_get_contents($copy);
$gz = gzencode($sqlite);
unlink($copy);

echo "Installer zonder config.php\n";
rename(T::$dir . '/config.php', T::$dir . '/config.test.php');
@unlink(T::$dir . '/storage/app.key'); // nieuwe server: de sleutel is er niet
$b = new Browser();
$b->get('/install.php');
T::ok($b->status === 200 && $b->has('bestaande database terugzetten'), 'installer toont de optie om een database terug te zetten');

$b->postFiles('/install.php', ['mode' => 'import'], ['database' => ['leeg.txt', 'dit is geen database']]);
T::ok($b->has('geen SQLite-database'), 'geen database: geweigerd');
$b->get('/install.php');
$b->postFiles('/install.php', ['mode' => 'import'], ['database' => ['clubapp.sqlite', $sqlite]]);
T::ok($b->has('versleutelde medische gegevens'), 'versleutelde gegevens zonder sleutel: geweigerd');
$b->get('/install.php');
$b->postFiles('/install.php', ['mode' => 'import', 'app_key' => base64_encode(random_bytes(32))], ['database' => ['clubapp.sqlite', $sqlite]]);
T::ok($b->has('kunnen de versleutelde gegevens niet gelezen worden'), 'verkeerde sleutel: geweigerd');
$b->get('/install.php');
$b->postFiles('/install.php', ['mode' => 'import', 'app_key' => 'geen-sleutel'], ['database' => ['clubapp.sqlite', $sqlite]]);
T::ok($b->has('sleutel (app_key) is ongeldig'), 'ongeldige sleutel: geweigerd');
T::ok(!is_file(T::$dir . '/config.php') && !is_file(T::$dir . '/storage/data/clubapp.sqlite'), 'na een fout niets weggeschreven');

$b->get('/install.php');
$b->postFiles('/install.php', ['mode' => 'import', 'app_key' => $key], ['database' => ['hzs-20261117.sqlite.gz', $gz]]);
T::ok($b->has('Database teruggezet') && $b->has("$users gebruikers"), 'back-up (.sqlite.gz) met de juiste sleutel teruggezet');
$cfg = require T::$dir . '/config.php';
T::ok(($cfg['db']['path'] ?? '') === T::$dir . '/storage/data/clubapp.sqlite' || str_ends_with((string) ($cfg['db']['path'] ?? ''), '/storage/data/clubapp.sqlite'), 'config.php wijst naar de teruggezette database');
T::ok(($cfg['app_key'] ?? '') === $key, 'config.php bevat de opgegeven sleutel');
T::ok(is_file(T::$dir . '/storage/data/clubapp.sqlite'), 'databasebestand staat op zijn plaats');

$b->get('/install.php');
T::ok($b->status === 403, 'daarna is de installer vergrendeld');

$a = Browser::login('admin@demo.local');
$a->get('/app/leden/' . $mid);
T::ok($a->status === 200 && $a->has('Allergie: pinda'), 'aanmelden met een bestaand account; medische opmerking leesbaar');
$a->get('/app/kalender');
T::ok($a->status === 200, 'kalender werkt na het terugzetten');

echo "Nooit een bestaande database overschrijven\n";
rename(T::$dir . '/config.php', T::$dir . '/config.imported.php');
$c = new Browser();
$c->get('/install.php');
$c->postFiles('/install.php', ['mode' => 'import', 'app_key' => $key], ['database' => ['clubapp.sqlite', $sqlite]]);
T::ok($c->has('wordt niet overschreven'), 'database met gebruikers op dezelfde plek: geweigerd');
rename(T::$dir . '/config.imported.php', T::$dir . '/config.php');
