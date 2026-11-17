<?php
/**
 * Trainersplanning v0.28: Mijn trainingen met "Ik kan niet", vraag het aan de groep (mail met "Ik neem over"
 * zonder aan te melden), raster voor de coördinator, "Ik ben een tijd weg" en "Hier kan je helpen".
 */
T::autoRan();

$gid = (int) T::val("SELECT id FROM `groups` WHERE name = 'Wedstrijdploeg'");
$coordId = (int) T::val("SELECT id FROM users WHERE email = 'coordinator@demo.local'");
$kobeId = (int) T::val("SELECT id FROM users WHERE email = 'competitie@demo.local'");
$typeTraining = (int) T::val("SELECT id FROM activity_types WHERE code = 'TRAINING'");
T::exec('DELETE FROM group_trainers WHERE user_id = ?', [$coordId]);
T::exec('UPDATE `groups` SET min_trainers = NULL WHERE id = ?', [$gid]);

$coord = Browser::login('coordinator@demo.local');
$kobe = Browser::login('competitie@demo.local');
$board = Browser::login('bestuur@demo.local');

$data = ['type_id' => $typeTraining, 'title' => 'Avond WP', 'location' => 'Kapermolen', 'start_date' => date('Y-m-d', strtotime('+1 day')), 'end_date' => date('Y-m-d', strtotime('+6 days')), 'group_ids' => [$gid]];
foreach (range(1, 7) as $d) {
    $data['slot_on'][$d] = '1';
    $data['slot_start'][$d] = '21:00';
    $data['slot_end'][$d] = '22:00';
}
$board->post('/app/series-save', $data);
$sid = (int) T::val("SELECT id FROM activity_series WHERE title = 'Avond WP'");
$day = fn(int $n) => (int) T::val('SELECT id FROM activities WHERE series_id = ? AND substr(starts_at,1,10) = ?', [$sid, date('Y-m-d', strtotime("+$n days"))]);
[$t1, $t2, $t3, $t4] = [$day(1), $day(2), $day(3), $day(4)];
T::ok($t1 && $t2 && $t3 && $t4, 'testtrainingen aangemaakt');

echo "Mijn trainingen\n";
$kobe->get('/app/trainersplanning');
T::ok($kobe->status === 200 && $kobe->has('<h1>Mijn trainingen</h1>'), 'trainer: standaardweergave is Mijn trainingen');
T::ok(preg_match('/id="a' . $t1 . '".*?Avond WP.*?✓ Jij komt.*?data-sheet="sheet-' . $t1 . '"[^>]*>Ik kan niet/s', $kobe->body) === 1, 'elke training met één knop Ik kan niet');
T::ok(preg_match('/<dialog class="sheet" id="sheet-' . $t1 . '"(?:(?!<\/dialog>).)*Lander Coördinator(?:(?!<\/dialog>).)*Vraag het aan de groep/s', $kobe->body) === 1, 'paneel: vrije collega als vervanger + Vraag het aan de groep');
T::ok(!preg_match('/<dialog[^>]*id="sheet-' . $t1 . '"[^>]* open/', $kobe->body), 'paneel staat dicht');
$kobe->get('/app/trainersplanning?view=mine&cannot=' . $t1);
T::ok(preg_match('/<dialog[^>]*id="sheet-' . $t1 . '"[^>]* open>/', $kobe->body) === 1, 'zonder JavaScript: link opent de pagina met het paneel open');
$coord->get('/app/trainersplanning');
T::ok($coord->has('class="plan-grid"'), 'coördinator: standaardweergave is het raster');

echo "Ik kan niet: vraag het aan de groep\n";
T::clearMails();
$kobe->post('/app/coverage-action', ['activity_id' => $t1, 'do' => 'absent', 'user_id' => $kobeId, 'reason' => 'Ziek', 'replacement' => 'ask', 'back' => 'mine']);
T::ok(str_contains($kobe->location, 'view=mine') && str_contains($kobe->location, '#a' . $t1), 'terug naar Mijn trainingen');
$kobe->follow();
T::ok($kobe->has('Je bent afgemeld.') && $kobe->has('We vroegen'), 'melding: afgemeld en collega\'s gevraagd');
T::ok(T::val('SELECT note FROM activity_trainers WHERE activity_id = ? AND user_id = ? AND status = ?', [$t1, $kobeId, 'ABSENT']) === 'Ziek', 'reden uit de knoppen bewaard');
T::ok(preg_match('/id="a' . $t1 . '".*?Je bent afgemeld<\/strong> · Ziek.*?Er wordt nog een vervanger gezocht.*?Toch wel/s', $kobe->body) === 1, 'kaart: afgemeld, vervanger gezocht, Toch wel');
$out = T::outbox();
T::ok(str_contains($out, 'Kan jij overnemen? Avond WP') && str_contains($out, 'Competitie kan niet (Ziek)'), 'mail "Kan jij overnemen?" met naam en reden');
preg_match_all('/r=coverage_take&t=([a-f0-9]{48})/', $out, $m);
$tokens = [];
foreach ($m[1] as $tok) {
    $tokens[(int) T::val('SELECT user_id FROM coverage_offers WHERE token_hash = ?', [hash('sha256', $tok)])] = $tok;
}
T::ok(isset($tokens[$coordId]) && !isset($tokens[$kobeId]), 'persoonlijke link voor de vrije collega, niet voor wie zich afmeldt');

echo "Ik neem over (zonder aan te melden)\n";
$guest = new Browser();
$guest->get('/?r=coverage_take&t=' . $tokens[$coordId]);
T::ok($guest->status === 200 && $guest->has('Kan jij overnemen?') && $guest->has('>Ik neem over</button>') && $guest->has('Avond WP'), 'link toont de training met één knop');
T::ok(!T::val("SELECT 1 FROM activity_trainers WHERE activity_id = ? AND user_id = ?", [$t1, $coordId]), 'openen alleen verandert niets (mailscanners)');
T::clearMails();
$guest->post('/?r=coverage_take', ['t' => $tokens[$coordId]]);
T::ok($guest->status === 200 && $guest->has('Bedankt, Lander!') && $guest->has('Je staat ingepland'), 'klik: bevestiging');
T::ok(T::val('SELECT status FROM activity_trainers WHERE activity_id = ? AND user_id = ?', [$t1, $coordId]) === 'ASSIGNED', 'Lander staat ingepland');
T::ok(str_contains(T::outbox(), 'Lander Coördinator neemt over: Avond WP'), 'wie het vroeg, krijgt een mail');
T::ok((bool) T::val("SELECT 1 FROM audit_log WHERE action = 'COVERAGE_TAKE' AND entity_id = ? AND user_id = ?", [$t1, $coordId]), 'auditlog');
$guest->get('/?r=coverage_take&t=' . $tokens[$coordId]);
T::ok($guest->has('Je staat al ingepland'), 'nog eens klikken: al ingepland');
foreach ($tokens as $u => $tok) {
    if ($u !== $coordId) {
        $guest->get('/?r=coverage_take&t=' . $tok);
        T::ok($guest->has('Lander Coördinator neemt deze training al over'), 'te laat: iemand anders neemt al over');
        break;
    }
}
$guest->get('/?r=coverage_take&t=' . str_repeat('a', 48));
T::ok($guest->has('Deze link werkt niet'), 'ongeldige link');
$kobe->get('/app/trainersplanning?view=mine');
T::ok(preg_match('/id="a' . $t1 . '".*?Er zijn genoeg trainers/s', $kobe->body) === 1, 'Mijn trainingen: vervanger gevonden');

echo "Raster voor de coördinator\n";
$coord->get('/app/trainersplanning?view=grid');
T::ok($coord->has('Wedstrijdploeg') && $coord->has('Wedstrijden') && preg_match('/class="cell cell-ok"[^>]*sel=' . $t2 . '/', $coord->body) === 1, 'raster: groepen, wedstrijden, gekleurde vakken');
$coord->get('/app/trainersplanning?view=grid&sel=' . $t2);
T::ok($coord->has('Vrij op dat moment') && preg_match('/name="user_id" value="' . $coordId . '">\s*<button class="panel-add"/', $coord->body) === 1, 'paneel: vrije trainers toevoegen');
$coord->post('/app/coverage-action', ['activity_id' => $t2, 'do' => 'assign', 'user_id' => $coordId, 'back' => 'grid', 'w' => '0']);
T::ok(str_contains($coord->location, 'view=grid') && str_contains($coord->location, 'sel=' . $t2), 'na toevoegen terug in het raster, zelfde vak');
T::ok(T::val('SELECT status FROM activity_trainers WHERE activity_id = ? AND user_id = ?', [$t2, $coordId]) === 'ASSIGNED', 'toegevoegd');
$coord->post('/app/coverage-action', ['activity_id' => $t2, 'do' => 'remove', 'user_id' => $coordId, 'back' => 'grid']);
T::exec('DELETE FROM activity_trainers WHERE activity_id = ?', [$t2]);

echo "Hier kan je helpen\n";
T::clearMails();
$kobe->post('/app/coverage-action', ['activity_id' => $t3, 'do' => 'absent', 'user_id' => $kobeId, 'replacement' => 'ask', 'back' => 'mine']);
$coord->get('/app/trainersplanning?view=mine');
T::ok(preg_match('/Hier kan je helpen.*?id="a' . $t3 . '".*?Nog 1 trainer nodig.*?Ik neem over/s', $coord->body) === 1, 'tekort waar jij vrij bent');
$coord->post('/app/coverage-action', ['activity_id' => $t3, 'do' => 'claim', 'back' => 'mine']);
T::ok(T::val('SELECT status FROM activity_trainers WHERE activity_id = ? AND user_id = ?', [$t3, $coordId]) === 'ASSIGNED', 'overgenomen vanuit Mijn trainingen');

echo "Ik ben een tijd weg\n";
$from = date('Y-m-d', strtotime('+2 days'));
$to = date('Y-m-d', strtotime('+4 days'));
$kobe->get('/app/trainersplanning/afwezig');
T::ok($kobe->status === 200 && $kobe->has('Toon wat dit raakt'), 'formulier met periode');
$kobe->get('/app/trainersplanning/afwezig?' . http_build_query(['from' => $from, 'to' => $to, 'reason' => 'Op reis']));
T::ok($kobe->has('Dit raakt ') && $kobe->has('name="ids[]" value="' . $t2 . '"') && $kobe->has('name="ids[]" value="' . $t4 . '"') && !$kobe->has('name="ids[]" value="' . $t3 . '"'), 'toont wat de periode raakt (al afgemelde training niet)');
T::ok(preg_match('/name="rep\[' . $t2 . '\]" value="' . $coordId . '"/', $kobe->body) === 1 && $kobe->has('value="ask" checked'), 'per training: vervanger of Vraag de groep');
T::clearMails();
$kobe->post('/app/coverage-away-save', ['reason' => 'Op reis', 'ids' => [$t2, $t4], 'rep' => [$t2 => (string) $coordId, $t4 => 'ask']]);
$kobe->follow();
T::ok($kobe->has('Je bent afgemeld voor 2 training(en)') && $kobe->has('Neemt over: Lander Coördinator'), 'melding na afmelden voor een periode');
T::ok(T::val('SELECT note FROM activity_trainers WHERE activity_id = ? AND user_id = ?', [$t4, $kobeId]) === 'Op reis'
    && T::val('SELECT status FROM activity_trainers WHERE activity_id = ? AND user_id = ?', [$t2, $coordId]) === 'ASSIGNED', 'afwezig + vervanger opgeslagen');
T::ok((bool) T::val('SELECT 1 FROM coverage_offers WHERE activity_id = ? AND user_id = ?', [$t4, $coordId]), 'Vraag de groep: Lander kreeg een vraag voor de andere training');

echo "Wedstrijd aanmaken: meteen trainers kiezen\n";
$typeMeet = (int) T::val("SELECT id FROM activity_types WHERE code = 'COMPETITION'");
$board->get('/app/kalender/nieuw');
T::ok($board->has('data-for-type="TRAINING COMPETITION" data-trainer-picks') && $board->has('name="trainer_ids[]" value="' . $coordId . '"') && !$board->has('Aantal trainers nodig') && !$board->has('name="min_trainers"'), 'formulier: trainers kiezen, geen vraag naar het aantal');
T::ok(preg_match('/value="' . $kobeId . '" data-groups="[^"]*\b' . $gid . '\b/', $board->body) === 1, 'trainer kent zijn groepen (voorvinken bij een nieuwe training)');
$d = date('Y-m-d', strtotime('+9 days'));
$board->post('/app/activity-save', ['type_id' => $typeMeet, 'title' => 'Testmeet', 'starts_at' => "{$d}T09:00", 'ends_at' => "{$d}T12:00", 'audience' => 'CLUB', 'min_trainers' => '3', 'trainer_ids' => [$coordId, $kobeId]]);
$meet = (int) T::val("SELECT id FROM activities WHERE title = 'Testmeet'");
$board->follow();
T::ok($board->has('Activiteit aangemaakt.') && $board->has('Kobe Competitie en Lander Coördinator gaan mee.'), 'melding: wie meegaat');
T::ok((int) T::val("SELECT COUNT(*) FROM activity_trainers WHERE activity_id = ? AND status = 'ASSIGNED'", [$meet]) === 2 && T::val('SELECT min_trainers FROM activities WHERE id = ?', [$meet]) === null, 'twee trainers ingepland, geen aantal bewaard');
$board->get('/app/kalender/' . $meet . '/wijzigen');
T::ok($board->has('data-for-type="TRAINING COMPETITION" data-trainer-picks') && preg_match('/value="' . $kobeId . '"[^>]*checked/', $board->body) === 1, 'wijzigen: aangevinkt wie meegaat');
$board->post('/app/activity-save', ['id' => $meet, 'type_id' => $typeMeet, 'title' => 'Testmeet', 'starts_at' => "{$d}T09:00", 'ends_at' => "{$d}T12:00", 'audience' => 'CLUB', 'status' => 'PLANNED', 'trainers_sync' => '1', 'trainer_ids' => [$kobeId]]);
T::ok((int) T::val("SELECT COUNT(*) FROM activity_trainers WHERE activity_id = ? AND status = 'ASSIGNED'", [$meet]) === 1, 'uitvinken haalt de trainer weg');
$board->post('/app/activity-save', ['id' => $meet, 'type_id' => $typeMeet, 'title' => 'Testmeet', 'starts_at' => "{$d}T09:00", 'ends_at' => "{$d}T12:00", 'audience' => 'CLUB', 'status' => 'PLANNED', 'trainers_sync' => '1']);
$board->follow();
T::ok($board->has('Er gaat nog geen trainer mee') && preg_match('/Geen trainer · 0\/1/', $board->body) === 1, 'niemand mee: trainer gezocht (0/1)');

echo "Training aanmaken en wijzigen: trainers kiezen\n";
$d2 = date('Y-m-d', strtotime('+11 days'));
$tr = ['type_id' => $typeTraining, 'title' => 'Losse training', 'starts_at' => "{$d2}T07:00", 'ends_at' => "{$d2}T08:00", 'audience' => 'GROUPS', 'group_ids' => [$gid]];
$board->post('/app/activity-save', $tr + ['trainer_ids' => [$kobeId, $coordId], 'trainers_sync' => '1']);
$lt = (int) T::val("SELECT id FROM activities WHERE title = 'Losse training'");
$board->follow();
T::ok($board->has('Ingepland: Lander Coördinator.'), 'nieuwe training: extra trainer ingepland, groepstrainer staat er vanzelf op');
T::ok(T::val('SELECT status FROM activity_trainers WHERE activity_id = ? AND user_id = ?', [$lt, $coordId]) === 'ASSIGNED' && !T::val('SELECT 1 FROM activity_trainers WHERE activity_id = ? AND user_id = ?', [$lt, $kobeId]), 'Kobe als vaste trainer, Lander extra');
$board->get('/app/kalender/' . $lt . '/wijzigen');
T::ok(preg_match('/value="' . $kobeId . '"[^>]*checked/', $board->body) === 1 && preg_match('/value="' . $coordId . '"[^>]*checked/', $board->body) === 1 && $board->has('name="trainers_sync" value="1"'), 'wijzigen: huidige trainers aangevinkt');
$board->post('/app/activity-save', $tr + ['id' => $lt, 'status' => 'PLANNED', 'trainers_sync' => '1', 'trainer_ids' => [$coordId]]);
T::ok(T::val('SELECT status FROM activity_trainers WHERE activity_id = ? AND user_id = ?', [$lt, $kobeId]) === 'ABSENT', 'groepstrainer uitgevinkt: niet ingepland (afgemeld)');
$board->post('/app/activity-save', $tr + ['id' => $lt, 'status' => 'PLANNED', 'trainers_sync' => '1']);
$board->follow();
T::ok($board->has('nog geen trainer') && !T::val("SELECT 1 FROM activity_trainers WHERE activity_id = ? AND status = 'ASSIGNED'", [$lt]), 'niemand aangevinkt: trainer gezocht');
$board->post('/app/activity-save', $tr + ['id' => $lt, 'status' => 'PLANNED', 'trainers_sync' => '1', 'trainer_ids' => [$kobeId]]);
T::ok(!T::val('SELECT 1 FROM activity_trainers WHERE activity_id = ? AND user_id = ?', [$lt, $kobeId]), 'groepstrainer weer aangevinkt: gewoon vaste trainer');
$board->post('/app/activity-save', $tr + ['title' => 'Zonder JS', 'trainer_ids' => []]);
$nj = (int) T::val("SELECT id FROM activities WHERE title = 'Zonder JS'");
T::ok(!T::val('SELECT 1 FROM activity_trainers WHERE activity_id = ?', [$nj]), 'nieuwe training zonder JavaScript (geen trainers_sync): groepstrainers blijven staan');

echo "Opruimen\n";
$board->post('/app/series-delete', ['id' => $sid]);
T::ok(!T::val('SELECT 1 FROM coverage_offers WHERE activity_id IN (?,?,?,?)', [$t1, $t2, $t3, $t4]) || (bool) T::val('SELECT 1 FROM activities WHERE id = ?', [$t1]), 'vragen verdwijnen met de activiteit');
