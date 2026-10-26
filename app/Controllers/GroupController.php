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

        // Trainingsmoment per lid (zwemschool): elke actieve reeks van de groep (een reeks = een trainingsmoment).
        $daySlots = [];
        $assigned = [];
        if (Auth::can('activities.read')) {
            foreach (Database::all(
                'SELECT s.id, s.title FROM activity_series s JOIN activity_series_groups sg ON sg.series_id = s.id
                 WHERE sg.group_id = ? AND s.is_active = 1 AND s.end_date >= ?',
                [$group['id'], today()]
            ) as $r) {
                $slots = Database::all('SELECT * FROM activity_series_slots WHERE series_id = ? ORDER BY weekday', [$r['id']]);
                $daySlots[] = ['key' => (string) $r['id'], 'series_id' => (int) $r['id'], 'label' => $r['title'],
                    'when' => ucfirst(SeriesService::summary($slots)),
                    'sort' => $slots ? sprintf('%d%s', $slots[0]['weekday'], $slots[0]['start_time']) : '9'];
            }
            usort($daySlots, fn($x, $y) => [$x['sort'], $x['label']] <=> [$y['sort'], $y['label']]);
            if ($daySlots) {
                $ids = array_column($daySlots, 'series_id');
                foreach (Database::all('SELECT DISTINCT member_id, series_id FROM member_series WHERE series_id IN (' . Scope::in($ids) . ')', $ids) as $r) {
                    $assigned[(int) $r['member_id']][(string) $r['series_id']] = true;
                }
            }
        }

        $memberCount = (int) Database::val('SELECT COUNT(*) FROM group_memberships WHERE group_id = ?', [$group['id']]);
        $waitlist = Auth::can('groups.write') ? Database::all(
            "SELECT w.id, w.note, w.created_at, m.id AS member_id, m.first_name, m.last_name, m.birth_date FROM group_waitlist w JOIN members m ON m.id = w.member_id
             WHERE w.group_id = ? AND m.status = 'ACTIVE' ORDER BY w.created_at, w.id",
            [$group['id']]
        ) : [];
        $waitCandidates = Auth::can('groups.write') ? Database::all(
            "SELECT m.id, m.first_name, m.last_name, m.birth_date FROM members m
             WHERE m.status = 'ACTIVE' AND m.id NOT IN (SELECT member_id FROM group_waitlist WHERE group_id = ?) AND m.id NOT IN (SELECT member_id FROM group_memberships WHERE group_id = ?)
             ORDER BY m.last_name, m.first_name",
            [$group['id'], $group['id']]
        ) : [];

        render('groups/show', compact('group', 'memberships', 'trainers', 'candidates', 'blockedIds', 'targets', 'activities', 'series', 'daySlots', 'assigned', 'memberCount', 'waitlist', 'waitCandidates'), $group['name']);
    }

    /** Trainingsmoment(en) (dag + uur) van de leden van een groep opslaan. Niets aangevinkt = alle trainingen van de groep. */
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
                        Database::exec('INSERT INTO member_series (member_id, series_id, weekday) VALUES (?, ?, 0)', [$mid, $sid]);
                    }
                }
            }
        });
        audit('UPDATE', 'groups', (int) $group['id'], 'trainingsmoment per lid');
        flash('success', 'Trainingsmomenten opgeslagen.');
        redirect(url('group', ['id' => $group['id']]) . '#trainingsmoment');
    }

    public static function form(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        $group = $id ? self::loadVisible($id) : ['id' => 0, 'name' => '', 'description' => '', 'goals' => '', 'category' => input('category', 'SWIM_SCHOOL'), 'is_active' => 1, 'capacity' => null, 'fee_cents' => null, 'next_group_id' => null];
        $selected = $id ? array_map('intval', array_column(Database::all('SELECT user_id FROM group_trainers WHERE group_id = ?', [$id]), 'user_id')) : [];
        render('groups/form', ['group' => $group, 'errors' => [], 'trainerOptions' => self::trainerOptions($selected), 'selectedTrainers' => $selected, 'otherGroups' => self::otherGroups($id)], $id ? 'Groep wijzigen' : 'Nieuwe groep');
    }

    private static function otherGroups(int $exceptId): array
    {
        return Database::all('SELECT id, name FROM `groups` WHERE id <> ? ORDER BY category, name', [$exceptId]);
    }

    /** "12,50" of "12.5" → 1250 (eurocent); leeg → null; ongeldig → false. @return int|null|false */
    public static function parseMoney(string $v)
    {
        $v = trim(str_replace(['€', ' '], '', $v));
        if ($v === '') {
            return null;
        }
        if (!preg_match('/^\d{1,5}([.,]\d{1,2})?$/', $v)) {
            return false;
        }
        return (int) round(((float) str_replace(',', '.', $v)) * 100);
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
            'capacity' => input('capacity'),
            'fee' => input('fee'),
            'next_group_id' => (int) input('next_group_id', '0'),
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
        $capacity = null;
        if ($data['capacity'] !== '') {
            if (!ctype_digit($data['capacity']) || (int) $data['capacity'] < 1 || (int) $data['capacity'] > 999) {
                $errors[] = 'Capaciteit moet een getal tussen 1 en 999 zijn (of leeg).';
            } else {
                $capacity = (int) $data['capacity'];
            }
        }
        $fee = self::parseMoney($data['fee']);
        if ($fee === false) {
            $errors[] = 'Lidgeld is ongeldig. Gebruik bv. 125 of 125,50.';
            $fee = null;
        }
        $next = $data['next_group_id'] > 0 && $data['next_group_id'] !== $id && Database::val('SELECT COUNT(*) FROM `groups` WHERE id = ?', [$data['next_group_id']]) ? $data['next_group_id'] : null;

        if ($errors) {
            http_response_code(422);
            $sel = array_map('intval', (array) ($_POST['trainers'] ?? []));
            render('groups/form', ['group' => array_merge(['id' => $id, 'fee_cents' => null], $data, ['next_group_id' => $next]), 'errors' => $errors, 'trainerOptions' => self::trainerOptions($sel), 'selectedTrainers' => $sel, 'otherGroups' => self::otherGroups($id)], $id ? 'Groep wijzigen' : 'Nieuwe groep');
            return;
        }

        $desc = $data['description'] === '' ? null : $data['description'];
        $goals = $data['goals'] === '' ? null : $data['goals'];
        if ($id) {
            Database::exec('UPDATE `groups` SET name=?, description=?, category=?, is_active=?, goals=?, capacity=?, fee_cents=?, next_group_id=? WHERE id=?', [$data['name'], $desc, $data['category'], $data['is_active'], $goals, $capacity, $fee, $next, $id]);
            audit('UPDATE', 'group', $id);
            flash('success', 'Groep bijgewerkt.');
        } else {
            $id = Database::insert('INSERT INTO `groups` (name, description, category, is_active, goals, capacity, fee_cents, next_group_id) VALUES (?,?,?,?,?,?,?,?)', [$data['name'], $desc, $data['category'], $data['is_active'], $goals, $capacity, $fee, $next]);
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
            if (self::$notice) {
                flash('warning', self::$notice);
            }
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
            SeriesService::clearMemberBlocks($memberId);
            Database::exec('DELETE FROM group_memberships WHERE member_id = ?', [$memberId]);
            audit('DELETE', 'group_membership', null, "member=$memberId");
            flash('success', 'Lid zit nu in geen enkele groep.');
            redirect($back);
        }
        $error = self::placeMember($memberId, $groupId, true);
        flash($error ? 'error' : 'success', $error ?: 'Groep van het lid bijgewerkt.');
        if (!$error && self::$notice) {
            flash('warning', self::$notice);
        }
        redirect($back);
    }

    /** Waarschuwing na het plaatsen van een lid (bv. groep over capaciteit); de aanroeper toont ze. */
    public static ?string $notice = null;

    /** Geeft een waarschuwing als de groep meer leden heeft dan de capaciteit. */
    public static function capacityNotice(int $groupId): ?string
    {
        $g = Database::one('SELECT name, capacity FROM `groups` WHERE id = ?', [$groupId]);
        if (!$g || $g['capacity'] === null) {
            return null;
        }
        $n = (int) Database::val('SELECT COUNT(*) FROM group_memberships WHERE group_id = ?', [$groupId]);
        return $n > (int) $g['capacity'] ? 'Let op: "' . $g['name'] . '" heeft nu ' . $n . ' leden bij een capaciteit van ' . (int) $g['capacity'] . '.' : null;
    }

    /** @return ?string foutmelding, of null bij succes */
    public static function placeMember(int $memberId, int $groupId, bool $replace): ?string
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
            SeriesService::clearMemberBlocks($memberId);
            Database::exec('DELETE FROM group_memberships WHERE member_id = ?', [$memberId]);
            $id = Database::insert('INSERT INTO group_memberships (member_id, group_id, start_date) VALUES (?,?,?)', [$memberId, $groupId, today()]);
            Database::exec('DELETE FROM group_waitlist WHERE member_id = ? AND group_id = ?', [$memberId, $groupId]);
            audit('CREATE', 'group_membership', $id);
        });
        self::$notice = self::capacityNotice($groupId);
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
            SeriesService::clearMemberBlocks((int) $ms['member_id']);
            Database::exec('UPDATE group_memberships SET group_id = ? WHERE id = ?', [$to, $ms['id']]);
            audit('MOVE', 'group_membership', (int) $ms['id'], json_encode(['from' => $from, 'to' => $to]));
            $moved++;
        }
        $skipped = count($ids) - $moved;
        if ($moved && ($cn = self::capacityNotice($to))) {
            flash('warning', $cn);
        }
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
        SeriesService::clearMemberBlocks((int) $row['member_id']);
        Database::exec('DELETE FROM group_memberships WHERE id = ?', [$row['id']]);
        audit('DELETE', 'group_membership', (int) $row['id']);
        flash('success', 'Lid uit de groep gehaald.');
        redirect(input('return') === 'group' ? url('group', ['id' => $row['group_id']]) : url('member', ['id' => $row['member_id']]));
    }

    /** Lid op de wachtlijst van een groep zetten. */
    public static function waitlistAdd(): void
    {
        $gid = (int) input('group_id', '0');
        $mid = (int) input('member_id', '0');
        $back = url('group', ['id' => $gid]) . '#wachtlijst';
        $ok = Database::val('SELECT COUNT(*) FROM `groups` WHERE id = ? AND is_active = 1', [$gid]) && Database::val("SELECT COUNT(*) FROM members WHERE id = ? AND status = 'ACTIVE'", [$mid]);
        if (!$ok) {
            flash('error', 'Lid of groep bestaat niet (meer) of is niet actief.');
        } elseif (Database::val('SELECT COUNT(*) FROM group_memberships WHERE member_id = ? AND group_id = ?', [$mid, $gid])) {
            flash('error', 'Dit lid zit al in de groep.');
        } elseif (!Database::val('SELECT 1 FROM group_waitlist WHERE member_id = ? AND group_id = ?', [$mid, $gid])) {
            Database::exec('INSERT INTO group_waitlist (member_id, group_id, note, created_at) VALUES (?,?,?,?)', [$mid, $gid, mb_substr(input('note'), 0, 200) ?: null, now()]);
            audit('CREATE', 'group_waitlist', $gid, "member=$mid");
            flash('success', 'Lid op de wachtlijst gezet.');
        }
        redirect($back);
    }

    public static function waitlistRemove(): void
    {
        $w = Database::one('SELECT * FROM group_waitlist WHERE id = ?', [(int) input('id', '0')]);
        if (!$w) {
            abort(404, 'Deze wachtlijstplaats bestaat niet.');
        }
        Database::exec('DELETE FROM group_waitlist WHERE id = ?', [$w['id']]);
        audit('DELETE', 'group_waitlist', (int) $w['group_id'], 'member=' . $w['member_id']);
        flash('success', 'Van de wachtlijst gehaald.');
        redirect(url('group', ['id' => $w['group_id']]) . '#wachtlijst');
    }

    /** Eerste (of gekozen) lid van de wachtlijst indelen in de groep, met een mail naar ouder of lid. */
    public static function waitlistEnroll(): void
    {
        $w = Database::one('SELECT * FROM group_waitlist WHERE id = ?', [(int) input('id', '0')]);
        if (!$w) {
            abort(404, 'Deze wachtlijstplaats bestaat niet.');
        }
        $back = url('group', ['id' => $w['group_id']]) . '#wachtlijst';
        $error = self::placeMember((int) $w['member_id'], (int) $w['group_id'], true);
        if ($error) {
            flash('error', $error);
            redirect($back);
        }
        flash('success', 'Lid ingedeeld in de groep.');
        if (self::$notice) {
            flash('warning', self::$notice);
        }
        if (input('notify') === '1') {
            $g = Database::one('SELECT name FROM `groups` WHERE id = ?', [$w['group_id']]);
            $m = Database::one('SELECT first_name, last_name FROM members WHERE id = ?', [$w['member_id']]);
            $mails = [];
            foreach (Mailing::recipients([(int) $w['member_id']]) as $to => $name) {
                $mails[] = [$to, 'Plaats in ' . $g['name'], "Dag $name,\n\nEr is een plaats vrij: {$m['first_name']} {$m['last_name']} is ingedeeld in de groep {$g['name']} van Hasseltse Zwemvereniging Spartacus.\nDe trainer of het bestuur laat je snel weten wanneer de eerstvolgende training is.\n\nSportieve groeten,\nHasseltse Zwemvereniging Spartacus"];
            }
            if ($mails) {
                $res = Mailer::sendMany($mails);
                flash('success', 'Een bericht is verstuurd naar ' . count(array_filter($res)) . ' ontvanger(s).');
            } else {
                flash('warning', 'Er is geen e-mailadres bekend om te verwittigen.');
            }
        }
        redirect($back);
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
