<?php
/** Bezoekersstatistieken (enkel voor beheerders, permissie stats.read). */
final class StatsController
{
    public static function index(): void
    {
        $days = (int) ($_GET['days'] ?? 30);
        if (!in_array($days, [7, 30, 90, 365], true)) {
            $days = 30;
        }
        render('stats/index', ['r' => Stats::report($days), 'days' => $days], 'Statistieken');
    }
}
