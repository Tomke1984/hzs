<?php
/**
 * Accountbeheer (enkel beheerders): gebruikers, rollen, ouder-kindkoppeling en trainer-groeptoewijzing.
 */
final class UserController
{
    private const ROLE_CODES = ['ADMIN', 'BOARD', 'TRAINER', 'SWIMMER', 'PARENT'];

    public static function index(): void
    {
        $tab = (string) ($_GET['tab'] ?? 'staff') === 'members' ? 'members' : 'staff';
        $q = trim((string) ($_GET['q'] ?? ''));

        $all = Database::all(
            'SELECT u.*, m.first_name AS m_first, m.last_name AS m_last
             FROM users u LEFT JOIN members m ON m.id = u.member_id
             ORDER BY u.display_name'
        );

        $rolesByUser = [];
        foreach (Database::all('SELECT ur.user_id, r.code FROM user_roles ur JOIN roles r ON r.id = ur.role_id') as $r) {
            $rolesByUser[$r['user_id']][] = $r['code'];
        }
        $childCount = array_column(
            Database::all('SELECT user_id, COUNT(*) AS n FROM guardian_links GROUP BY user_id'),
            'n',
            'user_id'
        );

        // Tab "Beheer en trainers": beheerders, bestuur en trainers. Tab "Ouders en zwemmers": alle overige accounts.
        $isStaff = fn(array $u) => (bool) array_intersect(['ADMIN', 'BOARD', 'TRAINER'], $rolesByUser[$u['id']] ?? []);
        $counts = ['staff' => 0, 'members' => 0];
        $users = [];
        foreach ($all as $u) {
            $t = $isStaff($u) ? 'staff' : 'members';
            $counts[$t]++;
            if ($t !== $tab) {
                continue;
            }
            if ($q !== '') {
                $hay = mb_strtolower($u['display_name'] . ' ' . $u['email']);
                $ok = true;
                foreach (preg_split('/\s+/', mb_strtolower($q)) as $w) {
                    if (mb_strpos($hay, $w) === false) {
                        $ok = false;
                    }
                }
                if (!$ok) {
                    continue;
                }
            }
            $users[] = $u;
        }

        render('users/index', compact('users', 'rolesByUser', 'childCount', 'tab', 'q', 'counts'), 'Gebruikers');
    }

    public static function form(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        if ($id) {
            $user = self::load($id);
            $roles = self::rolesOf($id);
        } else {
            $user = ['id' => 0, 'display_name' => '', 'email' => '', 'member_id' => null, 'is_active' => 1];
            $roles = [];
            // Vanaf de ledenpagina: eigen account voor een lid (rol Zwemmer, gegevens vooraf ingevuld).
            $mid = (int) ($_GET['member_id'] ?? 0);
            $m = $mid ? Database::one('SELECT id, first_name, last_name, email FROM members WHERE id = ?', [$mid]) : null;
            if ($m) {
                $taken = Database::val('SELECT id FROM users WHERE member_id = ?', [$m['id']]);
                if ($taken) {
                    flash('error', 'Dit lid heeft al een account.');
                    redirect(url('user_form', ['id' => $taken]));
                }
                $user['member_id'] = (int) $m['id'];
                $user['display_name'] = $m['first_name'] . ' ' . $m['last_name'];
                $user['email'] = (string) $m['email'];
                $roles = ['SWIMMER'];
            }
        }
        self::renderForm($user, $roles, []);
    }

    public static function save(): void
    {
        $id = (int) input('id', '0');
        $existing = $id ? self::load($id) : null;
        $me = Auth::user();

        $user = [
            'id'           => $id,
            'display_name' => input('display_name'),
            'email'        => strtolower(input('email')),
            'member_id'    => (int) input('member_id', '0') ?: null,
            'is_active'    => input('is_active') === '1' ? 1 : 0,
        ];
        $rawRoles = $_POST['roles'] ?? [];
        $roles = is_array($rawRoles) ? array_values(array_intersect(self::ROLE_CODES, array_map('strval', $rawRoles))) : [];
        $password = (string) ($_POST['password'] ?? '');

        $errors = [];
        if ($user['display_name'] === '' || mb_strlen($user['display_name']) > 120) {
            $errors[] = 'Naam is verplicht (max. 120 tekens).';
        }
        if (!filter_var($user['email'], FILTER_VALIDATE_EMAIL) || strlen($user['email']) > 190) {
            $errors[] = 'E-mailadres is ongeldig.';
        } elseif (Database::val('SELECT COUNT(*) FROM users WHERE email = ? AND id <> ?', [$user['email'], $id]) > 0) {
            $errors[] = 'Dit e-mailadres is al in gebruik.';
        }
        if (!$existing && $password === '') {
            $errors[] = 'Een wachtwoord is verplicht voor een nieuwe gebruiker.';
        }
        if ($password !== '' && strlen($password) < 10) {
            $errors[] = 'Het wachtwoord moet minstens 10 tekens bevatten.';
        }
        if (!$roles) {
            $errors[] = 'Kies minstens één rol.';
        }

        if (in_array('SWIMMER', $roles, true)) {
            if (!$user['member_id'] || !Database::val('SELECT COUNT(*) FROM members WHERE id = ?', [$user['member_id']])) {
                $errors[] = 'Kies het lid waarmee dit zwemmersaccount verbonden is.';
            }
        } else {
            $user['member_id'] = null;
        }

        if ($existing && in_array('PARENT', self::rolesOf($id), true) && !in_array('PARENT', $roles, true)
            && Database::val('SELECT COUNT(*) FROM guardian_links WHERE user_id = ?', [$id]) > 0) {
            $errors[] = 'Ontkoppel eerst alle kinderen voordat je de rol Ouder intrekt.';
        }

        // Voorkom dat er geen beheerder meer overblijft of dat je jezelf buitensluit.
        $willBeAdmin = in_array('ADMIN', $roles, true) && $user['is_active'] === 1;
        if ($existing) {
            if ((int) $existing['id'] === (int) $me['id'] && !$willBeAdmin) {
                $errors[] = 'Je kunt je eigen beheerdersrol niet intrekken en je eigen account niet deactiveren.';
            }
            $otherAdmins = (int) Database::val(
                "SELECT COUNT(DISTINCT u.id) FROM users u
                 JOIN user_roles ur ON ur.user_id = u.id JOIN roles r ON r.id = ur.role_id
                 WHERE r.code = 'ADMIN' AND u.is_active = 1 AND u.id <> ?",
                [$id]
            );
            if ($otherAdmins === 0 && !$willBeAdmin) {
                $errors[] = 'Er moet minstens één actieve beheerder overblijven.';
            }
        }

        if ($errors) {
            http_response_code(422);
            self::renderForm($user, $roles, $errors);
            return;
        }

        $id = Database::tx(function () use ($id, $user, $roles, $password) {
            if ($id) {
                Database::exec(
                    'UPDATE users SET display_name = ?, email = ?, member_id = ?, is_active = ? WHERE id = ?',
                    [$user['display_name'], $user['email'], $user['member_id'], $user['is_active'], $id]
                );
                if ($password !== '') {
                    Database::exec('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $id]);
                }
                Database::exec('DELETE FROM user_roles WHERE user_id = ?', [$id]);
            } else {
                $id = Database::insert(
                    'INSERT INTO users (email, password_hash, display_name, member_id, is_active, created_at) VALUES (?,?,?,?,?,?)',
                    [$user['email'], password_hash($password, PASSWORD_DEFAULT), $user['display_name'], $user['member_id'], $user['is_active'], now()]
                );
            }
            foreach ($roles as $code) {
                Database::exec('INSERT INTO user_roles (user_id, role_id) SELECT ?, id FROM roles WHERE code = ?', [$id, $code]);
            }
            return $id;
        });

        audit($existing ? 'UPDATE' : 'CREATE', 'user', $id, implode(',', $roles));
        if ($existing) {
            flash('success', 'Gebruiker bijgewerkt.' . ($password !== '' ? ' Het wachtwoord is gewijzigd.' : ''));
        } elseif (in_array('PARENT', $roles, true)) {
            flash('success', 'Gebruiker aangemaakt. Koppel hieronder het kind of de kinderen van deze ouder.');
        } elseif (in_array('TRAINER', $roles, true)) {
            flash('success', 'Gebruiker aangemaakt. Wijs hieronder de groepen van deze trainer toe.');
        } else {
            flash('success', 'Gebruiker aangemaakt.');
        }
        redirect(url('user_form', ['id' => $id]));
    }

    /** Kind aan een ouder koppelen. */
    public static function addChild(): void
    {
        $userId = (int) input('user_id', '0');
        $memberId = (int) input('member_id', '0');
        $relationship = input('relationship');
        $back = input('return') === 'member' && $memberId
            ? url('member', ['id' => $memberId])
            : url('user_form', ['id' => $userId]);
        self::load($userId);

        if (!in_array('PARENT', self::rolesOf($userId), true)) {
            flash('error', 'Geef deze gebruiker eerst de rol Ouder.');
            redirect($back);
        }
        if (!Database::val('SELECT COUNT(*) FROM members WHERE id = ?', [$memberId])) {
            flash('error', 'Kies een lid om te koppelen.');
            redirect($back);
        }
        if (mb_strlen($relationship) > 40) {
            flash('error', 'Omschrijving van de relatie is te lang (max. 40 tekens).');
            redirect($back);
        }
        if (Database::val('SELECT COUNT(*) FROM guardian_links WHERE user_id = ? AND member_id = ?', [$userId, $memberId]) > 0) {
            flash('error', 'Dit kind is al aan deze ouder gekoppeld.');
            redirect($back);
        }

        $linkId = Database::insert(
            'INSERT INTO guardian_links (user_id, member_id, relationship) VALUES (?,?,?)',
            [$userId, $memberId, $relationship === '' ? null : $relationship]
        );
        audit('CREATE', 'guardian_link', $linkId);
        flash('success', 'Kind gekoppeld aan ouder.');
        redirect($back);
    }

    /** Koppeling tussen ouder en kind opheffen. Het lid en de aanwezigheidshistoriek blijven bestaan. */
    public static function removeChild(): void
    {
        $linkId = (int) input('link_id', '0');
        $link = Database::one('SELECT * FROM guardian_links WHERE id = ?', [$linkId]);
        if (!$link) {
            abort(404, 'Deze koppeling bestaat niet.');
        }
        $member = Database::one('SELECT id, status, birth_date FROM members WHERE id = ?', [$link['member_id']]);
        if ($member && $member['status'] === 'ACTIVE' && Guardianship::isMinor($member['birth_date'])
            && Guardianship::count((int) $member['id']) <= 1) {
            flash('error', 'Dit is de enige ouder of voogd van een lid jonger dan 18. Koppel eerst een andere ouder of voogd.');
            redirect(url('user_form', ['id' => $link['user_id']]));
        }
        Database::exec('DELETE FROM guardian_links WHERE id = ?', [$linkId]);
        audit('DELETE', 'guardian_link', $linkId);
        flash('success', 'Koppeling verwijderd. De ouder ziet dit kind niet meer.');
        redirect(url('user_form', ['id' => $link['user_id']]));
    }

    public static function addTrainerGroup(): void
    {
        $userId = (int) input('user_id', '0');
        $groupId = (int) input('group_id', '0');
        $start = input('start_date', today());
        $back = url('user_form', ['id' => $userId]);
        self::load($userId);

        if (!in_array('TRAINER', self::rolesOf($userId), true)) {
            flash('error', 'Geef deze gebruiker eerst de rol Trainer.');
            redirect($back);
        }
        if (!Database::val('SELECT COUNT(*) FROM `groups` WHERE id = ? AND is_active = 1', [$groupId])) {
            flash('error', 'Kies een actieve groep.');
            redirect($back);
        }
        if (!valid_date($start)) {
            flash('error', 'Startdatum is ongeldig.');
            redirect($back);
        }
        if (Database::val(
            'SELECT COUNT(*) FROM group_trainers WHERE user_id = ? AND group_id = ? AND (end_date IS NULL OR end_date >= ?)',
            [$userId, $groupId, $start]
        ) > 0) {
            flash('error', 'Deze trainer is al aan deze groep toegewezen.');
            redirect($back);
        }

        $id = Database::insert('INSERT INTO group_trainers (user_id, group_id, start_date) VALUES (?,?,?)', [$userId, $groupId, $start]);
        audit('CREATE', 'group_trainer', $id);
        flash('success', 'Trainer toegewezen aan groep.');
        redirect($back);
    }

    public static function endTrainerGroup(): void
    {
        $id = (int) input('trainer_group_id', '0');
        $row = Database::one('SELECT * FROM group_trainers WHERE id = ?', [$id]);
        if (!$row) {
            abort(404, 'Deze toewijzing bestaat niet.');
        }
        $back = url('user_form', ['id' => $row['user_id']]);
        if ($row['end_date'] !== null && $row['end_date'] < today()) {
            flash('error', 'Deze toewijzing is al beëindigd.');
            redirect($back);
        }
        $end = max(today(), $row['start_date']);
        Database::exec('UPDATE group_trainers SET end_date = ? WHERE id = ?', [$end, $id]);
        audit('END', 'group_trainer', $id);
        flash('success', 'Toewijzing beëindigd per ' . fmt_date($end) . '.');
        redirect($back);
    }

    private static function load(int $id): array
    {
        $user = $id ? Database::one('SELECT id, display_name, email, member_id, is_active FROM users WHERE id = ?', [$id]) : null;
        if (!$user) {
            abort(404, 'Deze gebruiker bestaat niet.');
        }
        return $user;
    }

    /** @return string[] */
    private static function rolesOf(int $userId): array
    {
        return array_column(Database::all(
            'SELECT r.code FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = ?',
            [$userId]
        ), 'code');
    }

    private static function renderForm(array $user, array $selectedRoles, array $errors): void
    {
        $id = (int) $user['id'];
        $storedRoles = $id ? self::rolesOf($id) : [];

        $children = $id ? Database::all(
            'SELECT gl.id, gl.relationship, m.id AS member_id, m.birth_date, m.first_name, m.last_name, m.status
             FROM guardian_links gl JOIN members m ON m.id = gl.member_id
             WHERE gl.user_id = ? ORDER BY m.last_name, m.first_name',
            [$id]
        ) : [];

        $linkableMembers = $id ? Database::all(
            "SELECT id, birth_date, first_name, last_name FROM members
             WHERE status = 'ACTIVE' AND id NOT IN (SELECT member_id FROM guardian_links WHERE user_id = ?)
             ORDER BY last_name, first_name",
            [$id]
        ) : [];

        $trainerGroups = $id ? Database::all(
            'SELECT gt.id, gt.start_date, gt.end_date, g.id AS group_id, g.name
             FROM group_trainers gt JOIN `groups` g ON g.id = gt.group_id
             WHERE gt.user_id = ? ORDER BY (gt.end_date IS NOT NULL), g.name',
            [$id]
        ) : [];

        render('users/form', [
            'user' => $user,
            'selectedRoles' => $selectedRoles,
            'storedRoles' => $storedRoles,
            'errors' => $errors,
            'members' => Database::all("SELECT id, birth_date, first_name, last_name FROM members WHERE status = 'ACTIVE' ORDER BY last_name, first_name"),
            'groups' => Database::all('SELECT id, name FROM `groups` WHERE is_active = 1 ORDER BY name'),
            'children' => $children,
            'linkableMembers' => $linkableMembers,
            'trainerGroups' => $trainerGroups,
            'pending' => $id && Invites::isPending((array) Database::one('SELECT password_hash FROM users WHERE id = ?', [$id])),
            'isSelf' => $id && (int) $id === (int) Auth::user()['id'],
        ], $id ? 'Gebruiker wijzigen' : 'Nieuwe gebruiker');
    }
}
