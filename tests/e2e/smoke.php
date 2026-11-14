<?php
/** Rooktest: de belangrijkste pagina's laden voor elke rol zonder serverfout. */
T::autoRan();
$pages = ['/app', '/app/kalender', '/app/leden', '/app/groepen', '/app/reeksen', '/app/account', '/app/aanwezigheidsrapport', '/app/trainersplanning'];
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
foreach (['Kalender', 'Trainingsreeksen', 'Trainersplanning', 'Aanwezigheden', 'Bericht sturen', 'Groepen', 'Gebruikers'] as $label) {
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
