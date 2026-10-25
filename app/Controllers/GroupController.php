<?php
final class GroupController
{
    public static function index(): void
    {
        $gids = Scope::groupIds();
        $where = '1=1';
        $params = [today(), today()];
        if ($gids !== null) {
            $where = $gids ? 'g.id IN (' . Scope::in($gids) . ')' : '0=1';
            $params = array_merge($params, $gids);
        }
        $groups = Database::all(
            "SELECT g.*,
                (SELECT COUNT(*) FROM group_memberships gm WHERE gm.group_id = g.id
                   AND gm.start_date <= ? AND (gm.end_date IS NULL OR gm.end_date >= ?)) AS member_count
             FROM `groups` g WHERE $where ORDER BY g.is_active DESC, g.category, g.name",
            $params
        );
        render('groups/index', ['groups' => $groups], 'Groepen');
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
            "SELECT gm.id AS membership_id, gm.start_date, gm.end_date, m.id, m.birth_date, m.first_name, m.last_name, m.status
             FROM group_memberships gm JOIN members m ON m.id = gm.member_id
             WHERE $where ORDER BY (gm.end_date IS NOT NULL), m.last_name, m.first_name",
            $params
        );

        $trainers = Database::all(
            'SELECT u.display_name, gt.start_date, gt.end_date FROM group_trainers gt JOIN users u ON u.id = gt.user_id
             WHERE gt.group_id = ? AND gt.start_date <= ? AND (gt.end_date IS NULL OR gt.end_date >= ?) ORDER BY u.display_name',
            [$group['id'], $today, $today]
        );

        $candidates = [];
        if (Auth::can('groups.write')) {
            $candidates = Database::all(
                "SELECT m.id, m.birth_date, m.first_name, m.last_name FROM members m
                 WHERE m.status = 'ACTIVE' AND NOT EXISTS (
                    SELECT 1 FROM group_memberships gm WHERE gm.member_id = m.id AND gm.group_id = ?
                      AND (gm.end_date IS NULL OR gm.end_date >= ?))
                 ORDER BY m.last_name, m.first_name",
                [$group['id'], $today]
            );
        }

        $blockedIds = $candidates ? Guardianship::missingIds() : [];

        render('groups/show', compact('group', 'memberships', 'trainers', 'candidates', 'blockedIds'), $group['name']);
    }

    public static function form(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        $group = $id ? self::loadVisible($id) : ['id' => 0, 'name' => '', 'description' => '', 'category' => input('category', 'SWIM_SCHOOL'), 'is_active' => 1];
        render('groups/form', ['group' => $group, 'errors' => []], $id ? 'Groep wijzigen' : 'Nieuwe groep');
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
        if (mb_strlen($data['description']) > 255) {
            $errors[] = 'Omschrijving is te lang (max. 255 tekens).';
        }

        if ($errors) {
            http_response_code(422);
            render('groups/form', ['group' => array_merge(['id' => $id], $data), 'errors' => $errors], $id ? 'Groep wijzigen' : 'Nieuwe groep');
            return;
        }

        $desc = $data['description'] === '' ? null : $data['description'];
        if ($id) {
            Database::exec('UPDATE `groups` SET name=?, description=?, category=?, is_active=? WHERE id=?', [$data['name'], $desc, $data['category'], $data['is_active'], $id]);
            audit('UPDATE', 'group', $id);
            flash('success', 'Groep bijgewerkt.');
        } else {
            $id = Database::insert('INSERT INTO `groups` (name, description, category, is_active) VALUES (?,?,?,?)', [$data['name'], $desc, $data['category'], $data['is_active']]);
            audit('CREATE', 'group', $id);
            flash('success', 'Groep aangemaakt.');
        }
        redirect(url('group', ['id' => $id]));
    }

    /** Lid aan groep koppelen; kan vanaf zowel de ledenpagina als de groepspagina. */
    public static function addMember(): void
    {
        $memberId = (int) input('member_id', '0');
        $groupId = (int) input('group_id', '0');
        $start = input('start_date', today());
        $back = input('return') === 'group' ? url('group', ['id' => $groupId]) : url('member', ['id' => $memberId]);

        $member = Database::one('SELECT id, status, birth_date FROM members WHERE id = ?', [$memberId]);
        $group = Database::one('SELECT id, is_active FROM `groups` WHERE id = ?', [$groupId]);

        if (!$member || !$group) {
            flash('error', 'Lid of groep bestaat niet.');
            redirect(url('members'));
        }
        if ($member['status'] !== 'ACTIVE' || (int) $group['is_active'] !== 1) {
            flash('error', 'Enkel actieve leden kunnen aan actieve groepen gekoppeld worden.');
            redirect($back);
        }
        if (!valid_date($start)) {
            flash('error', 'Startdatum is ongeldig.');
            redirect($back);
        }
        if (Guardianship::isMinor($member['birth_date']) && Guardianship::count($memberId) === 0) {
            flash('error', 'Dit lid is jonger dan 18 en heeft nog geen ouder of voogd. Koppel eerst een ouder of voogd.');
            redirect(url('member', ['id' => $memberId]));
        }
        $overlap = Database::val(
            'SELECT COUNT(*) FROM group_memberships WHERE member_id = ? AND group_id = ? AND (end_date IS NULL OR end_date >= ?)',
            [$memberId, $groupId, $start]
        );
        if ($overlap > 0) {
            flash('error', 'Dit lid zit al in deze groep (of de vorige koppeling loopt nog).');
            redirect($back);
        }

        $id = Database::insert(
            'INSERT INTO group_memberships (member_id, group_id, start_date) VALUES (?,?,?)',
            [$memberId, $groupId, $start]
        );
        audit('CREATE', 'group_membership', $id);
        flash('success', 'Lid gekoppeld aan groep.');
        redirect($back);
    }

    /** Koppeling beëindigen via einddatum; er wordt niets verwijderd. */
    public static function endMembership(): void
    {
        $id = (int) input('membership_id', '0');
        $row = Database::one('SELECT * FROM group_memberships WHERE id = ?', [$id]);
        if (!$row) {
            abort(404, 'Deze koppeling bestaat niet.');
        }
        $back = input('return') === 'group' ? url('group', ['id' => $row['group_id']]) : url('member', ['id' => $row['member_id']]);

        if ($row['end_date'] !== null && $row['end_date'] < today()) {
            flash('error', 'Deze koppeling is al beëindigd.');
            redirect($back);
        }
        // Einddatum nooit vóór de startdatum.
        $end = max(today(), $row['start_date']);
        Database::exec('UPDATE group_memberships SET end_date = ? WHERE id = ?', [$end, $id]);
        audit('END', 'group_membership', $id);
        flash('success', 'Koppeling beëindigd per ' . fmt_date($end) . '.');
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
