<?php
final class DashboardController
{
    public static function index(): void
    {
        $upcoming = [];
        if (Auth::can('activities.read')) {
            [$sql, $params] = Scope::activityFilter('a');
            $upcoming = Database::all(
                "SELECT a.*, t.code AS type_code, t.name AS type_name
                 FROM activities a JOIN activity_types t ON t.id = a.type_id
                 WHERE a.ends_at >= ? AND a.status = 'PLANNED' AND $sql
                 ORDER BY a.starts_at LIMIT 8",
                array_merge([now()], $params)
            );
        }

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
            compact('upcoming', 'stats', 'guardianMissing'),
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
        return ['unfilled' => ReportController::unfilled(21, 6), 'absences' => array_slice($absences, 0, 12)];
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
