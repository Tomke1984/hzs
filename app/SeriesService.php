<?php
/**
 * Trainingsreeksen: een reeks (groepen + weekschema + periode) wordt omgezet in losse activiteiten.
 * Elke activiteit blijft een gewone activiteit (kalender, aanwezigheid, schrappen, wijzigen).
 * Individueel gewijzigde trainingen (is_custom = 1) worden door de reeks niet meer overschreven.
 */
final class SeriesService
{
    public const MAX_DAYS = 550;
    public const DAYS = [1 => 'maandag', 2 => 'dinsdag', 3 => 'woensdag', 4 => 'donderdag', 5 => 'vrijdag', 6 => 'zaterdag', 7 => 'zondag'];

    /** Beschrijving van het weekschema, bv. "di 18:30–20:00, wo 16:30–18:30". */
    public static function summary(array $slots): string
    {
        usort($slots, fn($a, $b) => $a['weekday'] <=> $b['weekday']);
        return implode(', ', array_map(
            fn($s) => substr(self::DAYS[(int) $s['weekday']], 0, 2) . ' ' . $s['start_time'] . '–' . $s['end_time'],
            $slots
        ));
    }

    /**
     * Brengt de komende trainingen in lijn met de reeks en vult het verleden aan vóór de eerste bestaande training.
     * @return array{created:int,updated:int,removed:int,kept:int}
     */
    /**
     * Activiteit schrappen = annuleren en verwijderen. Met geregistreerde aanwezigheden blijft ze (geannuleerd) bewaard.
     * Een training uit een reeks komt niet terug bij het bijwerken van de reeks. Geeft 'deleted' of 'kept'.
     */
    public static function erase(int $activityId, string $reason = ''): string
    {
        $a = Database::one('SELECT id, series_id, starts_at FROM activities WHERE id = ?', [$activityId]);
        if (!$a) {
            return 'deleted';
        }
        $marked = (int) Database::val("SELECT COUNT(*) FROM attendance WHERE activity_id = ? AND status <> 'UNKNOWN'", [$activityId]);
        if ($marked > 0) {
            Database::exec("UPDATE activities SET status='CANCELLED', status_note=? WHERE id=?", [$reason === '' ? null : $reason, $activityId]);
            return 'kept';
        }
        Database::tx(function () use ($a) {
            if ($a['series_id']) {
                $day = substr($a['starts_at'], 0, 10);
                if (!Database::val('SELECT COUNT(*) FROM activity_series_skips WHERE series_id = ? AND day = ?', [$a['series_id'], $day])) {
                    Database::exec('INSERT INTO activity_series_skips (series_id, day) VALUES (?, ?)', [$a['series_id'], $day]);
                }
            }
            Database::exec('DELETE FROM attendance WHERE activity_id = ?', [$a['id']]);
            Database::exec('DELETE FROM activity_groups WHERE activity_id = ?', [$a['id']]);
            Database::exec('DELETE FROM activities WHERE id = ?', [$a['id']]);
        });
        return 'deleted';
    }

    public static function sync(int $seriesId): array
    {
        $s = Database::one('SELECT * FROM activity_series WHERE id = ?', [$seriesId]);
        $out = ['created' => 0, 'updated' => 0, 'removed' => 0, 'kept' => 0];
        if (!$s) {
            return $out;
        }
        $slots = [];
        foreach (Database::all('SELECT * FROM activity_series_slots WHERE series_id = ?', [$seriesId]) as $sl) {
            $slots[(int) $sl['weekday']] = $sl;
        }
        $groupIds = array_map('intval', array_column(Database::all('SELECT group_id FROM activity_series_groups WHERE series_id = ?', [$seriesId]), 'group_id'));
        $today = today();

        // Gewenste trainingen: elke datum vanaf vandaag waarop een weekdag-slot past.
        $desired = [];
        if ((int) $s['is_active'] === 1 && $slots && $groupIds) {
            $d = new DateTimeImmutable($s['start_date']);
            $end = new DateTimeImmutable($s['end_date']);
            $guard = 0;
            for (; $d <= $end && $guard < self::MAX_DAYS + 1; $d = $d->modify('+1 day'), $guard++) {
                $n = (int) $d->format('N');
                if (isset($slots[$n])) {
                    $desired[$d->format('Y-m-d')] = $slots[$n];
                }
            }
        }

        // Bewust geschrapte (verwijderde) dagen komen niet terug.
        foreach (Database::all('SELECT day FROM activity_series_skips WHERE series_id = ?', [$seriesId]) as $sk) {
            unset($desired[$sk['day']]);
        }

        $existing = [];
        $earliest = $today;
        foreach (Database::all('SELECT id, starts_at, is_custom FROM activities WHERE series_id = ?', [$seriesId]) as $a) {
            $day = substr($a['starts_at'], 0, 10);
            $earliest = min($earliest, $day);
            if ($day >= $today) {
                $existing[$day] = $a;
            }
        }
        // Verleden: enkel aanvullen vóór de eerste bestaande training (bv. een reeks met startdatum in het verleden);
        // bestaande trainingen uit het verleden blijven altijd ongemoeid.
        foreach ($desired as $day => $_) {
            if ($day < $today && $day >= $earliest) {
                unset($desired[$day]);
            }
        }

        Database::tx(function () use ($s, $desired, $existing, $groupIds, &$out, $seriesId) {
            foreach ($existing as $date => $a) {
                if (!isset($desired[$date])) {
                    $hasAtt = (int) Database::val('SELECT COUNT(*) FROM attendance WHERE activity_id = ?', [$a['id']]) > 0;
                    if ((int) $a['is_custom'] === 0 && !$hasAtt) {
                        Database::exec('DELETE FROM activity_groups WHERE activity_id = ?', [$a['id']]);
                        Database::exec('DELETE FROM activities WHERE id = ?', [$a['id']]);
                        $out['removed']++;
                    } else {
                        $out['kept']++;
                    }
                    continue;
                }
                if ((int) $a['is_custom'] === 1) {
                    $out['kept']++;
                    continue;
                }
                $sl = $desired[$date];
                Database::exec(
                    'UPDATE activities SET type_id=?, title=?, description=?, location=?, starts_at=?, ends_at=?, audience=?, is_public=? WHERE id=?',
                    [$s['type_id'], $s['title'], $s['description'], $sl['location'] ?: $s['location'], "$date {$sl['start_time']}:00", "$date {$sl['end_time']}:00", 'GROUPS', (int) $s['is_public'], $a['id']]
                );
                self::setGroups((int) $a['id'], $groupIds);
                $out['updated']++;
            }
            foreach ($desired as $date => $sl) {
                if (isset($existing[$date])) {
                    continue;
                }
                $id = Database::insert(
                    'INSERT INTO activities (type_id, title, description, location, starts_at, ends_at, audience, status, created_by, created_at, series_id, is_custom, is_public)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,0,?)',
                    [$s['type_id'], $s['title'], $s['description'], $sl['location'] ?: $s['location'], "$date {$sl['start_time']}:00", "$date {$sl['end_time']}:00", 'GROUPS', 'PLANNED', $s['created_by'], now(), $seriesId, (int) $s['is_public']]
                );
                self::setGroups($id, $groupIds);
                $out['created']++;
            }
        });
        return $out;
    }

    private static function setGroups(int $activityId, array $groupIds): void
    {
        Database::exec('DELETE FROM activity_groups WHERE activity_id = ?', [$activityId]);
        foreach ($groupIds as $gid) {
            Database::exec('INSERT INTO activity_groups (activity_id, group_id) VALUES (?,?)', [$activityId, $gid]);
        }
    }
}
