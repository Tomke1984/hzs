<?php
final class GroupController
{
    public static function index(): void
    {
        $gids = Scope::groupIds();
        $where = '1=1';
        $params = [];
        if ($gids !== null) {
            $where = $gids ? 'g.id IN (' . Scope::in($gids) . ')' : '0=1';
            $params = array_merge($params, $gids);
        }
        $groups = Database::all(
            "SELECT g.*,
                (SELECT COUNT(*) FROM group_memberships gm WHERE gm.group_id = g.id) AS member_count
             FROM `groups` g WHERE $where ORDER BY g.is_active DESC, g.category, g.name",
            $params
        );
        render('groups/index', ['groups' => $groups], 'Groepen');
    }

    /** Groepen die de gebruiker mag zien, voor filterlijsten (per soort gesorteerd). */
    public static function filterOptions(): array
    {
        $gids = Scope::groupIds();
        if ($gids !== null && !$gids) {
            return [];
        }
        $where = $gids === null ? '1=1' : 'id IN (' . Scope::in($gids) . ')';
        return Database::all("SELECT id, name, category FROM `groups` WHERE is_active = 1 AND $where ORDER BY category, name", $gids ?? []);
    }

    /** @return array{0:string,1:int} gevalideerde filter (soort, groep-id) uit de querystring */
    public static function readFilter(): array
    {
        $cat = (string) ($_GET['cat'] ?? '');
        if (!array_key_exists($cat, group_categories())) {
            $cat = '';
        }
        $gid = (int) ($_GET['g'] ?? 0);
        if ($gid && !array_filter(self::filterOptions(), fn($g) => (int) $g['id'] === $gid)) {
            $gid = 0;
        }
        return [$cat, $gid];
    }

    public static function show(): void
    {
        $group = self::loadVisible((int) ($_GET['id'] ?? 0));
        $today = today();

        $where = 'gm.group_id = ?';
        $params = [$group['id']];
        $mids = Scope::memberIds();
        if ($mids !== null) {
            $where .= $mids ? ' AND m.id IN (' . Scope::in($mids) . ')' : ' AND 0=1';
            $params = array_merge($params, $mids);
        }

        $memberships = Database::all(
            "SELECT gm.id AS membership_id, m.id, m.birth_date, m.first_name, m.last_name, m.status
             FROM group_memberships gm JOIN members m ON m.id = gm.member_id
             WHERE $where ORDER BY m.last_name, m.first_name",
            $params
        );

        $trainers = Database::all(
            'SELECT u.display_name FROM group_trainers gt JOIN users u ON u.id = gt.user_id
             WHERE gt.group_id = ? ORDER BY u.display_name',
            [$group['id']]
        );

        $candidates = [];
        if (Auth::can('groups.write')) {
            $candidates = Database::all(
                "SELECT m.id, m.birth_date, m.first_name, m.last_name FROM members m
                 WHERE m.status = 'ACTIVE' AND NOT EXISTS (
                    SELECT 1 FROM group_memberships gm WHERE gm.member_id = m.id)
                 ORDER BY m.last_name, m.first_name"
            );
        }
        $targets = Auth::can('groups.write')
            ? Database::all('SELECT id, name, category FROM `groups` WHERE is_active = 1 AND id != ? ORDER BY category, name', [$group['id']])
            : [];

        $now = date('Y-m-d H:i:s');
        $activities = Auth::can('activities.read') ? Database::all(
            "SELECT a.id, a.title, a.starts_at, a.ends_at, a.location, a.status, t.name AS type_name
             FROM activities a JOIN activity_groups ag ON ag.activity_id = a.id
             JOIN activity_types t ON t.id = a.type_id
             WHERE ag.group_id = ? AND a.ends_at >= ? ORDER BY a.starts_at LIMIT 15",
            [$group['id'], $now]
        ) : [];
        $series = Auth::can('activities.write') ? Database::all(
            'SELECT s.id, s.title, s.start_date, s.end_date, s.is_active FROM activity_series s
             JOIN activity_series_groups sg ON sg.series_id = s.id
             WHERE sg.group_id = ? ORDER BY s.is_active DESC, s.title',
            [$group['id']]
        ) : [];

        $blockedIds = $candidates ? Guardianship::missingIds() : [];

        // Trainingsdag per lid (zwemschool): enkel actieve reeksen van de groep.
        $daySeries = [];
        $assigned = [];
        if (Auth::can('activities.read')) {
            $daySeries = Database::all(
                'SELECT s.id, s.title FROM activity_series s JOIN activity_series_groups sg ON sg.series_id = s.id
                 WHERE sg.group_id = ? AND s.is_active = 1 AND s.end_date >= ? ORDER BY s.title',
                [$group['id'], today()]
            );
            foreach ($daySeries as &$ds) {
                $ds['summary'] = SeriesService::summary(Database::all('SELECT * FROM activity_series_slots WHERE series_id = ?', [$ds['id']]));
            }
            unset($ds);
            if ($daySeries) {
                $ids = array_map('intval', array_column($daySeries, 'id'));
                foreach (Database::all('SELECT member_id, series_id FROM member_series WHERE series_id IN (' . Scope::in($ids) . ')', $ids) as $r) {
                    $assigned[(int) $r['member_id']][(int) $r['series_id']] = true;
                }
            }
        }

        render('groups/show', compact('group', 'memberships', 'trainers', 'candidates', 'blockedIds', 'targets', 'activities', 'series', 'daySeries', 'assigned'), $group['name']);
    }

    /** Trainingsdag(en) van de leden van een groep opslaan. Niets aangevinkt = alle trainingen van de groep. */
    public static function saveDays(): void
    {
        $group = Database::one('SELECT id FROM `groups` WHERE id = ?', [(int) input('group_id', '0')]);
        if (!$group) {
            abort(404, 'Groep niet gevonden.');
        }
        $gSeries = array_map('intval', array_column(Database::all('SELECT series_id FROM activity_series_groups WHERE group_id = ?', [$group['id']]), 'series_id'));
        $members = array_map('intval', array_column(Database::all('SELECT member_id FROM group_memberships WHERE group_id = ?', [$group['id']]), 'member_id'));
        $post = is_array($_POST['days'] ?? null) ? $_POST['days'] : [];
        Database::tx(function () use ($gSeries, $members, $post) {
            foreach ($members as $mid) {
                if ($gSeries) {
                    Database::exec('DELETE FROM member_series WHERE member_id = ? AND series_id IN (' . Scope::in($gSeries) . ')', array_merge([$mid], $gSeries));
                }
                $picked = is_array($post[$mid] ?? null) ? array_unique(array_map('intval', $post[$mid])) : [];
                foreach ($picked as $sid) {
                    if (in_array($sid, $gSeries, true)) {
                        Database::exec('INSERT INTO member_series (member_id, series_id) VALUES (?, ?)', [$mid, $sid]);
                    }
                }
            }
        });
        audit('UPDATE', 'groups', (int) $group['id'], 'trainingsdagen per lid');
        flash('success', 'Trainingsdagen opgeslagen.');
        redirect(url('group', ['id' => $group['id']]) . '#trainingsdagen');
    }

    public static function form(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        $group = $id ? self::loadVisible($id) : ['id' => 0, 'name' => '', 'description' => '', 'goals' => '', 'category' => input('category', 'SWIM_SCHOOL'), 'is_active' => 1];
        $selected = $id ? array_map('intval', array_column(Database::all('SELECT user_id FROM group_trainers WHERE group_id = ?', [$id]), 'user_id')) : [];
        render('groups/form', ['group' => $group, 'errors' => [], 'trainerOptions' => self::trainerOptions($selected), 'selectedTrainers' => $selected], $id ? 'Groep wijzigen' : 'Nieuwe groep');
    }

    /** Actieve trainers, plus reeds gekoppelde (ook indien nu inactief) zodat ze niet stilletjes verdwijnen. */
    private static function trainerOptions(array $selected): array
    {
        $rows = Database::all(
            "SELECT DISTINCT u.id, u.display_name, u.is_active FROM users u
             JOIN user_roles ur ON ur.user_id = u.id JOIN roles r ON r.id = ur.role_id
             WHERE r.code = 'TRAINER' ORDER BY u.display_name"
        );
        return array_values(array_filter($rows, fn($u) => (int) $u['is_active'] === 1 || in_array((int) $u['id'], $selected, true)));
    }

    private static function syncTrainers(int $groupId, array $wanted): void
    {
        $valid = array_map('intval', array_column(self::trainerOptions([]), 'id'));
        $wanted = array_values(array_intersect(array_unique(array_map('intval', $wanted)), $valid));
        $current = array_map('intval', array_column(Database::all('SELECT user_id FROM group_trainers WHERE group_id = ?', [$groupId]), 'user_id'));
        foreach (array_diff($current, $wanted) as $uid) {
            Database::exec('DELETE FROM group_trainers WHERE group_id = ? AND user_id = ?', [$groupId, $uid]);
            audit('DELETE', 'group_trainer', $groupId);
        }
        foreach (array_diff($wanted, $current) as $uid) {
            Database::insert('INSERT INTO group_trainers (user_id, group_id, start_date) VALUES (?,?,?)', [$uid, $groupId, today()]);
            audit('CREATE', 'group_trainer', $groupId);
        }
    }

    public static function save(): void
    {
        $id = (int) input('id', '0');
        if ($id) {
            self::loadVisible($id);
        }
        $data = [
            'name' => input('name'),
            'description' => input('description'),
            'goals' => input('goals'),
            'category' => input('category'),
            'is_active' => input('is_active') === '1' ? 1 : 0,
        ];

        if ($data['category'] === '') {
            $data['category'] = 'SWIM_SCHOOL';
        }
        $errors = [];
        if ($data['name'] === '') {
            $errors[] = 'Naam is verplicht.';
        } elseif (mb_strlen($data['name']) > 80) {
            $errors[] = 'Naam is te lang (max. 80 tekens).';
        } elseif (Database::val('SELECT COUNT(*) FROM `groups` WHERE name = ? AND id <> ?', [$data['name'], $id]) > 0) {
            $errors[] = 'Er bestaat al een groep met deze naam.';
        }
        if (!array_key_exists($data['category'], group_categories())) {
            $errors[] = 'Kies zwemschool of competitie.';
        }
        if (mb_strlen($data['goals']) > 8000) {
            $errors[] = 'Doelstellingen zijn te lang (max. 8000 tekens).';
        }
        if (mb_strlen($data['description']) > 255) {
            $errors[] = 'Omschrijving is te lang (max. 255 tekens).';
        }

        if ($errors) {
            http_response_code(422);
            $sel = array_map('intval', (array) ($_POST['trainers'] ?? []));
            render('groups/form', ['group' => array_merge(['id' => $id], $data), 'errors' => $errors, 'trainerOptions' => self::trainerOptions($sel), 'selectedTrainers' => $sel], $id ? 'Groep wijzigen' : 'Nieuwe groep');
            return;
        }

        $desc = $data['description'] === '' ? null : $data['description'];
        $goals = $data['goals'] === '' ? null : $data['goals'];
        if ($id) {
            Database::exec('UPDATE `groups` SET name=?, description=?, category=?, is_active=?, goals=? WHERE id=?', [$data['name'], $desc, $data['category'], $data['is_active'], $goals, $id]);
            audit('UPDATE', 'group', $id);
            flash('success', 'Groep bijgewerkt.');
        } else {
            $id = Database::insert('INSERT INTO `groups` (name, description, category, is_active, goals) VALUES (?,?,?,?,?)', [$data['name'], $desc, $data['category'], $data['is_active'], $goals]);
            ensure_group_colors();
            audit('CREATE', 'group', $id);
            flash('success', 'Groep aangemaakt.');
        }
        self::syncTrainers($id, (array) ($_POST['trainers'] ?? []));
        redirect(url('group', ['id' => $id]));
    }

    /** Lid aan groep koppelen; kan vanaf zowel de ledenpagina als de groepspagina. */
    /** Lid in een groep plaatsen (enkel als het lid nog in geen enkele groep zit). */
    public static function addMember(): void
    {
        $memberId = (int) input('member_id', '0');
        $groupId = (int) input('group_id', '0');
        $back = input('return') === 'group' ? url('group', ['id' => $groupId]) : url('member', ['id' => $memberId]);

        $error = self::placeMember($memberId, $groupId, false);
        if ($error) {
            flash('error', $error);
        } else {
            flash('success', 'Lid in de groep geplaatst.');
        }
        redirect($back);
    }

    /** Groep van één lid instellen of wijzigen vanaf de ledenpagina (0 = geen groep). */
    public static function setMemberGroup(): void
    {
        $memberId = (int) input('member_id', '0');
        $groupId = (int) input('group_id', '0');
        $back = url('member', ['id' => $memberId]);
        if (!Database::val('SELECT COUNT(*) FROM members WHERE id = ?', [$memberId])) {
            abort(404, 'Lid niet gevonden.');
        }
        if ($groupId === 0) {
            Database::exec('DELETE FROM group_memberships WHERE member_id = ?', [$memberId]);
            audit('DELETE', 'group_membership', null, "member=$memberId");
            flash('success', 'Lid zit nu in geen enkele groep.');
            redirect($back);
        }
        $error = self::placeMember($memberId, $groupId, true);
        flash($error ? 'error' : 'success', $error ?: 'Groep van het lid bijgewerkt.');
        redirect($back);
    }

    /** @return ?string foutmelding, of null bij succes */
    private static function placeMember(int $memberId, int $groupId, bool $replace): ?string
    {
        $member = Database::one('SELECT id, status, birth_date FROM members WHERE id = ?', [$memberId]);
        $group = Database::one('SELECT id, is_active FROM `groups` WHERE id = ?', [$groupId]);
        if (!$member || !$group) {
            return 'Lid of groep bestaat niet.';
        }
        if ($member['status'] !== 'ACTIVE' || (int) $group['is_active'] !== 1) {
            return 'Enkel actieve leden kunnen in actieve groepen geplaatst worden.';
        }
        if (Guardianship::isMinor($member['birth_date']) && Guardianship::count($memberId) === 0) {
            return 'Dit lid is jonger dan 18 en heeft nog geen ouder of voogd. Koppel eerst een ouder of voogd.';
        }
        $current = Database::one(
            'SELECT gm.id, gm.group_id, g.name FROM group_memberships gm JOIN `groups` g ON g.id = gm.group_id WHERE gm.member_id = ? ORDER BY gm.id LIMIT 1',
            [$memberId]
        );
        if ($current && (int) $current['group_id'] === $groupId) {
            return 'Dit lid zit al in deze groep.';
        }
        if ($current && !$replace) {
            return 'Dit lid zit al in de groep "' . $current['name'] . '". Een lid kan maar in één groep zitten: verplaats het lid naar de nieuwe groep.';
        }
        Database::tx(function () use ($memberId, $groupId) {
            Database::exec('DELETE FROM group_memberships WHERE member_id = ?', [$memberId]);
            $id = Database::insert('INSERT INTO group_memberships (member_id, group_id, start_date) VALUES (?,?,?)', [$memberId, $groupId, today()]);
            audit('CREATE', 'group_membership', $id);
        });
        return null;
    }

    /** Groep verwijderen: enkel als er geen leden, activiteiten of reeksen aan hangen. */
    public static function delete(): void
    {
        $id = (int) input('id', '0');
        $group = Database::one('SELECT id, name FROM `groups` WHERE id = ?', [$id]);
        if (!$group) {
            abort(404, 'Groep niet gevonden.');
        }
        Database::tx(function () use ($id) {
            // Reeksen die enkel voor deze groep bestaan verdwijnen mee, met hun trainingen.
            $solo = array_column(Database::all(
                'SELECT series_id FROM activity_series_groups GROUP BY series_id HAVING COUNT(*) = 1 AND MAX(CASE WHEN group_id = ? THEN 1 ELSE 0 END) = 1',
                [$id]
            ), 'series_id');
            foreach ($solo as $sid) {
                foreach (array_column(Database::all('SELECT id FROM activities WHERE series_id = ?', [$sid]), 'id') as $aid) {
                    self::deleteActivity((int) $aid);
                }
                Database::exec('DELETE FROM activity_series_slots WHERE series_id = ?', [$sid]);
                Database::exec('DELETE FROM activity_series_groups WHERE series_id = ?', [$sid]);
                Database::exec('DELETE FROM activity_series_skips WHERE series_id = ?', [$sid]);
                Database::exec('DELETE FROM activity_series WHERE id = ?', [$sid]);
            }
            Database::exec('DELETE FROM group_memberships WHERE group_id = ?', [$id]);
            Database::exec('DELETE FROM group_trainers WHERE group_id = ?', [$id]);
            Database::exec('DELETE FROM activity_series_groups WHERE group_id = ?', [$id]);
            Database::exec('DELETE FROM activity_groups WHERE group_id = ?', [$id]);
            // Groepsactiviteiten die nu aan geen enkele groep meer hangen, verdwijnen ook.
            foreach (array_column(Database::all(
                "SELECT a.id FROM activities a WHERE a.audience = 'GROUPS' AND NOT EXISTS (SELECT 1 FROM activity_groups ag WHERE ag.activity_id = a.id)"
            ), 'id') as $aid) {
                self::deleteActivity((int) $aid);
            }
            Database::exec('DELETE FROM `groups` WHERE id = ?', [$id]);
        });
        audit('DELETE', 'group', $id, $group['name']);
        flash('success', 'Groep "' . $group['name'] . '" verwijderd.');
        redirect(url('groups'));
    }

    private static function deleteActivity(int $id): void
    {
        Database::exec('DELETE FROM attendance WHERE activity_id = ?', [$id]);
        Database::exec('DELETE FROM activity_groups WHERE activity_id = ?', [$id]);
        Database::exec('DELETE FROM activities WHERE id = ?', [$id]);
    }

    /** Meerdere leden van de ene groep naar de andere verplaatsen. */
    public static function moveMembers(): void
    {
        $from = (int) input('from_group_id', '0');
        $to = (int) input('to_group_id', '0');
        $back = url('group', ['id' => $from]);
        $raw = $_POST['membership_ids'] ?? [];
        $ids = is_array($raw) ? array_values(array_unique(array_filter(array_map('intval', $raw)))) : [];

        $target = Database::one('SELECT id, name, is_active FROM `groups` WHERE id = ?', [$to]);
        if (!Database::val('SELECT COUNT(*) FROM `groups` WHERE id = ?', [$from])) {
            abort(404, 'Groep niet gevonden.');
        }
        if (!$ids) {
            flash('error', 'Selecteer eerst de leden die je wilt verplaatsen.');
            redirect($back);
        }
        if (!$target || (int) $target['is_active'] !== 1 || $to === $from) {
            flash('error', 'Kies een andere, actieve groep als bestemming.');
            redirect($back);
        }
        $moved = 0;
        foreach ($ids as $mid) {
            $ms = Database::one(
                "SELECT gm.id, gm.member_id FROM group_memberships gm JOIN members m ON m.id = gm.member_id
                 WHERE gm.id = ? AND gm.group_id = ? AND m.status = 'ACTIVE'",
                [$mid, $from]
            );
            if (!$ms) {
                continue;
            }
            Database::exec('UPDATE group_memberships SET group_id = ? WHERE id = ?', [$to, $ms['id']]);
            audit('MOVE', 'group_membership', (int) $ms['id'], json_encode(['from' => $from, 'to' => $to]));
            $moved++;
        }
        $skipped = count($ids) - $moved;
        flash($moved ? 'success' : 'error', $moved
            ? $moved . ($moved === 1 ? ' lid' : ' leden') . ' verplaatst naar "' . $target['name'] . '"' . ($skipped ? ", $skipped overgeslagen" : '') . '.'
            : 'Geen enkel lid kon worden verplaatst.');
        redirect($back);
    }

    /** Lid uit de groep halen. */
    public static function removeMember(): void
    {
        $row = Database::one('SELECT * FROM group_memberships WHERE id = ?', [(int) input('membership_id', '0')]);
        if (!$row) {
            abort(404, 'Dit lid zit niet in een groep.');
        }
        Database::exec('DELETE FROM group_memberships WHERE id = ?', [$row['id']]);
        audit('DELETE', 'group_membership', (int) $row['id']);
        flash('success', 'Lid uit de groep gehaald.');
        redirect(input('return') === 'group' ? url('group', ['id' => $row['group_id']]) : url('member', ['id' => $row['member_id']]));
    }

    private static function loadVisible(int $id): array
    {
        $group = $id ? Database::one('SELECT * FROM `groups` WHERE id = ?', [$id]) : null;
        if (!$group || !Scope::canSeeGroup((int) $group['id'])) {
            abort(404, 'Deze groep bestaat niet of je hebt er geen toegang toe.');
        }
        return $group;
    }
}
