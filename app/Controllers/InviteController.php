<?php
/**
 * Uitnodigingen per e-mail (enkel beheerders): ouders/voogden en leden krijgen een link om zelf hun account te activeren.
 */
final class InviteController
{
    /** Ouder of voogd uitnodigen voor één lid. */
    public static function parent(): void
    {
        $memberId = (int) input('member_id', '0');
        $member = Database::one('SELECT id, first_name, last_name FROM members WHERE id = ?', [$memberId]);
        if (!$member) {
            abort(404, 'Dit lid bestaat niet.');
        }
        $back = url('member', ['id' => $memberId]);
        $email = strtolower(input('email'));
        $name = input('display_name');
        $rel = input('relationship');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) {
            flash('error', 'Vul een geldig e-mailadres in.');
            redirect($back);
        }
        if (mb_strlen($name) > 120 || mb_strlen($rel) > 40) {
            flash('error', 'Naam of relatie is te lang.');
            redirect($back);
        }
        redirect(self::inviteParentTo($member, $email, $name, $rel, $back));
    }

    /** @return string url waarnaar teruggekeerd wordt (flash is gezet) */
    private static function inviteParentTo(array $member, string $email, string $name, string $rel, string $back): string
    {
        $user = Database::one('SELECT id, email, display_name, password_hash, is_active FROM users WHERE email = ?', [$email]);
        $created = false;
        if ($user && (int) $user['is_active'] !== 1) {
            flash('error', 'Dit e-mailadres hoort bij een inactief account. Activeer het eerst bij Gebruikers.');
            return $back;
        }
        if (!$user) {
            if ($name === '') {
                $name = 'Ouder van ' . $member['last_name'];
            }
            $uid = Database::tx(fn() => Invites::createPendingUser($email, $name, 'PARENT'));
            $created = true;
        } else {
            $uid = (int) $user['id'];
            $has = Database::val('SELECT COUNT(*) FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = ? AND r.code = ?', [$uid, 'PARENT']);
            if (!$has) {
                Database::exec('INSERT INTO user_roles (user_id, role_id) SELECT ?, id FROM roles WHERE code = ?', [$uid, 'PARENT']);
            }
        }
        if (!Database::val('SELECT COUNT(*) FROM guardian_links WHERE user_id = ? AND member_id = ?', [$uid, $member['id']])) {
            Database::exec('INSERT INTO guardian_links (user_id, member_id, relationship) VALUES (?,?,?)', [$uid, $member['id'], $rel !== '' ? $rel : null]);
            audit('LINK', 'guardian', (int) $member['id'], 'user ' . $uid);
        }
        $row = Database::one('SELECT id, password_hash FROM users WHERE id = ?', [$uid]);
        $msg = '';
        if ($created || Invites::isPending($row)) {
            [$sent, $link] = Invites::sendInvite($uid, 'Je wordt gekoppeld als ouder of voogd van ' . $member['first_name'] . ' ' . $member['last_name'] . '.');
            audit('INVITE', 'user', $uid);
            $msg = $sent ? "Uitnodiging verstuurd naar $email." : "Uitnodiging kon niet verstuurd worden naar $email. Controleer de mailinstellingen.";
            if (Mailer::driver() === 'log') {
                $msg .= " (Testmodus: geen echte mail; zie storage/mail. Link: $link)";
            }
            flash($sent ? 'success' : 'error', $msg);
        } else {
            flash('success', "$email is gekoppeld als ouder of voogd van " . $member['first_name'] . ' ' . $member['last_name'] . '. Dit account is al geactiveerd.');
        }
        return $back;
    }

    /** Het lid zelf uitnodigen op het e-mailadres van het lid. */
    public static function member(): void
    {
        $memberId = (int) input('member_id', '0');
        $member = Database::one('SELECT id, first_name, last_name, email FROM members WHERE id = ?', [$memberId]);
        if (!$member) {
            abort(404, 'Dit lid bestaat niet.');
        }
        $back = url('member', ['id' => $memberId]);
        $email = strtolower((string) $member['email']);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Dit lid heeft geen geldig e-mailadres. Vul het eerst in bij Wijzigen.');
            redirect($back);
        }
        if (Database::val('SELECT COUNT(*) FROM users WHERE member_id = ?', [$memberId]) || Database::val('SELECT COUNT(*) FROM users WHERE email = ?', [$email])) {
            flash('error', 'Er bestaat al een account voor dit lid of dit e-mailadres.');
            redirect($back);
        }
        $uid = Database::tx(fn() => Invites::createPendingUser($email, $member['first_name'] . ' ' . $member['last_name'], 'SWIMMER', $memberId));
        [$sent, $link] = Invites::sendInvite($uid, 'Je kunt er je eigen kalender en groepen bekijken.');
        audit('INVITE', 'user', $uid);
        $msg = $sent ? "Uitnodiging verstuurd naar $email." : "Uitnodiging kon niet verstuurd worden naar $email. Controleer de mailinstellingen.";
        if (Mailer::driver() === 'log') {
            $msg .= " (Testmodus: geen echte mail; zie storage/mail. Link: $link)";
        }
        flash($sent ? 'success' : 'error', $msg);
        redirect($back);
    }

    /** Uitnodiging opnieuw versturen voor een account dat nog niet geactiveerd is. */
    public static function resend(): void
    {
        $uid = (int) input('user_id', '0');
        $user = Database::one('SELECT id, email, password_hash, is_active FROM users WHERE id = ?', [$uid]);
        if (!$user) {
            abort(404, 'Deze gebruiker bestaat niet.');
        }
        $back = url('user_form', ['id' => $uid]);
        if (!Invites::isPending($user) || (int) $user['is_active'] !== 1) {
            flash('error', 'Dit account is al geactiveerd of niet actief.');
            redirect($back);
        }
        [$sent, $link] = Invites::sendInvite($uid, 'Dit is een nieuwe uitnodiging; eerdere links zijn niet meer geldig.');
        audit('INVITE', 'user', $uid);
        $msg = $sent ? 'Uitnodiging opnieuw verstuurd.' : 'Uitnodiging kon niet verstuurd worden. Controleer de mailinstellingen.';
        if (Mailer::driver() === 'log') {
            $msg .= " (Testmodus: geen echte mail; zie storage/mail. Link: $link)";
        }
        flash($sent ? 'success' : 'error', $msg);
        redirect($back);
    }

    /**
     * Bepaalt wie uitgenodigd kan worden:
     *  - minderjarig lid zonder ouder, met e-mailadres: ouder-account op dat adres (broers/zussen delen één account);
     *  - ander actief lid zonder account, met e-mailadres: eigen account (rol Zwemmer).
     * @return array{parents:array,self:array,noEmail:array,skipped:int}
     */
    private static function plan(): array
    {
        $members = Database::all("SELECT id, first_name, last_name, birth_date, email FROM members WHERE status = 'ACTIVE' ORDER BY last_name, first_name");
        $hasGuardian = array_flip(array_map('intval', array_column(Database::all('SELECT DISTINCT member_id FROM guardian_links'), 'member_id')));
        $hasAccount = array_flip(array_map('intval', array_column(Database::all('SELECT member_id FROM users WHERE member_id IS NOT NULL'), 'member_id')));
        $emails = array_flip(array_map('strtolower', array_column(Database::all('SELECT email FROM users'), 'email')));

        $parents = [];   // e-mail => [kinderen]
        $self = [];
        $noEmail = [];
        $skipped = 0;
        foreach ($members as $m) {
            $mail = strtolower(trim((string) $m['email']));
            $valid = filter_var($mail, FILTER_VALIDATE_EMAIL) !== false;
            if (Guardianship::isMinor($m['birth_date'])) {
                if (isset($hasGuardian[(int) $m['id']])) {
                    $skipped++;
                } elseif (!$valid) {
                    $noEmail[] = $m;
                } else {
                    $parents[$mail][] = $m;
                }
            } else {
                if (isset($hasAccount[(int) $m['id']]) || ($valid && isset($emails[$mail]))) {
                    $skipped++;
                } elseif (!$valid) {
                    $noEmail[] = $m;
                } else {
                    $self[$mail] = $m;
                }
            }
        }
        return compact('parents', 'self', 'noEmail', 'skipped');
    }

    public static function bulk(): void
    {
        render('invite/bulk', ['plan' => self::plan(), 'driver' => Mailer::driver()], 'Leden uitnodigen');
    }

    public static function bulkSend(): void
    {
        @set_time_limit(300);
        $plan = self::plan();
        $queue = [];   // [uid, context]
        $pending = 0;

        foreach ($plan['parents'] as $mail => $kids) {
            $names = implode(' en ', array_map(fn($k) => $k['first_name'] . ' ' . $k['last_name'], $kids));
            $user = Database::one('SELECT id, password_hash, is_active FROM users WHERE email = ?', [$mail]);
            if ($user && (int) $user['is_active'] !== 1) {
                continue;
            }
            $uid = $user ? (int) $user['id'] : Database::tx(fn() => Invites::createPendingUser($mail, 'Ouder van ' . $kids[0]['last_name'], 'PARENT'));
            if ($user && !Database::val('SELECT COUNT(*) FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = ? AND r.code = ?', [$uid, 'PARENT'])) {
                Database::exec('INSERT INTO user_roles (user_id, role_id) SELECT ?, id FROM roles WHERE code = ?', [$uid, 'PARENT']);
            }
            foreach ($kids as $k) {
                if (!Database::val('SELECT COUNT(*) FROM guardian_links WHERE user_id = ? AND member_id = ?', [$uid, $k['id']])) {
                    Database::exec('INSERT INTO guardian_links (user_id, member_id, relationship) VALUES (?,?,?)', [$uid, $k['id'], 'ouder']);
                }
            }
            $row = Database::one('SELECT password_hash FROM users WHERE id = ?', [$uid]);
            if (!$user || Invites::isPending($row)) {
                $queue[] = [$uid, 'Je wordt gekoppeld als ouder of voogd van ' . $names . '.'];
            }
        }
        foreach ($plan['self'] as $mail => $m) {
            $uid = Database::tx(fn() => Invites::createPendingUser($mail, $m['first_name'] . ' ' . $m['last_name'], 'SWIMMER', (int) $m['id']));
            $queue[] = [$uid, 'Je kunt er je eigen kalender en groepen bekijken.'];
        }

        // Alle mails over één verbinding versturen.
        $messages = [];
        foreach ($queue as [$uid, $context]) {
            $user = Database::one('SELECT id, email, display_name FROM users WHERE id = ?', [$uid]);
            $link = Invites::link(Invites::createToken($uid, 'INVITE'));
            [$subject, $body] = Invites::inviteMessage($user, $link, $context);
            $messages[] = [$user['email'], $subject, $body];
        }
        $results = $messages ? Mailer::sendMany($messages) : [];
        $ok = count(array_filter($results));
        $fail = count($results) - $ok;
        audit('INVITE_BULK', 'user', null, "verstuurd $ok, mislukt $fail");

        $text = "$ok uitnodiging" . ($ok === 1 ? '' : 'en') . ' verstuurd.';
        if ($fail) {
            $text .= " $fail mislukt: controleer de mailinstellingen en probeer opnieuw via 'Uitnodiging opnieuw versturen'.";
        }
        if (Mailer::driver() === 'log') {
            $text .= ' (Testmodus: geen echte mails; ze staan in storage/mail.)';
        }
        flash($fail ? 'error' : 'success', $text);
        redirect(url('users', ['tab' => 'members']));
    }
}
