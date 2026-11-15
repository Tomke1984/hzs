<?php
/**
 * Trainersplanning (v0.12, herzien in v0.17): groepstrainers zijn de vaste trainers, afmelden met vervanger,
 * geen training en wedstrijd tegelijk, meerdere tegelijk, mails, deellink, migratie.
 */
T::autoRan(); // automatische herinnering pas testen als we dat zelf willen

$gid = (int) T::val("SELECT id FROM `groups` WHERE name = 'Wedstrijdploeg'");
$coordId = (int) T::val("SELECT id FROM users WHERE email = 'coordinator@demo.local'");
$kobeId = (int) T::val("SELECT id FROM users WHERE email = 'competitie@demo.local'");
$tinaId = (int) T::val("SELECT id FROM users WHERE email = 'trainer@demo.local'");
$typeTraining = (int) T::val("SELECT id FROM activity_types WHERE code = 'TRAINING'");
$typeMeet = (int) T::val("SELECT id FROM activity_types WHERE code = 'COMPETITION'");
// Uitgangssituatie: Kobe is de enige trainer van de Wedstrijdploeg; Lander is coördinator (geen groepstrainer).
T::exec('DELETE FROM group_trainers WHERE user_id = ?', [$coordId]);

echo "Rollen en toegang\n";
T::ok((bool) T::val("SELECT 1 FROM roles WHERE code = 'COORDINATOR'"), 'rol Coördinator competitie bestaat');
T::ok((bool) T::val("SELECT 1 FROM role_permissions rp JOIN roles r ON r.id = rp.role_id JOIN permissions p ON p.id = rp.permission_id WHERE r.code = 'BOARD' AND p.code = 'coverage.manage'"), 'bestuur heeft coverage.manage');
$coord = Browser::login('coordinator@demo.local');
$kobe = Browser::login('competitie@demo.local');
$tina = Browser::login('trainer@demo.local');
$parent = Browser::login('ouder@demo.local');
$board = Browser::login('bestuur@demo.local');

$coord->get('/app/trainersplanning');
T::ok($coord->status === 200 && $coord->has('Vaste trainers en instellingen'), 'coördinator ziet de trainersplanning met instellingen');
T::ok($coord->has('class="week-grid"'), 'standaardweergave trainersplanning: weekkalender');
$coord->get('/app');
T::ok($coord->has('class="week-grid week-plain"'), 'standaardweergave overzicht: weekkalender');
// De rest van de test werkt met de lijst (de gekozen weergave wordt onthouden)
$coord->get('/app/trainersplanning?view=list');
$kobe->get('/app/trainersplanning?view=list');
$kobe->get('/app/trainersplanning');
T::ok($kobe->status === 200 && !$kobe->has('Vaste trainers en instellingen'), 'groepstrainer ziet de planning, zonder instellingen');
$kobe->get('/app/trainersplanning/instellingen');
T::ok($kobe->status === 403, 'groepstrainer: instellingen geweigerd (403)');
$parent->get('/app/trainersplanning');
T::ok($parent->status === 403, 'ouder: geen toegang (403)');

echo "Groepstrainers = vaste trainers\n";
$data = ['type_id' => $typeTraining, 'title' => 'Avond WP', 'location' => 'Kapermolen', 'start_date' => date('Y-m-d'), 'end_date' => date('Y-m-d', strtotime('+30 days')), 'group_ids' => [$gid]];
foreach (range(1, 7) as $d) {
    $data['slot_on'][$d] = '1';
    $data['slot_start'][$d] = '18:00';
    $data['slot_end'][$d] = '19:30';
}
$board->post('/app/series-save', $data);
$sid = (int) T::val("SELECT id FROM activity_series WHERE title = 'Avond WP'");
$dayId = fn(int $n) => (int) T::val('SELECT id FROM activities WHERE series_id = ? AND substr(starts_at,1,10) = ?', [$sid, date('Y-m-d', strtotime("+$n days"))]);
$tomorrow = $dayId(1);
$day2 = $dayId(2);
$later = $dayId(10);
$coord->get('/app/trainersplanning');
T::ok(preg_match('/id="a' . $tomorrow . '".*?In orde · 1\/1.*?Kobe Competitie.*?>Vast</s', $coord->body) === 1, 'Kobe staat als vaste trainer op elke training van zijn groep');
T::ok(preg_match('/Training Miniemen.*?Tina Trainer/s', $coord->body) === 1, 'Tina staat als vaste trainer op de trainingen van Miniemen');
$coord->get('/app/trainersplanning/instellingen');
T::ok($coord->has('Vaste trainers en minimum per groep') && $coord->has('Kobe Competitie') && !$coord->has('Vaste trainers per blok'), 'instellingen tonen de groepstrainers, geen blokken meer');
$coord->get('/app/groepen');
T::ok(preg_match('/Wedstrijdploeg.*?Vaste trainer:\s*<strong>Kobe Competitie<\/strong>/s', $coord->body) === 1 && preg_match('/Miniemen.*?Vaste trainer:\s*<strong>Tina Trainer<\/strong>/s', $coord->body) === 1, 'groepenoverzicht toont de vaste trainer per groep');
$meetDemo = (int) T::val("SELECT id FROM activities WHERE title = 'Interclubwedstrijd'");
$coord->get('/app/trainersplanning?weeks=8');
T::ok(preg_match('/id="a' . $meetDemo . '".*?Geen trainer · 0\/1/s', $coord->body) === 1, 'wedstrijden hebben geen vaste trainers');
T::exec('INSERT INTO group_trainers (user_id, group_id, start_date) VALUES (?, ?, ?)', [$tinaId, $gid, date('Y-m-d')]);
$coord->get('/app/trainersplanning');
T::ok(preg_match('/id="a' . $tomorrow . '".*?In orde · 2\/1/s', $coord->body) === 1, 'tweede groepstrainer staat meteen op de trainingen');
T::exec('DELETE FROM group_trainers WHERE user_id = ? AND group_id = ?', [$tinaId, $gid]);

echo "Weekkalender\n";
$coord->get('/app/trainersplanning?view=week');
$tomDate = date('Y-m-d', strtotime('+1 day'));
$wOff = (int) floor((strtotime('monday this week', strtotime($tomDate)) - strtotime('monday this week', strtotime(date('Y-m-d')))) / 604800);
$coord->get('/app/trainersplanning?view=week&w=' . $wOff);
T::ok($coord->has('class="week-grid"') && $coord->has('Weekkalender</a>') && preg_match('/aria-current="page">Weekkalender/', $coord->body) === 1, 'weekkalender getoond');
T::ok(preg_match('/href="[^"]*kalender\/' . $tomorrow . '#trainers".*?Avond WP.*?In orde · 1\/1.*?Kobe Competitie/s', $coord->body) === 1, 'training met status en trainer; klik opent de activiteit');
$kobe->get('/app/trainersplanning?view=week&w=' . $wOff);
T::ok(preg_match('/class="week-item[^"]*is-mine"[^>]*kalender\/' . $tomorrow . '#trainers">\s*<span class="chip mine-chip">Jij</s', $kobe->body) === 1, 'weekkalender: eigen trainingen gemarkeerd');
$kobe->get('/app?view=week&w=' . $wOff);
T::ok(preg_match('/is-mine"[^>]*kalender\/' . $tomorrow . '#trainers">\s*<span class="chip mine-chip">Jij geeft training/s', $kobe->body) === 1, 'overzicht: eigen trainingen gemarkeerd');
T::ok(!preg_match('/is-mine[^>]*>\s*<span class="chip mine-chip">[^<]*<\/span>\s*<span class="week-time">[^<]*<\/span>\s*<span class="week-title">Training Miniemen/', $kobe->body), 'overzicht: trainingen van anderen niet gemarkeerd');
$kobe->get('/app?view=list');
$kobe->get('/app/trainersplanning?view=list');
T::ok(preg_match('/class="cover-card[^"]*is-mine" id="a' . $tomorrow . '">/', $kobe->body) === 1 && $kobe->has('Jij geeft training'), 'lijst: eigen trainingen gemarkeerd');
$coord->get('/app/trainersplanning');
T::ok($coord->has('class="week-grid"'), 'gekozen weergave wordt onthouden');
$coord->get('/app/trainersplanning?view=week&w=' . ($wOff + 1));
T::ok($coord->has('Vorige') && $coord->has('Deze week'), 'navigatie naar andere weken');
$coord->get('/app/trainersplanning?view=week&show=mine&w=' . $wOff);
T::ok(!$coord->has('Avond WP'), 'filter Mijn planning werkt ook in de weekkalender');
$coord->get('/app/trainersplanning?view=list');
T::ok(!$coord->has('class="week-grid"') && $coord->has('Periode'), 'terug naar de lijst');

$wpP = Browser::login('ouder.wouters@demo.local');
$wpP->get('/app?view=week&w=' . $wOff);
T::ok($wpP->status === 200 && $wpP->has('class="week-grid week-plain"') && preg_match('/kalender\/' . $tomorrow . '".*?Avond WP/s', $wpP->body) === 1, 'overzicht: weekkalender met de trainingen van het eigen kind');
T::ok(!$wpP->has('Training Miniemen'), 'overzicht: geen activiteiten van andere groepen');
$wpP->get('/app?view=list');
T::ok($wpP->has('Eerstvolgende activiteiten') && !$wpP->has('week-grid'), 'overzicht: lijst');
$wpP->get('/app');
T::ok($wpP->has('Eerstvolgende activiteiten') && !$wpP->has('week-grid'), 'overzicht: gekozen weergave onthouden');

echo "Afmelden met vervanger\n";
T::clearMails();
$kobe->get('/app/trainersplanning');
T::ok(preg_match('/id="a' . $tomorrow . '".*?name="replacement" required/s', $kobe->body) === 1, 'afmeldformulier: vervanger verplicht als er anders een tekort is');
$kobe->post('/app/coverage-action', ['activity_id' => $tomorrow, 'do' => 'absent', 'user_id' => $kobeId, 'note' => 'Examen']);
$kobe->follow();
T::ok($kobe->has('Kies wie de training overneemt'), 'zonder keuze vervanger: geweigerd');
T::ok(!T::val('SELECT 1 FROM activity_trainers WHERE activity_id = ?', [$tomorrow]), 'niets opgeslagen');
$kobe->post('/app/coverage-action', ['activity_id' => $tomorrow, 'do' => 'absent', 'user_id' => $kobeId, 'note' => 'Examen', 'replacement' => (string) $coordId]);
$kobe->follow();
T::ok($kobe->has('Je bent afgemeld. Lander Coördinator neemt over.'), 'afmelden met vervanger: melding');
T::ok(T::val('SELECT status FROM activity_trainers WHERE activity_id = ? AND user_id = ?', [$tomorrow, $kobeId]) === 'ABSENT'
    && T::val('SELECT status FROM activity_trainers WHERE activity_id = ? AND user_id = ?', [$tomorrow, $coordId]) === 'ASSIGNED', 'afwezigheid en vervanger opgeslagen');
T::ok(T::outbox() === '', 'geen mail: er is een vervanger');
$kobe->post('/app/coverage-action', ['activity_id' => $day2, 'do' => 'absent', 'user_id' => $kobeId, 'note' => 'Ziek', 'replacement' => 'none']);
$kobe->follow();
T::ok($kobe->has('Je bent afgemeld.') && $kobe->has('kregen een mail'), '"Nog geen vervanger": afgemeld en mail vertrokken');
$box = T::outbox();
T::ok(str_contains($box, 'aan coordinator@demo.local') && str_contains($box, 'aan competitie@demo.local') && str_contains($box, 'aan trainer@demo.local'), 'mail naar coördinator en alle groepstrainers');
T::ok(!str_contains($box, 'aan bestuur@demo.local') && !str_contains($box, 'aan ouder@demo.local'), 'geen mail naar bestuur of ouders');
T::ok(str_contains($box, 'afgemeld: Kobe Competitie (Ziek)') && str_contains($box, '0 van 1 trainer(s)'), 'mail bevat afmelding met reden en bezetting');
$kobe->post('/app/coverage-action', ['activity_id' => $later, 'do' => 'absent', 'user_id' => $kobeId, 'replacement' => (string) $kobeId]);
$kobe->follow();
T::ok($kobe->has('Kies als vervanger een andere trainer'), 'jezelf als vervanger: geweigerd');
T::exec("INSERT INTO activity_trainers (activity_id, user_id, status, updated_at) VALUES (?, ?, 'ASSIGNED', ?)", [$later, $tinaId, date('Y-m-d H:i:s')]);
$kobe->get('/app/trainersplanning?weeks=2');
T::ok(preg_match('/id="a' . $later . '".*?Niemand nodig \(er blijven genoeg trainers\)/s', $kobe->body) === 1, 'genoeg trainers: vervanger optioneel');
$kobe->post('/app/coverage-action', ['activity_id' => $later, 'do' => 'absent', 'user_id' => $kobeId]);
$kobe->follow();
T::ok($kobe->has('Je bent afgemeld.') && !$kobe->has('ontbreekt'), 'afmelden zonder vervanger als er genoeg blijven');

echo "Overnemen, terugzetten, weghalen\n";
$coord->post('/app/coverage-action', ['activity_id' => $day2, 'do' => 'claim']);
$coord->follow();
T::ok($coord->has('Bedankt! Je staat ingepland.'), 'coördinator neemt over');
$coord->post('/app/coverage-action', ['activity_id' => $day2, 'do' => 'back', 'user_id' => $kobeId]);
$coord->follow();
T::ok($coord->has('Kobe Competitie staat weer ingepland') && !T::val('SELECT 1 FROM activity_trainers WHERE activity_id = ? AND user_id = ?', [$day2, $kobeId]), 'afmelding terugzetten');
$coord->post('/app/coverage-action', ['activity_id' => $day2, 'do' => 'remove', 'user_id' => $kobeId]);
$coord->follow();
T::ok($coord->has('vaste trainer van de groep haal je weg met'), 'vaste trainer weghalen geweigerd');
$coord->post('/app/coverage-action', ['activity_id' => $day2, 'do' => 'remove', 'user_id' => $coordId]);
T::ok(!T::val('SELECT 1 FROM activity_trainers WHERE activity_id = ? AND user_id = ?', [$day2, $coordId]), 'extra trainer weggehaald');
$parent->post('/app/coverage-action', ['activity_id' => $day2, 'do' => 'claim']);
T::ok($parent->status === 403, 'ouder: actie geweigerd (403)');
$feest = (int) T::val("SELECT id FROM activities WHERE title = 'Clubfeest'");
$coord->post('/app/coverage-action', ['activity_id' => $feest, 'do' => 'claim']);
T::ok($coord->status === 404, 'clubactiviteit hoort niet bij de planning (404)');

echo "Geen training en wedstrijd tegelijk\n";
$d5 = date('Y-m-d', strtotime('+5 days'));
T::exec("INSERT INTO activities (type_id, title, location, starts_at, ends_at, audience, status, created_at) VALUES (?, 'Testmeeting', 'Gent', ?, ?, 'GROUPS', 'PLANNED', ?)", [$typeMeet, "$d5 17:00:00", "$d5 21:00:00", date('Y-m-d H:i:s')]);
$meet = (int) T::pdo()->lastInsertId();
T::exec('INSERT INTO activity_groups (activity_id, group_id) VALUES (?, ?)', [$meet, $gid]);
$train5 = $dayId(5);
$coord->post('/app/coverage-action', ['activity_id' => $meet, 'do' => 'assign', 'user_id' => $kobeId]);
$coord->follow();
T::ok($coord->has('Kobe Competitie is ingepland.') && $coord->has('nu als &quot;op wedstrijd&quot; afgemeld'), 'Kobe op de wedstrijd: training op hetzelfde moment automatisch "op wedstrijd"');
$coord->get('/app/trainersplanning');
T::ok(preg_match('/id="a' . $train5 . '".*?Geen trainer · 0\/1.*?Op wedstrijd.*?Testmeeting/s', $coord->body) === 1, 'training toont "Op wedstrijd" en een tekort');
$coord->post('/app/coverage-action', ['activity_id' => $train5, 'do' => 'back', 'user_id' => $kobeId]);
$coord->follow();
T::ok($coord->has('staat op dat moment op een wedstrijd'), 'terugzetten op de training kan niet zolang hij op de wedstrijd staat');
$coord->post('/app/coverage-action', ['activity_id' => $train5, 'do' => 'assign', 'user_id' => $kobeId]);
$coord->follow();
T::ok($coord->has('Niet ingepland:') && $coord->has('Testmeeting'), 'inplannen op een training tijdens je wedstrijd: geweigerd met reden');
$coord->post('/app/coverage-action', ['activity_id' => $train5, 'do' => 'claim']);
$coord->follow();
T::ok($coord->has('Bedankt! Je staat ingepland.'), 'andere trainer neemt de training over');
$coord->post('/app/coverage-action', ['activity_id' => $meet, 'do' => 'claim']);
$coord->follow();
T::ok($coord->has('Bedankt! Je staat ingepland.') && $coord->has('op wedstrijd'), 'wedstrijd gaat voor: Lander gaat ook mee, zijn training wordt "op wedstrijd"');
T::exec("INSERT INTO activities (type_id, title, starts_at, ends_at, audience, status, created_at) VALUES (?, 'Tweede meeting', ?, ?, 'CLUB', 'PLANNED', ?)", [$typeMeet, "$d5 18:00:00", "$d5 20:00:00", date('Y-m-d H:i:s')]);
$meet2 = (int) T::pdo()->lastInsertId();
$coord->post('/app/coverage-action', ['activity_id' => $meet2, 'do' => 'assign', 'user_id' => $kobeId]);
$coord->follow();
T::ok($coord->has('Niet ingepland:'), 'twee wedstrijden tegelijk: geweigerd');
$coord->post('/app/coverage-action', ['activity_id' => $meet, 'do' => 'remove', 'user_id' => $kobeId]);
$coord->get('/app/trainersplanning');
T::ok(preg_match('/id="a' . $train5 . '".*?Kobe Competitie.*?>Vast</s', $coord->body) === 1, 'van de wedstrijd gehaald: Kobe staat weer op zijn training');

echo "Meerdere tegelijk\n";
$coord->get('/app/trainersplanning/meerdere');
T::ok($coord->status === 200 && $coord->has('1. Wie en wat?'), 'pagina Meerdere tegelijk');
$from = date('Y-m-d');
$to = date('Y-m-d', strtotime('+20 days'));
$coord->get('/app/trainersplanning/meerdere?show=1&user_id=' . $kobeId . '&do=absent&g=' . $gid . '&d=0&from=' . $from . '&to=' . $to);
T::ok($coord->has('3. Controleren en bevestigen') && $coord->has('name="replacement" required') && $coord->has('Nog geen vervanger'), 'afmelden in bulk: vervanger kiezen is verplicht');
preg_match_all('/name="ids\[\]" value="(\d+)"/', $coord->body, $mm);
$ids = array_map('intval', $mm[1]);
T::ok(count($ids) >= 10, 'trainingen waar Kobe ingepland is, vooraf aangevinkt');
$coord->post('/app/coverage-bulk', ['ids' => $ids, 'user_id' => $kobeId, 'do' => 'absent', 'g' => $gid, 'from' => $from, 'to' => $to]);
$coord->follow();
T::ok($coord->has('Kies wie overneemt'), 'bulk afmelden zonder keuze vervanger: geweigerd');
$coord->post('/app/coverage-bulk', ['ids' => $ids, 'user_id' => $kobeId, 'do' => 'absent', 'note' => 'Vakantie', 'replacement' => (string) $tinaId, 'g' => $gid, 'from' => $from, 'to' => $to]);
T::ok(str_contains($coord->location, 'trainersplanning/meerdere'), 'terug naar de lijst');
$coord->follow();
T::ok($coord->has('Kobe Competitie is afgemeld voor ' . count($ids)) && $coord->has('Tina Trainer neemt'), 'bulk: afgemeld met vervanger');
T::ok((int) T::val("SELECT COUNT(*) FROM activity_trainers WHERE user_id = ? AND status = 'ABSENT' AND note = 'Vakantie'", [$kobeId]) === count($ids), 'afmeldingen met reden opgeslagen');
$coord->get('/app/trainersplanning/meerdere?show=1&user_id=' . $kobeId . '&do=assign&g=' . $gid . '&d=0&from=' . $d5 . '&to=' . $d5);
T::ok($coord->has('3. Controleren'), 'bulk inplannen: lijst getoond');
$coord->post('/app/coverage-bulk', ['ids' => [$feest], 'user_id' => $coordId, 'do' => 'assign']);
T::ok(!T::val('SELECT 1 FROM activity_trainers WHERE activity_id = ?', [$feest]), 'bulk: clubactiviteit overgeslagen');
$coord->post('/app/coverage-bulk', ['ids' => [], 'user_id' => $coordId, 'do' => 'assign']);
$coord->follow();
T::ok($coord->has('niets aangevinkt'), 'zonder aangevinkte trainingen: melding');
$parent->post('/app/coverage-bulk', ['ids' => $ids, 'user_id' => $tinaId, 'do' => 'assign']);
T::ok($parent->status === 403, 'ouder: geen toegang tot bulk (403)');

echo "Minimum, kalender, overzicht, activiteitspagina\n";
$board->post('/app/coverage-settings-save', ['min' => [$gid => '3']]);
$coord->get('/app/trainersplanning');
T::ok(preg_match('/id="a' . $tomorrow . '".*?Trainer gezocht · 1\/3/s', $coord->body) === 1, 'minimum 3 per groep: 1/3');
$board->post('/app/coverage-settings-save', ['min' => [$gid => '0']]);
$board->follow();
T::ok($board->has('getal van 1 tot 9'), 'minimum 0 geweigerd');
$board->post('/app/coverage-settings-save', ['min' => [$gid => '']]);
$coord->get('/app/kalender');
T::ok($coord->has('short-flag'), 'kalender markeert tekorten');
$coord->get('/app');
T::ok($coord->has('Trainer gezocht'), 'overzicht: blok Trainer gezocht');
$coord->get('/app/kalender/' . $tomorrow);
T::ok($coord->has('id="trainers"') && $coord->has('Lander Coördinator'), 'activiteitspagina toont de trainers');
$wpParent = Browser::login('ouder.wouters@demo.local');
$wpParent->get('/app/kalender/' . $tomorrow);
T::ok($wpParent->status === 200 && !$wpParent->has('id="trainers"'), 'ouder ziet geen trainerskaart');

echo "Automatische herinnering\n";
// Zeker een tekort binnen 3 dagen, ongeacht de weekdag waarop de test loopt
T::exec('UPDATE `groups` SET min_trainers = 3 WHERE id = ?', [$gid]);
T::clearMails();
T::exec('DELETE FROM coverage_alerts');
T::autoRan(false);
(new Browser())->get('/aanmelden');
T::ok(str_contains(T::outbox(), 'gezocht'), 'bezoek aan de app verstuurt de herinnering (zonder cron)');
$n1 = substr_count(T::outbox(), ' | aan ');
T::autoRan(false);
(new Browser())->get('/aanmelden');
T::ok(substr_count(T::outbox(), ' | aan ') === $n1, 'zelfde tekorten worden niet opnieuw gemeld');
T::clearMails();
$coord->post('/app/coverage-remind', []);
$coord->follow();
T::ok($coord->has('Mail verstuurd voor') && T::outbox() !== '', 'knop "Nu mail sturen"');
$kobe->post('/app/coverage-remind', []);
T::ok($kobe->status === 403, 'groepstrainer kan de mail niet manueel sturen');

T::exec('UPDATE `groups` SET min_trainers = NULL WHERE id = ?', [$gid]);

echo "Deellink weekplanning\n";
$coord->post('/app/coverage-share', ['group_id' => $gid]);
$tok = (string) T::val('SELECT share_token FROM `groups` WHERE id = ?', [$gid]);
$anon = new Browser();
$anon->get('/?r=weekplanning&t=' . $tok);
T::ok($anon->status === 200 && $anon->has('Weekplanning Wedstrijdploeg') && $anon->has('Avond WP'), 'deellink toont de weekplanning zonder aanmelden');
T::ok(!$anon->has('Kobe') && !$anon->has('Lander') && !$anon->has('Training Miniemen'), 'geen namen en geen andere groepen');
$coord->post('/app/coverage-share', ['group_id' => $gid]);
$anon->get('/?r=weekplanning&t=' . $tok);
T::ok($anon->status === 404, 'oude link werkt niet meer na vernieuwen');
$coord->post('/app/coverage-share', ['group_id' => 999999]);
T::ok($coord->status === 400, 'geen deellink voor een onbekende groep');

echo "Opruimen bij verwijderen\n";
$board->post('/app/series-delete', ['id' => $sid]);
T::ok((int) T::val('SELECT COUNT(*) FROM activity_trainers WHERE activity_id NOT IN (SELECT id FROM activities)') === 0, 'geen verweesde afwijkingen na het verwijderen van een reeks');

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
