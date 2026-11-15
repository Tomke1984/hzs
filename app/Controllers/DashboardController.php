<?php
final class DashboardController
{
    public static function index(): void
    {
        $upcoming = [];
        // Eerstvolgende activiteiten: als lijst of als weekkalender (keuze onthouden in de sessie).
        $view = in_array($_GET['view'] ?? '', ['list', 'week'], true) ? $_GET['view'] : ($_SESSION['dashboard_view'] ?? 'week');
        $_SESSION['dashboard_view'] = $view;
        $offset = max(-8, min(52, (int) ($_GET['w'] ?? 0)));
        $monday = date('Y-m-d', strtotime('monday this week', strtotime(today())));
        $monday = date('Y-m-d', strtotime("$monday $offset weeks"));
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
                if ($week && Coverage::canView()) {
                    $me = (int) Auth::user()['id'];
                    foreach (Coverage::list($monday . ' 00:00:00', date('Y-m-d 00:00:00', strtotime("$monday +7 days"))) as $c) {
                        if (array_filter($c['trainers'], fn($t) => $t['id'] === $me && !$t['absent'])) {
                            $mineIds[(int) $c['id']] = true;
                        }
                    }
                }
                foreach ($week as $a) {
                    $a['groups'] = $names[(int) $a['id']] ?? [];
                    $a['mine'] = isset($mineIds[(int) $a['id']]);
                    $byDay[substr($a['starts_at'], 0, 10)][] = $a;
                }
            } else {
                $upcoming = Database::all(
                    "SELECT a.*, t.code AS type_code, t.name AS type_name
                     FROM activities a JOIN activity_types t ON t.id = a.type_id
                     WHERE a.ends_at >= ? AND a.status = 'PLANNED' AND $sql
                     ORDER BY a.starts_at LIMIT 8",
                    array_merge([now()], $params)
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

        $stats = null;
        if (Scope::clubWide()) {
            $stats = [
                'members'    => (int) Database::val("SELECT COUNT(*) FROM members WHERE status = 'ACTIVE'"),
                'groups'     => (int) Database::val('SELECT COUNT(*) FROM `groups` WHERE is_active = 1'),
                'activities' => (int) Database::val("SELECT COUNT(*) FROM activities WHERE starts_at >= ? AND status = 'PLANNED'", [now()]),
            ];
        }

        $guardianMissing = Auth::can('members.contact') ? Guardianship::missingCount() : 0;

        render('dashboard', array_merge(
            compact('upcoming', 'stats', 'guardianMissing', 'mode', 'offset', 'monday', 'byDay'),
            self::coach(),
            self::office(),
            self::coverage()
        ), 'Overzicht');
    }

    /** Trainersplanning competitie: tekorten in de komende 14 dagen en de eigen trainingen van deze week. */
    private static function coverage(): array
    {
        if (!Coverage::canView()) {
            return ['coverShort' => [], 'coverMine' => [], 'coverView' => false];
        }
        $uid = (int) Auth::user()['id'];
        $rows = Coverage::list(now(), date('Y-m-d H:i:s', strtotime('+14 days')));
        $short = array_values(array_filter($rows, fn($a) => $a['missing'] > 0));
        $mine = array_values(array_filter($rows, fn($a) => $a['starts_at'] < date('Y-m-d H:i:s', strtotime('+7 days'))
            && (bool) array_filter($a['trainers'], fn($t) => $t['id'] === $uid && !$t['absent'])));
        return ['coverShort' => $short, 'coverMine' => $mine, 'coverView' => true];
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

    /** Beheer: tweestapsverificatie nog niet aan. */
    private static function office(): array
    {
        $o = ['noTfa' => false];
        $u = Auth::user();
        $o['noTfa'] = (Auth::hasRole('ADMIN') || Auth::hasRole('BOARD')) && !(int) Database::val('SELECT totp_enabled FROM users WHERE id = ?', [$u['id']]);
        return $o;
    }
}
