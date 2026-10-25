<?php
/**
 * Trainingsreeksen: een reeks (groepen + weekschema + periode) wordt omgezet in losse activiteiten.
 * Elke activiteit blijft een gewone activiteit (kalender, aanwezigheid, schrappen, wijzigen).
 * Individueel gewijzigde trainingen (is_custom = 1) worden door de reeks niet meer overschreven.
 */
final class SeriesService
{
    public const MAX_DAYS = 800;
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

    /** Is dit een zwemschoolblok? (minstens één van de groepen is een zwemschoolgroep) */
    public static function isSwim(int $seriesId): bool
    {
        return (int) Database::val(
            "SELECT COUNT(*) FROM activity_series_groups sg JOIN `groups` g ON g.id = sg.group_id WHERE sg.series_id = ? AND g.category = 'SWIM_SCHOOL'",
            [$seriesId]
        ) > 0;
    }

    /** Leden die nu aan dit blok gekoppeld zijn (en nog in een toegelaten groep zitten). */
    public static function blockMembers(int $seriesId): array
    {
        return Database::all(
            "SELECT DISTINCT m.id, m.first_name, m.last_name, g.name AS group_name
             FROM member_series ms
             JOIN members m ON m.id = ms.member_id AND m.status = 'ACTIVE'
             JOIN group_memberships gm ON gm.member_id = m.id
             JOIN activity_series_groups asg ON asg.series_id = ms.series_id AND asg.group_id = gm.group_id
             JOIN `groups` g ON g.id = gm.group_id
             WHERE ms.series_id = ? ORDER BY m.last_name, m.first_name",
            [$seriesId]
        );
    }

    /** Actieve leden uit de toegelaten groepen die nog niet aan dit blok gekoppeld zijn. */
    public static function blockCandidates(int $seriesId): array
    {
        return Database::all(
            "SELECT DISTINCT m.id, m.first_name, m.last_name, g.name AS group_name
             FROM members m
             JOIN group_memberships gm ON gm.member_id = m.id
             JOIN activity_series_groups asg ON asg.group_id = gm.group_id AND asg.series_id = ?
             JOIN `groups` g ON g.id = gm.group_id
             WHERE m.status = 'ACTIVE' AND NOT EXISTS (SELECT 1 FROM member_series ms WHERE ms.member_id = m.id AND ms.series_id = ?)
             ORDER BY m.last_name, m.first_name",
            [$seriesId, $seriesId]
        );
    }

    /**
     * Actieve leden van een zwemschoolgroep die nog aan geen enkel (lopend) blok van hun groep gekoppeld zijn.
     * @return array<int, array{id:int,first_name:string,last_name:string,group_id:int,group_name:string,blocks:array}>
     */
    public static function unassignedSwimMembers(): array
    {
        $rows = Database::all(
            "SELECT m.id, m.first_name, m.last_name, g.id AS group_id, g.name AS group_name
             FROM members m JOIN group_memberships gm ON gm.member_id = m.id JOIN `groups` g ON g.id = gm.group_id
             WHERE m.status = 'ACTIVE' AND g.is_active = 1 AND g.category = 'SWIM_SCHOOL'
               AND NOT EXISTS (
                 SELECT 1 FROM member_series ms
                 JOIN activity_series s ON s.id = ms.series_id AND s.is_active = 1 AND s.end_date >= ?
                 JOIN activity_series_groups asg ON asg.series_id = ms.series_id AND asg.group_id = gm.group_id
                 WHERE ms.member_id = m.id)
             ORDER BY g.name, m.last_name, m.first_name",
            [today()]
        );
        $blocks = [];
        foreach ($rows as &$r) {
            $gid = (int) $r['group_id'];
            if (!isset($blocks[$gid])) {
                $blocks[$gid] = [];
                foreach (Database::all(
                    'SELECT s.id, s.title FROM activity_series s JOIN activity_series_groups sg ON sg.series_id = s.id WHERE sg.group_id = ? AND s.is_active = 1 AND s.end_date >= ? ORDER BY s.title',
                    [$gid, today()]
                ) as $b) {
                    $slots = Database::all('SELECT * FROM activity_series_slots WHERE series_id = ? ORDER BY weekday', [$b['id']]);
                    $blocks[$gid][] = ['id' => (int) $b['id'], 'label' => ucfirst(self::summary($slots))];
                }
            }
            $r['blocks'] = $blocks[$gid];
        }
        unset($r);
        return $rows;
    }

    /** Lid koppelt aan een blok (enkel als de huidige groep van het lid in het blok is toegelaten). */
    public static function assign(int $memberId, int $seriesId): bool
    {
        $ok = (int) Database::val(
            'SELECT COUNT(*) FROM group_memberships gm JOIN activity_series_groups asg ON asg.group_id = gm.group_id AND asg.series_id = ? WHERE gm.member_id = ?',
            [$seriesId, $memberId]
        ) > 0;
        if (!$ok) {
            return false;
        }
        if (!Database::val('SELECT COUNT(*) FROM member_series WHERE member_id = ? AND series_id = ? AND weekday = 0', [$memberId, $seriesId])) {
            Database::exec('INSERT INTO member_series (member_id, series_id, weekday) VALUES (?, ?, 0)', [$memberId, $seriesId]);
        }
        return true;
    }

    /** Alle blok-indelingen van een lid wissen (bij een groepswissel krijgt het lid een nieuwe indeling). */
    public static function clearMemberBlocks(int $memberId): void
    {
        Database::exec('DELETE FROM member_series WHERE member_id = ?', [$memberId]);
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
            for (; $d <= $end && $guard < self::MAX_DAYS + 1; $d = $d->modify('+1 day')) {
                if ($d->format('Y-m-d') >= $today) {
                    $guard++;
                }
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
