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

        render('dashboard', compact('upcoming', 'stats', 'guardianMissing'), 'Overzicht');
    }
}
