<?php
/**
 * v0.28: meerderjarige leden krijgen automatisch een account; ouders vragen een eigen account aan voor een minderjarig kind.
 * Het kind ziet de eigen kalender en wijzigt enkel e-mail en telefoon; al de rest blijft bij de ouders.
 */
T::autoRan();

$parentId = (int) T::val("SELECT id FROM users WHERE email = 'ouder@demo.local'");
$childId = (int) T::val('SELECT gl.member_id FROM guardian_links gl JOIN members m ON m.id = gl.member_id WHERE gl.user_id = ? ORDER BY gl.member_id LIMIT 1', [$parentId]);
T::exec('UPDATE members SET birth_date = ?, email = NULL WHERE id = ?', [date('Y-m-d', strtotime('-13 years')), $childId]);
$child = T::all('SELECT * FROM members WHERE id = ?', [$childId])[0];

echo "Meerderjarig lid: automatisch een account\n";
$board = Browser::login('bestuur@demo.local');
$admin = Browser::login('admin@demo.local');
$admin->get('/app/leden/nieuw');
T::ok($admin->has('name="create_account" value="1" checked'), 'nieuw lid: vinkje "eigen account" staat standaard aan');
T::clearMails();
$admin->post('/app/member-save', ['first_name' => 'Sofie', 'last_name' => 'Volwassen', 'birth_date' => '1990-04-04', 'email' => 'sofie@voorbeeld.be', 'status' => 'ACTIVE', 'create_account' => '1']);
$admin->follow();
$sofie = (int) T::val("SELECT id FROM members WHERE email = 'sofie@voorbeeld.be'");
T::ok($admin->has('Eigen account: Uitnodiging verstuurd naar sofie@voorbeeld.be'), 'melding: uitnodiging verstuurd');
T::ok((bool) T::val("SELECT 1 FROM users u JOIN user_roles ur ON ur.user_id = u.id JOIN roles r ON r.id = ur.role_id WHERE u.member_id = ? AND r.code = 'SWIMMER'", [$sofie]), 'account met rol Zwemmer, gekoppeld aan het lid');
T::ok(str_contains(T::outbox(), 'aan sofie@voorbeeld.be'), 'uitnodiging in de mail');
$admin->get('/app/leden/' . $sofie . '/wijzigen');
T::ok(!$admin->has('name="create_account"'), 'heeft al een account: geen vinkje meer');
$admin->post('/app/member-save', ['first_name' => 'Kai', 'last_name' => 'Jong', 'birth_date' => date('Y-m-d', strtotime('-10 years')), 'email' => 'kai@voorbeeld.be', 'status' => 'ACTIVE', 'create_account' => '1']);
T::ok(!T::val("SELECT 1 FROM users WHERE email = 'kai@voorbeeld.be'"), 'minderjarig lid: geen automatisch account');
$admin->post('/app/member-save', ['first_name' => 'Ruth', 'last_name' => 'Zonder', 'birth_date' => '1985-01-01', 'email' => 'ruth@voorbeeld.be', 'status' => 'ACTIVE']);
T::ok(!T::val("SELECT 1 FROM users WHERE email = 'ruth@voorbeeld.be'"), 'vinkje uit: geen account');
$admin->post('/app/member-save', ['first_name' => 'Ouder', 'last_name' => 'Dubbel', 'birth_date' => '1980-01-01', 'email' => 'ouder@demo.local', 'status' => 'ACTIVE', 'create_account' => '1']);
$admin->follow();
T::ok($admin->has('wordt al gebruikt'), 'e-mailadres al in gebruik: duidelijke melding, lid wel opgeslagen');

echo "Ouder vraagt een account aan voor een kind\n";
$parent = Browser::login('ouder@demo.local');
$parent->get('/app/leden/' . $childId);
T::ok($parent->has('id="eigen-account"') && $parent->has('Eigen account aanvragen'), 'kindpagina: account aanvragen');
$parent->post('/app/child-account', ['member_id' => $childId, 'email' => 'ouder@demo.local']);
$parent->follow();
T::ok($parent->has('wordt al gebruikt') && !T::val('SELECT 1 FROM users WHERE member_id = ?', [$childId]), 'e-mail van de ouder zelf: geweigerd');
T::clearMails();
$parent->post('/app/child-account', ['member_id' => $childId, 'email' => 'kind@voorbeeld.be']);
$parent->follow();
$kidUser = (int) T::val('SELECT id FROM users WHERE member_id = ?', [$childId]);
T::ok($kidUser > 0 && $parent->has('krijgt een eigen account'), 'account aangemaakt');
T::ok(str_contains(T::outbox(), 'aan kind@voorbeeld.be') && str_contains(T::outbox(), 'Je ouder heeft een eigen account voor je aangevraagd'), 'kind krijgt de uitnodiging');
T::ok(T::val('SELECT email FROM members WHERE id = ?', [$childId]) === 'kind@voorbeeld.be', 'e-mailadres ook bij het lid ingevuld (was leeg)');
T::ok($parent->has('Uitnodiging nog niet aanvaard'), 'ouder ziet de status');
$other = (int) T::val('SELECT id FROM members WHERE id NOT IN (SELECT member_id FROM guardian_links WHERE user_id = ?) AND id <> ? ORDER BY id LIMIT 1', [$parentId, $sofie]);
$parent->post('/app/child-account', ['member_id' => $other, 'email' => 'vreemd@voorbeeld.be']);
T::ok($parent->status === 404 && !T::val("SELECT 1 FROM users WHERE email = 'vreemd@voorbeeld.be'"), 'niet voor het kind van iemand anders');

echo "Het kind meldt aan\n";
T::exec('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash('Demo1234!', PASSWORD_DEFAULT), $kidUser]);
$kid = Browser::login('kind@voorbeeld.be');
$kid->get('/app');
T::ok($kid->status === 200 && $kid->has('Volgende training'), 'kind ziet het overzicht met de volgende training');
T::ok($kid->has('Vraag je ouder om je af te melden') && !$kid->has('name="member_id" value="' . $childId . '"'), 'kind kan zich niet zelf afmelden');
$aid = (int) T::val("SELECT a.id FROM activities a JOIN activity_groups ag ON ag.activity_id = a.id JOIN group_memberships gm ON gm.group_id = ag.group_id WHERE gm.member_id = ? AND a.starts_at > ? AND a.status = 'PLANNED' ORDER BY a.starts_at LIMIT 1", [$childId, date('Y-m-d H:i:s')]);
if ($aid) {
    $kid->post('/app/absence', ['activity_id' => $aid, 'member_id' => $childId, 'reason' => 'Geen zin']);
    T::ok($kid->status === 403 && !T::val('SELECT 1 FROM absence_reports WHERE activity_id = ? AND member_id = ?', [$aid, $childId]), 'afmelden via een omweg: geweigerd');
}
$kid->get('/app/kalender');
T::ok($kid->status === 200, 'kind ziet de kalender');
$kid->get('/app/leden/' . $childId);
T::ok($kid->has('Mijn e-mail en telefoon wijzigen') && !$kid->has('>Gegevens wijzigen<'), 'eigen pagina: enkel e-mail en telefoon wijzigen');
T::ok(!$kid->has('id="eigen-account"') && !$kid->has('Noodcontact'), 'geen accountbeheer en geen noodgegevens');

$kid->get('/app/leden/' . $childId . '/mijn-gegevens');
T::ok($kid->status === 200 && $kid->has('name="email"') && $kid->has('name="phone"') && !$kid->has('name="first_name"') && !$kid->has('name="street"') && !$kid->has('name="medical_note"'), 'formulier met enkel e-mail en telefoon');
$kid->post('/app/child-save', ['id' => $childId, 'email' => 'kind.nieuw@voorbeeld.be', 'phone' => '0499 11 22 33',
    'first_name' => 'Gewijzigd', 'last_name' => 'Gewijzigd', 'birth_date' => '1990-01-01', 'street' => 'Hackstraat 1', 'medical_note' => 'hack']);
$row = T::all('SELECT * FROM members WHERE id = ?', [$childId])[0];
T::ok($row['email'] === 'kind.nieuw@voorbeeld.be' && $row['phone'] === '0499 11 22 33', 'e-mail en telefoon aangepast');
T::ok($row['first_name'] === $child['first_name'] && $row['birth_date'] === $child['birth_date'] && $row['street'] === $child['street'], 'naam, geboortedatum en adres onveranderd');
T::ok((string) $row['medical_note'] === (string) $child['medical_note'], 'medische opmerking onveranderd');
T::ok((bool) T::val("SELECT 1 FROM audit_log WHERE action = 'UPDATE_OWN' AND entity_id = ? AND detail LIKE 'door het lid zelf%'", [$childId]), 'auditlog: door het lid zelf');
$kid->get('/app/leden/' . $other . '/mijn-gegevens');
T::ok($kid->status === 404, 'kind kan geen ander lid wijzigen');

echo "Ouder trekt het account in\n";
$parent->get('/app/leden/' . $childId);
T::ok($parent->has('Account van') && $parent->has('child-account-delete'), 'ouder ziet de knop in de gevarenzone');
$parent->post('/app/child-account-delete', ['member_id' => $childId]);
$parent->follow();
T::ok(!T::val('SELECT 1 FROM users WHERE id = ?', [$kidUser]) && $parent->has('is verwijderd'), 'account van het kind verwijderd');
T::ok((bool) T::val('SELECT 1 FROM members WHERE id = ?', [$childId]), 'het lid zelf blijft bestaan');
$kid->get('/app');
T::ok($kid->status === 302, 'kind is meteen afgemeld');

echo "Bestuur\n";
$board->get('/app/leden/' . $childId);
T::ok(!$board->has('id="eigen-account"'), 'bestuur (zonder gebruikersbeheer) ziet het blok niet: dat doen ouder of beheerder');
$admin->get('/app/leden/' . $childId);
T::ok($admin->has('id="eigen-account"'), 'beheerder kan voor een minderjarige ook een account aanvragen');

echo "Mailinstellingen in de app\n";
$admin->get('/app/instellingen/mail');
T::ok($admin->status === 200 && $admin->has('Mailinstellingen') && $admin->has('Testmodus'), 'beheerder: pagina mailinstellingen, toont testmodus');
$board->get('/app/instellingen/mail');
T::ok($board->status === 403, 'bestuur (zonder gebruikersbeheer): geen toegang');
$admin->post('/app/mail-settings-save', ['driver' => 'smtp', 'from_email' => '', 'smtp_host' => '']);
T::ok($admin->status === 422 && $admin->has('geldig afzenderadres') && $admin->has('SMTP-server'), 'onvolledige instellingen: geweigerd');
// Een SMTP-server die niet antwoordt: testmail en uitnodiging mislukken met een duidelijke melding
$admin->post('/app/mail-settings-save', ['driver' => 'smtp', 'from_email' => 'noreply@khzs.be', 'from_name' => 'HZS', 'smtp_host' => '127.0.0.1', 'smtp_port' => '1', 'smtp_encryption' => 'none', 'smtp_user' => 'club', 'smtp_pass' => 'Geheim123!']);
$admin->follow();
T::ok($admin->has('Opgeslagen.'), 'SMTP-instellingen opgeslagen');
$stored = (string) T::val("SELECT v FROM settings WHERE k = 'mail'");
T::ok(str_contains($stored, '"host":"127.0.0.1"') && !str_contains($stored, 'Geheim123!') && str_contains($stored, 'enc1:'), 'wachtwoord versleuteld bewaard');
$admin->post('/app/mail-test', []);
$admin->follow();
T::ok($admin->has('Testmail mislukt') && $admin->has('SMTP-verbinding mislukt'), 'testmail: foutmelding van de SMTP-server');
$admin->get('/app/instellingen/mail');
T::ok(!$admin->has('Geheim123!') && $admin->has('ongewijzigd laten'), 'wachtwoord wordt niet getoond');
$admin->post('/app/mail-settings-save', ['driver' => 'smtp', 'from_email' => 'noreply@khzs.be', 'smtp_host' => '127.0.0.1', 'smtp_port' => '1', 'smtp_encryption' => 'none', 'smtp_user' => 'club', 'smtp_pass' => '']);
T::ok(str_contains((string) T::val("SELECT v FROM settings WHERE k = 'mail'"), 'enc1:'), 'leeg wachtwoordveld: bestaand wachtwoord behouden');
$kid2 = (int) T::val("SELECT id FROM members WHERE first_name = 'Kai'");
T::exec('INSERT INTO guardian_links (user_id, member_id) VALUES (?, ?)', [$parentId, $kid2]);
$parent->post('/app/child-account', ['member_id' => $kid2, 'email' => 'kai.zelf@voorbeeld.be']);
$parent->follow();
T::ok($parent->has('uitnodiging kon niet verstuurd worden'), 'mail lukt niet: de ouder krijgt dat te horen');
$admin->post('/app/mail-settings-save', ['driver' => 'log', 'from_email' => 'noreply@khzs.be']);
T::clearMails();
$admin->post('/app/invite-resend', ['user_id' => (int) T::val("SELECT id FROM users WHERE email = 'kai.zelf@voorbeeld.be'")]);
$admin->follow();
T::ok($admin->has('er is GEEN mail verstuurd') && $admin->has('Mailinstellingen'), 'testmodus: melding zegt duidelijk dat er geen mail vertrok');
