<?php
/**
 * UX-advies (v0.18): overzicht per rol, één tekortsignaal, minder uitleg, veilige acties, mobiel.
 */
T::autoRan();

echo "Ouders: volgende training bovenaan, weekkalender als standaard\n";
$parent = Browser::login('ouder@demo.local');
$parent->get('/app');
T::ok($parent->has('class="next-trainings"') && $parent->has('Volgende training'), 'kaart Volgende training');
T::ok(strpos($parent->body, 'next-trainings') < strpos($parent->body, 'week-grid'), 'kaart staat boven de activiteiten');
T::ok($parent->has('class="week-grid week-plain"'), 'ouder: weekkalender is de standaardweergave');
T::ok(!$parent->has('short-badge') && !$parent->has('Waar is actie nodig'), 'ouder: geen tekorten, geen actieblok');
preg_match('/name="activity_id" value="(\d+)">\s*<input type="hidden" name="member_id" value="(\d+)">\s*<input type="hidden" name="back" value="dashboard">/', $parent->body, $m);
T::ok(isset($m[2]), 'afmeldformulier op het overzicht');
$parent->post('/app/absence', ['activity_id' => $m[1], 'member_id' => $m[2], 'reason' => 'Ziek', 'back' => 'dashboard']);
T::ok(str_contains($parent->location, '/app') && !str_contains($parent->location, 'kalender'), 'na afmelden terug naar het overzicht');
$parent->follow();
T::ok($parent->has('Afgemeld: Ziek') && $parent->has('Toch aanwezig'), 'kaart toont de afmelding');
T::ok((bool) T::val('SELECT 1 FROM absence_reports WHERE activity_id = ? AND member_id = ?', [$m[1], $m[2]]), 'afmelding opgeslagen');
$parent->post('/app/absence', ['activity_id' => $m[1], 'member_id' => $m[2], 'do' => 'remove', 'back' => 'dashboard']);
$parent->follow();
T::ok($parent->has('Afmelding ingetrokken') && !T::val('SELECT 1 FROM absence_reports WHERE activity_id = ? AND member_id = ?', [$m[1], $m[2]]), 'afmelding intrekken');

echo "Trainers en bestuur: waar is actie nodig\n";
$board = Browser::login('bestuur@demo.local');
$board->get('/app');
T::ok($board->has('Waar is actie nodig') && !$board->has('class="stats"') && !$board->has('actieve leden'), 'actieblok in plaats van tegels');
T::ok($board->has('week-grid'), 'bestuur: weekkalender als standaard');
$admin = Browser::login('admin@demo.local');
T::exec("UPDATE users SET password_hash = '!pending' WHERE email = 'zwemmer@demo.local'");
$admin->get('/app');
T::ok(preg_match('/<strong>\d+<\/strong> uitnodiging(en zijn| is) nog niet geactiveerd/', $admin->body) === 1, 'uitnodigingen die nog niet geactiveerd zijn');
T::exec("UPDATE users SET password_hash = ? WHERE email = 'zwemmer@demo.local'", [password_hash('Demo1234!', PASSWORD_DEFAULT)]);

echo "Eén tekortsignaal\n";
$coord = Browser::login('coordinator@demo.local');
$coord->get('/app');
preg_match('/<span class="badge" aria-label="(\d+) openstaand">/', $coord->body, $nav);
preg_match('/Trainer gezocht <span class="badge">(\d+)<\/span>/', $coord->body, $dash);
$coord->get('/app/trainersplanning');
preg_match('/Trainer gezocht <span class="badge">(\d+)<\/span>/', $coord->body, $plan);
T::ok(isset($nav[1], $dash[1], $plan[1]) && $nav[1] === $dash[1] && $dash[1] === $plan[1], 'menubadge, overzicht en planning tonen hetzelfde aantal tekorten');
T::ok($coord->has('komende 14 dagen'), 'periode staat erbij');
// Geen activiteiten met tekort in de komende 14 dagen (afgelast), wel een wedstrijd verder weg
T::exec("UPDATE activities SET status = 'CANCELLED' WHERE starts_at > ? AND starts_at < ?", [date('Y-m-d H:i:s'), date('Y-m-d H:i:s', strtotime('+15 days'))]);
$typeMeet = (int) T::val("SELECT id FROM activity_types WHERE code = 'COMPETITION'");
$far = date('Y-m-d', strtotime('+40 days'));
T::exec("INSERT INTO activities (type_id, title, starts_at, ends_at, audience, status, created_at) VALUES (?, 'Verre wedstrijd', ?, ?, 'CLUB', 'PLANNED', ?)", [$typeMeet, "$far 09:00:00", "$far 12:00:00", date('Y-m-d H:i:s')]);
$coord->get('/app/trainersplanning');
T::ok($coord->has('Geen tekorten in de komende 14 dagen') && $coord->has('Volgend tekort:'), 'planning: geen tekorten, wel het volgende tekort met link');
T::ok(!$coord->has('Alle trainingen en wedstrijden in deze periode hebben genoeg trainers'), 'geen misleidende groene melding meer');
$coord->get('/app');
T::ok($coord->has('Geen tekorten aan trainers') && $coord->has('Volgend tekort:'), 'overzicht: zelfde melding');
T::ok(!preg_match('/aria-label="\d+ openstaand"/', $coord->body), 'menu: geen badge');

echo "Minder uitleg, actiemenu\n";
$coord->get('/app/trainersplanning');
T::ok($coord->has('class="howto"') && $coord->has('class="more-menu"') && $coord->has('Meerdere trainingen tegelijk'), 'uitleg ingeklapt, acties in het menu ⋯');

echo "Kalender: geen × per dag, tekort als badge, eenvoudige legende\n";
$board->get('/app/kalender');
T::ok(!$board->has('cal-skip') && $board->has('Afgelasten / verplaatsen'), 'geen × per dag; afgelasten via de knop bovenaan');
T::ok(!$board->has('gele rand') && !$board->has('is-comp-sw'), 'legende zonder aparte wedstrijdkleur');
$parent->get('/app/kalender');
T::ok(!$parent->has('short-flag'), 'ouder ziet geen tekorten in de kalender');

echo "Activiteitspagina\n";
$aid = (int) T::val("SELECT a.id FROM activities a JOIN activity_types t ON t.id = a.type_id WHERE t.code = 'TRAINING' AND a.status = 'PLANNED' AND a.starts_at > ? ORDER BY a.starts_at LIMIT 1", [date('Y-m-d H:i:s')]);
$coord->get('/app/kalender/' . $aid);
T::ok($coord->has('id="trainers"') && !preg_match('/id="trainers".*?cover-when/s', $coord->body), 'trainerskaart herhaalt datum en titel niet');
T::ok(substr_count($coord->body, '← Terug') <= 1, 'Terug maar één keer');

echo "Mobiel\n";
T::ok($coord->has('class="tabbar"') && $coord->has('>Planning</span>') && $coord->has('class="tab app-menu-btn"'), 'tabbalk met Overzicht, Kalender, Planning en Menu');
$parent->get('/app');
T::ok($parent->has('class="tabbar"') && !$parent->has('>Planning</span>'), 'ouder: tabbalk zonder Planning');
$board->get('/app/leden');
T::ok($board->has('member-table') && !$board->has('>Actief</span>'), 'ledenlijst: compacte tabel, enkel afwijkende status');
T::ok($board->has('data-auto'), 'filters passen meteen toe');

echo "Bladeren blijft op dezelfde hoogte\n";
$parent->get('/app?view=week');
T::ok(substr_count($parent->body, 'data-keep-scroll') >= 4, 'overzicht: ‹ › en Week/Lijst houden de scrollpositie vast');
$board->get('/app/kalender');
T::ok(substr_count($board->body, 'class="week-arrow" data-keep-scroll') === 2, 'kalender: ‹ › houden de scrollpositie vast');
