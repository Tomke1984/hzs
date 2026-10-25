<?php
final class AuthController
{
    public static function showLogin(): void
    {
        if (Auth::user()) {
            redirect(url('dashboard'));
        }
        render('login', ['email' => ''], 'Aanmelden');
    }

    public static function login(): void
    {
        $email = input('email');
        $password = (string) ($_POST['password'] ?? '');

        if ($email !== '' && Auth::throttled($email)) {
            http_response_code(429);
            flash('error', 'Te veel mislukte pogingen. Probeer het over 15 minuten opnieuw, of gebruik “Wachtwoord vergeten?”.');
            render('login', ['email' => $email], 'Aanmelden');
            return;
        }
        if ($email === '' || $password === '' || !Auth::attempt($email, $password)) {
            if ($email !== '') {
                Auth::recordFailure($email);
            }
            http_response_code(401);
            flash('error', 'E-mailadres of wachtwoord is onjuist, of het account is niet actief.');
            render('login', ['email' => $email], 'Aanmelden');
            return;
        }
        redirect(url('dashboard'));
    }

    /** Wachtwoord vergeten: altijd dezelfde melding, zodat niet te raden is welke e-mailadressen bestaan. */
    public static function showForgot(): void
    {
        render('auth/forgot', ['sent' => false, 'email' => ''], 'Wachtwoord vergeten');
    }

    public static function forgot(): void
    {
        $email = strtolower(input('email'));
        $u = filter_var($email, FILTER_VALIDATE_EMAIL)
            ? Database::one('SELECT id, email, display_name, password_hash, is_active FROM users WHERE email = ?', [$email])
            : null;
        if ($u && (int) $u['is_active'] === 1) {
            // Maximaal één mail per minuut per account (tegen mailspam naar een ander).
            $recent = (int) Database::val('SELECT COUNT(*) FROM user_tokens WHERE user_id = ? AND created_at > ?', [$u['id'], date('Y-m-d H:i:s', time() - 60)]);
            if (!$recent) {
                if (Invites::isPending($u)) {
                    Invites::sendInvite((int) $u['id'], 'Je account staat klaar, maar is nog niet geactiveerd.');
                } else {
                    $link = Invites::link(Invites::createToken((int) $u['id'], 'RESET'));
                    [$subject, $body] = Invites::resetMessage($u, $link);
                    Mailer::send($u['email'], $subject, $body);
                }
            }
        }
        render('auth/forgot', ['sent' => true, 'email' => $email], 'Wachtwoord vergeten');
    }

    /** Link uit een uitnodiging of reset-mail: wachtwoord kiezen. */
    public static function showActivate(): void
    {
        $token = (string) ($_GET['token'] ?? '');
        $found = Invites::find($token);
        if (!$found) {
            http_response_code(410);
        }
        render('auth/setpassword', ['found' => $found, 'token' => $token, 'errors' => [], 'name' => $found['display_name'] ?? ''], 'Wachtwoord kiezen');
    }

    public static function activate(): void
    {
        $token = input('token');
        $found = Invites::find($token);
        if (!$found) {
            http_response_code(410);
            render('auth/setpassword', ['found' => null, 'token' => '', 'errors' => [], 'name' => ''], 'Wachtwoord kiezen');
            return;
        }
        $password = (string) ($_POST['password'] ?? '');
        $again = (string) ($_POST['password2'] ?? '');
        $name = $found['purpose'] === 'INVITE' ? input('display_name') : '';
        $errors = [];
        if ($found['purpose'] === 'INVITE' && $name === '') {
            $errors[] = 'Vul je naam in.';
        } elseif (mb_strlen($name) > 120) {
            $errors[] = 'Naam is te lang.';
        }
        if (mb_strlen($password) < 10) {
            $errors[] = 'Het wachtwoord moet minstens 10 tekens hebben.';
        }
        if ($password !== $again) {
            $errors[] = 'De twee wachtwoorden komen niet overeen.';
        }
        if ($errors) {
            http_response_code(422);
            render('auth/setpassword', ['found' => $found, 'token' => $token, 'errors' => $errors, 'name' => $name !== '' ? $name : $found['display_name']], 'Wachtwoord kiezen');
            return;
        }
        Invites::setPassword($found, $password, $name);
        Database::exec(
            'INSERT INTO audit_log (user_id, action, entity, entity_id, detail, created_at) VALUES (?,?,?,?,?,?)',
            [$found['user_id'], $found['purpose'] === 'INVITE' ? 'ACTIVATE' : 'RESET_PASSWORD', 'user', $found['user_id'], null, now()]
        );
        flash('success', 'Je wachtwoord is ingesteld. Je kunt nu aanmelden.');
        redirect(url('login'));
    }

    public static function logout(): void
    {
        Auth::logout();
        redirect(url('login'));
    }
}
