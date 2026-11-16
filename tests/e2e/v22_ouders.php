<?php
/**
 * Ouders passen zelf de gegevens van hun kind aan (v0.22); volwassen leden hun eigen gegevens.
 */
T::autoRan();

$parentId = (int) T::val("SELECT id FROM users WHERE email = 'ouder@demo.local'");
$childId = (int) T::val('SELECT member_id FROM guardian_links WHERE user_id = ? ORDER BY member_id LIMIT 1', [$parentId]);
$child = T::all('SELECT * FROM members WHERE id = ?', [$childId])[0];
$otherId = (int) T::val('SELECT id FROM members WHERE id NOT IN (SELECT member_id FROM guardian_links WHERE user_id = ?) ORDER BY id LIMIT 1', [$parentId]);

echo "Ouder: eigen kind\n";
$parent = Browser::login('ouder@demo.local');
$parent->get('/app/leden/' . $childId);
T::ok($parent->status === 200 && $parent->has('Gegevens wijzigen'), 'kindpagina: knop Gegevens wijzigen');
T::ok($parent->has('<dt>Telefoon</dt>') && $parent->has('<dt>Noodcontact</dt>'), 'ouder ziet contact- en noodgegevens van het eigen kind');
T::ok(!$parent->has('member_delete') && !$parent->has('>Verwijderen<'), 'geen beheerknoppen');
$parent->get('/app');
T::ok($parent->has('kalender') && preg_match('/leden\/' . $childId . '">Gegevens/', $parent->body) === 1, 'overzicht: link naar de gegevens van het kind');

$parent->get('/app/leden/' . $childId . '/mijn-gegevens');
T::ok($parent->status === 200 && $parent->has('name="medical_note"') && !$parent->has('name="status"'), 'formulier zonder status of groep');
$parent->post('/app/child-save', [
    'id' => $childId, 'first_name' => $child['first_name'], 'last_name' => $child['last_name'], 'birth_date' => (string) $child['birth_date'],
    'email' => 'kind@voorbeeld.be', 'phone' => '0470 12 34 56', 'street' => 'Zwembadstraat 1', 'postal_code' => '3500', 'city' => 'Hasselt',
    'emergency_name' => 'Oma', 'emergency_phone' => '011 22 33 44', 'medical_note' => 'Astma: puffer in de zwemtas',
]);
T::ok(str_contains($parent->location, '/app/leden/' . $childId), 'na opslaan terug naar de kindpagina');
$parent->follow();
T::ok($parent->has('Gegevens opgeslagen.') && $parent->has('Astma: puffer in de zwemtas') && $parent->has('Zwembadstraat 1'), 'nieuwe gegevens zichtbaar');
T::ok(T::val('SELECT phone FROM members WHERE id = ?', [$childId]) === '0470 12 34 56' && T::val('SELECT emergency_name FROM members WHERE id = ?', [$childId]) === 'Oma', 'opgeslagen in de database');
T::ok(T::val('SELECT status FROM members WHERE id = ?', [$childId]) === $child['status'], 'status ongewijzigd');
T::ok((bool) T::val("SELECT 1 FROM audit_log WHERE action = 'UPDATE_OWN' AND entity = 'member' AND entity_id = ? AND user_id = ?", [$childId, $parentId]), 'wijziging staat in het auditlog');

echo "Validatie\n";
$base = ['id' => $childId, 'first_name' => $child['first_name'], 'last_name' => $child['last_name'], 'birth_date' => (string) $child['birth_date']];
$parent->post('/app/child-save', ['first_name' => ''] + $base);
T::ok($parent->status === 422 && $parent->has('Voornaam en achternaam zijn verplicht'), 'naam verplicht');
$parent->post('/app/child-save', ['email' => 'geen-mail'] + $base);
T::ok($parent->status === 422 && $parent->has('E-mailadres is ongeldig'), 'e-mail gecontroleerd');
if ($child['birth_date'] && (string) $child['birth_date'] > date('Y-m-d', strtotime('-18 years'))) {
    $parent->post('/app/child-save', ['birth_date' => '1990-01-01'] + $base);
    T::ok($parent->status === 422 && $parent->has('kan enkel het bestuur aanpassen'), 'kind niet volwassen maken via de geboortedatum');
}
$parent->post('/app/child-save', ['status' => 'INACTIVE', 'medical_note' => ''] + $base);
T::ok(T::val('SELECT status FROM members WHERE id = ?', [$childId]) === $child['status'], 'status kan niet via het formulier gewijzigd worden');

echo "Geen toegang tot andere leden\n";
$parent->get('/app/leden/' . $otherId . '/mijn-gegevens');
T::ok($parent->status === 404, 'ander lid: formulier geweigerd (404)');
$before = T::val('SELECT phone FROM members WHERE id = ?', [$otherId]);
$parent->post('/app/child-save', ['id' => $otherId, 'first_name' => 'Hack', 'last_name' => 'Poging', 'phone' => '000']);
T::ok($parent->status === 404 && T::val('SELECT phone FROM members WHERE id = ?', [$otherId]) === $before && T::val('SELECT first_name FROM members WHERE id = ?', [$otherId]) !== 'Hack', 'ander lid: opslaan geweigerd');
$trainer = Browser::login('trainer@demo.local');
$trainer->get('/app/leden/' . $childId . '/mijn-gegevens');
T::ok($trainer->status === 404, 'trainer (geen ouder) kan dit formulier niet gebruiken');
$board = Browser::login('bestuur@demo.local');
$board->get('/app/leden/' . $childId);
T::ok(!$board->has('mijn-gegevens') && $board->has('>Wijzigen<'), 'bestuur gebruikt het gewone formulier');

echo "Volwassen lid: eigen gegevens\n";
$swimmerId = (int) T::val("SELECT member_id FROM users WHERE email = 'zwemmer@demo.local'");
if ($swimmerId) {
    T::exec("UPDATE members SET birth_date = '1995-05-05' WHERE id = ?", [$swimmerId]);
    $sw = Browser::login('zwemmer@demo.local');
    $sw->get('/app/leden/' . $swimmerId);
    T::ok($sw->has('Gegevens wijzigen'), 'volwassen zwemmer kan de eigen gegevens wijzigen');
    T::exec("UPDATE members SET birth_date = ? WHERE id = ?", [date('Y-m-d', strtotime('-12 years')), $swimmerId]);
    $sw->get('/app/leden/' . $swimmerId . '/mijn-gegevens');
    T::ok($sw->status === 404, 'minderjarige zwemmer niet: dat doet de ouder');
}
