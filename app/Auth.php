<?php
final class Auth
{
    private static bool $loaded = false;
    private static ?array $user = null;
    private static ?array $roles = null;
    private static ?array $perms = null;

    public static function user(): ?array
    {
        if (!self::$loaded) {
            self::$loaded = true;
            $id = $_SESSION['uid'] ?? null;
            if ($id) {
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

    public static function attempt(string $email, string $password): bool
    {
        $u = Database::one('SELECT * FROM users WHERE email = ?', [strtolower($email)]);
        // Altijd een hash controleren, zodat de responstijd niet verraadt of het account bestaat.
        $hash = $u['password_hash'] ?? '$2y$10$kcSFMnl5XT/AxXVmFqWOae6un3aD6j1QwaP1eWv0KNojJnTNgnTXa';
        $ok = password_verify($password, $hash);
        if (!$u || !$ok || (int) $u['is_active'] !== 1) {
            return false;
        }
        session_regenerate_id(true);
        $_SESSION['uid'] = (int) $u['id'];
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
        self::$loaded = false;
        self::$roles = self::$perms = null;
        return true;
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
