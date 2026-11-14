<?php
/**
 * Trainersplanning competitie: mail voor trainingen en wedstrijden in de komende dagen waar nog een trainer ontbreekt.
 * De app doet dit standaard zelf (hooguit één keer per uur, bij een bezoek). Kan je op je hosting een cron-taak
 * instellen, dan kan je dit script bv. elk uur laten lopen en in config.php 'coverage' => ['auto_remind' => false] zetten.
 *
 * Gebruik (vanuit de projectmap):  php tools/coverage-reminders.php [--force]
 *   --force  alle huidige tekorten opnieuw melden (ook als er al een mail voor vertrok)
 * Zet 'app_url' in config.php, anders kloppen de links in de mail niet.
 */
if (PHP_SAPI !== 'cli') {
    exit("Enkel via de commandoregel te gebruiken.\n");
}
require dirname(__DIR__) . '/app/bootstrap.php';
if (config('db') === null) {
    fwrite(STDERR, "Geen config.php gevonden.\n");
    exit(1);
}
if (trim((string) config('app_url', '')) === '') {
    fwrite(STDERR, "Let op: 'app_url' staat niet in config.php; de link in de mail is dan onvolledig.\n");
}
$n = Coverage::remind(in_array('--force', $argv, true));
echo $n ? "Mail verstuurd voor $n training(en)/wedstrijd(en) zonder genoeg trainers.\n" : "Geen nieuwe tekorten; niets verstuurd.\n";
