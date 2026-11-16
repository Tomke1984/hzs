<?php
/**
 * UX-analyse ronde 2 (v0.23): ouders, kalender, activiteitspagina, consistentie en noodcontact.
 */
date_default_timezone_set('Europe/Brussels');
T::autoRan();

$parentId = (int) T::val("SELECT id FROM users WHERE email = 'ouder@demo.local'");
$childId = (int) T::val('SELECT member_id FROM guardian_links WHERE user_id = ? ORDER BY member_id LIMIT 1', [$parentId]);
$childFirst = (string) T::val('SELECT first_name FROM members WHERE id = ?', [$childId]);
T::exec("UPDATE members SET emergency_name = NULL, emergency_phone = NULL WHERE id = ?", [$childId]);

echo "Ouders: overzicht\n";
$parent = Browser::login('ouder@demo.local');
$parent->get('/app?view=week');
T::ok($parent->has('<details class="absence-pick">') && $parent->has('>Afmelden</summary>'), 'afmelden: eerst enkel de knop (reden na de tik)');
T::ok(strpos($parent->body, 'Afmelden</summary>') < strpos($parent->body, 'name="reason_pick"'), 'snelle redenen staan achter de knop');
T::ok($parent->has(fmt_week_range_test((new DateTime('now', new DateTimeZone('Europe/Brussels')))->format('Y-m-d'))), 'weekkalender van een ouder begint vandaag (rollend)');
T::ok($parent->has('Noodcontact voor ' . $childFirst . ' toevoegen'), 'herinnering: noodcontact ontbreekt');

$parent->get('/app?view=list');
$n = substr_count($parent->body, 'class="absence-pick"');
T::ok($n >= 2, "lijst: afmelden per training ($n keer, niet enkel de volgende)");

preg_match_all('/name="activity_id" value="(\d+)">\s*<input type="hidden" name="member_id" value="(\d+)">/', $parent->body, $m);
$later = (int) end($m[1]);
$parent->post('/app/absence', ['activity_id' => $later, 'member_id' => $childId, 'reason_pick' => 'Op reis', 'reason' => '', 'back' => 'dashboard']);
$parent->follow();
T::ok(T::val('SELECT reason FROM absence_reports WHERE activity_id = ? AND member_id = ?', [$later, $childId]) === 'Op reis', 'snelle reden opgeslagen');
T::ok($parent->has($childFirst . ' is afgemeld voor') && $parent->has('De trainer ziet dit bij de training.'), 'melding klopt (geen verwijzing naar verborgen aanwezigheden)');
$parent->post('/app/absence', ['activity_id' => $later, 'member_id' => $childId, 'reason_pick' => 'Ziek', 'reason' => 'Kamp', 'back' => 'dashboard']);
T::ok(T::val('SELECT reason FROM absence_reports WHERE activity_id = ? AND member_id = ?', [$later, $childId]) === 'Op reis', 'dubbel afmelden verandert niets');
T::exec('DELETE FROM absence_reports WHERE activity_id = ? AND member_id = ?', [$later, $childId]);
$parent->post('/app/absence', ['activity_id' => $later, 'member_id' => $childId, 'reason_pick' => 'Ziek', 'reason' => 'Kamp', 'back' => 'dashboard']);
T::ok(T::val('SELECT reason FROM absence_reports WHERE activity_id = ? AND member_id = ?', [$later, $childId]) === 'Kamp', 'getypte reden gaat voor de snelle keuze');
$parent->post('/app/absence', ['activity_id' => $later, 'member_id' => $childId, 'do' => 'remove', 'back' => 'dashboard']);

echo "Ouders: Mijn kinderen en de kindpagina\n";
$parent->get('/app');
$single = (int) T::val('SELECT COUNT(*) FROM guardian_links WHERE user_id = ?', [$parentId]) === 1;
if ($single) {
    T::ok(preg_match('#class="tab[^"]*" href="/?app/leden/' . $childId . '"#', $parent->body) === 1, 'één kind: tab opent meteen de kindpagina');
}
$parent->get('/app/leden');
T::ok(!$parent->has('class="filters"') && !$parent->has('name="status"'), 'ledenlijst van een ouder: geen zoekveld of filters');
$parent->get('/app/leden/' . $childId);
T::ok(!$parent->has('<dt>Status</dt>'), 'status enkel als die afwijkt');
T::ok($parent->has('missing is-important') && $parent->has('/mijn-gegevens#nood'), 'leeg noodcontact = uitnodiging om aan te vullen');
$parent->get('/app/leden/' . $childId . '/mijn-gegevens');
T::ok($parent->has('id="nood"') && $parent->has('nooit in een export'), 'privacyzin bij de noodgegevens');
T::ok($parent->has('class="cancel-link"') && !$parent->has('btn-ghost" href="/app/leden/' . $childId . '">Annuleren'), 'Annuleren als link');
T::exec("UPDATE members SET emergency_name = 'Oma', emergency_phone = '011 22 33 44' WHERE id = ?", [$childId]);
$parent->get('/app');
T::ok(!$parent->has('Noodcontact voor ' . $childFirst . ' toevoegen'), 'herinnering verdwijnt als het noodcontact ingevuld is');

echo "Kalender\n";
$parent->get('/app/kalender');
T::ok($parent->has('today-chip') && $parent->has('data-scroll-today'), 'vandaag gemarkeerd, telefoon scrolt ernaartoe');
T::ok(!$parent->has('name="g"'), 'ouder met één groep: geen groepsfilter');
$admin = Browser::login('admin@demo.local');
$admin->get('/app/kalender');
T::ok($admin->has('name="g"'), 'beheerder: groepsfilter blijft');
T::ok(!$admin->has('Grijs: wedstrijd voor een andere groep'), 'hint over grijze wedstrijden enkel als er een is');
$admin->get('/app/kalender?m=' . date('Y-m', strtotime('+2 months')));
T::ok(!$admin->has('data-scroll-today'), 'andere maand: niet scrollen');

echo "Activiteitspagina\n";
$past = (int) T::val("SELECT id FROM activities WHERE ends_at < ? AND status = 'PLANNED' ORDER BY starts_at DESC LIMIT 1", [date('Y-m-d H:i:s')]);
$future = (int) T::val("SELECT a.id FROM activities a JOIN activity_types t ON t.id = a.type_id WHERE a.starts_at > ? AND a.status = 'PLANNED' AND t.code = 'TRAINING' ORDER BY a.starts_at LIMIT 1", [date('Y-m-d H:i:s')]);
$admin->get('/app/kalender/' . $past);
T::ok(!$admin->has('Training afgelasten') && $admin->has('Deze activiteit is voorbij'), 'voorbij: geen Afgelasten');
$admin->get('/app/kalender/' . $future);
T::ok($admin->has('afgelasten') || $admin->has('Afgelasten'), 'toekomst: Afgelasten blijft');
T::ok($admin->has('class="roster-count"') && $admin->has('class="roster-list"') && !$admin->has('<th>Afmelding</th>'), 'compacte deelnemerslijst met teller');

echo "Consistentie\n";
$memberId = (int) T::val("SELECT id FROM members WHERE status = 'ACTIVE' ORDER BY id LIMIT 1");
$admin->get('/app/leden/' . $memberId);
T::ok(!$admin->has('btn btn-ghost btn-danger') && $admin->has('class="danger-zone"'), 'lid verwijderen in de gevarenzone, niet bovenaan');
T::ok(!$admin->has('Terug naar de lijst'), 'Terug maar één keer');
T::ok($admin->has('data-autosubmit'), 'groep wijzigen slaat meteen op');
$admin->get('/app/leden');
$tina = T::all("SELECT first_name, last_name FROM members WHERE last_name = 'Trainer' LIMIT 1");
if ($tina) {
    T::ok($admin->has('>' . $tina[0]['first_name'] . ' ' . $tina[0]['last_name'] . '</a>'), 'namen: voornaam eerst');
}
$admin->get('/app/kalender/nieuw');
T::ok($admin->has('class="cancel-link"'), 'activiteitformulier: Annuleren als link');

/** Zelfde weergave als fmt_week_range() in de app ("17–23 nov"), zonder de app te laden. */
function fmt_week_range_test(string $start): string
{
    $m = [1 => 'jan', 'feb', 'mrt', 'apr', 'mei', 'jun', 'jul', 'aug', 'sep', 'okt', 'nov', 'dec'];
    $a = new DateTime($start);
    $b = (clone $a)->modify('+6 days');
    return $a->format('n') === $b->format('n')
        ? $a->format('j') . '–' . $b->format('j') . ' ' . $m[(int) $b->format('n')]
        : $a->format('j') . ' ' . $m[(int) $a->format('n')] . ' – ' . $b->format('j') . ' ' . $m[(int) $b->format('n')];
}
