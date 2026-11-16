<?php
/**
 * v0.19: database afschermen, snelheid (indexen, schemaversie, WAL, geen N+1) en sessiebeveiliging.
 */
T::autoRan();

echo "Snelheid: schemaversie, indexen, WAL\n";
$admin = Browser::login('admin@demo.local');
$admin->get('/app');
T::ok($admin->status === 200, 'app werkt');
T::ok((int) T::val("SELECT v FROM app_meta WHERE k = 'schema'") === 15, 'schemaversie staat in de database (app_meta)');
T::ok((string) T::val("SELECT v FROM app_meta WHERE k = 'reference'") !== '', 'vingerafdruk van rollen en rechten bewaard');
$idx = (int) T::val("SELECT COUNT(*) FROM sqlite_master WHERE type = 'index' AND name LIKE 'idx_%'");
T::ok($idx === 17, "alle indexen aangemaakt ($idx)");
T::ok(strtolower((string) T::val('PRAGMA journal_mode')) === 'wal', 'SQLite draait in WAL-modus');

// Upgrade van een oudere database: geen app_meta, geen indexen, geen kolom session_version
T::oldDatabase();
T::exec('ALTER TABLE users DROP COLUMN session_version');
$b = Browser::login('bestuur@demo.local');
$b->get('/app');
T::ok($b->status === 200, 'na de update werkt de app');
T::ok((int) T::val("SELECT v FROM app_meta WHERE k = 'schema'") === 15, 'na de update: schemaversie bijgewerkt');
T::ok((int) T::val("SELECT COUNT(*) FROM sqlite_master WHERE type = 'index' AND name LIKE 'idx_%'") === 17, 'na de update: indexen aangemaakt');
T::ok((bool) T::val("SELECT 1 FROM pragma_table_info('users') WHERE name = 'session_version'"), 'na de update: kolom session_version');

// Rechten worden enkel opnieuw gesynchroniseerd als de code (rollen/permissies) veranderde
$perm = "SELECT COUNT(*) FROM role_permissions rp JOIN roles r ON r.id = rp.role_id JOIN permissions p ON p.id = rp.permission_id WHERE r.code = 'COORDINATOR' AND p.code = 'coverage.manage'";
T::exec("DELETE FROM role_permissions WHERE role_id = (SELECT id FROM roles WHERE code = 'COORDINATOR') AND permission_id = (SELECT id FROM permissions WHERE code = 'coverage.manage')");
$b->get('/app');
T::ok((int) T::val($perm) === 0, 'geen controle van rollen/rechten bij elk verzoek');
T::exec("UPDATE app_meta SET v = 'oud' WHERE k = 'reference'");
$b->get('/app');
T::ok((int) T::val($perm) === 1, 'na een codewijziging aan rollen/rechten: aangevuld');

// Kalender: wedstrijd "van jou" zonder extra query per activiteit (zelfde resultaat als voorheen)
$p = Browser::login('ouder@demo.local');
$p->get('/app/kalender');
T::ok($p->status === 200 && $p->has('cal-'), 'kalender voor ouders werkt');

echo "Database buiten de webmap\n";
foreach (['db', 'tools', 'docs'] as $d) {
    T::ok(str_contains((string) @file_get_contents(T::$dir . "/$d/.htaccess"), 'Require all denied'), "$d/ geweigerd, ook zonder mod_rewrite");
}
// Regressie v0.22: een .htaccess met "Require all denied" in app/ blokkeert op Apache (webroot = hoofdmap) elke
// pagina onder /app met "Forbidden", omdat Apache die leest vóór de herschrijfregel naar public/.
T::ok(!is_file(T::$dir . '/app/.htaccess'), 'geen .htaccess in app/ (zou /app op Apache blokkeren)');
T::ok(str_contains((string) file_get_contents(T::$dir . '/.htaccess'), 'DirectorySlash Off'), '/app zonder doorverwijzing naar /app/');
$ht = (string) file_get_contents(T::$dir . '/.htaccess');
T::ok(str_contains($ht, '<FilesMatch') && str_contains($ht, 'sqlite') && str_contains($ht, 'config'), 'hoofd-.htaccess weigert .sqlite en config.php');
$admin->get('/app');
T::ok(!$admin->has('het databasebestand staat binnen de webmap'), 'geen waarschuwing als de database buiten de webmap staat');
$inside = T::$dir . '/public/uploads/kopie.sqlite';
T::pdo()->exec("VACUUM INTO '" . $inside . "'");
$origDb = (require T::$dir . '/config.php')['db'];
T::config(['db' => ['path' => $inside]]);
$a2 = Browser::login('admin@demo.local');
$a2->get('/app');
T::ok($a2->has('het databasebestand staat binnen de webmap'), 'beheerder krijgt een waarschuwing als de database in de webmap staat');
$t2 = Browser::login('trainer@demo.local');
$t2->get('/app');
T::ok(!$t2->has('het databasebestand staat binnen de webmap'), 'trainer krijgt die waarschuwing niet');
T::config(['db' => $origDb]);
@unlink($inside);

echo "Sessies\n";
// Eigen wachtwoord wijzigen: andere toestellen worden afgemeld, het huidige blijft aangemeld
$phone = Browser::login('trainer@demo.local');
$laptop = Browser::login('trainer@demo.local');
$laptop->get('/app/account');
$laptop->post('/app/account-password', ['current_password' => 'Demo1234!', 'new_password' => 'NieuwWacht1!', 'new_password2' => 'NieuwWacht1!']);
$laptop->follow();
T::ok($laptop->status === 200 && $laptop->has('Op andere toestellen moet je opnieuw aanmelden'), 'wachtwoord gewijzigd, huidige sessie blijft');
$phone->get('/app');
T::ok($phone->status === 302 && str_contains($phone->location, 'aanmelden'), 'andere sessie is afgemeld na nieuw wachtwoord');
$laptop->get('/app/leden');
T::ok($laptop->status === 200, 'huidige sessie werkt nog');

// Beheerder zet 2FA van een gebruiker uit: diens sessies vervallen
$swimmer = Browser::login('zwemmer@demo.local');
$sid = (int) T::val("SELECT id FROM users WHERE email = 'zwemmer@demo.local'");
$admin->get('/app/gebruikers/' . $sid . '/wijzigen');
$admin->post('/app/user-2fa-reset', ['id' => $sid]);
$swimmer->get('/app');
T::ok($swimmer->status === 302, 'reset door beheerder meldt de gebruiker af');
$admin->get('/app');
T::ok($admin->status === 200, 'beheerder zelf blijft aangemeld');

// Wachtwoord vergeten + nieuwe link: oude sessies vervallen
$parent = Browser::login('ouder@demo.local');
T::clearMails();
$anon = new Browser();
$anon->get('/wachtwoord-vergeten');
$anon->post('/wachtwoord-vergeten', ['email' => 'ouder@demo.local']);
preg_match('/token=([a-f0-9]{64})/', T::outbox(), $m);
T::ok(isset($m[1]), 'resetlink verstuurd');
$anon->get('/activeren?token=' . ($m[1] ?? ''));
$anon->post('/activeren', ['token' => $m[1] ?? '', 'password' => 'AnderWacht1!', 'password2' => 'AnderWacht1!']);
$parent->get('/app');
T::ok($parent->status === 302, 'na een wachtwoordreset zijn bestaande sessies afgemeld');

// Account uitgezet: meteen afgemeld (bestond al, blijft werken)
$coach = Browser::login('competitie@demo.local');
$coach->get('/app');
$cid = (int) T::val("SELECT id FROM users WHERE email = 'competitie@demo.local'");
T::exec('UPDATE users SET is_active = 0 WHERE id = ?', [$cid]);
$coach->get('/app');
T::ok($coach->status === 302, 'uitgezet account is meteen afgemeld');
T::exec('UPDATE users SET is_active = 1 WHERE id = ?', [$cid]);

echo "Tweestapsverificatie voor bestuur\n";
T::config(['security' => ['require_2fa_admin' => true]]);
$bb = Browser::login('bestuur@demo.local');
$bb->get('/app/leden');
T::ok($bb->status === 302 && str_contains($bb->location, 'account'), 'bestuur moet eerst 2FA aanzetten');
$tt = Browser::login('competitie@demo.local');
$tt->get('/app');
T::ok($tt->status === 200, 'trainer hoeft geen 2FA');
T::config(['security' => ['require_2fa_admin' => false]]);
$bb->get('/app/leden');
T::ok($bb->status === 200, 'met require_2fa_admin = false geen verplichting');

echo "Secure cookie over HTTPS\n";
$h = new Browser();
$h->headers = ['X-Forwarded-Proto: https'];
$h->get('/aanmelden');
$c = implode("\n", $h->setCookies);
T::ok(stripos($c, 'hzs_session=') !== false && stripos($c, 'secure') !== false, 'over HTTPS krijgt het sessiecookie de vlag Secure');
$plain = new Browser();
$plain->get('/aanmelden');
T::ok(stripos(implode("\n", $plain->setCookies), 'secure') === false, 'lokaal over HTTP geen Secure-vlag');
