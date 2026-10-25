<?php
/**
 * Ontwikkelhulp (niet in de app): zet de startinhoud van khzs.be en/of de competitie-uren in een database,
 * bijvoorbeeld voor tests. Gebruik:  php tools/seed-site.php [pages] [competition]
 * Zonder argument gebeurt beide. Bestaande pagina's, groepen en reeksen blijven ongemoeid.
 */
if (PHP_SAPI !== 'cli') {
    exit("Enkel via de commandoregel te gebruiken.\n");
}
require dirname(__DIR__) . '/app/bootstrap.php';
$what = array_slice($argv, 1) ?: ['pages', 'competition'];
if (in_array('pages', $what, true)) {
    echo 'Pagina\'s: ' . json_encode(SiteSeed::import()) . "\n";
}
if (in_array('competition', $what, true)) {
    echo 'Competitie: ' . json_encode(CompetitionSeed::import()) . "\n";
}
