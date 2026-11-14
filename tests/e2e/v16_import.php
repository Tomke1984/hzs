<?php
/** v0.16 – Trainingsreeksen importeren (uurrooster khzs.be). */
T::autoRan();
foreach (['Competitie 3', 'Competitie 2', 'Beloften', 'Competitie 1 Provinciaal', 'Competitie 1 Nationaal', 'Kern'] as $g) {
    T::exec("INSERT INTO `groups` (name, category, is_active) VALUES (?, 'COMPETITION', 1)", [$g]);
}
$kern = (int) T::val("SELECT id FROM `groups` WHERE name = 'Kern'");
$lander = (int) T::val("SELECT id FROM users WHERE email = 'coordinator@demo.local'");
$board = Browser::login('bestuur@demo.local');

$board->get('/app/reeksen');
T::ok($board->has('href="app/reeksen/importeren"'), 'knop Importeren op Trainingsreeksen');
$board->get('/app/reeksen/importeren?voorbeeld=khzs');
T::ok($board->status === 200 && $board->has('Competitie 1 Nationaal;donderdag;18:30;20:00;Zwemtraining'), 'uurrooster van khzs.be ingevuld');
preg_match('#<textarea name="text"[^>]*>(.*?)</textarea>#s', $board->body, $m);
$text = html_entity_decode($m[1] ?? '', ENT_QUOTES);
T::ok(substr_count(trim($text), "\n") === 33, '33 trainingsmomenten + kopregel');

$start = date('Y-m-d');
$end = date('Y-m-d', strtotime('+6 weeks'));
$board->post('/app/reeksen/importeren', ['text' => $text, 'start_date' => $start, 'end_date' => $end, 'do' => 'preview']);
T::ok($board->status === 200 && $board->has('33 trainingsmomenten → 11 reeksen'), 'voorbeeld: 33 momenten → 11 reeksen');
T::ok($board->has('Reeksen aanmaken (11)'), 'knop aanmaken met aantal');
T::ok($board->has('Trainer \"Jorien\" niet gevonden') || $board->has('Trainer &quot;Jorien&quot; niet gevonden'), 'waarschuwing voor onbekende trainer');
T::ok(!T::val("SELECT 1 FROM activity_series WHERE title = 'Zwemtraining'"), 'voorbeeld maakt nog niets aan');

$board->post('/app/reeksen/importeren', ['text' => $text, 'start_date' => $start, 'end_date' => $end, 'do' => 'create']);
$board->follow();
T::ok($board->has('11 reeksen aangemaakt'), 'reeksen aangemaakt: melding');
T::ok((int) T::val("SELECT COUNT(*) FROM activity_series WHERE title IN ('Zwemtraining', 'Droogtraining')") === 11, '11 reeksen in de database');
$kernZwem = (int) T::val("SELECT s.id FROM activity_series s JOIN activity_series_groups g ON g.series_id = s.id WHERE g.group_id = ? AND s.title = 'Zwemtraining'", [$kern]);
T::ok((int) T::val('SELECT COUNT(*) FROM activity_series_slots WHERE series_id = ?', [$kernZwem]) === 6, 'Kern Zwemtraining: 6 weekdagen');
T::ok(T::val('SELECT start_time || \'-\' || end_time FROM activity_series_slots WHERE series_id = ? AND weekday = 4', [$kernZwem]) === '18:30-20:00', 'Kern donderdag 18:30–20:00');
T::ok((int) T::val('SELECT COUNT(*) FROM activities WHERE series_id = ?', [$kernZwem]) >= 30, 'trainingen in de kalender aangemaakt');
T::ok((int) T::val('SELECT COUNT(*) FROM series_trainers WHERE series_id = ? AND user_id = ?', [$kernZwem, $lander]) === 6, 'Lander vaste trainer van Kern op elk moment');

$board->post('/app/reeksen/importeren', ['text' => $text, 'start_date' => $start, 'end_date' => $end, 'do' => 'create']);
$board->follow();
T::ok($board->has('0 reeksen aangemaakt') && $board->has('11 overgeslagen'), 'nogmaals importeren: alles overgeslagen');
T::ok((int) T::val("SELECT COUNT(*) FROM activity_series WHERE title IN ('Zwemtraining', 'Droogtraining')") === 11, 'geen dubbele reeksen');

echo "Andere notaties en fouten\n";
$t2 = "Kern\tzo\t9.00-10.30\tExtra\tSportoase\nKern | zo | 11:00 | 12:00 | Extra\nWedstrijdploeg (Jan);ZA;8h00;9h00";
$board->post('/app/reeksen/importeren', ['text' => $t2, 'start_date' => $start, 'end_date' => $end, 'do' => 'preview']);
T::ok($board->has('3 trainingsmomenten → 3 reeksen') && $board->has('Extra 2'), 'tabs, | en 9.00-10.30; zelfde dag dubbel wordt "Extra 2"; groepsnaam met (trainer)');
T::ok($board->has('Sportoase'), 'locatie overgenomen');
$bad = "Onbestaand;maandag;18:00;19:00\nKern;maan;18:00;19:00\nKern;maandag;19:00;18:00";
$board->post('/app/reeksen/importeren', ['text' => $bad, 'start_date' => $start, 'end_date' => $end, 'do' => 'create']);
T::ok($board->status === 200 && $board->has('bestaat niet') && $board->has('onbekende dag') && $board->has('ongeldige uren'), 'fouten per regel getoond');
T::ok(!$board->has('Reeksen aanmaken ('), 'geen aanmaakknop zolang er fouten zijn');
$board->post('/app/reeksen/importeren', ['text' => "Kern;maandag;06:00;07:00;X", 'start_date' => $end, 'end_date' => $start, 'do' => 'create']);
T::ok($board->has('geldige periode') && !T::val("SELECT 1 FROM activity_series WHERE title = 'X'"), 'ongeldige periode geweigerd');

$tr = Browser::login('trainer@demo.local');
$tr->get('/app/reeksen/importeren');
T::ok($tr->status === 403, 'trainer: geen toegang (403)');
