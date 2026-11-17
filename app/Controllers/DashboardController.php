<?php
final class DashboardController
{
    public static function index(): void
    {
        $upcoming = [];
        // Ouders en zwemmers (geen trainer, geen beheer): eerst "wanneer is de volgende training?"
        $family = Scope::ownMemberIds() && !Auth::hasRole('TRAINER') && !Auth::can('activities.write');
        // Activiteiten: als lijst of als weekkalender (keuze onthouden in de sessie).
        $view = in_array($_GET['view'] ?? '', ['list', 'week'], true) ? $_GET['view'] : ($_SESSION['dashboard_view'] ?? 'week');
        $_SESSION['dashboard_view'] = $view;
        $offset = max(-8, min(52, (int) ($_GET['w'] ?? 0)));
        // Ouders en zwemmers: de week begint vandaag (rollend), zodat de eerstvolgende dagen altijd in beeld zijn.
        // Trainers en bestuur houden ma–zo (zelfde week als de trainersplanning).
        $monday = $family ? date('Y-m-d', strtotime(today() . ' ' . ($offset * 7) . ' days')) : week_monday($offset);
        $byDay = [];
        if (Auth::can('activities.read')) {
            [$sql, $params] = Scope::activityFilter('a');
            if ($view === 'week') {
                $week = Database::all(
                    "SELECT a.*, t.code AS type_code, t.name AS type_name
                     FROM activities a JOIN activity_types t ON t.id = a.type_id
                     WHERE a.starts_at >= ? AND a.starts_at < ? AND a.status = 'PLANNED' AND $sql
                     ORDER BY a.starts_at",
                    array_merge([$monday . ' 00:00:00', date('Y-m-d 00:00:00', strtotime("$monday +7 days"))], $params)
                );
                $names = [];
                if ($week) {
                    $ids = array_map(fn($a) => (int) $a['id'], $week);
                    foreach (Database::all(
                        'SELECT ag.activity_id, g.name FROM activity_groups ag JOIN `groups` g ON g.id = ag.group_id
                         WHERE ag.activity_id IN (' . Scope::in($ids) . ') ORDER BY g.name',
                        $ids
                    ) as $g) {
                        $names[(int) $g['activity_id']][] = $g['name'];
                    }
                }
                // Trainers: markeer de activiteiten waar je zelf ingepland staat (en niet afgemeld bent).
                $mineIds = [];
                $shortIds = [];
                if ($week && Coverage::canView()) {
                    $me = (int) Auth::user()['id'];
                    foreach (Coverage::list($monday . ' 00:00:00', date('Y-m-d 00:00:00', strtotime("$monday +7 days"))) as $c) {
                        if (array_filter($c['trainers'], fn($t) => $t['id'] === $me && !$t['absent'])) {
                            $mineIds[(int) $c['id']] = true;
                        }
                        if ($c['missing'] > 0) {
                            $shortIds[(int) $c['id']] = $c['present'];
                        }
                    }
                }
                foreach ($week as $a) {
                    $a['short'] = $shortIds[(int) $a['id']] ?? null;
                    $a['groups'] = $names[(int) $a['id']] ?? [];
                    $a['mine'] = isset($mineIds[(int) $a['id']]);
                    $byDay[substr($a['starts_at'], 0, 10)][] = $a;
                }
            } else {
                $upcoming = Database::all(
                    "SELECT a.*, t.code AS type_code, t.name AS type_name
                     FROM activities a JOIN activity_types t ON t.id = a.type_id
                     WHERE a.ends_at >= ? AND a.starts_at < ? AND a.status = 'PLANNED' AND $sql
                     ORDER BY a.starts_at LIMIT 12",
                    array_merge([now(), date('Y-m-d 00:00:00', strtotime(today() . ' +15 days'))], $params)
                );
                if ($upcoming && Coverage::canView()) {
                    $me = (int) Auth::user()['id'];
                    $mineIds = [];
                    foreach (Coverage::list('2000-01-01 00:00:00', '2100-01-01 00:00:00', array_column($upcoming, 'id')) as $c) {
                        if (array_filter($c['trainers'], fn($t) => $t['id'] === $me && !$t['absent'])) {
                            $mineIds[(int) $c['id']] = true;
                        }
                    }
                    foreach ($upcoming as &$a) {
                        $a['mine'] = isset($mineIds[(int) $a['id']]);
                    }
                    unset($a);
                }
            }
        }
        $mode = $view;

        // Ouders en zwemmers: per activiteit de eigen kinderen (of jezelf) met afmeldstatus, om meteen te kunnen afmelden.
        $own = [];
        if ($family) {
            $acts = $upcoming;
            foreach ($byDay as $items) {
                $acts = array_merge($acts, $items);
            }
            $own = self::ownPerActivity($acts);
        }

        // Uitnodigingen die nog niet geactiveerd zijn (actie voor wie gebruikers beheert)
        $pendingInvites = Auth::can('users.manage')
            ? (int) Database::val("SELECT COUNT(*) FROM users WHERE is_active = 1 AND password_hash LIKE '!%'")
            : 0;

        $guardianMissing = Auth::can('members.contact') ? Guardianship::missingCount() : 0;
        $nextTrainings = $family ? self::nextTrainings() : [];
        // Herinnering voor ouders en volwassen leden: noodcontact nog niet ingevuld
        $missingEmergency = $family ? array_values(array_filter(Database::all(
            "SELECT * FROM members WHERE status = 'ACTIVE' AND id IN (" . Scope::in(Scope::ownMemberIds()) . ")
             AND COALESCE(emergency_name, '') = '' AND COALESCE(emergency_phone, '') = '' ORDER BY first_name",
            Scope::ownMemberIds()
        ), fn($m) => Guardianship::canEditOwn((int) Auth::user()['id'], $m))) : [];

        render('dashboard', array_merge(
            compact('upcoming', 'pendingInvites', 'guardianMissing', 'mode', 'offset', 'monday', 'byDay', 'family', 'nextTrainings', 'own', 'missingEmergency'),
            self::coach(),
            self::office(),
            self::coverage()
        ), 'Overzicht');
    }

    /**
     * Eigen leden (kinderen of jezelf) per toekomstige training/activiteit, met afmeldstatus.
     * @param list<array> $acts
     * @return array<int, list<array{member:array,absent:?array}>> per activiteit-id
     */
    private static function ownPerActivity(array $acts): array
    {
        $ownIds = Scope::ownMemberIds();
        if (!$acts || !$ownIds) {
            return [];
        }
        $members = [];
        foreach (Database::all("SELECT id, first_name FROM members WHERE status = 'ACTIVE' AND id IN (" . Scope::in($ownIds) . ') ORDER BY first_name', $ownIds) as $m) {
            $members[(int) $m['id']] = $m;
        }
        $out = [];
        foreach ($acts as $a) {
            $aid = (int) $a['id'];
            if (isset($out[$aid]) || $a['ends_at'] <= now() || $a['status'] !== 'PLANNED') {
                continue;
            }
            if ($a['audience'] === 'CLUB') {
                $ids = array_keys($members);
            } else {
                $ids = array_map('intval', array_column(Database::all(
                    'SELECT DISTINCT gm.member_id FROM activity_groups ag JOIN group_memberships gm ON gm.group_id = ag.group_id
                     WHERE ag.activity_id = ? AND gm.member_id IN (' . Scope::in($ownIds) . ')',
                    array_merge([$aid], $ownIds)
                ), 'member_id'));
            }
            $absent = [];
            if ($ids) {
                foreach (Database::all('SELECT member_id, reason FROM absence_reports WHERE activity_id = ? AND member_id IN (' . Scope::in($ids) . ')', array_merge([$aid], $ids)) as $r) {
                    $absent[(int) $r['member_id']] = $r;
                }
            }
            foreach ($ids as $mid) {
                if (isset($members[$mid])) {
                    $out[$aid][] = ['member' => $members[$mid], 'absent' => $absent[$mid] ?? null];
                }
            }
        }
        return $out;
    }

    /** Trainersplanning competitie: tekorten in de komende 14 dagen en de eigen trainingen van deze week. */
    private static function coverage(): array
    {
        if (!Coverage::canView()) {
            return ['coverShort' => [], 'coverNext' => null, 'coverMine' => [], 'coverView' => false];
        }
        $uid = (int) Auth::user()['id'];
        $rows = Coverage::list(now(), date('Y-m-d H:i:s', strtotime('+7 days')));
        $mine = array_values(array_filter($rows, fn($a) => (bool) array_filter($a['trainers'], fn($t) => $t['id'] === $uid && !$t['absent'])));
        // Zelfde tekorten (en periode) als de menubadge en de trainersplanning
        return ['coverShort' => Coverage::shortages(), 'coverNext' => Coverage::shortages() ? null : Coverage::nextShortage(), 'coverMine' => $mine, 'coverView' => true];
    }

    /**
     * Ouders en zwemmers: per eigen lid (kind of zichzelf) de eerstvolgende training, met afmeldstatus.
     * @return list<array{member:array,activity:?array,absent:?array}>
     */
    private static function nextTrainings(): array
    {
        $out = [];
        $ids = Scope::ownMemberIds();
        if (!$ids) {
            return [];
        }
        $members = Database::all("SELECT id, first_name, last_name FROM members WHERE status = 'ACTIVE' AND id IN (" . Scope::in($ids) . ') ORDER BY first_name', $ids);
        foreach ($members as $m) {
            $a = Database::one(
                "SELECT a.*, t.name AS type_name FROM activities a JOIN activity_types t ON t.id = a.type_id
                 WHERE a.status = 'PLANNED' AND a.ends_at > ? AND t.code = 'TRAINING' AND (
                   a.audience = 'CLUB' OR EXISTS (
                     SELECT 1 FROM activity_groups ag JOIN group_memberships gm ON gm.group_id = ag.group_id
                     WHERE ag.activity_id = a.id AND gm.member_id = ? AND gm.start_date <= substr(a.starts_at, 1, 10)
                       AND (gm.end_date IS NULL OR gm.end_date >= substr(a.starts_at, 1, 10))))
                 ORDER BY a.starts_at LIMIT 1",
                [now(), $m['id']]
            );
            $absent = $a ? Database::one('SELECT reason FROM absence_reports WHERE activity_id = ? AND member_id = ?', [$a['id'], $m['id']]) : null;
            $out[] = ['member' => $m, 'activity' => $a, 'absent' => $absent];
        }
        return $out;
    }

    /** Trainer of bestuur: aanwezigheden die nog ingevuld moeten worden en recente afmeldingen. */
    private static function coach(): array
    {
        if (!Auth::can('attendance.write')) {
            return ['unfilled' => [], 'absences' => []];
        }
        [$sql, $params] = Scope::activityFilter('a');
        $coach = Scope::coachMemberIds();
        $absences = [];
        foreach (Database::all(
            "SELECT ar.member_id, ar.reason, a.id AS activity_id, a.title, a.starts_at, m.first_name, m.last_name
             FROM absence_reports ar JOIN activities a ON a.id = ar.activity_id JOIN members m ON m.id = ar.member_id
             WHERE a.status = 'PLANNED' AND a.starts_at > ? AND a.starts_at < ? AND $sql ORDER BY a.starts_at, m.last_name LIMIT 60",
            array_merge([now(), date('Y-m-d H:i:s', strtotime('+14 days'))], $params)
        ) as $r) {
            if ($coach === null || in_array((int) $r['member_id'], $coach, true)) {
                $absences[] = $r;
            }
        }
        return ['unfilled' => feature('attendance') ? ReportController::unfilled(21, 6) : [], 'absences' => array_slice($absences, 0, 12)];
    }

    /** Beheer: meldingen over database, back-up en sleutel. */
    private static function office(): array
    {
        $o = ['dbExposed' => Auth::hasRole('ADMIN') && db_inside_webroot(),
            'backupOverdue' => Auth::hasRole('ADMIN') && Backup::isOverdue(),
            'keyInFile' => Auth::hasRole('ADMIN') && (string) config('app_key', '') === '' && is_file(APP_ROOT . '/storage/app.key')];
        return $o;
    }
}
