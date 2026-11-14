<?php
/**
 * Trainingsreeksen importeren uit een eenvoudige lijst (plakken uit Excel, een tabel of getypt).
 * Eén regel per trainingsmoment:  Groep ; Dag ; Begin ; Einde ; Titel ; Locatie ; Vaste trainers
 * Scheidingsteken: tab, ; of |. Begin en einde mogen ook samen staan ("18:30-20:00"). Titel, locatie en trainers zijn optioneel.
 * Regels met dezelfde groep en titel worden één reeks (één tijdslot per weekdag); valt een groep twee keer met dezelfde
 * titel op dezelfde dag, dan wordt dat een tweede reeks ("Titel 2").
 */
final class SeriesImport
{
    private const DAY_WORDS = [
        'maandag' => 1, 'ma' => 1, 'dinsdag' => 2, 'di' => 2, 'woensdag' => 3, 'wo' => 3, 'donderdag' => 4, 'do' => 4,
        'vrijdag' => 5, 'vr' => 5, 'zaterdag' => 6, 'za' => 6, 'zondag' => 7, 'zo' => 7,
    ];

    /** Uurrooster competitie van khzs.be/trainingsuren-competitie (november 2026), met de groepsnamen zonder trainers. */
    public const KHZS_EXAMPLE = <<<TXT
Groep;Dag;Begin;Einde;Titel;Locatie;Vaste trainers
Competitie 3;dinsdag;18:30;20:00;Zwemtraining;;Jorien
Competitie 3;woensdag;16:30;18:30;Zwemtraining;;Jorien
Competitie 3;donderdag;17:30;18:30;Droogtraining;;Jorien
Competitie 3;zaterdag;07:00;07:30;Droogtraining;;Jorien
Competitie 3;zaterdag;07:30;09:00;Zwemtraining;;Jorien
Competitie 2;maandag;18:00;20:00;Zwemtraining;;Dimitry, Jelle
Competitie 2;woensdag;18:30;20:30;Zwemtraining;;Dimitry, Jelle
Competitie 2;donderdag;17:30;18:30;Droogtraining;;Dimitry, Jelle
Competitie 2;vrijdag;16:30;18:00;Zwemtraining;;Dimitry, Jelle
Competitie 2;zaterdag;07:00;09:00;Zwemtraining;;Dimitry, Jelle
Beloften;maandag;18:00;20:00;Zwemtraining;;Dimitry, Jelle
Beloften;woensdag;16:30;18:30;Zwemtraining;;Dimitry, Jelle
Beloften;donderdag;17:30;18:30;Droogtraining;;Dimitry, Jelle
Beloften;donderdag;18:30;20:00;Zwemtraining;;Dimitry, Jelle
Beloften;vrijdag;16:30;18:00;Zwemtraining;;Dimitry, Jelle
Beloften;zaterdag;07:00;09:00;Zwemtraining;;Dimitry, Jelle
Competitie 1 Provinciaal;woensdag;16:30;18:30;Zwemtraining;;Jelle, Lander, Dimitry
Competitie 1 Provinciaal;vrijdag;18:30;20:00;Zwemtraining;;Jelle, Lander, Dimitry
Competitie 1 Provinciaal;zaterdag;07:00;09:00;Zwemtraining;;Jelle, Lander, Dimitry
Competitie 1 Nationaal;maandag;18:00;20:00;Zwemtraining;;Lander, Antoinette
Competitie 1 Nationaal;woensdag;18:30;20:30;Zwemtraining;;Lander, Antoinette
Competitie 1 Nationaal;donderdag;18:00;18:30;Droogtraining;;Lander, Antoinette
Competitie 1 Nationaal;donderdag;18:30;20:00;Zwemtraining;;Lander, Antoinette
Competitie 1 Nationaal;vrijdag;18:00;20:00;Zwemtraining;;Lander, Antoinette
Competitie 1 Nationaal;zaterdag;07:00;09:00;Zwemtraining;;Lander, Antoinette
Kern;maandag;18:00;20:00;Zwemtraining;;Lander, Antoinette
Kern;dinsdag;17:30;18:30;Droogtraining;;Lander, Antoinette
Kern;dinsdag;18:30;20:00;Zwemtraining;;Lander, Antoinette
Kern;woensdag;18:30;20:30;Zwemtraining;;Lander, Antoinette
Kern;donderdag;18:00;18:30;Droogtraining;;Lander, Antoinette
Kern;donderdag;18:30;20:00;Zwemtraining;;Lander, Antoinette
Kern;vrijdag;18:00;20:00;Zwemtraining;;Lander, Antoinette
Kern;zaterdag;07:00;09:00;Zwemtraining;;Lander, Antoinette
TXT;

    /**
     * Leest de tekst en bouwt de reeksen op.
     * @return array{series: array<int,array>, errors: string[], warnings: string[], lines: int}
     */
    public static function parse(string $text): array
    {
        $groups = [];
        foreach (Database::all('SELECT id, name FROM `groups` WHERE is_active = 1') as $g) {
            $groups[self::norm($g['name'])] = ['id' => (int) $g['id'], 'name' => $g['name']];
        }
        $users = [];
        foreach (Coverage::pool() as $uid => $name) {
            $users[$uid] = $name;
        }
        $series = [];
        $errors = [];
        $warnings = [];
        $count = 0;
        foreach (preg_split('/\r\n|\r|\n/', $text) as $i => $line) {
            $n = $i + 1;
            if (trim($line) === '') {
                continue;
            }
            $f = array_map('trim', preg_split('/\t|;|\|/', $line));
            // Kopregel overslaan
            if ($n === 1 && preg_match('/^groep$/i', $f[0] ?? '')) {
                continue;
            }
            // "18:30-20:00" in één kolom → begin en einde
            if (isset($f[2]) && preg_match('/^(\d{1,2}[:.h]\d{2})\s*[-–]\s*(\d{1,2}[:.h]\d{2})$/u', $f[2], $m)) {
                array_splice($f, 2, 1, [$m[1], $m[2]]);
            }
            [$gName, $dayW, $start, $end] = array_pad(array_slice($f, 0, 4), 4, '');
            $title = ($f[4] ?? '') !== '' ? mb_substr($f[4], 0, 150) : 'Training';
            $loc = mb_substr($f[5] ?? '', 0, 150);
            $trainerText = $f[6] ?? '';

            $g = self::findGroup($gName, $groups);
            if (!$g) {
                $errors[] = "Regel $n: groep \"$gName\" bestaat niet (of is niet actief).";
                continue;
            }
            $day = self::DAY_WORDS[mb_strtolower(trim($dayW, ' .'))] ?? null;
            if (!$day) {
                $errors[] = "Regel $n: onbekende dag \"$dayW\" (gebruik maandag … zondag of ma … zo).";
                continue;
            }
            $st = self::time($start);
            $en = self::time($end);
            if (!$st || !$en || $en <= $st) {
                $errors[] = "Regel $n: ongeldige uren \"$start\" – \"$end\" (bv. 18:30 en 20:00; het einde na het begin).";
                continue;
            }
            $trainers = [];
            foreach (array_filter(array_map('trim', preg_split('/,|\ben\b|&|\//u', $trainerText))) as $tName) {
                $uid = self::findUser($tName, $users);
                if ($uid) {
                    $trainers[$uid] = $users[$uid];
                } else {
                    $warnings["t:$tName"] = "Trainer \"$tName\" niet gevonden bij de competitietrainers (gebruiker met rol Trainer, gekoppeld aan een groep): wordt niet als vaste trainer ingesteld.";
                }
            }
            // Zoek een reeks van deze groep en titel waar deze weekdag nog vrij is.
            $k = 1;
            while (isset($series[$g['id'] . '|' . $title . '|' . $k]['slots'][$day])) {
                $k++;
            }
            $key = $g['id'] . '|' . $title . '|' . $k;
            $series[$key] ??= ['group_id' => $g['id'], 'group' => $g['name'], 'title' => $k > 1 ? "$title $k" : $title, 'slots' => []];
            $series[$key]['slots'][$day] = ['weekday' => $day, 'start_time' => $st, 'end_time' => $en, 'location' => $loc, 'trainers' => $trainers];
            $count++;
        }
        foreach ($series as &$s) {
            ksort($s['slots']);
            $s['exists'] = (bool) Database::val(
                'SELECT 1 FROM activity_series s JOIN activity_series_groups sg ON sg.series_id = s.id WHERE sg.group_id = ? AND s.title = ? AND s.is_active = 1 AND s.end_date >= ?',
                [$s['group_id'], $s['title'], today()]
            );
        }
        unset($s);
        usort($series, fn($a, $b) => [$a['group'], $a['title']] <=> [$b['group'], $b['title']]);
        return ['series' => $series, 'errors' => $errors, 'warnings' => array_values($warnings), 'lines' => $count];
    }

    /**
     * Maakt de reeksen aan (en hun trainingen), behalve reeksen die al bestaan (zelfde groep en titel, nog lopend).
     * @return array{created:int, skipped:int, trainings:int}
     */
    public static function create(array $series, string $startDate, string $endDate, int $typeId): array
    {
        $out = ['created' => 0, 'skipped' => 0, 'trainings' => 0];
        $ids = [];
        Database::tx(function () use ($series, $startDate, $endDate, $typeId, &$out, &$ids) {
            foreach ($series as $s) {
                if ($s['exists']) {
                    $out['skipped']++;
                    continue;
                }
                $id = Database::insert(
                    'INSERT INTO activity_series (type_id, title, description, location, start_date, end_date, is_active, is_public, created_by, created_at) VALUES (?,?,?,?,?,?,1,0,?,?)',
                    [$typeId, $s['title'], null, null, $startDate, $endDate, Auth::user()['id'] ?? null, now()]
                );
                Database::exec('INSERT INTO activity_series_groups (series_id, group_id) VALUES (?,?)', [$id, $s['group_id']]);
                foreach ($s['slots'] as $sl) {
                    Database::exec(
                        'INSERT INTO activity_series_slots (series_id, weekday, start_time, end_time, location) VALUES (?,?,?,?,?)',
                        [$id, $sl['weekday'], $sl['start_time'], $sl['end_time'], $sl['location'] === '' ? null : $sl['location']]
                    );
                    foreach (array_keys($sl['trainers']) as $uid) {
                        Database::exec('INSERT INTO series_trainers (series_id, weekday, user_id) VALUES (?,?,?)', [$id, $sl['weekday'], $uid]);
                    }
                }
                $ids[] = $id;
                $out['created']++;
            }
        });
        foreach ($ids as $id) {
            $r = SeriesService::sync($id);
            $out['trainings'] += $r['created'];
        }
        return $out;
    }

    private static function norm(string $s): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $s)));
    }

    /** Groep op naam; "Competitie 3 (Jorien)" vindt ook de groep "Competitie 3". */
    private static function findGroup(string $name, array $groups): ?array
    {
        $n = self::norm($name);
        if (isset($groups[$n])) {
            return $groups[$n];
        }
        $short = self::norm((string) preg_replace('/\s*\(.*\)\s*$/u', '', $name));
        return $groups[$short] ?? null;
    }

    /** Trainer op volledige naam, anders op voornaam als die uniek is. */
    private static function findUser(string $name, array $users): ?int
    {
        $n = self::norm($name);
        $first = [];
        foreach ($users as $uid => $full) {
            if (self::norm($full) === $n) {
                return $uid;
            }
            if (self::norm(explode(' ', $full)[0]) === $n) {
                $first[] = $uid;
            }
        }
        return count($first) === 1 ? $first[0] : null;
    }

    private static function time(string $t): ?string
    {
        if (!preg_match('/^(\d{1,2})[:.h](\d{2})$/', trim($t), $m) || (int) $m[1] > 23 || (int) $m[2] > 59) {
            return null;
        }
        return sprintf('%02d:%02d', (int) $m[1], (int) $m[2]);
    }
}
