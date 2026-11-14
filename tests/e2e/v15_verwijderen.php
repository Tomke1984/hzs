<?php
/** v0.15 – Leden, gebruikers en groepen definitief verwijderen. */
T::autoRan();
$board = Browser::login('bestuur@demo.local');
$admin = Browser::login('admin@demo.local');
$trainer = Browser::login('trainer@demo.local');

echo "Lid verwijderen\n";
$emma = (int) T::val("SELECT id FROM members WHERE first_name = 'Emma'");
$emmaUser = (int) T::val("SELECT id FROM users WHERE email = 'zwemmer@demo.local'");
$act = (int) T::val("SELECT a.id FROM activities a JOIN activity_groups ag ON ag.activity_id = a.id JOIN group_memberships gm ON gm.group_id = ag.group_id WHERE gm.member_id = ? ORDER BY a.starts_at LIMIT 1", [$emma]);
T::exec("INSERT INTO attendance (activity_id, member_id, status, updated_at) VALUES (?, ?, 'PRESENT', ?)", [$act, $emma, date('Y-m-d H:i:s')]);
T::exec('INSERT INTO absence_reports (activity_id, member_id, created_at) VALUES (?, ?, ?)', [$act, $emma, date('Y-m-d H:i:s')]);
T::exec('INSERT INTO guardian_links (user_id, member_id, relationship) VALUES ((SELECT id FROM users WHERE email = ?), ?, ?)', ['ouder@demo.local', $emma, 'ouder']);
T::exec('CREATE TABLE dues (id INTEGER PRIMARY KEY, member_id INT, amount_cents INT)'); // oude tabel van vóór v0.13
T::exec('INSERT INTO dues (member_id, amount_cents) VALUES (?, 12500)', [$emma]);

$board->get('/app/leden/' . $emma);
T::ok($board->has('action="app/member-delete"'), 'bestuur ziet de knop Verwijderen op de ledenpagina');
$trainer->get('/app/leden/' . $emma);
T::ok(!$trainer->has('member-delete'), 'trainer ziet geen knop Verwijderen');
$trainer->post('/app/member-delete', ['id' => $emma]);
T::ok($trainer->status === 403, 'trainer kan geen lid verwijderen (403)');

$board->post('/app/member-delete', ['id' => $emma]);
T::ok($board->status === 302, 'bestuur verwijdert het lid');
$board->follow();
T::ok($board->has('definitief verwijderd') && $board->has('zwemmer@demo.local'), 'melding vermeldt het gekoppelde account dat blijft');
T::ok(!T::val('SELECT 1 FROM members WHERE id = ?', [$emma]), 'lid is weg');
foreach (['group_memberships', 'guardian_links', 'attendance', 'absence_reports', 'dues'] as $t) {
    T::ok(!T::val("SELECT 1 FROM $t WHERE member_id = ?", [$emma]), "geen rijen meer in $t");
}
T::ok((bool) T::val('SELECT 1 FROM users WHERE id = ? AND member_id IS NULL', [$emmaUser]), 'gekoppeld account blijft bestaan, zonder lid');
T::ok((bool) T::val("SELECT 1 FROM audit_log WHERE action = 'DELETE' AND entity = 'member' AND entity_id = ?", [$emma]), 'verwijdering in het auditlog');
$board->get('/app/leden/' . $emma);
T::ok($board->status === 404, 'ledenpagina geeft 404');

echo "Gebruiker verwijderen\n";
$kobe = (int) T::val("SELECT id FROM users WHERE email = 'competitie@demo.local'");
$gid = (int) T::val("SELECT id FROM `groups` WHERE name = 'Wedstrijdploeg'");
$typeTraining = (int) T::val("SELECT id FROM activity_types WHERE code = 'TRAINING'");
$data = ['type_id' => $typeTraining, 'title' => 'Ochtend WP', 'location' => 'Kapermolen', 'start_date' => date('Y-m-d'), 'end_date' => date('Y-m-d', strtotime('+20 days')), 'group_ids' => [$gid]];
$data['slot_on'][3] = '1'; $data['slot_start'][3] = '07:00'; $data['slot_end'][3] = '08:00';
$board->post('/app/series-save', $data);
$sid = (int) T::val("SELECT id FROM activity_series WHERE title = 'Ochtend WP'");
$board->post('/app/coverage-settings-save', ['min' => [$gid => ''], 'slot' => [$sid => [3 => ['', (string) $kobe]]]]);
$future = (int) T::val('SELECT id FROM activities WHERE series_id = ? AND starts_at > ? ORDER BY starts_at LIMIT 1', [$sid, date('Y-m-d H:i:s')]);
T::exec("INSERT INTO activity_trainers (activity_id, user_id, status, updated_by, updated_at) VALUES (?, ?, 'ABSENT', ?, ?)", [$future, $kobe, $kobe, date('Y-m-d H:i:s')]);
T::exec('UPDATE activities SET created_by = ? WHERE id = ?', [$kobe, $future]);
T::ok((bool) T::val('SELECT 1 FROM series_trainers WHERE user_id = ?', [$kobe]) && (bool) T::val('SELECT 1 FROM group_trainers WHERE user_id = ?', [$kobe]), 'Kobe is vaste trainer en groepstrainer');

$admin->get('/app/gebruikers/' . $kobe . '/wijzigen');
T::ok($admin->has('Gebruiker verwijderen'), 'beheerder ziet Gebruiker verwijderen');
$board->post('/app/user-delete', ['id' => $kobe]);
T::ok($board->status === 403, 'bestuur kan geen gebruiker verwijderen (403)');
$admin->post('/app/user-delete', ['id' => $kobe]);
$admin->follow();
T::ok($admin->has('definitief verwijderd'), 'beheerder verwijdert de gebruiker');
T::ok(!T::val('SELECT 1 FROM users WHERE id = ?', [$kobe]), 'gebruiker is weg');
foreach (['user_roles', 'group_trainers', 'series_trainers', 'activity_trainers', 'user_tokens'] as $t) {
    T::ok(!T::val("SELECT 1 FROM $t WHERE user_id = ?", [$kobe]), "geen rijen meer in $t");
}
T::ok((bool) T::val('SELECT 1 FROM activities WHERE id = ? AND created_by IS NULL', [$future]), 'activiteit blijft, zonder maker');
$c = Browser::login('coordinator@demo.local');
$c->get('/app/trainersplanning');
T::ok($c->status === 200 && !$c->has('Kobe Competitie'), 'trainersplanning werkt en toont de verwijderde trainer niet meer');

// Ouderaccount verwijderen: kind blijft
$lucasParent = (int) T::val("SELECT id FROM users WHERE email = 'ouder@demo.local'");
$lucas = (int) T::val("SELECT id FROM members WHERE first_name = 'Lucas'");
T::exec('INSERT INTO guardian_links (user_id, member_id, relationship) VALUES ((SELECT id FROM users WHERE email = ?), ?, ?)', ['coordinator@demo.local', $lucas, 'voogd']);
$admin->post('/app/user-delete', ['id' => $lucasParent]);
T::ok(!T::val('SELECT 1 FROM users WHERE id = ?', [$lucasParent]) && (bool) T::val('SELECT 1 FROM members WHERE id = ?', [$lucas]), 'ouder verwijderd, kind blijft');

// Eigen account en laatste beheerder
$me = (int) T::val("SELECT id FROM users WHERE email = 'admin@demo.local'");
$admin->get('/app/gebruikers/' . $me . '/wijzigen');
T::ok(!$admin->has('Gebruiker verwijderen'), 'geen verwijderknop bij je eigen account');
$admin->post('/app/user-delete', ['id' => $me]);
$admin->follow();
T::ok($admin->has('eigen account niet verwijderen') && (bool) T::val('SELECT 1 FROM users WHERE id = ?', [$me]), 'eigen account verwijderen geweigerd');

echo "Groep verwijderen (bestond al)\n";
$kad = (int) T::val("SELECT id FROM `groups` WHERE name = 'Kadetten'");
$n = (int) T::val('SELECT COUNT(*) FROM group_memberships WHERE group_id = ?', [$kad]);
$board->post('/app/group-delete', ['id' => $kad]);
T::ok(!T::val('SELECT 1 FROM `groups` WHERE id = ?', [$kad]) && $n > 0, 'groep met leden verwijderd');
T::ok((int) T::val("SELECT COUNT(*) FROM members WHERE status = 'ACTIVE'") > 0, 'leden blijven bestaan');

echo "Laatste ouder van een minderjarige\n";
$julie = (int) T::val("SELECT id FROM members WHERE first_name = 'Julie'");
$jp = (int) T::val('SELECT user_id FROM guardian_links WHERE member_id = ? LIMIT 1', [$julie]);
$admin->post('/app/user-delete', ['id' => $jp]);
$admin->follow();
T::ok($admin->has('geen ouder of voogd meer') && $admin->has('Julie'), 'waarschuwing als een minderjarige zijn laatste ouder verliest');
