<?php
/**
 * Ontwikkelhulp (niet in de app): zet de startinhoud van khzs.be en/of de competitie-uren in een database,
 * bijvoorbeeld voor tests. Gebruik:  php tools/seed-site.php [pages] [competition]  of  php tools/seed-site.php privacy (forceert het nieuwe privacybeleid)
 * Zonder argument gebeurt beide. Bestaande pagina's, groepen en reeksen blijven ongemoeid.
 */
if (PHP_SAPI !== 'cli') {
    exit("Enkel via de commandoregel te gebruiken.\n");
}
require dirname(__DIR__) . '/app/bootstrap.php';
$what = array_slice($argv, 1) ?: ['pages', 'competition'];
if (in_array('privacy', $what, true)) {
    // Forceert de nieuwe tekst van het privacybeleid (overschrijft de bestaande pagina; maakt ze aan als ze ontbreekt).
    if (Database::val("SELECT id FROM pages WHERE slug = 'privacybeleid'")) {
        Database::exec("UPDATE pages SET body = ?, status = 'PUBLISHED', updated_at = ? WHERE slug = 'privacybeleid'", [SiteSeed::privacyBody(), now()]);
        echo "Privacybeleid bijgewerkt.\n";
    } else {
        $club = (int) Database::val("SELECT id FROM pages WHERE slug = 'club'");
        Database::exec("INSERT INTO pages (title, slug, body, parent_id, sort_order, status, in_menu, updated_at) VALUES ('Privacybeleid', 'privacybeleid', ?, ?, 3, 'PUBLISHED', 1, ?)", [SiteSeed::privacyBody(), $club ?: null, now()]);
        echo "Privacybeleid aangemaakt.\n";
    }
    exit(0);
}
if (in_array('pages', $what, true)) {
    echo 'Pagina\'s: ' . json_encode(SiteSeed::import()) . "\n";
}
if (in_array('competition', $what, true)) {
    echo 'Competitie: ' . json_encode(CompetitionSeed::import()) . "\n";
}
