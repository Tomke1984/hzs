<?php
/** Rooktest: de belangrijkste pagina's laden voor elke rol zonder serverfout. */
T::autoRan();
$pages = ['/app', '/app/kalender', '/app/leden', '/app/groepen', '/app/reeksen', '/app/account', '/app/trainersplanning'];
foreach (['admin', 'bestuur', 'trainer', 'coordinator', 'competitie', 'zwemmer', 'ouder'] as $who) {
    $b = Browser::login("$who@demo.local");
    foreach ($pages as $p) {
        $b->get($p);
        T::ok(in_array($b->status, [200, 403], true), "$who $p geeft {$b->status}");
    }
    foreach (T::all("SELECT id FROM activities ORDER BY id LIMIT 40") as $a) {
        $b->get('/app/kalender/' . $a['id']);
        T::ok(in_array($b->status, [200, 404], true), "$who activiteit {$a['id']} geeft {$b->status}");
    }
}
$anon = new Browser();
$anon->get('/aanmelden');
T::ok($anon->status === 200 && $anon->has('Aanmelden') && !$anon->has('Schrijf je in'), 'aanmeldpagina zonder link naar inschrijven');
$anon->get('/');
T::ok($anon->status === 302 && str_contains($anon->location, 'aanmelden'), 'startpagina stuurt naar aanmelden (geen publieke website meer)');

echo "Verwijderde onderdelen (v0.13)\n";
foreach (['/kalender', '/trainingsuren', '/zwemtest', '/inschrijven', '/fotos', '/nieuws', '/sitemap.xml', '/robots.txt', '/contact', '/?r=home', '/?r=swimtest', '/?r=register', '/?r=event&id=1', '/?r=newsletter_subscribe'] as $p) {
    $anon->get($p);
    T::ok($anon->status === 404, "bezoeker $p bestaat niet meer (geeft {$anon->status})");
}
$admin = Browser::login('admin@demo.local');
foreach (['/app/lidgeld', '/app/inschrijvingen', '/app/wachtlijst', '/app/berichten', '/app/website', '/app/nieuwsbrief', '/app/fotogalerij', '/app/statistieken', '/app/exporteren', '/app/seizoensovergang'] as $p) {
    $admin->get($p);
    T::ok($admin->status === 404, "beheerder $p bestaat niet meer (geeft {$admin->status})");
}
$admin->get('/app');
foreach (['Lidgeld', 'Inschrijvingen', 'Zwemtest', 'Website', 'Nieuwsbrief', 'Fotogalerij', 'Statistieken', 'Exporteren'] as $label) {
    T::ok(!preg_match('#<span>' . $label . '</span>#', $admin->body), "menu bevat geen $label");
}
foreach (['Kalender', 'Trainingsreeksen', 'Trainersplanning', 'Bericht sturen', 'Groepen', 'Gebruikers'] as $label) {
    T::ok((bool) preg_match('#<span>' . $label . '</span>#', $admin->body), "menu bevat $label");
}
$gid = (int) T::val("SELECT id FROM `groups` ORDER BY id LIMIT 1");
$admin->get('/app/groepen/' . $gid . '/wijzigen');
T::ok(!$admin->has('name="fee"') && !$admin->has('name="capacity"') && !$admin->has('name="goals"') && !$admin->has('next_group_id'), 'groepsformulier zonder lidgeld, capaciteit, doelstellingen en doorstroomgroep');
$admin->get('/app/reeksen/nieuw');
T::ok(!$admin->has('name="is_public"') && !$admin->has('name="capacity"'), 'reeksformulier zonder website en capaciteit');
$admin->get('/app/kalender/nieuw');
T::ok(!$admin->has('signup_open'), 'activiteitsformulier zonder online inschrijven');
$mid = (int) T::val("SELECT id FROM members ORDER BY id LIMIT 1");
$admin->get('/app/leden/' . $mid);
T::ok($admin->status === 200 && !$admin->has('Lidgeld') && !$admin->has('Wedstrijdresultaten'), 'ledenpagina zonder lidgeld en resultaten');
foreach (['registrations', 'pages', 'posts', 'dues', 'seasons', 'swimtest_requests', 'subscribers', 'gallery_albums', 'page_views'] as $t) {
    T::ok(!T::val("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = ?", [$t]), "nieuwe installatie heeft geen tabel $t");
}
T::ok(!T::val("SELECT 1 FROM permissions WHERE code IN ('fees.manage','content.manage','registrations.manage','stats.read','export.data')"), 'geen rechten meer voor verwijderde onderdelen');

echo "Bestaande database (v0.12) bijwerken\n";
T::exec("INSERT INTO permissions (code, name) VALUES ('fees.manage', 'Lidgeld')");
T::exec("INSERT INTO role_permissions (role_id, permission_id) SELECT r.id, p.id FROM roles r, permissions p WHERE r.code = 'BOARD' AND p.code = 'fees.manage'");
T::exec('CREATE TABLE dues (id INTEGER PRIMARY KEY, member_id INT, amount_cents INT)');
T::exec('INSERT INTO dues (member_id, amount_cents) VALUES (1, 12500)');
$b = Browser::login('bestuur@demo.local');
$b->get('/app');
T::ok($b->status === 200, 'app werkt na de update');
T::ok(!T::val("SELECT 1 FROM permissions WHERE code = 'fees.manage'"), 'oud recht opgeruimd');
T::ok((int) T::val('SELECT COUNT(*) FROM dues') === 1, 'oude gegevens (lidgeld) blijven onaangeroerd in de database');

echo "Zwemschool uit de app (v0.14)\n";
$admin = Browser::login('admin@demo.local');
$admin->get('/app/groepen/nieuw');
T::ok(!$admin->has('name="category"') && !$admin->has('Zwemschool'), 'groepsformulier zonder keuze zwemschool/competitie');
$admin->get('/app/kalender');
T::ok(!$admin->has('name="cat"') && !$admin->has('Weekoverzicht zwemschool'), 'kalender zonder filter zwemschool/competitie en zonder weekoverzicht');
$admin->get('/app/reeksen');
T::ok(!$admin->has('Nog niet ingedeeld') && !$admin->has('Zwemschool'), 'reeksen zonder zwemblokken');
foreach (['/app/series-week', '/app/series-member-add', '/app/member-days'] as $p) {
    $admin->get($p);
    T::ok($admin->status === 404, "$p bestaat niet meer (geeft {$admin->status})");
}
T::ok(!T::val("SELECT 1 FROM `groups` WHERE category <> 'COMPETITION'"), 'demodata: enkel competitiegroepen');
// Een bestaande database met een zwemschoolgroep (leden, reeks, blokindeling, training met aanwezigheid en afmelding)
$sg = T::pdo();
T::exec("INSERT INTO `groups` (name, description, category, is_active) VALUES ('Zwemschool Oud', NULL, 'SWIM_SCHOOL', 1)");
$swimG = (int) $sg->lastInsertId();
$mid = (int) T::val("SELECT id FROM members WHERE first_name = 'Emma'");
T::exec('DELETE FROM group_memberships WHERE member_id = ?', [$mid]);
T::exec('INSERT INTO group_memberships (member_id, group_id, start_date) VALUES (?,?,?)', [$mid, $swimG, '2025-09-01']);
T::exec("INSERT INTO activity_series (type_id, title, start_date, end_date, is_active, created_at) VALUES ((SELECT id FROM activity_types WHERE code = 'TRAINING'), 'Zwemblok di', ?, ?, 1, ?)", [date('Y-m-d'), date('Y-m-d', strtotime('+30 days')), date('Y-m-d H:i:s')]);
$swimS = (int) $sg->lastInsertId();
T::exec('INSERT INTO activity_series_groups (series_id, group_id) VALUES (?,?)', [$swimS, $swimG]);
T::exec('INSERT INTO activity_series_slots (series_id, weekday, start_time, end_time) VALUES (?, 2, ?, ?)', [$swimS, '18:00', '19:00']);
T::exec('INSERT INTO member_series (member_id, series_id, weekday) VALUES (?, ?, 0)', [$mid, $swimS]);
T::exec("INSERT INTO activities (type_id, title, starts_at, ends_at, audience, status, created_at, series_id) VALUES ((SELECT id FROM activity_types WHERE code = 'TRAINING'), 'Zwemblok di', ?, ?, 'GROUPS', 'PLANNED', ?, ?)", [date('Y-m-d', strtotime('+3 days')) . ' 18:00:00', date('Y-m-d', strtotime('+3 days')) . ' 19:00:00', date('Y-m-d H:i:s'), $swimS]);
$swimA = (int) $sg->lastInsertId();
T::exec('INSERT INTO activity_groups (activity_id, group_id) VALUES (?,?)', [$swimA, $swimG]);
T::exec("INSERT INTO attendance (activity_id, member_id, status, updated_at) VALUES (?, ?, 'PRESENT', ?)", [$swimA, $mid, date('Y-m-d H:i:s')]);
T::exec('INSERT INTO absence_reports (activity_id, member_id, created_at) VALUES (?, ?, ?)', [$swimA, $mid, date('Y-m-d H:i:s')]);
$b = Browser::login('bestuur@demo.local');
$b->get('/app/groepen');
T::ok($b->status === 200 && !$b->has('Zwemschool Oud'), 'na de update: zwemschoolgroep verwijderd');
T::ok(!T::val('SELECT 1 FROM `groups` WHERE id = ?', [$swimG]) && !T::val('SELECT 1 FROM activity_series WHERE id = ?', [$swimS]) && !T::val('SELECT 1 FROM activities WHERE id = ?', [$swimA]), 'reeks en trainingen van de zwemschoolgroep verwijderd');
T::ok((bool) T::val('SELECT 1 FROM members WHERE id = ?', [$mid]) && !T::val('SELECT 1 FROM group_memberships WHERE member_id = ?', [$mid]), 'lid blijft bestaan, zonder groep');
T::ok(!T::val('SELECT 1 FROM member_series'), 'blokindeling gewist');
T::ok((bool) T::val("SELECT 1 FROM `groups` WHERE name = 'Wedstrijdploeg'"), 'competitiegroepen blijven');

echo "Aanwezigheden voorlopig verborgen (feature 'attendance')\n";
$admin = Browser::login('admin@demo.local');
$admin->get('/app');
T::ok(!preg_match('#<span>Aanwezigheden</span>#', $admin->body) && !$admin->has('Aanwezigheid nog in te vullen'), 'standaard: geen menu-item en geen blok aanwezigheden');
$admin->get('/app/aanwezigheidsrapport');
T::ok($admin->status === 404, 'aanwezigheidsrapport geeft 404');
$act = (int) T::val("SELECT id FROM activities WHERE title = 'Training Wedstrijdploeg' AND starts_at > ? ORDER BY starts_at LIMIT 1", [date('Y-m-d H:i:s')]);
$admin->get('/app/kalender/' . $act);
T::ok($admin->status === 200 && !$admin->has('name="status[') && !$admin->has('Aanwezigheden opslaan') && $admin->has('Deelnemers en afmeldingen'), 'activiteit zonder presentielijst, wel deelnemers en afmeldingen');
$admin->post('/app/attendance-save', ['activity_id' => $act]);
T::ok($admin->status === 404, 'aanwezigheden opslaan geeft 404');
$wpChild = (int) T::val("SELECT gm.member_id FROM group_memberships gm JOIN `groups` g ON g.id = gm.group_id JOIN guardian_links gl ON gl.member_id = gm.member_id WHERE g.name = 'Wedstrijdploeg' LIMIT 1");
$parentEmail = (string) T::val('SELECT u.email FROM users u JOIN guardian_links gl ON gl.user_id = u.id WHERE gl.member_id = ?', [$wpChild]);
$p = Browser::login($parentEmail);
$p->get('/app/kalender/' . $act);
T::ok($p->has('Kan niet komen'), 'ouder kan zijn kind nog altijd afmelden');
$p->post('/app/absence', ['activity_id' => $act, 'member_id' => $wpChild, 'reason' => 'Ziek']);
T::ok((bool) T::val('SELECT 1 FROM absence_reports WHERE activity_id = ? AND member_id = ?', [$act, $wpChild]), 'afmelding opgeslagen');
$admin->get('/app/kalender/' . $act);
T::ok($admin->has('Afgemeld') && $admin->has('Ziek'), 'trainer/bestuur ziet de afmelding');
T::config(['features' => ['attendance' => true]]);
$admin->get('/app');
T::ok((bool) preg_match('#<span>Aanwezigheden</span>#', $admin->body), 'met features.attendance = true: menu-item terug');
$admin->get('/app/aanwezigheidsrapport');
T::ok($admin->status === 200, 'aanwezigheidsrapport weer bereikbaar');
$admin->get('/app/kalender/' . $act);
T::ok($admin->has('Aanwezigheden opslaan'), 'presentielijst weer zichtbaar');
T::config(['features' => ['attendance' => false]]);

echo "Kalender: filter op soort activiteit\n";
$fb = Browser::login('bestuur@demo.local');
$m = substr((string) T::val("SELECT starts_at FROM activities WHERE title = 'Clubfeest'"), 0, 7);
$fb->get('/app/kalender?m=' . $m);
T::ok($fb->has('name="t"') && $fb->has('Alle soorten') && $fb->has('Clubfeest'), 'kalender: keuzelijst soort, standaard alles');
$fb->get('/app/kalender?m=' . $m . '&t=TRAINING');
T::ok(!$fb->has('Clubfeest') && $fb->has('type-training') && !$fb->has('cal-event type-club'), 'filter Training: enkel trainingen');
T::ok($fb->has('Wis filter') && str_contains($fb->body, 't=TRAINING'), 'filter blijft bij vorige/volgende maand');
$fb->get('/app/kalender?m=' . $m . '&t=CLUB');
T::ok($fb->has('Clubfeest') && !$fb->has('cal-event type-training'), 'filter Clubactiviteit');
$fb->get('/app/kalender?m=' . $m . '&t=ONBEKEND');
T::ok($fb->status === 200 && $fb->has('Clubfeest'), 'onbekende soort: geen filter');
