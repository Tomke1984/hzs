<?php
/**
 * Inschrijvingen: openbare aanvraag (met bevestiging per e-mail) en beoordeling door het bestuur.
 * Pas bij goedkeuring worden leden en accounts aangemaakt.
 */
final class RegistrationController
{
    private const MAX_PERSONS = 4;
    private const CONFIRM_DAYS = 7;

    // ------------------------------------------------------------------ openbaar

    public static function showForm(): void
    {
        render('register/form', ['old' => self::blank(), 'errors' => [], 'max' => self::MAX_PERSONS], 'Inschrijven');
    }

    private static function blank(): array
    {
        return [
            'applicant_type' => 'PARENT', 'contact_name' => '', 'contact_email' => '', 'contact_phone' => '',
            'relationship' => '', 'street' => '', 'postal_code' => '', 'city' => '', 'remarks' => '',
            'persons' => array_fill(0, self::MAX_PERSONS, ['first_name' => '', 'last_name' => '', 'birth_date' => '', 'preferred_category' => '']),
        ];
    }

    public static function submit(): void
    {
        // Verborgen veld dat enkel robots invullen: doe alsof het gelukt is.
        if (input('website') !== '') {
            render('register/done', ['state' => 'sent', 'email' => '', 'link' => null], 'Bijna klaar');
            return;
        }

        $old = self::blank();
        foreach (['contact_name', 'contact_email', 'contact_phone', 'relationship', 'street', 'postal_code', 'city', 'remarks'] as $k) {
            $old[$k] = input($k);
        }
        $old['contact_email'] = strtolower($old['contact_email']);
        $old['applicant_type'] = input('applicant_type') === 'SELF' ? 'SELF' : 'PARENT';
        $posted = $_POST['persons'] ?? [];
        for ($i = 0; $i < self::MAX_PERSONS; $i++) {
            $p = is_array($posted[$i] ?? null) ? $posted[$i] : [];
            foreach (['first_name', 'last_name', 'birth_date', 'preferred_category'] as $k) {
                $v = $p[$k] ?? '';
                $old['persons'][$i][$k] = is_array($v) ? '' : trim((string) $v);
            }
        }

        $errors = [];
        $persons = [];
        foreach ($old['persons'] as $p) {
            if ($p['first_name'] === '' && $p['last_name'] === '' && $p['birth_date'] === '') {
                continue;
            }
            $persons[] = $p;
        }
        $self = $old['applicant_type'] === 'SELF';

        if (!$persons) {
            $errors[] = $self ? 'Vul je eigen gegevens in.' : 'Vul minstens één kind in.';
        }
        if ($self && count($persons) > 1) {
            $errors[] = 'Bij een eigen inschrijving vul je enkel jezelf in.';
        }
        $minDate = date('Y-m-d', strtotime('-100 years'));
        foreach ($persons as $i => $p) {
            $n = $i + 1;
            if ($p['first_name'] === '' || $p['last_name'] === '' || mb_strlen($p['first_name']) > 80 || mb_strlen($p['last_name']) > 80) {
                $errors[] = ($self ? 'Je' : "Kind $n:") . ' voornaam en achternaam zijn verplicht (max. 80 tekens).';
            }
            if (!valid_date($p['birth_date']) || $p['birth_date'] > today() || $p['birth_date'] < $minDate) {
                $errors[] = ($self ? 'Je' : "Kind $n:") . ' geboortedatum is ongeldig.';
            } elseif ($self && Guardianship::isMinor($p['birth_date'])) {
                $errors[] = 'Jonger dan 18? Dan schrijft een ouder of voogd je in. Kies "Ik schrijf mijn kind in".';
            }
            if ($p['preferred_category'] !== '' && !array_key_exists($p['preferred_category'], group_categories())) {
                $errors[] = 'Ongeldige voorkeur.';
            }
        }
        if ($self && $persons) {
            $old['contact_name'] = $persons[0]['first_name'] . ' ' . $persons[0]['last_name'];
        } elseif (!$self && ($old['contact_name'] === '' || mb_strlen($old['contact_name']) > 120)) {
            $errors[] = 'Je naam (van de ouder of voogd) is verplicht.';
        }
        if (!filter_var($old['contact_email'], FILTER_VALIDATE_EMAIL) || mb_strlen($old['contact_email']) > 190) {
            $errors[] = 'Vul een geldig e-mailadres in.';
        }
        if ($old['contact_phone'] === '' || mb_strlen($old['contact_phone']) > 40) {
            $errors[] = 'Een telefoonnummer is verplicht.';
        }
        if ($old['street'] === '' || mb_strlen($old['street']) > 120) {
            $errors[] = 'Straat en nummer zijn verplicht.';
        }
        if (!preg_match('/^[0-9A-Za-z][0-9A-Za-z \-]{1,9}$/', $old['postal_code'])) {
            $errors[] = 'Vul een geldige postcode in.';
        }
        if ($old['city'] === '' || mb_strlen($old['city']) > 80) {
            $errors[] = 'Gemeente is verplicht.';
        }
        if (mb_strlen($old['relationship']) > 40 || mb_strlen($old['remarks']) > 500) {
            $errors[] = 'Relatie of opmerking is te lang.';
        }
        if (input('consent') !== '1') {
            $errors[] = 'Je moet akkoord gaan met het reglement en de verwerking van je gegevens.';
        }

        // Tegen misbruik: beperk het aantal aanvragen per e-mailadres en in totaal per uur.
        if (!$errors) {
            $since = date('Y-m-d H:i:s', time() - 3600);
            if ((int) Database::val('SELECT COUNT(*) FROM registrations WHERE contact_email = ? AND created_at > ?', [$old['contact_email'], $since]) >= 3
                || (int) Database::val('SELECT COUNT(*) FROM registrations WHERE created_at > ?', [$since]) >= 100) {
                $errors[] = 'Er zijn te veel aanvragen. Probeer het over een uur opnieuw of neem contact op met de club.';
            }
        }

        if ($errors) {
            http_response_code(422);
            render('register/form', ['old' => $old, 'errors' => array_values(array_unique($errors)), 'max' => self::MAX_PERSONS], 'Inschrijven');
            return;
        }

        // Onbevestigde aanvragen van meer dan een week oud opruimen.
        $old7 = date('Y-m-d H:i:s', time() - self::CONFIRM_DAYS * 86400);
        $stale = array_column(Database::all("SELECT id FROM registrations WHERE status = 'UNCONFIRMED' AND created_at < ?", [$old7]), 'id');
        foreach ($stale as $sid) {
            Database::exec('DELETE FROM registration_persons WHERE registration_id = ?', [$sid]);
            Database::exec('DELETE FROM registrations WHERE id = ?', [$sid]);
        }

        $token = bin2hex(random_bytes(32));
        $regId = Database::tx(function () use ($old, $persons, $token, $self) {
            $id = Database::insert(
                'INSERT INTO registrations (status, applicant_type, contact_name, contact_email, contact_phone, relationship, street, postal_code, city, remarks, consent_at, confirm_token_hash, created_at)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',
                ['UNCONFIRMED', $self ? 'SELF' : 'PARENT', $old['contact_name'], $old['contact_email'], $old['contact_phone'],
                 $old['relationship'] !== '' ? $old['relationship'] : null, $old['street'], $old['postal_code'], $old['city'],
                 $old['remarks'] !== '' ? $old['remarks'] : null, now(), hash('sha256', $token), now()]
            );
            foreach ($persons as $p) {
                Database::exec(
                    'INSERT INTO registration_persons (registration_id, first_name, last_name, birth_date, preferred_category) VALUES (?,?,?,?,?)',
                    [$id, $p['first_name'], $p['last_name'], $p['birth_date'], $p['preferred_category'] !== '' ? $p['preferred_category'] : null]
                );
            }
            return $id;
        });

        $link = Invites::baseUrl() . '/?' . http_build_query(['r' => 'register_confirm', 'token' => $token]);
        $names = implode(' en ', array_map(fn($p) => $p['first_name'] . ' ' . $p['last_name'], $persons));
        $app = (string) config('app_name', 'HZS Clubapp');
        $sent = Mailer::send(
            $old['contact_email'],
            'Bevestig je inschrijving bij HZS',
            "Dag {$old['contact_name']},\n\nBedankt voor de inschrijving van $names bij Hasseltse Zwemvereniging Spartacus.\n\n"
            . "Bevestig eerst je e-mailadres met deze link (geldig " . self::CONFIRM_DAYS . " dagen):\n$link\n\n"
            . "Daarna beoordeelt het bestuur de inschrijving. Zodra die is goedgekeurd, krijg je een mail om je account voor de $app te activeren.\n\n"
            . "Heb je je niet ingeschreven? Dan kun je deze mail negeren.\n\nHasseltse Zwemvereniging Spartacus"
        );
        render('register/done', [
            'state' => $sent ? 'sent' : 'mailfail',
            'email' => $old['contact_email'],
            'link'  => Mailer::driver() === 'log' ? $link : null,
        ], 'Bijna klaar');
    }

    public static function confirm(): void
    {
        $token = (string) ($_GET['token'] ?? '');
        $reg = preg_match('/^[a-f0-9]{64}$/', $token)
            ? Database::one("SELECT id, created_at FROM registrations WHERE confirm_token_hash = ? AND status = 'UNCONFIRMED'", [hash('sha256', $token)])
            : null;
        if (!$reg || strtotime($reg['created_at']) < time() - self::CONFIRM_DAYS * 86400) {
            http_response_code(410);
            render('register/done', ['state' => 'invalid', 'email' => '', 'link' => null], 'Link niet geldig');
            return;
        }
        Database::exec("UPDATE registrations SET status = 'PENDING', confirmed_at = ?, confirm_token_hash = NULL WHERE id = ?", [now(), $reg['id']]);

        $notify = trim((string) config('registration.notify_email', ''));
        if ($notify !== '') {
            Mailer::send($notify, 'Nieuwe inschrijving bij HZS', "Er is een nieuwe inschrijving om te beoordelen:\n" . Invites::baseUrl() . '/?' . http_build_query(['r' => 'registration', 'id' => $reg['id']]) . "\n");
        }
        render('register/done', ['state' => 'confirmed', 'email' => '', 'link' => null], 'Bedankt');
    }

    // ------------------------------------------------------------------ bestuur

    public static function index(): void
    {
        $tab = (string) ($_GET['tab'] ?? 'new') === 'done' ? 'done' : 'new';
        $statuses = $tab === 'new' ? "('PENDING')" : "('APPROVED','REJECTED')";
        $rows = Database::all(
            "SELECT * FROM registrations WHERE status IN $statuses ORDER BY " . ($tab === 'new' ? 'confirmed_at ASC' : 'decided_at DESC') . ' LIMIT 300'
        );
        $names = [];
        foreach (Database::all('SELECT registration_id, first_name, last_name FROM registration_persons ORDER BY id') as $p) {
            $names[$p['registration_id']][] = $p['first_name'] . ' ' . $p['last_name'];
        }
        render('registrations/index', [
            'rows' => $rows,
            'names' => $names,
            'tab' => $tab,
            'counts' => [
                'new' => (int) Database::val("SELECT COUNT(*) FROM registrations WHERE status = 'PENDING'"),
                'done' => (int) Database::val("SELECT COUNT(*) FROM registrations WHERE status IN ('APPROVED','REJECTED')"),
                'unconfirmed' => (int) Database::val("SELECT COUNT(*) FROM registrations WHERE status = 'UNCONFIRMED'"),
            ],
        ], 'Inschrijvingen');
    }

    public static function show(): void
    {
        $reg = self::load((int) ($_GET['id'] ?? 0));
        $persons = Database::all('SELECT * FROM registration_persons WHERE registration_id = ? ORDER BY id', [$reg['id']]);
        $dups = [];
        foreach ($persons as $p) {
            $dups[$p['id']] = Database::all(
                'SELECT id, first_name, last_name FROM members WHERE LOWER(first_name) = ? AND LOWER(last_name) = ? AND birth_date = ?',
                [mb_strtolower($p['first_name']), mb_strtolower($p['last_name']), $p['birth_date']]
            );
        }
        $account = Database::one('SELECT id, is_active, password_hash FROM users WHERE email = ?', [strtolower($reg['contact_email'])]);
        $pendingAccounts = $reg['status'] === 'APPROVED' ? self::pendingAccountsFor($reg['id']) : [];
        $decider = $reg['decided_by'] ? Database::val('SELECT display_name FROM users WHERE id = ?', [$reg['decided_by']]) : null;
        render('registrations/show', [
            'reg' => $reg,
            'persons' => $persons,
            'dups' => $dups,
            'account' => $account,
            'groups' => Database::all('SELECT id, name, category FROM `groups` WHERE is_active = 1 ORDER BY category, name'),
            'pendingAccounts' => $pendingAccounts,
            'decider' => $decider,
        ], 'Inschrijving');
    }

    public static function approve(): void
    {
        $reg = self::load((int) input('id', '0'));
        $back = url('registration', ['id' => $reg['id']]);
        if ($reg['status'] !== 'PENDING') {
            flash('error', 'Deze inschrijving is al afgehandeld.');
            redirect($back);
        }
        $persons = Database::all('SELECT * FROM registration_persons WHERE registration_id = ? ORDER BY id', [$reg['id']]);
        $email = strtolower($reg['contact_email']);
        $user = Database::one('SELECT id, member_id, is_active, password_hash FROM users WHERE email = ?', [$email]);
        if ($user && (int) $user['is_active'] !== 1) {
            flash('error', 'Het e-mailadres van de inschrijver hoort bij een inactief account. Activeer dat account eerst (Gebruikers) of wijs de inschrijving af.');
            redirect($back);
        }
        $self = $reg['applicant_type'] === 'SELF';
        $groupPick = $_POST['group'] ?? [];
        $chosen = [];
        foreach ($persons as $p) {
            $gid = is_array($groupPick) ? (int) ($groupPick[$p['id']] ?? 0) : 0;
            if ($gid && !Database::val('SELECT COUNT(*) FROM `groups` WHERE id = ? AND is_active = 1', [$gid])) {
                flash('error', 'Een gekozen groep bestaat niet of is niet actief.');
                redirect($back);
            }
            $chosen[$p['id']] = $gid;
        }

        $result = Database::tx(function () use ($reg, $persons, $user, $email, $self, $chosen) {
            $memberIds = [];
            foreach ($persons as $p) {
                $mid = Database::insert(
                    'INSERT INTO members (member_no, first_name, last_name, birth_date, email, phone, street, postal_code, city, status, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?)',
                    [MemberController::nextMemberNo(), $p['first_name'], $p['last_name'], $p['birth_date'], $self ? $email : null, $reg['contact_phone'],
                     $reg['street'], $reg['postal_code'], $reg['city'], 'ACTIVE', now()]
                );
                $memberIds[$p['id']] = $mid;
                Database::exec('UPDATE registration_persons SET member_id = ? WHERE id = ?', [$mid, $p['id']]);
            }

            $uid = $user ? (int) $user['id'] : null;
            $created = false;
            $role = $self ? 'SWIMMER' : 'PARENT';
            if (!$uid) {
                $uid = Invites::createPendingUser($email, $reg['contact_name'], $role, $self ? reset($memberIds) : null);
                $created = true;
            } else {
                $has = Database::val('SELECT COUNT(*) FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = ? AND r.code = ?', [$uid, $role]);
                if (!$has) {
                    Database::exec('INSERT INTO user_roles (user_id, role_id) SELECT ?, id FROM roles WHERE code = ?', [$uid, $role]);
                }
                if ($self && !$user['member_id']) {
                    Database::exec('UPDATE users SET member_id = ? WHERE id = ?', [reset($memberIds), $uid]);
                }
            }
            if (!$self) {
                foreach ($memberIds as $mid) {
                    Database::exec('INSERT INTO guardian_links (user_id, member_id, relationship) VALUES (?,?,?)', [$uid, $mid, $reg['relationship']]);
                }
            }
            // Groepen pas na de ouder-koppeling (minderjarigen hebben eerst een ouder of voogd nodig).
            foreach ($persons as $p) {
                if ($chosen[$p['id']]) {
                    Database::exec('INSERT INTO group_memberships (member_id, group_id, start_date) VALUES (?,?,?)', [$memberIds[$p['id']], $chosen[$p['id']], today()]);
                }
            }
            Database::exec(
                "UPDATE registrations SET status = 'APPROVED', decided_by = ?, decided_at = ? WHERE id = ?",
                [Auth::user()['id'], now(), $reg['id']]
            );
            return [$uid, $created, $memberIds];
        });
        [$uid, $created, $memberIds] = $result;
        audit('APPROVE', 'registration', (int) $reg['id'], count($memberIds) . ' leden');

        $names = implode(' en ', array_map(fn($p) => $p['first_name'] . ' ' . $p['last_name'], $persons));
        $row = Database::one('SELECT password_hash FROM users WHERE id = ?', [$uid]);
        if (Invites::isPending($row)) {
            [$sent, $link] = Invites::sendInvite($uid, "De inschrijving van $names is goedgekeurd. Activeer je account om de kalender te bekijken.");
            $msg = $sent ? "Goedgekeurd. Uitnodiging verstuurd naar $email." : "Goedgekeurd, maar de uitnodiging kon niet verstuurd worden naar $email. Gebruik 'Uitnodiging opnieuw versturen'.";
            if (Mailer::driver() === 'log') {
                $msg .= " (Testmodus: geen echte mail; zie storage/mail. Link: $link)";
            }
            flash($sent ? 'success' : 'error', $msg);
        } else {
            Mailer::send($email, 'Inschrijving goedgekeurd', "Dag {$reg['contact_name']},\n\nDe inschrijving van $names bij Hasseltse Zwemvereniging Spartacus is goedgekeurd. Je kunt aanmelden met je bestaande account.\n\nHasseltse Zwemvereniging Spartacus");
            flash('success', "Goedgekeurd. $email had al een account: de leden zijn eraan gekoppeld en er is een bevestiging gestuurd.");
        }
        redirect($back);
    }

    public static function reject(): void
    {
        $reg = self::load((int) input('id', '0'));
        $back = url('registration', ['id' => $reg['id']]);
        if ($reg['status'] !== 'PENDING') {
            flash('error', 'Deze inschrijving is al afgehandeld.');
            redirect($back);
        }
        $reason = input('reason');
        if (mb_strlen($reason) > 500) {
            flash('error', 'De reden is te lang (max. 500 tekens).');
            redirect($back);
        }
        Database::exec(
            "UPDATE registrations SET status = 'REJECTED', decided_by = ?, decided_at = ?, reject_reason = ? WHERE id = ?",
            [Auth::user()['id'], now(), $reason !== '' ? $reason : null, $reg['id']]
        );
        audit('REJECT', 'registration', (int) $reg['id']);
        $names = implode(' en ', array_map(fn($p) => $p['first_name'] . ' ' . $p['last_name'], Database::all('SELECT first_name, last_name FROM registration_persons WHERE registration_id = ?', [$reg['id']])));
        $sent = Mailer::send(
            $reg['contact_email'],
            'Je inschrijving bij HZS',
            "Dag {$reg['contact_name']},\n\nJe inschrijving van $names bij Hasseltse Zwemvereniging Spartacus kon helaas niet aanvaard worden."
            . ($reason !== '' ? "\n\nReden: $reason" : '') . "\n\nHeb je vragen? Neem gerust contact op met de club.\n\nHasseltse Zwemvereniging Spartacus"
        );
        flash('success', 'Inschrijving afgewezen' . ($sent ? ' en de inschrijver is verwittigd.' : '. De mail aan de inschrijver kon niet verstuurd worden.'));
        redirect($back);
    }

    /** Uitnodiging opnieuw sturen naar de accounts die bij deze (goedgekeurde) inschrijving horen. */
    public static function resend(): void
    {
        $reg = self::load((int) input('id', '0'));
        $back = url('registration', ['id' => $reg['id']]);
        $n = 0;
        $fail = 0;
        foreach (self::pendingAccountsFor((int) $reg['id']) as $u) {
            [$sent] = Invites::sendInvite((int) $u['id'], 'Dit is een nieuwe uitnodiging; eerdere links zijn niet meer geldig.');
            $sent ? $n++ : $fail++;
        }
        flash($fail || !$n ? 'error' : 'success', $n ? "$n uitnodiging verstuurd." . ($fail ? " $fail mislukt." : '') : 'Er is geen account dat nog geactiveerd moet worden.');
        redirect($back);
    }

    /** @return array[] accounts (nog niet geactiveerd) die uit deze inschrijving voortkomen */
    private static function pendingAccountsFor(int $regId): array
    {
        $mids = array_map('intval', array_filter(array_column(Database::all('SELECT member_id FROM registration_persons WHERE registration_id = ?', [$regId]), 'member_id')));
        if (!$mids) {
            return [];
        }
        $in = Scope::in($mids);
        $rows = Database::all(
            "SELECT DISTINCT u.id, u.email, u.password_hash FROM users u
             WHERE u.is_active = 1 AND (u.member_id IN ($in) OR u.id IN (SELECT user_id FROM guardian_links WHERE member_id IN ($in)))",
            array_merge($mids, $mids)
        );
        return array_values(array_filter($rows, fn($u) => Invites::isPending($u)));
    }

    private static function load(int $id): array
    {
        $reg = $id ? Database::one('SELECT * FROM registrations WHERE id = ?', [$id]) : null;
        if (!$reg || $reg['status'] === 'UNCONFIRMED') {
            abort(404, 'Deze inschrijving bestaat niet.');
        }
        return $reg;
    }
}
