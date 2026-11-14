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
foreach (['/', '/kalender', '/trainingsuren', '/aanmelden'] as $p) {
    $anon->get($p);
    T::ok($anon->status === 200, "bezoeker $p geeft {$anon->status}");
}
