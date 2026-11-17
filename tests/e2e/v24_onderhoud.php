<?php
/**
 * v0.24: sessiemap, back-up, wachtwoord vergeten en wachtwoordregels, vervallende links, cache-busting,
 * versleuteling, genummerde migraties, Purge-bewaking en het opruimen van oude tabellen.
 */
T::autoRan();
date_default_timezone_set('Europe/Brussels'); // zoals de app

$cli = function (string $cmd): array {
    $out = (string) shell_exec('cd ' . escapeshellarg(T::$dir) . ' && ' . $cmd . ' 2>&1; echo "EXIT:$?"');
    preg_match('/EXIT:(\d+)\s*$/', $out, $m);
    return [(int) ($m[1] ?? 1), $out];
};

echo "Sessies in de eigen map\n";
$admin = Browser::login('admin@demo.local');
$admin->get('/app');
$files = glob(T::$dir . '/storage/sessions/sess_*') ?: [];
T::ok(count($files) > 0, 'sessiebestanden staan in storage/sessions (' . count($files) . ')');

echo "Back-up\n";
$dir = T::$dir . '/storage/data/backup';
$backups = glob("$dir/hzs-*.sqlite.gz") ?: [];
T::ok(count($backups) >= 1, 'automatische back-up gemaakt bij het eerste bezoek');
$raw = $backups ? gzdecode((string) file_get_contents($backups[0])) : '';
T::ok(str_starts_with((string) $raw, 'SQLite format 3'), 'back-up is een geldige SQLite-database');
T::ok(is_file("$dir/.htaccess") && str_contains((string) file_get_contents("$dir/.htaccess"), 'Require all denied'), 'back-upmap afgeschermd');
T::ok((string) T::val("SELECT v FROM app_meta WHERE k = 'last_backup'") !== '', 'tijdstip laatste back-up bewaard');
$admin->get('/app');
T::ok(!$admin->has('<strong>Back-up:</strong>'), 'geen waarschuwing na een recente back-up');
T::exec("UPDATE app_meta SET v = '2020-01-01 00:00:00' WHERE k = 'last_backup'");
touch(T::$dir . '/storage/backup-last-run');
$admin->get('/app');
T::ok($admin->has('<strong>Back-up:</strong>'), 'beheerder ziet een waarschuwing als de laatste back-up te oud is');
$trainer = Browser::login('trainer@demo.local');
$trainer->get('/app');
T::ok(!$trainer->has('<strong>Back-up:</strong>'), 'trainer ziet die waarschuwing niet');
@unlink(T::$dir . '/storage/backup-last-run');
$admin->get('/app'); // nieuwe automatische back-up na het antwoord
$admin->get('/app');
T::ok(!$admin->has('<strong>Back-up:</strong>'), 'na de volgende automatische back-up is de waarschuwing weg');
[$code, $out] = $cli('php tools/backup.php');
T::ok($code === 0 && str_contains($out, 'Back-up gemaakt'), 'php tools/backup.php werkt');
[$code, $out] = $cli('php tools/backup.php --list');
T::ok($code === 0 && substr_count($out, '.sqlite.gz') >= 2, 'php tools/backup.php --list toont de back-ups');

echo "Wachtwoord vergeten\n";
T::clearMails();
$anon = new Browser();
for ($i = 0; $i < 4; $i++) {
    $anon->get('/wachtwoord-vergeten');
    $anon->post('/wachtwoord-vergeten', ['email' => 'zwemmer@demo.local', 'captcha_n' => $anon->captcha()]);
}
$uid = (int) T::val("SELECT id FROM users WHERE email = 'zwemmer@demo.local'");
T::ok((int) T::val("SELECT COUNT(*) FROM user_tokens WHERE user_id = ? AND purpose = 'RESET'", [$uid]) === 3, 'hooguit 3 resetmails per uur per account');
T::ok(substr_count(T::outbox(), 'token=') === 3, 'de mails vertrekken (na het antwoord)');
$anon->get('/wachtwoord-vergeten');
$anon->post('/wachtwoord-vergeten', ['email' => 'bestaat-niet@demo.local', 'captcha_n' => $anon->captcha()]);
T::ok($anon->status === 200 && $anon->has('Bestaat er een account'), 'onbekend adres: zelfde melding');
for ($i = 0; $i < 6; $i++) {
    $anon->get('/wachtwoord-vergeten');
    $anon->post('/wachtwoord-vergeten', ['email' => "x$i@demo.local", 'captcha_n' => $anon->captcha()]);
}
T::ok($anon->status === 429, 'na 10 aanvragen per uur vanaf hetzelfde IP-adres: geblokkeerd (' . $anon->status . ')');
$ok = Browser::login('bestuur@demo.local');
$ok->get('/app');
T::ok($ok->status === 200, 'aanmelden blijft mogelijk (aanvragen tellen niet als mislukte login)');
T::exec("DELETE FROM login_attempts WHERE email LIKE 'forgot:%'");

echo "Wachtwoordregels\n";
$p = Browser::login('ouder@demo.local');
foreach (['wachtwoord123' => 'veelgebruikte', 'qwertyuiop' => 'veelgebruikte', 'aaaaaaaaab' => 'te weinig verschillende', '1234567890' => 'veelgebruikte', 'ouder.van.lucas' => 'naam of e-mailadres'] as $pw => $why) {
    $p->get('/app/account');
    $p->post('/app/account-password', ['current_password' => 'Demo1234!', 'new_password' => $pw, 'new_password2' => $pw]);
    $p->follow();
    T::ok(!$p->has('Je wachtwoord is gewijzigd'), "geweigerd: $pw ($why)");
}
$p->post('/app/account-password', ['current_password' => 'Demo1234!', 'new_password' => 'Blauw zwembad 42!', 'new_password2' => 'Blauw zwembad 42!']);
$p->follow();
T::ok($p->has('Je wachtwoord is gewijzigd'), 'een sterk wachtwoord wordt aanvaard');
// Rehash: een hash met een lagere kost wordt bij het aanmelden bijgewerkt
T::exec("UPDATE users SET password_hash = ? WHERE email = 'competitie@demo.local'", [password_hash('Demo1234!', PASSWORD_BCRYPT, ['cost' => 4])]);
Browser::login('competitie@demo.local');
T::ok(!str_starts_with((string) T::val("SELECT password_hash FROM users WHERE email = 'competitie@demo.local'"), '$2y$04$'), 'zwakkere hash bijgewerkt bij het aanmelden');

echo "Vervallende links\n";
$board = Browser::login('coordinator@demo.local');
$gid = (int) T::val("SELECT id FROM `groups` WHERE is_active = 1 ORDER BY id LIMIT 1");
$board->get('/app/trainersplanning/instellingen');
$board->post('/app/coverage-share', ['group_id' => $gid]);
$tok = (string) T::val('SELECT share_token FROM `groups` WHERE id = ?', [$gid]);
T::ok(T::val('SELECT share_expires FROM `groups` WHERE id = ?', [$gid]) === date('Y-m-d', strtotime('+365 days')), 'deellink geldig voor 365 dagen');
$anon->get('/?r=weekplanning&t=' . $tok);
T::ok($anon->status === 200, 'geldige deellink werkt');
T::exec('UPDATE `groups` SET share_expires = ? WHERE id = ?', [date('Y-m-d', strtotime('-1 day')), $gid]);
$anon->get('/?r=weekplanning&t=' . $tok);
T::ok($anon->status === 410 && $anon->has('verlopen'), 'verlopen deellink geeft 410');
$board->get('/app/trainersplanning/instellingen');
T::ok($board->has('Verlopen op'), 'instellingen tonen dat de link verlopen is');

$p->get('/app/account');
$p->post('/app/account-ical', []);
$ical = (string) T::val("SELECT ical_token FROM users WHERE email = 'ouder@demo.local'");
T::ok(T::val("SELECT ical_last_used FROM users WHERE email = 'ouder@demo.local'") === date('Y-m-d'), 'iCal-link: laatst gebruikt = vandaag');
$anon->get('/app/ical?t=' . $ical);
T::ok($anon->status === 200 && $anon->has('BEGIN:VCALENDAR'), 'iCal-link werkt');
T::exec("UPDATE users SET ical_last_used = ? WHERE email = 'ouder@demo.local'", [date('Y-m-d', strtotime('-200 days'))]);
$anon->get('/app/ical?t=' . $ical);
T::ok($anon->status === 404 && !T::val("SELECT ical_token FROM users WHERE email = 'ouder@demo.local'"), 'iCal-link die 180 dagen niet gebruikt werd, vervalt');

echo "Cache-busting en compressie\n";
$admin->get('/app');
T::ok((bool) preg_match('#assets/style\.css\?v=\d+#', $admin->body) && (bool) preg_match('#assets/app\.js\?v=\d+#', $admin->body), 'CSS en JS met versie in het adres');
T::ok(str_contains((string) file_get_contents(T::$dir . '/public/assets/.htaccess'), 'immutable'), 'assets mogen een jaar in de cache');
T::ok(str_contains((string) file_get_contents(T::$dir . '/public/.htaccess'), 'DEFLATE'), 'compressie aan in .htaccess');

echo "Versleuteling\n";
$mid = (int) T::val("SELECT id FROM members WHERE first_name = 'Emma'");
$member = T::all('SELECT * FROM members WHERE id = ?', [$mid])[0];
$tr = Browser::login('admin@demo.local');
$tr->get('/app/leden/' . $mid . '/wijzigen');
preg_match('/name="status"[^>]*>.*?<option value="([A-Z]+)" selected/s', $tr->body, $st);
$form = ['id' => $mid, 'first_name' => $member['first_name'], 'last_name' => $member['last_name'], 'birth_date' => (string) $member['birth_date'],
    'email' => (string) $member['email'], 'phone' => (string) $member['phone'], 'status' => $member['status'],
    'emergency_name' => 'Papa', 'emergency_phone' => '0470 00 00 00', 'medical_note' => 'Allergie: noten'];
$tr->post('/app/member-save', $form);
$stored = (string) T::val('SELECT medical_note FROM members WHERE id = ?', [$mid]);
T::ok(str_starts_with($stored, 'enc1:') && !str_contains($stored, 'noten'), 'medische opmerking versleuteld in de database');
T::ok(is_file(T::$dir . '/storage/app.key'), 'sleutel automatisch aangemaakt (storage/app.key)');
$coach = Browser::login('trainer@demo.local');
T::exec("DELETE FROM audit_log WHERE action = 'VIEW_MEDICAL'");
$coach->get('/app/leden/' . $mid);
if ($coach->status === 200) {
    T::ok($coach->has('Allergie: noten'), 'trainer met recht ziet de ontsleutelde tekst');
    T::ok((int) T::val("SELECT COUNT(*) FROM audit_log WHERE action = 'VIEW_MEDICAL' AND entity_id = ?", [$mid]) === 1, 'bekijken staat in de auditlog');
    $coach->get('/app/leden/' . $mid);
    T::ok((int) T::val("SELECT COUNT(*) FROM audit_log WHERE action = 'VIEW_MEDICAL' AND entity_id = ?", [$mid]) === 1, 'hooguit één logregel per dag');
} else {
    $tr->get('/app/leden/' . $mid);
    T::ok($tr->has('Allergie: noten'), 'beheerder ziet de ontsleutelde tekst');
}
// Bestaande, onversleutelde gegevens worden bij de update versleuteld; de oude 2FA-kolommen verdwijnen (v0.25, migratie 017)
T::exec("UPDATE members SET medical_note = 'Oud: epilepsie' WHERE id = ?", [$mid]);
T::exec('ALTER TABLE users ADD COLUMN totp_secret VARCHAR(255) NULL');
T::exec('ALTER TABLE users ADD COLUMN totp_enabled TINYINT NOT NULL DEFAULT 0');
T::exec("UPDATE users SET totp_secret = 'JBSWY3DPEHPK3PXP', totp_enabled = 1 WHERE email = 'bestuur@demo.local'");
T::oldDatabase();
$tr->get('/app');
T::ok(str_starts_with((string) T::val('SELECT medical_note FROM members WHERE id = ?', [$mid]), 'enc1:'), 'oude medische opmerking versleuteld bij de update');
T::ok((int) T::val("SELECT COUNT(*) FROM pragma_table_info('users') WHERE name IN ('totp_secret', 'totp_enabled')") === 0, 'oude 2FA-kolommen opgeruimd bij de update');
$b = Browser::login('bestuur@demo.local');
$b->get('/app');
T::ok($b->status === 200, 'wie vroeger 2FA had, meldt nu aan met wachtwoord en controlevraag');
$admin->get('/app');
T::ok($admin->has('<strong>Sleutel:</strong>'), 'beheerder krijgt de raad de sleutel naar config.php te verplaatsen');
T::config(['app_key' => trim((string) file_get_contents(T::$dir . '/storage/app.key'))]);
rename(T::$dir . '/storage/app.key', T::$dir . '/storage/app.key.bak');
$admin->get('/app');
T::ok(!$admin->has('<strong>Sleutel:</strong>'), 'met app_key in config.php geen melding meer');
$admin->get('/app/leden/' . $mid);
T::ok($admin->has('Oud: epilepsie'), 'ontsleutelen werkt met de sleutel uit config.php');

echo "Genummerde migraties\n";
T::ok((bool) T::val("SELECT 1 FROM schema_migrations WHERE name = '016_versleuteling_vervaldata_opruimen'"), 'migratie 016 geregistreerd');
foreach ([['groups', 'fee_cents'], ['groups', 'goals'], ['groups', 'next_group_id'], ['activities', 'signup_capacity'], ['activity_series', 'capacity']] as [$t, $c]) {
    T::ok(!T::val("SELECT 1 FROM pragma_table_info('$t') WHERE name = ?", [$c]), "oude kolom $t.$c verwijderd");
}
file_put_contents(T::$dir . '/db/migrations/999_test.php', "<?php\nreturn function (): void { Database::exec('CREATE TABLE IF NOT EXISTS migratie_test (id INTEGER)'); Database::exec('INSERT INTO migratie_test (id) VALUES (1)'); };\n");
$tr->get('/app');
$tr->get('/app');
T::ok((int) T::val('SELECT COUNT(*) FROM migratie_test') === 1, 'nieuwe migratie loopt precies één keer');
T::ok(T::val("SELECT v FROM app_meta WHERE k = 'migration'") === '999_test', 'laatste migratie onthouden');
unlink(T::$dir . '/db/migrations/999_test.php');
T::exec('DROP TABLE migratie_test');
T::exec("DELETE FROM schema_migrations WHERE name = '999_test'");

echo "Purge: elke verwijzing naar een lid of gebruiker is gedekt\n";
require_once T::$dir . '/app/Purge.php';
$memberCovered = array_merge(array_map(fn($t) => "$t.member_id", Purge::MEMBER_DELETE), array_map(fn($x) => "$x[0].$x[1]", Purge::MEMBER_NULL));
$userCovered = array_merge(array_map(fn($t) => "$t.user_id", Purge::USER_DELETE), array_map(fn($x) => "$x[0].$x[1]", Purge::USER_NULL), ['audit_log.user_id']);
preg_match_all('/CREATE TABLE IF NOT EXISTS `?([a-z_]+)`?/', (string) file_get_contents(T::$dir . '/db/schema.sql'), $tm);
$missing = [];
foreach ($tm[1] as $t) {
    foreach (T::all("SELECT * FROM pragma_foreign_key_list('$t')") as $fk) {
        if ($fk['table'] === 'members' && !in_array("$t.{$fk['from']}", $memberCovered, true)) {
            $missing[] = "$t.{$fk['from']} → members";
        }
        if ($fk['table'] === 'users' && !in_array("$t.{$fk['from']}", $userCovered, true)) {
            $missing[] = "$t.{$fk['from']} → users";
        }
    }
    foreach (T::all("SELECT name FROM pragma_table_info('$t')") as $col) {
        if ($col['name'] === 'member_id' && $t !== 'members' && !in_array("$t.member_id", $memberCovered, true)) {
            $missing[] = "$t.member_id";
        }
        if ($col['name'] === 'user_id' && !in_array("$t.user_id", $userCovered, true)) {
            $missing[] = "$t.user_id";
        }
    }
}
T::ok($missing === [], 'Purge dekt alle verwijzingen' . ($missing ? ': ontbreekt ' . implode(', ', array_unique($missing)) : ''));

echo "Oude tabellen opruimen\n";
T::exec('CREATE TABLE dues (id INTEGER PRIMARY KEY, member_id INT, amount_cents INT)');
T::exec('INSERT INTO dues (member_id, amount_cents) VALUES (1, 12500)');
[$code, $out] = $cli('php tools/legacy-data.php');
T::ok($code === 0 && str_contains($out, 'dues') && str_contains($out, '1 rijen'), 'tool toont de oude tabellen');
[$code] = $cli('php tools/legacy-data.php --drop');
T::ok($code === 1 && T::val("SELECT 1 FROM sqlite_master WHERE name = 'dues'"), 'wissen zonder export wordt geweigerd');
[$code, $out] = $cli('php tools/legacy-data.php --export --drop');
$exports = glob($dir . '/legacy-*.json.gz') ?: [];
T::ok($code === 0 && count($exports) === 1 && !T::val("SELECT 1 FROM sqlite_master WHERE name = 'dues'"), 'eerst geëxporteerd, dan gewist');
$json = json_decode((string) gzdecode((string) file_get_contents($exports[0] ?? '')), true);
T::ok(($json['dues'][0]['amount_cents'] ?? null) == 12500, 'export bevat de gegevens');
