<?php
final class MemberController
{
    /** Redenen waarom een lid inactief is. */
    public const REASONS = ['Gestopt', 'Geschorst', 'Verhuisd', 'Overgestapt naar een andere club', 'Andere'];

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
                    WHERE fgm.member_id = m.id';
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
                 WHERE gm.member_id IN (' . Scope::in($mids) . ')
                 ORDER BY g.name',
                $mids
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
            'SELECT gm.*, g.name AS group_name, g.category AS group_category FROM group_memberships gm JOIN `groups` g ON g.id = gm.group_id
             WHERE gm.member_id = ? ORDER BY gm.id DESC',
            [$member['id']]
        );

        $availableGroups = [];
        if (Auth::can('groups.write')) {
            $availableGroups = Database::all('SELECT id, name FROM `groups` WHERE is_active = 1 ORDER BY name');
        }

        $parents = [];
        if (Auth::can('members.contact')) {
            $parents = Database::all(
                'SELECT gl.id AS link_id, gl.relationship, u.id AS user_id, u.display_name, u.email, u.is_active
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

        // Ouder of volwassen lid zelf: ziet en wijzigt de eigen gegevens (contact, adres, noodgegevens).
        $canEditOwn = !Auth::can('members.write') && Guardianship::canEditOwn((int) Auth::user()['id'], $member);

        // Medische info is een bijzonder persoonsgegeven: wie ze bekijkt (behalve de ouder of het lid zelf) komt in de auditlog.
        if (Auth::can('members.medical') && !$canEditOwn && ($member['medical_note'] ?? '') !== '') {
            audit_medical_view('member', (int) $member['id']);
        }

        render('members/show', [
            'canEditOwn' => $canEditOwn,
            'showMedical' => Auth::can('members.medical') || $canEditOwn,
            'member' => $member,
            'isMinor' => Guardianship::isMinor($member['birth_date']),
            'guardianMissing' => $guardianMissing,
            'parentAccounts' => $parentAccounts,
            'account' => Auth::can('users.manage') ? Database::one('SELECT id, email, is_active FROM users WHERE member_id = ?', [$member['id']]) : null,
            'parents' => $parents,
            'memberships' => $memberships,
            'availableGroups' => $availableGroups,
            'showContact' => Auth::can('members.contact'),
            'showOwnContact' => Auth::can('members.contact') || $canEditOwn,
        ], $member['first_name'] . ' ' . $member['last_name']);
    }

    public static function form(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        $member = $id ? self::loadVisible($id) : [
            'id' => 0, 'first_name' => '', 'last_name' => '',
            'birth_date' => '', 'email' => '', 'phone' => '', 'street' => '', 'postal_code' => '', 'city' => '', 'status' => 'ACTIVE',
            'status_reason' => '', 'emergency_name' => '', 'emergency_phone' => '', 'medical_note' => '',
        ];
        render('members/form', ['member' => $member, 'errors' => [], 'duplicates' => []], $id ? 'Lid wijzigen' : 'Nieuw lid');
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
            'street'     => input('street'),
            'postal_code' => input('postal_code'),
            'city'       => input('city'),
            'status'     => input('status', 'ACTIVE'),
            'status_reason' => input('status_reason'),
            'emergency_name' => input('emergency_name'),
            'emergency_phone' => input('emergency_phone'),
            'medical_note' => input('medical_note'),
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
        if (mb_strlen($data['street']) > 120 || mb_strlen($data['postal_code']) > 10 || mb_strlen($data['city']) > 80) {
            $errors[] = 'Adres is te lang.';
        }
        if (!in_array($data['status'], ['ACTIVE', 'INACTIVE'], true)) {
            $errors[] = 'Status is ongeldig.';
        }
        if ($data['status_reason'] !== '' && !in_array($data['status_reason'], self::REASONS, true)) {
            $errors[] = 'Reden is ongeldig.';
        }
        if (mb_strlen($data['emergency_name']) > 120 || mb_strlen($data['emergency_phone']) > 40 || mb_strlen($data['medical_note']) > 500) {
            $errors[] = 'Noodcontact of medische opmerking is te lang (opmerking max. 500 tekens).';
        }
        // Dubbelcontrole bij een nieuw lid: zelfde naam en geboortedatum bestaat al.
        $duplicates = [];
        if (!$errors && !$existing && $data['birth_date'] !== '' && input('confirm_duplicate') !== '1') {
            $duplicates = Database::all(
                'SELECT id, first_name, last_name, birth_date, status FROM members WHERE LOWER(first_name) = ? AND LOWER(last_name) = ? AND birth_date = ?',
                [mb_strtolower($data['first_name']), mb_strtolower($data['last_name']), $data['birth_date']]
            );
            if ($duplicates) {
                $errors[] = 'Er bestaat al een lid met dezelfde naam en geboortedatum. Controleer dit eerst.';
            }
        }

        if ($errors) {
            http_response_code(422);
            $member = array_merge(['id' => $id], $data);
            render('members/form', ['member' => $member, 'errors' => $errors, 'duplicates' => $duplicates], $id ? 'Lid wijzigen' : 'Nieuw lid');
            return;
        }
        // Status: reden en datum horen enkel bij inactieve leden; de datum is het moment van de wijziging.
        $statusReason = $data['status'] === 'INACTIVE' ? ($data['status_reason'] !== '' ? $data['status_reason'] : 'Gestopt') : null;
        $statusDate = $existing ? $existing['status_date'] : null;
        if (!$existing || $existing['status'] !== $data['status']) {
            $statusDate = today();
        }
        if ($data['status'] === 'ACTIVE') {
            $statusDate = $existing && $existing['status'] === 'ACTIVE' ? $statusDate : null;
        }
        $canMedical = Auth::can('members.medical');

        $nullable = fn($v) => $v === '' ? null : $v;
        if ($existing) {
            Database::exec(
                'UPDATE members SET first_name=?, last_name=?, birth_date=?, email=?, phone=?, street=?, postal_code=?, city=?, status=?, status_reason=?, status_date=? WHERE id=?',
                [$data['first_name'], $data['last_name'], $nullable($data['birth_date']), $nullable($data['email']), $nullable($data['phone']), $nullable($data['street']), $nullable($data['postal_code']), $nullable($data['city']), $data['status'], $statusReason, $statusDate, $id]
            );
            if ($canMedical) {
                Database::exec('UPDATE members SET emergency_name=?, emergency_phone=?, medical_note=? WHERE id=?', [$nullable($data['emergency_name']), $nullable($data['emergency_phone']), Crypto::encrypt($nullable($data['medical_note'])), $id]);
            }
            audit('UPDATE', 'member', $id);
            flash('success', 'Lid bijgewerkt.');
        } else {
            $id = Database::insert(
                'INSERT INTO members (member_no, first_name, last_name, birth_date, email, phone, street, postal_code, city, status, created_at, status_reason, status_date, emergency_name, emergency_phone, medical_note) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
                [self::nextMemberNo(), $data['first_name'], $data['last_name'], $nullable($data['birth_date']), $nullable($data['email']), $nullable($data['phone']), $nullable($data['street']), $nullable($data['postal_code']), $nullable($data['city']), $data['status'], now(), $statusReason, $data['status'] === 'INACTIVE' ? $statusDate : null,
                    $canMedical ? $nullable($data['emergency_name']) : null, $canMedical ? $nullable($data['emergency_phone']) : null, $canMedical ? Crypto::encrypt($nullable($data['medical_note'])) : null]
            );
            audit('CREATE', 'member', $id);
            flash('success', Guardianship::isMinor($nullable($data['birth_date']))
                ? 'Lid toegevoegd. Koppel hieronder een ouder of voogd, daarna kan het lid aan een groep gekoppeld worden.'
                : 'Lid toegevoegd. Koppel het lid hieronder aan een groep.');
        }
        redirect(url('member', ['id' => $id]));
    }

    /** Formulier waarmee een ouder de gegevens van zijn kind (of een volwassen lid zijn eigen gegevens) aanpast. */
    public static function ownForm(): void
    {
        $member = self::loadOwn((int) ($_GET['id'] ?? 0));
        render('members/own_form', ['member' => $member, 'errors' => []], 'Gegevens wijzigen');
    }

    public static function ownSave(): void
    {
        $member = self::loadOwn((int) input('id', '0'));
        $fields = ['first_name', 'last_name', 'birth_date', 'email', 'phone', 'street', 'postal_code', 'city', 'emergency_name', 'emergency_phone', 'medical_note'];
        $data = [];
        foreach ($fields as $f) {
            $data[$f] = input($f);
        }
        $errors = [];
        if ($data['first_name'] === '' || $data['last_name'] === '') {
            $errors[] = 'Voornaam en achternaam zijn verplicht.';
        }
        if (mb_strlen($data['first_name']) > 80 || mb_strlen($data['last_name']) > 80) {
            $errors[] = 'Naam is te lang.';
        }
        if ($data['birth_date'] !== '' && (!valid_date($data['birth_date']) || $data['birth_date'] > today())) {
            $errors[] = 'Geboortedatum is ongeldig.';
        }
        // Een kind kan niet "volwassen" gemaakt worden om de koppeling met de ouder te omzeilen: enkel de club past dat aan.
        if (Guardianship::isMinor($member['birth_date']) && $data['birth_date'] !== (string) $member['birth_date'] && !Guardianship::isMinor($data['birth_date'] ?: null)) {
            $errors[] = 'Een geboortedatum waardoor je kind 18 of ouder wordt, kan enkel het bestuur aanpassen.';
        }
        if ($data['email'] !== '' && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'E-mailadres is ongeldig.';
        }
        if (mb_strlen($data['email']) > 190 || mb_strlen($data['phone']) > 40) {
            $errors[] = 'E-mail of telefoon is te lang.';
        }
        if (mb_strlen($data['street']) > 120 || mb_strlen($data['postal_code']) > 10 || mb_strlen($data['city']) > 80) {
            $errors[] = 'Adres is te lang.';
        }
        if (mb_strlen($data['emergency_name']) > 120 || mb_strlen($data['emergency_phone']) > 40 || mb_strlen($data['medical_note']) > 500) {
            $errors[] = 'Noodcontact of medische opmerking is te lang (opmerking max. 500 tekens).';
        }
        if ($errors) {
            http_response_code(422);
            render('members/own_form', ['member' => array_merge($member, $data), 'errors' => $errors], 'Gegevens wijzigen');
            return;
        }
        $nullable = fn($v) => $v === '' ? null : $v;
        $changed = [];
        foreach ($fields as $f) {
            if ((string) ($member[$f] ?? '') !== $data[$f]) {
                $changed[] = $f;
            }
        }
        Database::exec(
            'UPDATE members SET first_name=?, last_name=?, birth_date=?, email=?, phone=?, street=?, postal_code=?, city=?, emergency_name=?, emergency_phone=?, medical_note=? WHERE id=?',
            [$data['first_name'], $data['last_name'], $nullable($data['birth_date']), $nullable($data['email']), $nullable($data['phone']), $nullable($data['street']),
                $nullable($data['postal_code']), $nullable($data['city']), $nullable($data['emergency_name']), $nullable($data['emergency_phone']), Crypto::encrypt($nullable($data['medical_note'])), $member['id']]
        );
        audit('UPDATE_OWN', 'member', (int) $member['id'], $changed ? 'gewijzigd: ' . implode(', ', $changed) : 'geen wijzigingen');
        flash('success', $changed ? 'Gegevens opgeslagen.' : 'Er was niets gewijzigd.');
        redirect(url('member', ['id' => $member['id']]));
    }

    /** Lid laden voor de ouder (of het volwassen lid zelf); anders 404. */
    private static function loadOwn(int $id): array
    {
        $member = $id ? Database::one('SELECT * FROM members WHERE id = ?', [$id]) : null;
        if (!$member || !Guardianship::canEditOwn((int) Auth::user()['id'], $member)) {
            abort(404, 'Dit lid bestaat niet of je kan de gegevens niet aanpassen.');
        }
        $member['medical_note'] = Crypto::decrypt($member['medical_note'] ?? null); // versleuteld bewaard
        return $member;
    }

    /**
     * Het lidnummer is enkel een interne sleutel (verplicht en uniek in de database) en wordt
     * nergens getoond. Het wordt automatisch aangemaakt en bij wijzigen nooit aangepast.
     */
    public static function nextMemberNo(): string
    {
        $n = (int) Database::val('SELECT COALESCE(MAX(id), 0) FROM members') + 1;
        do {
            $no = sprintf('HZS-%04d', $n++);
        } while (Database::val('SELECT COUNT(*) FROM members WHERE member_no = ?', [$no]) > 0);
        return $no;
    }

    /** Lid definitief verwijderen (met groepslidmaatschap, ouderkoppelingen, aanwezigheden en afmeldingen). */
    public static function delete(): void
    {
        $member = self::loadVisible((int) input('id', '0'));
        $name = $member['first_name'] . ' ' . $member['last_name'];
        $account = Database::one('SELECT id, email FROM users WHERE member_id = ?', [$member['id']]);
        Database::tx(fn() => Purge::member((int) $member['id']));
        audit('DELETE', 'member', (int) $member['id'], $name);
        flash('success', '"' . $name . '" is definitief verwijderd.' . ($account ? ' Het gekoppelde account (' . $account['email'] . ') bestaat nog, zonder lid; verwijder het bij Gebruikers als het niet meer nodig is.' : ''));
        redirect(list_url('members'));
    }

    private static function loadVisible(int $id): array
    {
        $member = $id ? Database::one('SELECT * FROM members WHERE id = ?', [$id]) : null;
        if (!$member || !Scope::canSeeMember((int) $member['id'])) {
            abort(404, 'Dit lid bestaat niet of je hebt er geen toegang toe.');
        }
        $member['medical_note'] = Crypto::decrypt($member['medical_note'] ?? null); // versleuteld bewaard
        return $member;
    }
}
