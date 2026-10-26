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
        $newRegistrations = Auth::can('registrations.manage') ? (int) Database::val("SELECT COUNT(*) FROM registrations WHERE status = 'PENDING'") : 0;

        render('dashboard', array_merge(
            compact('upcoming', 'stats', 'guardianMissing', 'newRegistrations'),
            self::family(),
            self::coach(),
            self::office()
        ), 'Overzicht');
    }

    /** Ouder of lid: wat staat er open voor mijn kinderen of mezelf. */
    private static function family(): array
    {
        $own = Scope::ownMemberIds();
        $dues = [];
        $meetTodo = [];
        if ($own) {
            $in = Scope::in($own);
            $dues = Database::all(
                "SELECT d.*, m.first_name, m.last_name, s.name AS season_name FROM dues d JOIN members m ON m.id = d.member_id JOIN seasons s ON s.id = d.season_id
                 WHERE d.status = 'OPEN' AND d.member_id IN ($in) ORDER BY s.start_date, m.first_name",
                $own
            );
            if (Auth::can('activities.read')) {
                [$sql, $params] = Scope::activityFilter('a');
                foreach (Database::all(
                    "SELECT a.* FROM activities a JOIN activity_types t ON t.id = a.type_id
                     WHERE t.code = 'COMPETITION' AND a.status = 'PLANNED' AND a.starts_at > ? AND $sql ORDER BY a.starts_at LIMIT 10",
                    array_merge([now()], $params)
                ) as $a) {
                    $names = [];
                    foreach (Scope::targetMembers($a) as $m) {
                        if (in_array((int) $m['id'], $own, true) && !Database::val('SELECT 1 FROM meet_entries WHERE activity_id = ? AND member_id = ?', [$a['id'], $m['id']])) {
                            $names[] = $m['first_name'];
                        }
                    }
                    if ($names) {
                        $meetTodo[] = ['activity' => $a, 'names' => $names];
                    }
                }
            }
        }
        return ['openDues' => $dues, 'meetTodo' => $meetTodo, 'isFamily' => (bool) $own];
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

    /** Secretariaat en beheer: wat wacht er op een beslissing. */
    private static function office(): array
    {
        $o = ['office' => [], 'noTfa' => false];
        if (Auth::can('registrations.manage')) {
            $oldest = Database::val("SELECT MIN(confirmed_at) FROM registrations WHERE status = 'PENDING'");
            $msg = Database::val('SELECT MIN(created_at) FROM contact_messages WHERE handled_at IS NULL');
            $o['office']['pending'] = [(int) Database::val("SELECT COUNT(*) FROM registrations WHERE status = 'PENDING'"), $oldest];
            $o['office']['messages'] = [(int) Database::val('SELECT COUNT(*) FROM contact_messages WHERE handled_at IS NULL'), $msg];
            $o['office']['swimtest'] = [(int) Database::val("SELECT COUNT(*) FROM swimtest_requests WHERE status = 'WAITING'"), count(SwimTestController::followUp())];
        }
        if (Auth::can('fees.manage')) {
            $r = Database::one("SELECT COUNT(*) AS n, COALESCE(SUM(amount_cents), 0) AS total FROM dues WHERE status = 'OPEN'");
            $o['office']['dues'] = [(int) $r['n'], (int) $r['total']];
        }
        if (Auth::can('groups.write')) {
            $o['office']['places'] = Database::all(
                "SELECT g.id, g.name, g.capacity, (SELECT COUNT(*) FROM group_memberships gm WHERE gm.group_id = g.id) AS n,
                        (SELECT COUNT(*) FROM group_waitlist w JOIN members m ON m.id = w.member_id WHERE w.group_id = g.id AND m.status = 'ACTIVE') AS waiting
                 FROM `groups` g WHERE g.is_active = 1 AND g.capacity IS NOT NULL ORDER BY g.name"
            );
            $o['office']['places'] = array_values(array_filter($o['office']['places'], fn($g) => $g['waiting'] > 0 || $g['n'] >= $g['capacity']));
        }
        $u = Auth::user();
        $o['noTfa'] = (Auth::hasRole('ADMIN') || Auth::hasRole('BOARD')) && !(int) Database::val('SELECT totp_enabled FROM users WHERE id = ?', [$u['id']]);
        return $o;
    }
}
