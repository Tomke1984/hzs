<?php
/**
 * Startdata voor de competitiegroepen: de trainingsuren van de vorige site (khzs.be) als trainingsreeksen.
 * Per groep een reeks "Zwemmen" en een reeks "Droogtraining" (een reeks heeft maximaal één uur per weekdag).
 * Voegt enkel toe wat ontbreekt en wijzigt nooit bestaande groepen of reeksen. Trainers worden niet gekoppeld
 * (dat doe je bij Groep wijzigen, want trainers zijn gebruikers).
 */
final class CompetitionSeed
{
    /** groep => [reeks => [weekdag (1=ma) => [van, tot, plaats|null]]] */
    public static function plan(): array
    {
        return [
            'Competitie 3' => [
                'Zwemmen' => [2 => ['18:30', '20:00'], 3 => ['16:30', '18:30'], 6 => ['07:30', '09:00']],
                'Droogtraining' => [4 => ['17:30', '18:30'], 6 => ['07:00', '07:30']],
            ],
            'Competitie 2' => [
                'Zwemmen' => [1 => ['18:00', '20:00'], 3 => ['18:30', '20:30'], 5 => ['16:30', '18:00'], 6 => ['07:00', '09:00']],
                'Droogtraining' => [4 => ['17:30', '18:30']],
            ],
            'Beloften' => [
                'Zwemmen' => [1 => ['18:00', '20:00'], 3 => ['16:30', '18:30'], 4 => ['18:30', '20:00'], 5 => ['16:30', '18:00'], 6 => ['07:00', '09:00']],
                'Droogtraining' => [4 => ['17:30', '18:30']],
            ],
            'Competitie 1 Provinciaal' => [
                'Zwemmen' => [3 => ['16:30', '18:30'], 5 => ['18:30', '20:00'], 6 => ['07:00', '09:00']],
            ],
            'Competitie 1 Nationaal' => [
                'Zwemmen' => [1 => ['18:00', '20:00'], 3 => ['18:30', '20:30'], 4 => ['18:30', '20:00'], 5 => ['18:00', '20:00'], 6 => ['07:00', '09:00']],
                'Droogtraining' => [4 => ['18:00', '18:30', 'Zwembad']],
            ],
            'Kern' => [
                'Zwemmen' => [1 => ['18:00', '20:00'], 2 => ['18:30', '20:00'], 3 => ['18:30', '20:30'], 4 => ['18:30', '20:00'], 5 => ['18:00', '20:00'], 6 => ['07:00', '09:00']],
                'Droogtraining' => [2 => ['17:30', '18:30'], 4 => ['18:00', '18:30', 'Zwembad']],
            ],
        ];
    }

    private static function hasSeries(string $group, string $title): bool
    {
        return (int) Database::val(
            'SELECT COUNT(*) FROM activity_series s JOIN activity_series_groups sg ON sg.series_id = s.id JOIN `groups` g ON g.id = sg.group_id WHERE g.name = ? AND s.title = ?',
            [$group, $title]
        ) > 0;
    }

    /** Aantal reeksen dat nog zou worden aangemaakt. */
    public static function pending(): int
    {
        $n = 0;
        foreach (self::plan() as $group => $series) {
            foreach (array_keys($series) as $title) {
                if (!self::hasSeries($group, $title)) {
                    $n++;
                }
            }
        }
        return $n;
    }

    /** @return array{groups:int,series:int,trainings:int} */
    public static function import(): array
    {
        $out = ['groups' => 0, 'series' => 0, 'trainings' => 0];
        $year = (int) date('Y') + (((int) date('n')) >= 7 ? 1 : 0);
        $start = today();
        $end = $year . '-06-30';
        $type = (int) Database::val("SELECT id FROM activity_types WHERE code = 'TRAINING'");
        foreach (self::plan() as $group => $series) {
            $gid = (int) Database::val('SELECT id FROM `groups` WHERE name = ?', [$group]);
            if (!$gid) {
                $gid = Database::insert('INSERT INTO `groups` (name, category, is_active) VALUES (?,?,1)', [$group, 'COMPETITION']);
                $out['groups']++;
            }
            foreach ($series as $title => $slots) {
                if (self::hasSeries($group, $title)) {
                    continue;
                }
                $sid = Database::tx(function () use ($type, $title, $start, $end, $slots, $gid) {
                    $id = Database::insert(
                        'INSERT INTO activity_series (type_id, title, description, location, start_date, end_date, is_active, is_public, created_by, created_at) VALUES (?,?,?,?,?,?,?,?,?,?)',
                        [$type, $title, null, null, $start, $end, 1, 1, Auth::user()['id'] ?? null, now()]
                    );
                    foreach ($slots as $weekday => $sl) {
                        Database::exec(
                            'INSERT INTO activity_series_slots (series_id, weekday, start_time, end_time, location) VALUES (?,?,?,?,?)',
                            [$id, $weekday, $sl[0], $sl[1], $sl[2] ?? null]
                        );
                    }
                    Database::exec('INSERT INTO activity_series_groups (series_id, group_id) VALUES (?,?)', [$id, $gid]);
                    return $id;
                });
                $r = SeriesService::sync($sid);
                $out['series']++;
                $out['trainings'] += $r['created'];
            }
        }
        return $out;
    }
}
