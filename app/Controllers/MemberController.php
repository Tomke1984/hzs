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
        [$cat, $gid] = GroupController::readFilter();
        if ($cat !== '' || $gid) {
            $sub = 'EXISTS (SELECT 1 FROM group_memberships fgm JOIN `groups` fg ON fg.id = fgm.group_id
                    WHERE fgm.member_id = m.id AND fgm.start_date <= ? AND (fgm.end_date IS NULL OR fgm.end_date >= ?)';
            $params[] = today();
            $params[] = today();
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
        $missingOnly = Auth::can('members.contact') && (string) ($_GET['missing'] ?? '') === '1';
        if ($missingOnly) {
            $where[] = Guardianship::missingSql('m');
            $params[] = Guardianship::cutoff();
        }
        if ($q !== '') {
            // Elk ingetypt woord moet in de voor- of achternaam voorkomen ("emma pee" vindt Emma Peeters).
            foreach (preg_split('/\s+/', $q) as $word) {
                $where[] = '(m.first_name LIKE ? OR m.last_name LIKE ?)';
                array_push($params, "%$word%", "%$word%");
            }
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

        // Ouders/voogden enkel voor wie contactgegevens mag zien.
        $parentsByMember = [];
        $missingIds = [];
        if ($members && Auth::can('members.contact')) {
            $mids = array_map('intval', array_column($members, 'id'));
            $missingIds = array_map('intval', array_column(Database::all(
                'SELECT m.id FROM members m WHERE m.id IN (' . Scope::in($mids) . ') AND ' . Guardianship::missingSql('m'),
                array_merge($mids, [Guardianship::cutoff()])
            ), 'id'));
            foreach (Database::all(
                'SELECT gl.member_id, gl.relationship, u.id AS user_id, u.display_name, u.is_active
                 FROM guardian_links gl JOIN users u ON u.id = gl.user_id
                 WHERE gl.member_id IN (' . Scope::in($mids) . ') ORDER BY u.display_name',
                $mids
            ) as $p) {
                $parentsByMember[$p['member_id']][] = $p;
            }
        }

        render('members/index', [
            'members' => $members,
            'groupsByMember' => $groupsByMember,
            'parentsByMember' => $parentsByMember,
            'missingIds' => $missingIds,
            'missingOnly' => $missingOnly,
            'q' => $q,
            'status' => $status,
            'cat' => $cat,
            'gid' => $gid,
            'groupOptions' => GroupController::filterOptions(),
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

        $parents = [];
        if (Auth::can('members.contact')) {
            $parents = Database::all(
                'SELECT gl.relationship, u.id AS user_id, u.display_name, u.email, u.is_active
                 FROM guardian_links gl JOIN users u ON u.id = gl.user_id
                 WHERE gl.member_id = ? ORDER BY u.display_name',
                [$member['id']]
            );
        }

        $guardianMissing = Auth::can('members.contact') && Guardianship::missing($member);
        $parentAccounts = [];
        if (Auth::can('users.manage')) {
            $parentAccounts = Database::all(
                "SELECT u.id, u.display_name, u.email FROM users u
                 JOIN user_roles ur ON ur.user_id = u.id JOIN roles r ON r.id = ur.role_id
                 WHERE r.code = 'PARENT' AND u.is_active = 1
                   AND u.id NOT IN (SELECT user_id FROM guardian_links WHERE member_id = ?)
                 ORDER BY u.display_name",
                [$member['id']]
            );
        }

        render('members/show', [
            'member' => $member,
            'isMinor' => Guardianship::isMinor($member['birth_date']),
            'guardianMissing' => $guardianMissing,
            'parentAccounts' => $parentAccounts,
            'account' => Auth::can('users.manage') ? Database::one('SELECT id, email, is_active FROM users WHERE member_id = ?', [$member['id']]) : null,
            'parents' => $parents,
            'memberships' => $memberships,
            'availableGroups' => $availableGroups,
            'showContact' => Auth::can('members.contact'),
        ], $member['first_name'] . ' ' . $member['last_name']);
    }

    public static function form(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        $member = $id ? self::loadVisible($id) : [
            'id' => 0, 'first_name' => '', 'last_name' => '',
            'birth_date' => '', 'email' => '', 'phone' => '', 'status' => 'ACTIVE',
        ];
        render('members/form', ['member' => $member, 'errors' => []], $id ? 'Lid wijzigen' : 'Nieuw lid');
    }

    public static function save(): void
    {
        $id = (int) input('id', '0');
        $existing = $id ? self::loadVisible($id) : null;

        $data = [
            'first_name' => input('first_name'),
            'last_name'  => input('last_name'),
            'birth_date' => input('birth_date'),
            'email'      => input('email'),
            'phone'      => input('phone'),
            'status'     => input('status', 'ACTIVE'),
        ];

        $errors = [];
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
                'UPDATE members SET first_name=?, last_name=?, birth_date=?, email=?, phone=?, status=? WHERE id=?',
                [$data['first_name'], $data['last_name'], $nullable($data['birth_date']), $nullable($data['email']), $nullable($data['phone']), $data['status'], $id]
            );
            audit('UPDATE', 'member', $id);
            flash('success', 'Lid bijgewerkt.');
        } else {
            $id = Database::insert(
                'INSERT INTO members (member_no, first_name, last_name, birth_date, email, phone, status, created_at) VALUES (?,?,?,?,?,?,?,?)',
                [self::nextMemberNo(), $data['first_name'], $data['last_name'], $nullable($data['birth_date']), $nullable($data['email']), $nullable($data['phone']), $data['status'], now()]
            );
            audit('CREATE', 'member', $id);
            flash('success', Guardianship::isMinor($nullable($data['birth_date']))
                ? 'Lid toegevoegd. Koppel hieronder een ouder of voogd, daarna kan het lid aan een groep gekoppeld worden.'
                : 'Lid toegevoegd. Koppel het lid hieronder aan een groep.');
        }
        redirect(url('member', ['id' => $id]));
    }

    /**
     * Het lidnummer is enkel een interne sleutel (verplicht en uniek in de database) en wordt
     * nergens getoond. Het wordt automatisch aangemaakt en bij wijzigen nooit aangepast.
     */
    private static function nextMemberNo(): string
    {
        $n = (int) Database::val('SELECT COALESCE(MAX(id), 0) FROM members') + 1;
        do {
            $no = sprintf('HZS-%04d', $n++);
        } while (Database::val('SELECT COUNT(*) FROM members WHERE member_no = ?', [$no]) > 0);
        return $no;
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
