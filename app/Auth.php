<?php
final class Auth
{
    private const IDLE_SECONDS = 43200;
    private const MAX_FAILS_PER_EMAIL = 5;
    private const MAX_FAILS_PER_IP = 20;
    private const WINDOW_SECONDS = 900;

    private static bool $loaded = false;
    private static ?array $user = null;
    private static ?array $roles = null;
    private static ?array $perms = null;

    public static function user(): ?array
    {
        if (!self::$loaded) {
            self::$loaded = true;
            $id = $_SESSION['uid'] ?? null;
            // Sessie verloopt na 12 uur zonder activiteit.
            if ($id && time() - (int) ($_SESSION['last'] ?? 0) > self::IDLE_SECONDS) {
                unset($_SESSION['uid']);
                $id = null;
            }
            if ($id) {
                $_SESSION['last'] = time();
                $u = Database::one(
                    'SELECT id, email, display_name, member_id, is_active FROM users WHERE id = ?',
                    [(int) $id]
                );
                if ($u && (int) $u['is_active'] === 1) {
                    self::$user = $u;
                } else {
                    unset($_SESSION['uid']);
                }
            }
        }
        return self::$user;
    }

    /** Te veel mislukte pogingen in de laatste 15 minuten (per e-mailadres of per IP-adres)? */
    public static function throttled(string $email): bool
    {
        $since = date('Y-m-d H:i:s', time() - self::WINDOW_SECONDS);
        Database::exec('DELETE FROM login_attempts WHERE created_at < ?', [date('Y-m-d H:i:s', time() - 86400)]);
        $byEmail = (int) Database::val('SELECT COUNT(*) FROM login_attempts WHERE email = ? AND created_at > ?', [strtolower($email), $since]);
        $byIp = (int) Database::val('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND created_at > ?', [self::ip(), $since]);
        return $byEmail >= self::MAX_FAILS_PER_EMAIL || $byIp >= self::MAX_FAILS_PER_IP;
    }

    public static function recordFailure(string $email): void
    {
        Database::exec('INSERT INTO login_attempts (email, ip, created_at) VALUES (?,?,?)', [substr(strtolower($email), 0, 190), self::ip(), now()]);
    }

    private static function ip(): string
    {
        return substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
    }

    public static function attempt(string $email, string $password): bool
    {
        $u = Database::one('SELECT * FROM users WHERE email = ?', [strtolower($email)]);
        // Altijd een hash controleren, zodat de responstijd niet verraadt of het account bestaat.
        $hash = $u['password_hash'] ?? '$2y$10$kcSFMnl5XT/AxXVmFqWOae6un3aD6j1QwaP1eWv0KNojJnTNgnTXa';
        $ok = password_verify($password, $hash);
        if (!$u || !$ok || (int) $u['is_active'] !== 1) {
            return false;
        }
        Database::exec('DELETE FROM login_attempts WHERE email = ?', [strtolower($email)]);
        if ((int) ($u['totp_enabled'] ?? 0) === 1 && !empty($u['totp_secret'])) {
            // Wachtwoord klopt, maar de tweede stap (code uit de authenticator-app) moet nog volgen.
            $_SESSION['pending_uid'] = (int) $u['id'];
            $_SESSION['pending_at'] = time();
            return true;
        }
        self::completeLogin((int) $u['id']);
        return true;
    }

    /** Rondt het aanmelden af: nieuwe sessie-id, gebruiker onthouden. */
    public static function completeLogin(int $userId): void
    {
        session_regenerate_id(true);
        unset($_SESSION['pending_uid'], $_SESSION['pending_at']);
        $_SESSION['uid'] = $userId;
        $_SESSION['last'] = time();
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
        self::$loaded = false;
        self::$roles = self::$perms = null;
    }

    /** Gebruiker die het wachtwoord al gaf maar nog de tweede stap moet doen (max. 10 minuten geldig). */
    public static function pendingUserId(): ?int
    {
        $id = (int) ($_SESSION['pending_uid'] ?? 0);
        if ($id && time() - (int) ($_SESSION['pending_at'] ?? 0) <= 600) {
            return $id;
        }
        unset($_SESSION['pending_uid'], $_SESSION['pending_at']);
        return null;
    }

    /** Laat een verzoek zonder sessie (bv. een agenda-abonnement) onder de rechten van één gebruiker lopen. */
    public static function actAs(array $user): void
    {
        self::$loaded = true;
        self::$user = $user;
        self::$roles = self::$perms = null;
    }

    /** Beheerders moeten tweestapsverificatie aanzetten als 'security.require_2fa_admin' aan staat. */
    public static function mustEnableTwoFactor(): bool
    {
        $u = self::user();
        if (!$u || !config('security.require_2fa_admin', false) || !self::hasRole('ADMIN')) {
            return false;
        }
        return !(int) Database::val('SELECT totp_enabled FROM users WHERE id = ?', [$u['id']]);
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
        self::$loaded = false;
        self::$user = self::$roles = self::$perms = null;
    }

    public static function requireLogin(): array
    {
        $u = self::user();
        if (!$u) {
            redirect(url('login'));
        }
        return $u;
    }

    /** @return string[] rolcodes van de ingelogde gebruiker */
    public static function roles(): array
    {
        if (self::$roles === null) {
            $u = self::user();
            self::$roles = $u ? array_column(Database::all(
                'SELECT r.code FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = ?',
                [$u['id']]
            ), 'code') : [];
        }
        return self::$roles;
    }

    public static function hasRole(string $code): bool
    {
        return in_array($code, self::roles(), true);
    }

    public static function can(string $permission): bool
    {
        if (self::$perms === null) {
            $u = self::user();
            self::$perms = $u ? array_column(Database::all(
                'SELECT DISTINCT p.code FROM user_roles ur
                 JOIN role_permissions rp ON rp.role_id = ur.role_id
                 JOIN permissions p ON p.id = rp.permission_id
                 WHERE ur.user_id = ?',
                [$u['id']]
            ), 'code') : [];
        }
        return in_array($permission, self::$perms, true);
    }

    public static function require(string $permission): void
    {
        if (!self::can($permission)) {
            abort(403, 'Je hebt geen toegang tot deze pagina of actie.');
        }
    }
}
