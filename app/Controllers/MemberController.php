<?php
final class MemberController
{
    public static function index(): void
    {
        $q = trim((string) ($_GET['q'] ?? ''));
        $status = (string) ($_GET['status'] ?? 'ACTIVE');
        if (!in_array($status, ['ACTIVE', 'INACTIVE', 'ALL'], true)) {
            $status = 'ACTIVE';
        }

        $where = ['1=1'];
        $params = [];

        $ids = Scope::memberIds();
        if ($ids !== null) {
            if (!$ids) {
                $where[] = '0=1';
            } else {
                $where[] = 'm.id IN (' . Scope::in($ids) . ')';
                $params = array_merge($params, $ids);
            }
        }
        if ($status !== 'ALL') {
            $where[] = 'm.status = ?';
            $params[] = $status;
        }
        if ($q !== '') {
            $where[] = '(m.first_name LIKE ? OR m.last_name LIKE ? OR m.member_no LIKE ?)';
            array_push($params, "%$q%", "%$q%", "%$q%");
        }

        $members = Database::all(
            'SELECT m.* FROM members m WHERE ' . implode(' AND ', $where) . ' ORDER BY m.last_name, m.first_name LIMIT 500',
            $params
        );

        $groupsByMember = [];
        if ($members) {
            $mids = array_map('intval', array_column($members, 'id'));
            $rows = Database::all(
                'SELECT gm.member_id, g.name FROM group_memberships gm JOIN `groups` g ON g.id = gm.group_id
                 WHERE gm.member_id IN (' . Scope::in($mids) . ') AND gm.start_date <= ? AND (gm.end_date IS NULL OR gm.end_date >= ?)
                 ORDER BY g.name',
                array_merge($mids, [today(), today()])
            );
            foreach ($rows as $r) {
                $groupsByMember[$r['member_id']][] = $r['name'];
            }
        }

        render('members/index', [
            'members' => $members,
            'groupsByMember' => $groupsByMember,
            'q' => $q,
            'status' => $status,
            'showContact' => Auth::can('members.contact'),
        ], 'Leden');
    }

    public static function show(): void
    {
        $member = self::loadVisible((int) ($_GET['id'] ?? 0));

        $memberships = Database::all(
            'SELECT gm.*, g.name AS group_name FROM group_memberships gm JOIN `groups` g ON g.id = gm.group_id
             WHERE gm.member_id = ? ORDER BY gm.start_date DESC, gm.id DESC',
            [$member['id']]
        );

        $availableGroups = [];
        if (Auth::can('groups.write')) {
            $availableGroups = Database::all('SELECT id, name FROM `groups` WHERE is_active = 1 ORDER BY name');
        }

        render('members/show', [
            'member' => $member,
            'memberships' => $memberships,
            'availableGroups' => $availableGroups,
            'showContact' => Auth::can('members.contact'),
        ], $member['first_name'] . ' ' . $member['last_name']);
    }

    public static function form(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        $member = $id ? self::loadVisible($id) : [
            'id' => 0, 'member_no' => '', 'first_name' => '', 'last_name' => '',
            'birth_date' => '', 'email' => '', 'phone' => '', 'status' => 'ACTIVE',
        ];
        render('members/form', ['member' => $member, 'errors' => []], $id ? 'Lid wijzigen' : 'Nieuw lid');
    }

    public static function save(): void
    {
        $id = (int) input('id', '0');
        $existing = $id ? self::loadVisible($id) : null;

        $data = [
            'member_no'  => input('member_no'),
            'first_name' => input('first_name'),
            'last_name'  => input('last_name'),
            'birth_date' => input('birth_date'),
            'email'      => input('email'),
            'phone'      => input('phone'),
            'status'     => input('status', 'ACTIVE'),
        ];

        $errors = [];
        if ($data['member_no'] === '') {
            $errors[] = 'Lidnummer is verplicht.';
        } elseif (mb_strlen($data['member_no']) > 30) {
            $errors[] = 'Lidnummer is te lang (max. 30 tekens).';
        } elseif (Database::val('SELECT COUNT(*) FROM members WHERE member_no = ? AND id <> ?', [$data['member_no'], $id]) > 0) {
            $errors[] = 'Dit lidnummer bestaat al.';
        }
        if ($data['first_name'] === '' || $data['last_name'] === '') {
            $errors[] = 'Voornaam en achternaam zijn verplicht.';
        }
        if ($data['birth_date'] !== '' && (!valid_date($data['birth_date']) || $data['birth_date'] > today())) {
            $errors[] = 'Geboortedatum is ongeldig.';
        }
        if ($data['email'] !== '' && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'E-mailadres is ongeldig.';
        }
        if (!in_array($data['status'], ['ACTIVE', 'INACTIVE'], true)) {
            $errors[] = 'Status is ongeldig.';
        }

        if ($errors) {
            http_response_code(422);
            $member = array_merge(['id' => $id], $data);
            render('members/form', ['member' => $member, 'errors' => $errors], $id ? 'Lid wijzigen' : 'Nieuw lid');
            return;
        }

        $nullable = fn($v) => $v === '' ? null : $v;
        if ($existing) {
            Database::exec(
                'UPDATE members SET member_no=?, first_name=?, last_name=?, birth_date=?, email=?, phone=?, status=? WHERE id=?',
                [$data['member_no'], $data['first_name'], $data['last_name'], $nullable($data['birth_date']), $nullable($data['email']), $nullable($data['phone']), $data['status'], $id]
            );
            audit('UPDATE', 'member', $id);
            flash('success', 'Lid bijgewerkt.');
        } else {
            $id = Database::insert(
                'INSERT INTO members (member_no, first_name, last_name, birth_date, email, phone, status, created_at) VALUES (?,?,?,?,?,?,?,?)',
                [$data['member_no'], $data['first_name'], $data['last_name'], $nullable($data['birth_date']), $nullable($data['email']), $nullable($data['phone']), $data['status'], now()]
            );
            audit('CREATE', 'member', $id);
            flash('success', 'Lid toegevoegd. Koppel het lid hieronder aan een groep.');
        }
        redirect(url('member', ['id' => $id]));
    }

    private static function loadVisible(int $id): array
    {
        $member = $id ? Database::one('SELECT * FROM members WHERE id = ?', [$id]) : null;
        if (!$member || !Scope::canSeeMember((int) $member['id'])) {
            abort(404, 'Dit lid bestaat niet of je hebt er geen toegang toe.');
        }
        return $member;
    }
}
