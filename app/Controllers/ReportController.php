<?php
/** Aanwezigheidsrapport, auditlog en zoeken. */
final class ReportController
{
    /** Trainingen van de laatste weken waarvan de aanwezigheid nog niet is ingevuld (voor wie ze mag registreren). */
    public static function unfilled(int $days = 21, int $limit = 25): array
    {
        if (!Auth::can('attendance.write')) {
            return [];
        }
        [$sql, $params] = Scope::activityFilter('a');
        $rows = Database::all(
            "SELECT a.*, t.code AS type_code, t.name AS type_name FROM activities a JOIN activity_types t ON t.id = a.type_id
             WHERE a.status = 'PLANNED' AND a.ends_at < ? AND a.starts_at >= ? AND t.code IN ('TRAINING','COMPETITION') AND $sql
               AND NOT EXISTS (SELECT 1 FROM attendance at WHERE at.activity_id = a.id AND at.status <> 'UNKNOWN')
             ORDER BY a.starts_at DESC LIMIT 200",
            array_merge([now(), date('Y-m-d 00:00:00', strtotime("-$days days"))], $params)
        );
        $out = [];
        foreach ($rows as $a) {
            if (Scope::registrableMembers($a)) {
                $out[] = $a;
                if (count($out) >= $limit) {
                    break;
                }
            }
        }
        return $out;
    }

    public static function attendance(): void
    {
        $to = (string) ($_GET['to'] ?? '');
        $to = valid_date($to) ? $to : today();
        $from = (string) ($_GET['from'] ?? '');
        $from = valid_date($from) ? $from : date('Y-m-d', strtotime('-90 days'));
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }
        [$cat, $gid] = GroupController::readFilter();

        $where = ["a.status = 'PLANNED'", 'a.starts_at >= ?', 'a.starts_at < ?', "m.status = 'ACTIVE'"];
        $params = [$from . ' 00:00:00', date('Y-m-d', strtotime($to . ' +1 day')) . ' 00:00:00'];
        $ids = Scope::memberIds();
        if ($ids !== null) {
            $where[] = $ids ? 'm.id IN (' . Scope::in($ids) . ')' : '0=1';
            $params = array_merge($params, $ids);
        }
        if ($cat !== '' || $gid) {
            $sub = 'EXISTS (SELECT 1 FROM group_memberships fgm JOIN `groups` fg ON fg.id = fgm.group_id WHERE fgm.member_id = m.id';
            if ($cat !== '') {
                $sub .= ' AND fg.category = ?';
                $params[] = $cat;
            }
            if ($gid) {
                $sub .= ' AND fg.id = ?';
                $params[] = $gid;
            }
            $where[] = $sub . ')';
        }
        $rows = Database::all(
            "SELECT m.id, m.first_name, m.last_name, at.status, a.starts_at,
                (SELECT g.name FROM group_memberships gm JOIN `groups` g ON g.id = gm.group_id WHERE gm.member_id = m.id ORDER BY gm.id LIMIT 1) AS group_name
             FROM attendance at JOIN activities a ON a.id = at.activity_id JOIN members m ON m.id = at.member_id
             WHERE " . implode(' AND ', $where) . ' ORDER BY a.starts_at DESC',
            $params
        );
        $members = [];
        foreach ($rows as $r) {
            $m = &$members[$r['id']];
            $m ??= ['id' => (int) $r['id'], 'name' => $r['last_name'] . ' ' . $r['first_name'], 'group' => $r['group_name'] ?? '—', 'present' => 0, 'absent' => 0, 'last' => null, 'streak' => 0, 'streakOpen' => true];
            if ($r['status'] === 'PRESENT') {
                $m['present']++;
                $m['last'] ??= $r['starts_at'];
                $m['streakOpen'] = false;
            } elseif ($r['status'] === 'ABSENT') {
                $m['absent']++;
                if ($m['streakOpen']) {
                    $m['streak']++;
                }
            }
            unset($m);
        }
        foreach ($members as &$m) {
            $reg = $m['present'] + $m['absent'];
            $m['pct'] = $reg ? (int) round($m['present'] * 100 / $reg) : null;
            $m['flag'] = $m['streak'] >= 3 ? $m['streak'] . ' keer na elkaar afwezig' : ($reg >= 3 && $m['present'] === 0 ? 'nog niet aanwezig geweest' : '');
        }
        unset($m);
        $byGroup = [];
        foreach ($members as $m) {
            $g = &$byGroup[$m['group']];
            $g ??= ['members' => 0, 'present' => 0, 'absent' => 0];
            $g['members']++;
            $g['present'] += $m['present'];
            $g['absent'] += $m['absent'];
            unset($g);
        }
        ksort($byGroup);
        $sort = (string) ($_GET['sort'] ?? 'name');
        uasort($members, $sort === 'pct'
            ? fn($a, $b) => [$a['pct'] ?? 101, $a['name']] <=> [$b['pct'] ?? 101, $b['name']]
            : fn($a, $b) => strcasecmp($a['name'], $b['name']));
        render('reports/attendance', [
            'members' => array_values($members), 'byGroup' => $byGroup, 'from' => $from, 'to' => $to, 'sort' => $sort,
            'cat' => $cat, 'gid' => $gid, 'groupOptions' => GroupController::filterOptions(), 'unfilled' => self::unfilled(),
        ], 'Aanwezigheidsrapport');
    }

    public static function audit(): void
    {
        $entity = (string) ($_GET['entity'] ?? '');
        $action = (string) ($_GET['action'] ?? '');
        $uid = (int) ($_GET['u'] ?? 0);
        $page = max(1, (int) ($_GET['p'] ?? 1));
        $per = 100;
        $where = ['1=1'];
        $params = [];
        if ($entity !== '') {
            $where[] = 'l.entity = ?';
            $params[] = $entity;
        }
        if ($action !== '') {
            $where[] = 'l.action = ?';
            $params[] = $action;
        }
        if ($uid) {
            $where[] = 'l.user_id = ?';
            $params[] = $uid;
        }
        $w = implode(' AND ', $where);
        $total = (int) Database::val("SELECT COUNT(*) FROM audit_log l WHERE $w", $params);
        $rows = Database::all(
            "SELECT l.*, u.display_name FROM audit_log l LEFT JOIN users u ON u.id = l.user_id WHERE $w ORDER BY l.id DESC LIMIT $per OFFSET " . (($page - 1) * $per),
            $params
        );
        render('reports/audit', [
            'rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => max(1, (int) ceil($total / $per)),
            'entity' => $entity, 'action' => $action, 'uid' => $uid,
            'entities' => array_column(Database::all('SELECT DISTINCT entity FROM audit_log ORDER BY entity'), 'entity'),
            'actions' => array_column(Database::all('SELECT DISTINCT action FROM audit_log ORDER BY action'), 'action'),
            'users' => Database::all('SELECT id, display_name FROM users ORDER BY display_name'),
        ], 'Auditlog');
    }

    public static function search(): void
    {
        $q = trim((string) ($_GET['q'] ?? ''));
        $res = ['members' => [], 'groups' => [], 'activities' => [], 'users' => [], 'pages' => [], 'posts' => []];
        if (mb_strlen($q) >= 2) {
            $like = '%' . $q . '%';
            if (Auth::can('members.read')) {
                $where = ["(m.first_name LIKE ? OR m.last_name LIKE ? OR (m.first_name || ' ' || m.last_name) LIKE ?)"];
                $params = [$like, $like, $like];
                if (Database::driver() === 'mysql') {
                    $where = ["(m.first_name LIKE ? OR m.last_name LIKE ? OR CONCAT(m.first_name, ' ', m.last_name) LIKE ?)"];
                }
                $ids = Scope::memberIds();
                if ($ids !== null) {
                    $where[] = $ids ? 'm.id IN (' . Scope::in($ids) . ')' : '0=1';
                    $params = array_merge($params, $ids);
                }
                $res['members'] = Database::all('SELECT m.id, m.first_name, m.last_name, m.status FROM members m WHERE ' . implode(' AND ', $where) . ' ORDER BY m.last_name, m.first_name LIMIT 25', $params);
            }
            if (Auth::can('groups.read')) {
                $gids = Scope::groupIds();
                $res['groups'] = Database::all(
                    'SELECT id, name, category FROM `groups` WHERE name LIKE ?' . ($gids === null ? '' : ($gids ? ' AND id IN (' . Scope::in($gids) . ')' : ' AND 0=1')) . ' ORDER BY name LIMIT 15',
                    array_merge([$like], $gids ?: [])
                );
            }
            if (Auth::can('activities.read')) {
                [$sql, $params] = Scope::activityFilter('a');
                $res['activities'] = Database::all(
                    "SELECT a.id, a.title, a.starts_at, a.status FROM activities a WHERE (a.title LIKE ? OR a.location LIKE ?) AND a.starts_at >= ? AND $sql ORDER BY a.starts_at LIMIT 15",
                    array_merge([$like, $like, date('Y-m-d 00:00:00', strtotime('-60 days'))], $params)
                );
            }
            if (Auth::can('users.manage')) {
                $res['users'] = Database::all('SELECT id, display_name, email FROM users WHERE display_name LIKE ? OR email LIKE ? ORDER BY display_name LIMIT 15', [$like, $like]);
            }
            if (Auth::can('content.manage')) {
                $res['pages'] = Database::all('SELECT id, title, slug FROM pages WHERE title LIKE ? OR body LIKE ? ORDER BY title LIMIT 15', [$like, $like]);
                $res['posts'] = Database::all('SELECT id, title FROM posts WHERE title LIKE ? OR body LIKE ? ORDER BY id DESC LIMIT 15', [$like, $like]);
            }
        }
        render('reports/search', ['q' => $q, 'res' => $res, 'total' => array_sum(array_map('count', $res))], 'Zoeken');
    }
}
