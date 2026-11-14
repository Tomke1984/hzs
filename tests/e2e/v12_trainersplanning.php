<?php
/** v0.12 – Trainersplanning competitie. */

T::autoRan(); // automatische herinnering pas testen als we dat zelf willen

$gid = (int) T::val("SELECT id FROM `groups` WHERE name = 'Wedstrijdploeg'");
// Sinds v0.14 zijn alle groepen competitiegroepen. Tina is hier een trainer zonder groep (dus geen competitietrainer).
T::exec("DELETE FROM group_trainers WHERE user_id = (SELECT id FROM users WHERE email = 'trainer@demo.local')");
$coordId = (int) T::val("SELECT id FROM users WHERE email = 'coordinator@demo.local'");
$kobeId = (int) T::val("SELECT id FROM users WHERE email = 'competitie@demo.local'");
$tinaId = (int) T::val("SELECT id FROM users WHERE email = 'trainer@demo.local'");
$typeTraining = (int) T::val("SELECT id FROM activity_types WHERE code = 'TRAINING'");

echo "Rollen en toegang\n";
T::ok((bool) T::val("SELECT 1 FROM roles WHERE code = 'COORDINATOR'"), 'rol Coördinator competitie bestaat');
T::ok((bool) T::val("SELECT 1 FROM role_permissions rp JOIN roles r ON r.id = rp.role_id JOIN permissions p ON p.id = rp.permission_id WHERE r.code = 'BOARD' AND p.code = 'coverage.manage'"), 'bestuur heeft coverage.manage');
T::ok(!T::val("SELECT 1 FROM role_permissions rp JOIN roles r ON r.id = rp.role_id JOIN permissions p ON p.id = rp.permission_id WHERE r.code = 'TRAINER' AND p.code = 'coverage.manage'"), 'trainer heeft geen coverage.manage');

$coord = Browser::login('coordinator@demo.local');
$kobe = Browser::login('competitie@demo.local');
$tina = Browser::login('trainer@demo.local');      // trainer zonder groep (zwemt zelf in de Wedstrijdploeg)
$parent = Browser::login('ouder@demo.local');
$board = Browser::login('bestuur@demo.local');
$admin = Browser::login('admin@demo.local');

$coord->get('/app/trainersplanning');
T::ok($coord->status === 200 && $coord->has('Trainersplanning competitie'), 'coördinator ziet de trainersplanning');
T::ok($coord->has('Vaste trainers en instellingen'), 'coördinator ziet de knop instellingen');
T::ok($coord->has('href="app/trainersplanning"'), 'menu-item Trainersplanning voor de coördinator');
$kobe->get('/app/trainersplanning');
T::ok($kobe->status === 200, 'competitietrainer ziet de trainersplanning');
T::ok(!$kobe->has('Vaste trainers en instellingen'), 'competitietrainer ziet geen instellingen-knop');
$kobe->get('/app/trainersplanning/instellingen');
T::ok($kobe->status === 403, 'competitietrainer: instellingen geweigerd (403)');
$tina->get('/app/trainersplanning');
T::ok($tina->status === 403, 'trainer zonder groep: trainersplanning geweigerd (403)');
$tina->get('/app');
T::ok(!$tina->has('href="app/trainersplanning"'), 'trainer zonder groep: geen menu-item');
$parent->get('/app/trainersplanning');
T::ok($parent->status === 403, 'ouder: trainersplanning geweigerd (403)');
$board->get('/app/trainersplanning/instellingen');
T::ok($board->status === 200, 'bestuur: instellingen toegankelijk');
$admin->get('/app/gebruikers/nieuw');
T::ok($admin->has('value="COORDINATOR"') && $admin->has('Coördinator competitie'), 'gebruikersformulier toont de rol Coördinator competitie');
$admin->get('/app/gebruikers');
T::ok($admin->has('Lander Coördinator'), 'coördinator staat bij "Beheer en trainers"');

echo "Losse trainingen zonder trainer = tekort\n";
$coord->get('/app/trainersplanning');
T::ok($coord->has('Training Wedstrijdploeg') && $coord->has('Geen trainer'), 'losse competitietraining zonder trainer toont "Geen trainer"');
T::ok(!$coord->has('Clubfeest'), 'clubactiviteit (geen training of wedstrijd) staat niet in de trainersplanning');
T::ok($coord->has('Training Miniemen') && $coord->has('Training Kadetten'), 'trainingen van alle groepen staan in de trainersplanning');
T::ok($coord->has('Trainer gezocht'), 'blok "Trainer gezocht" bovenaan');

echo "Reeks voor de competitie + vaste trainers\n";
$start = date('Y-m-d');
$end = date('Y-m-d', strtotime('+30 days'));
$data = ['type_id' => $typeTraining, 'title' => 'Ochtendtraining WP', 'location' => 'Kapermolen', 'start_date' => $start, 'end_date' => $end, 'group_ids' => [$gid]];
foreach (range(1, 7) as $d) {
    $data['slot_on'][$d] = '1';
    $data['slot_start'][$d] = '07:00';
    $data['slot_end'][$d] = '08:30';
}
$board->get('/app/reeksen/nieuw');
$board->post('/app/series-save', $data);
T::ok($board->status === 302, 'reeks aangemaakt');
$sid = (int) T::val("SELECT id FROM activity_series WHERE title = 'Ochtendtraining WP'");
T::ok($sid > 0 && (int) T::val('SELECT COUNT(*) FROM activities WHERE series_id = ?', [$sid]) >= 30, 'reeks maakt dagelijkse trainingen aan');

$coord->get('/app/trainersplanning/instellingen');
T::ok($coord->has('Ochtendtraining WP') && $coord->has('Geen vaste trainer'), 'instellingen tonen het blok zonder vaste trainer');
$set = ['min' => [$gid => '']];
foreach (range(1, 7) as $d) {
    $set['slot'][$sid][$d] = ['', (string) $kobeId];
}
$coord->post('/app/coverage-settings-save', $set);
T::ok($coord->status === 302, 'instellingen opgeslagen');
T::ok((int) T::val('SELECT COUNT(*) FROM series_trainers WHERE series_id = ? AND user_id = ?', [$sid, $kobeId]) === 7, 'Kobe is vaste trainer op alle 7 dagen');

// Ongeldig: trainer zonder groep (niet in de pool) als vaste trainer
$bad = $set;
$bad['slot'][$sid][1] = ['', (string) $tinaId];
$coord->post('/app/coverage-settings-save', $bad);
$coord->follow();
T::ok($coord->has('Enkel trainers van de competitie'), 'trainer zonder groep kan geen vaste competitietrainer zijn');
T::ok((int) T::val('SELECT COUNT(*) FROM series_trainers WHERE series_id = ? AND user_id = ?', [$sid, $tinaId]) === 0, 'niets opgeslagen bij een fout');
$bad = $set;
$bad['min'][$gid] = '0';
$coord->post('/app/coverage-settings-save', $bad);
$coord->follow();
T::ok($coord->has('getal van 1 tot 9'), 'minimum 0 per groep geweigerd');

$tomorrow = (int) T::val('SELECT id FROM activities WHERE series_id = ? AND substr(starts_at,1,10) = ?', [$sid, date('Y-m-d', strtotime('+1 day'))]);
$later = (int) T::val('SELECT id FROM activities WHERE series_id = ? AND substr(starts_at,1,10) = ?', [$sid, date('Y-m-d', strtotime('+10 days'))]);
$coord->get('/app/trainersplanning');
T::ok(preg_match('/id="a' . $tomorrow . '".*?In orde · 1\/1/s', $coord->body) === 1, 'training van morgen: In orde 1/1 met vaste trainer');
T::ok(str_contains($coord->body, 'Kobe Competitie') && $coord->has('>Vast<'), 'vaste trainer zichtbaar met label Vast');

echo "Afmelden binnen 3 dagen → mail\n";
T::clearMails();
$kobe->get('/app/trainersplanning');
$kobe->post('/app/coverage-action', ['activity_id' => $tomorrow, 'do' => 'absent', 'user_id' => $kobeId, 'note' => 'Examen']);
T::ok($kobe->status === 302, 'Kobe meldt zich af');
$kobe->follow();
T::ok($kobe->has('Je bent afgemeld') && $kobe->has('kregen een mail'), 'melding: afgemeld + mail vertrokken');
T::ok(T::val('SELECT status FROM activity_trainers WHERE activity_id = ? AND user_id = ?', [$tomorrow, $kobeId]) === 'ABSENT', 'afmelding opgeslagen');
$box = T::outbox();
T::ok(str_contains($box, 'aan coordinator@demo.local') && str_contains($box, 'aan competitie@demo.local'), 'mail naar coördinator en competitietrainers');
T::ok(!str_contains($box, 'aan trainer@demo.local') && !str_contains($box, 'aan bestuur@demo.local'), 'geen mail naar trainer zonder groep of bestuur');
T::ok(str_contains($box, 'Ochtendtraining WP') && str_contains($box, 'afgemeld: Kobe Competitie (Examen)') && str_contains($box, '0 van 1 trainer(s)'), 'mail bevat training, afmelding met reden en bezetting');
T::ok(str_contains($box, T::$base . '/app/trainersplanning'), 'mail bevat de link naar de trainersplanning');
T::ok((int) T::val('SELECT missing FROM coverage_alerts WHERE activity_id = ?', [$tomorrow]) === 1, 'melding onthouden');
$mailsBefore = substr_count(T::outbox(), ' | aan ');

$coord->get('/app/trainersplanning');
T::ok(preg_match('/id="a' . $tomorrow . '".*?Geen trainer · 0\/1/s', $coord->body) === 1, 'coördinator ziet het tekort');
T::ok($coord->has('Examen'), 'reden van de afmelding zichtbaar');
$coord->get('/app');
T::ok($coord->has('Trainer gezocht') && $coord->has('Ochtendtraining WP'), 'dashboard: blok Trainer gezocht');
$coord->get('/app/kalender');
T::ok($coord->has('cal-event') && str_contains($coord->body, 'short'), 'kalender markeert tekorten');
$parent->get('/app/kalender');
T::ok(!str_contains($parent->body, ' short"') && !$parent->has('short-flag'), 'ouder ziet geen tekort-markering');

echo "Overnemen en toewijzen\n";
$coord->post('/app/coverage-action', ['activity_id' => $tomorrow, 'do' => 'claim']);
$coord->follow();
T::ok($coord->has('Bedankt! Je staat ingepland.'), 'coördinator neemt over');
T::ok(T::val('SELECT status FROM activity_trainers WHERE activity_id = ? AND user_id = ?', [$tomorrow, $coordId]) === 'ASSIGNED', 'vervanger opgeslagen');
$coord->get('/app/trainersplanning');
T::ok(preg_match('/id="a' . $tomorrow . '".*?In orde · 1\/1/s', $coord->body) === 1, 'na overnemen: In orde');
T::clearMails();
$coord->post('/app/coverage-remind', []);
$coord->follow();
T::ok(T::val('SELECT COUNT(*) FROM coverage_alerts WHERE activity_id = ?', [$tomorrow]) == 0, 'opgelost tekort: melding vervalt');
T::ok(!str_contains(T::outbox(), 'Ochtendtraining WP'), 'geen mail meer voor de opgeloste training');

// Kobe terugzetten → 2 trainers
$coord->post('/app/coverage-action', ['activity_id' => $tomorrow, 'do' => 'back', 'user_id' => $kobeId]);
$coord->follow();
T::ok($coord->has('Kobe Competitie staat weer ingepland'), 'afmelding terugzetten');
T::ok(!T::val('SELECT 1 FROM activity_trainers WHERE activity_id = ? AND user_id = ?', [$tomorrow, $kobeId]), 'vaste trainer terug: geen afwijking meer');
// Weghalen van een vaste trainer kan niet
$coord->post('/app/coverage-action', ['activity_id' => $tomorrow, 'do' => 'remove', 'user_id' => $kobeId]);
$coord->follow();
T::ok($coord->has('Afmelden'), 'vaste trainer weghalen geweigerd (gebruik Afmelden)');
// Extra trainer weghalen kan wel
$coord->post('/app/coverage-action', ['activity_id' => $tomorrow, 'do' => 'remove', 'user_id' => $coordId]);
T::ok(!T::val('SELECT 1 FROM activity_trainers WHERE activity_id = ? AND user_id = ?', [$tomorrow, $coordId]), 'extra trainer weggehaald');
// Toewijzen van iemand buiten de pool
$coord->post('/app/coverage-action', ['activity_id' => $later, 'do' => 'assign', 'user_id' => $tinaId]);
$coord->follow();
T::ok($coord->has('Kies een trainer van de competitie'), 'trainer zonder groep toewijzen geweigerd');
// Competitietrainer wijst iemand anders toe (coördinatie overnemen)
$kobe->post('/app/coverage-action', ['activity_id' => $later, 'do' => 'assign', 'user_id' => $coordId]);
$kobe->follow();
T::ok($kobe->has('Lander Coördinator is ingepland'), 'competitietrainer kan een collega inplannen');
// Zwemschooltrainer kan geen acties doen
$tina->post('/app/coverage-action', ['activity_id' => $later, 'do' => 'claim']);
T::ok($tina->status === 403, 'trainer zonder groep: actie geweigerd (403)');
// Een clubactiviteit hoort er niet bij
$swimAct = (int) T::val("SELECT id FROM activities WHERE title = 'Clubfeest' LIMIT 1");
$coord->post('/app/coverage-action', ['activity_id' => $swimAct, 'do' => 'claim']);
T::ok($coord->status === 404, 'clubactiviteit: actie geweigerd (404)');
// Voorbije training
$past = (int) T::val("SELECT id FROM activities WHERE title = 'Training Wedstrijdploeg' AND ends_at < ? ORDER BY starts_at DESC LIMIT 1", [date('Y-m-d H:i:s')]);
if ($past) {
    $coord->post('/app/coverage-action', ['activity_id' => $past, 'do' => 'claim']);
    $coord->follow();
    T::ok($coord->has('al voorbij'), 'voorbije training: geen wijzigingen meer');
}
// Afmelden buiten het mailvenster: geen mail
T::clearMails();
$kobe->post('/app/coverage-action', ['activity_id' => $later, 'do' => 'absent', 'user_id' => $kobeId]);
$kobe->follow();
T::ok(T::outbox() === '', 'afmelding over 10 dagen met vervanger: geen mail');
$coord->post('/app/coverage-action', ['activity_id' => $later, 'do' => 'remove', 'user_id' => $coordId]);
$coord->follow();
T::ok($coord->has('automatisch een mail'), 'tekort buiten het venster: melding dat er later een mail volgt');
T::ok(T::outbox() === '', 'nog geen mail buiten het venster');

echo "Activiteitspagina\n";
$coord->get('/app/kalender/' . $tomorrow);
T::ok($coord->has('id="trainers"') && $coord->has('Kobe Competitie'), 'activiteitspagina toont de trainers');
$coord->post('/app/coverage-action', ['activity_id' => $tomorrow, 'do' => 'claim', 'back' => 'activity']);
T::ok(str_contains($coord->location, 'app/kalender/' . $tomorrow), 'actie vanaf de activiteitspagina keert daar terug');
$wpParent = Browser::login('ouder.wouters@demo.local'); // ouder van een zwemmer van de Wedstrijdploeg
$wpParent->get('/app/kalender/' . $tomorrow);
T::ok($wpParent->status === 200 && !$wpParent->has('id="trainers"'), 'ouder van een competitiezwemmer ziet de training, zonder trainerskaart');
$tina->get('/app/kalender/' . $tomorrow);
T::ok($tina->status === 200 && !$tina->has('id="trainers"'), 'trainer zonder groep die zelf meezwemt ziet geen trainerskaart');

echo "Minimum aantal trainers\n";
$set['min'][$gid] = '3';
$coord->post('/app/coverage-settings-save', $set);
$coord->get('/app/trainersplanning');
T::ok(preg_match('/id="a' . $tomorrow . '".*?Trainer gezocht · 2\/3/s', $coord->body) === 1, 'minimum 3 per groep: 2/3 = tekort');
$set['min'][$gid] = '';
$coord->post('/app/coverage-settings-save', $set);
$comp = (int) T::val("SELECT id FROM activities WHERE title = 'Interclubwedstrijd'");
$board->get('/app/kalender/' . $comp . '/wijzigen');
T::ok($board->has('name="min_trainers"'), 'activiteitsformulier heeft "Aantal trainers nodig"');
$a = T::all('SELECT * FROM activities WHERE id = ?', [$comp])[0];
$form = ['id' => $comp, 'type_id' => $a['type_id'], 'title' => $a['title'], 'location' => $a['location'], 'starts_at' => str_replace(' ', 'T', substr($a['starts_at'], 0, 16)),
    'ends_at' => str_replace(' ', 'T', substr($a['ends_at'], 0, 16)), 'audience' => 'GROUPS', 'group_ids' => array_column(T::all('SELECT group_id FROM activity_groups WHERE activity_id = ?', [$comp]), 'group_id'),
    'status' => 'PLANNED', 'min_trainers' => '2'];
$board->post('/app/activity-save', $form);
T::ok((int) T::val('SELECT min_trainers FROM activities WHERE id = ?', [$comp]) === 2, 'wedstrijd: 2 trainers nodig opgeslagen');
$form['min_trainers'] = 'x';
$board->post('/app/activity-save', $form);
T::ok($board->status === 422, 'ongeldig aantal trainers geweigerd');
$coord->get('/app/trainersplanning?weeks=8');
T::ok(preg_match('/id="a' . $comp . '".*?Geen trainer · 0\/2/s', $coord->body) === 1, 'wedstrijd toont 0/2');

echo "Mijn planning\n";
$kobe->get('/app/trainersplanning?show=mine');
T::ok($kobe->has('Ochtendtraining WP') && !preg_match('/class="cover-title"[^>]*>Training Wedstrijdploeg/', $kobe->body), 'Mijn planning toont enkel eigen trainingen');
$kobe->get('/app');
T::ok($kobe->has('Jij geeft training'), 'dashboard: eigen trainingen van de week');

echo "Automatische herinnering\n";
$dayAfter = (int) T::val('SELECT id FROM activities WHERE series_id = ? AND substr(starts_at,1,10) = ?', [$sid, date('Y-m-d', strtotime('+2 days'))]);
T::exec("INSERT INTO activity_trainers (activity_id, user_id, status, updated_at) VALUES (?, ?, 'ABSENT', ?)", [$dayAfter, $kobeId, date('Y-m-d H:i:s')]);
T::clearMails();
T::exec('DELETE FROM coverage_alerts');
T::autoRan(false);
(new Browser())->get('/aanmelden');
T::ok(str_contains(T::outbox(), 'Trainers gezocht voor') || str_contains(T::outbox(), 'Trainer gezocht:'), 'bezoek aan de site verstuurt de herinnering (zonder cron)');
$n1 = substr_count(T::outbox(), ' | aan ');
T::autoRan(false);
(new Browser())->get('/aanmelden');
T::ok(substr_count(T::outbox(), ' | aan ') === $n1, 'zelfde tekorten worden niet opnieuw gemeld');
(new Browser())->get('/aanmelden');
T::ok(substr_count(T::outbox(), ' | aan ') === $n1, 'hooguit één keer per uur');
T::clearMails();
$coord->post('/app/coverage-remind', []);
$coord->follow();
T::ok($coord->has('Mail verstuurd voor') && T::outbox() !== '', 'knop "Nu mail sturen" stuurt alle tekorten opnieuw');
$kobe->post('/app/coverage-remind', []);
T::ok($kobe->status === 403, 'competitietrainer kan de mail niet manueel sturen');

echo "Deellink weekplanning\n";
$coord->post('/app/coverage-share', ['group_id' => $gid]);
$tok = (string) T::val('SELECT share_token FROM `groups` WHERE id = ?', [$gid]);
T::ok(strlen($tok) === 32, 'deellink gemaakt');
$anon = new Browser();
$anon->get('/?r=weekplanning&t=' . $tok);
T::ok($anon->status === 200 && $anon->has('Weekplanning Wedstrijdploeg') && $anon->has('Ochtendtraining WP'), 'deellink toont de weekplanning zonder aanmelden');
T::ok(!$anon->has('Kobe') && !$anon->has('Lander') && !$anon->has('Peeters'), 'geen namen van trainers of leden');
T::ok(!$anon->has('Training Miniemen'), 'geen trainingen van andere groepen');
$coord->post('/app/coverage-share', ['group_id' => $gid]);
$anon->get('/?r=weekplanning&t=' . $tok);
T::ok($anon->status === 404, 'oude link werkt niet meer na vernieuwen');
$coord->post('/app/coverage-share', ['group_id' => $gid, 'do' => 'remove']);
T::ok(T::val('SELECT share_token FROM `groups` WHERE id = ?', [$gid]) === null, 'deellink uitgezet');
$anon->get('/?r=weekplanning&t=xyz');
T::ok($anon->status === 404, 'ongeldige link: 404');
$coord->post('/app/coverage-share', ['group_id' => 999999]);
T::ok($coord->status === 400, 'geen deellink voor een onbekende groep');

echo "Opruimen bij verwijderen\n";
T::ok((int) T::val('SELECT COUNT(*) FROM activity_trainers a JOIN activities x ON x.id = a.activity_id WHERE x.series_id = ?', [$sid]) > 0, 'afwijkingen bestaan voor de reeks');
$board->post('/app/series-delete', ['id' => $sid]);
T::ok((int) T::val('SELECT COUNT(*) FROM series_trainers WHERE series_id = ?', [$sid]) === 0, 'reeks verwijderd: vaste trainers weg');
T::ok((int) T::val('SELECT COUNT(*) FROM activity_trainers WHERE activity_id NOT IN (SELECT id FROM activities)') === 0, 'geen verweesde afwijkingen');

echo "Migratie van een bestaande database (v0.11)\n";
foreach (['series_trainers', 'activity_trainers', 'coverage_alerts'] as $t) {
    T::exec("DROP TABLE $t");
}
T::exec('ALTER TABLE `groups` DROP COLUMN min_trainers');
T::exec('ALTER TABLE `groups` DROP COLUMN share_token');
T::exec('ALTER TABLE activities DROP COLUMN min_trainers');
T::exec("DELETE FROM role_permissions WHERE permission_id = (SELECT id FROM permissions WHERE code = 'coverage.manage')");
T::exec("DELETE FROM user_roles WHERE role_id = (SELECT id FROM roles WHERE code = 'COORDINATOR')");
T::exec("DELETE FROM roles WHERE code = 'COORDINATOR'");
T::exec("DELETE FROM permissions WHERE code = 'coverage.manage'");
$fresh = Browser::login('bestuur@demo.local');
$fresh->get('/app/trainersplanning');
T::ok($fresh->status === 200, 'na migratie werkt de trainersplanning');
T::ok((bool) T::val("SELECT 1 FROM roles WHERE code = 'COORDINATOR'") && (bool) T::val("SELECT 1 FROM permissions WHERE code = 'coverage.manage'"), 'rol en permissie aangevuld');
T::ok(T::val("SELECT COUNT(*) FROM pragma_table_info('groups') WHERE name IN ('min_trainers','share_token')") == 2, 'kolommen aangevuld');

echo "Bulk: meerdere trainingen tegelijk\n";
T::autoRan();
$gid = (int) T::val("SELECT id FROM `groups` WHERE name = 'Wedstrijdploeg'");
$data = ['type_id' => $typeTraining, 'title' => 'Avondtraining WP', 'location' => 'Kapermolen', 'start_date' => date('Y-m-d'), 'end_date' => date('Y-m-d', strtotime('+40 days')), 'group_ids' => [$gid]];
foreach ([2, 4] as $d) { $data['slot_on'][$d] = '1'; $data['slot_start'][$d] = '18:00'; $data['slot_end'][$d] = '19:30'; }
$board->post('/app/series-save', $data);
$sid2 = (int) T::val("SELECT id FROM activity_series WHERE title = 'Avondtraining WP'");
$c = Browser::login('coordinator@demo.local');
$board->post('/app/coverage-settings-save', ['min' => [$gid => ''], 'slot' => [$sid2 => [2 => ['', (string) $kobeId], 4 => ['']]]]); // (de coördinatorrol werd hierboven in de migratietest weggehaald)
T::ok($board->status === 302, 'vaste trainer voor het nieuwe blok ingesteld');
$c->get('/app/trainersplanning?weeks=8');
T::ok($c->has('id="cover-bulk"') && $c->has('class="cover-pick"'), 'bulkformulier en vinkjes per kaart');
T::ok($c->has('Avondtraining WP · dinsdag 18:00') && $c->has('Avondtraining WP · donderdag 18:00'), 'blokken om in één keer aan te vinken');
$thu = array_map('intval', array_column(T::all("SELECT id FROM activities WHERE series_id = ? AND starts_at > ? ORDER BY starts_at", [$sid2, date('Y-m-d H:i:s')]), 'id'));
$thu = array_values(array_filter($thu, fn($id) => (int) date('N', strtotime((string) T::val('SELECT starts_at FROM activities WHERE id = ?', [$id]))) === 4));
T::ok(count($thu) >= 4, 'genoeg donderdagtrainingen');
// Lander neemt alle donderdagen over
$c->post('/app/coverage-bulk', ['ids' => $thu, 'user_id' => $coordId, 'do' => 'assign']);
$c->follow();
T::ok($c->has('Lander Coördinator is ingepland voor ' . count($thu) . ' training(en)'), 'bulk inplannen: melding met aantal');
T::ok((int) T::val('SELECT COUNT(*) FROM activity_trainers WHERE user_id = ? AND status = ? AND activity_id IN (' . implode(',', $thu) . ')', [$coordId, 'ASSIGNED']) === count($thu), 'bulk inplannen opgeslagen');
$c->post('/app/coverage-bulk', ['ids' => $thu, 'user_id' => $coordId, 'do' => 'assign']);
$c->follow();
T::ok($c->has('ingepland voor 0 training(en)') && $c->has(count($thu) . ' overgeslagen'), 'nogmaals inplannen: alles overgeslagen');
// Kobe afwezig (vakantie) voor de dinsdagen van de komende 2 weken
$tue = array_map('intval', array_column(T::all("SELECT id FROM activities WHERE series_id = ? AND starts_at > ? AND starts_at < ? ORDER BY starts_at", [$sid2, date('Y-m-d H:i:s'), date('Y-m-d', strtotime('+15 days'))]), 'id'));
$tue = array_values(array_filter($tue, fn($id) => (int) date('N', strtotime((string) T::val('SELECT starts_at FROM activities WHERE id = ?', [$id]))) === 2));
$kb = Browser::login('competitie@demo.local');
$kb->post('/app/coverage-bulk', ['ids' => array_merge($tue, [$thu[0]]), 'user_id' => $kobeId, 'do' => 'absent', 'note' => 'Vakantie']);
$kb->follow();
T::ok($kb->has('Kobe Competitie is afgemeld voor ' . count($tue) . ' training(en)') && $kb->has('1 overgeslagen'), 'bulk afmelden: enkel waar Kobe ingepland was');
T::ok((int) T::val("SELECT COUNT(*) FROM activity_trainers WHERE user_id = ? AND status = 'ABSENT' AND note = 'Vakantie'", [$kobeId]) === count($tue), 'afmeldingen met reden opgeslagen');
$kb->get('/app/trainersplanning?show=problems');
T::ok($kb->has('Vakantie'), 'tekorten na bulk afmelden zichtbaar met reden');
// Fouten en rechten
$c->post('/app/coverage-bulk', ['ids' => [], 'user_id' => $coordId, 'do' => 'assign']);
$c->follow();
T::ok($c->has('Vink eerst'), 'zonder aangevinkte trainingen: foutmelding');
$c->post('/app/coverage-bulk', ['ids' => $thu, 'user_id' => $tinaId, 'do' => 'assign']);
$c->follow();
T::ok($c->has('Kies een trainer van de competitie'), 'bulk: trainer zonder groep geweigerd');
$tn = Browser::login('trainer@demo.local');
$tn->post('/app/coverage-bulk', ['ids' => $thu, 'user_id' => $tinaId, 'do' => 'assign']);
T::ok($tn->status === 403, 'bulk: trainer zonder groep geen toegang (403)');
$swim = (int) T::val("SELECT id FROM activities WHERE title = 'Clubfeest' LIMIT 1");
$c->post('/app/coverage-bulk', ['ids' => [$swim], 'user_id' => $coordId, 'do' => 'assign']);
T::ok(!T::val('SELECT 1 FROM activity_trainers WHERE activity_id = ?', [$swim]), 'bulk: clubactiviteit wordt overgeslagen');
