<?php
/**
 * Uitnodigingen en wachtwoordreset via éénmalige links.
 * Een account dat nog niet geactiveerd is, heeft een onbruikbare wachtwoordhash (begint met "!").
 */
final class Invites
{
    public const INVITE_DAYS = 7;
    public const RESET_MINUTES = 60;

    public static function baseUrl(): string
    {
        $cfg = trim((string) config('app_url', ''));
        if ($cfg !== '') {
            return rtrim($cfg, '/');
        }
        $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
        if (!preg_match('/^[A-Za-z0-9.\-:\[\]]+$/', $host)) {
            $host = 'localhost';
        }
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (bool) config('cookie_secure', false);
        return ($https ? 'https' : 'http') . '://' . $host . base_path();
    }

    public static function link(string $token): string
    {
        return self::baseUrl() . '/activeren?' . http_build_query(['token' => $token]);
    }

    public static function isPending(array $user): bool
    {
        return isset($user['password_hash']) && str_starts_with((string) $user['password_hash'], '!');
    }

    /** Maakt een nieuwe token (oudere ongebruikte van dezelfde soort vervallen). Geeft de leesbare token terug. */
    public static function createToken(int $userId, string $purpose): string
    {
        Database::exec('UPDATE user_tokens SET used_at = ? WHERE user_id = ? AND purpose = ? AND used_at IS NULL', [now(), $userId, $purpose]);
        $token = bin2hex(random_bytes(32));
        $ttl = $purpose === 'INVITE' ? self::INVITE_DAYS * 86400 : self::RESET_MINUTES * 60;
        Database::exec(
            'INSERT INTO user_tokens (user_id, token_hash, purpose, created_at, expires_at) VALUES (?,?,?,?,?)',
            [$userId, hash('sha256', $token), $purpose, now(), date('Y-m-d H:i:s', time() + $ttl)]
        );
        return $token;
    }

    /** @return array|null token-rij + gebruiker, enkel als geldig, ongebruikt en niet verlopen */
    public static function find(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            return null;
        }
        return Database::one(
            'SELECT t.id AS token_id, t.purpose, t.expires_at, u.id AS user_id, u.email, u.display_name, u.is_active, u.password_hash
             FROM user_tokens t JOIN users u ON u.id = t.user_id
             WHERE t.token_hash = ? AND t.used_at IS NULL AND t.expires_at >= ? AND u.is_active = 1',
            [hash('sha256', $token), now()]
        );
    }

    public static function setPassword(array $found, string $password, ?string $displayName): void
    {
        Database::tx(function () use ($found, $password, $displayName) {
            Database::exec('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $found['user_id']]);
            if ($displayName !== null && $displayName !== '') {
                Database::exec('UPDATE users SET display_name = ? WHERE id = ?', [$displayName, $found['user_id']]);
            }
            Database::exec('UPDATE user_tokens SET used_at = ? WHERE user_id = ? AND used_at IS NULL', [now(), $found['user_id']]);
            Database::exec('UPDATE users SET session_version = session_version + 1 WHERE id = ?', [$found['user_id']]); // andere sessies uit
        });
    }

    /** Onbruikbare hash voor een account dat nog geactiveerd moet worden. */
    public static function unusableHash(): string
    {
        return '!' . bin2hex(random_bytes(16));
    }

    /** Maakt een nog niet geactiveerd account met een rol (en optioneel een lid). */
    public static function createPendingUser(string $email, string $name, string $roleCode, ?int $memberId = null): int
    {
        $id = Database::insert(
            'INSERT INTO users (email, password_hash, display_name, member_id, is_active, created_at) VALUES (?,?,?,?,1,?)',
            [strtolower($email), self::unusableHash(), $name, $memberId, now()]
        );
        Database::exec('INSERT INTO user_roles (user_id, role_id) SELECT ?, id FROM roles WHERE code = ?', [$id, $roleCode]);
        return $id;
    }

    /** @return array{0:string,1:string} [onderwerp, tekst] */
    public static function inviteMessage(array $user, string $link, string $context): array
    {
        $app = (string) config('app_name', 'HZS Clubapp');
        return [
            "Uitnodiging voor de $app",
            "Dag {$user['display_name']},\n\n"
            . "Je bent uitgenodigd voor de $app van Hasseltse Zwemvereniging Spartacus. $context\n\n"
            . "Maak je account aan met deze persoonlijke link (geldig " . self::INVITE_DAYS . " dagen, één keer te gebruiken):\n$link\n\n"
            . "Je meldt daarna aan met dit e-mailadres: {$user['email']}\n\n"
            . "Heb je dit niet verwacht? Dan kun je deze mail negeren.\n\nHasseltse Zwemvereniging Spartacus",
        ];
    }

    public static function resetMessage(array $user, string $link): array
    {
        $app = (string) config('app_name', 'HZS Clubapp');
        return [
            "Nieuw wachtwoord voor de $app",
            "Dag {$user['display_name']},\n\n"
            . "Er is een nieuw wachtwoord aangevraagd voor je account. Kies het via deze link (geldig " . self::RESET_MINUTES . " minuten, één keer te gebruiken):\n$link\n\n"
            . "Heb je dit niet aangevraagd? Dan kun je deze mail negeren; je wachtwoord blijft ongewijzigd.\n\nHasseltse Zwemvereniging Spartacus",
        ];
    }

    /**
     * Eigen account (rol Zwemmer) voor een lid aanmaken en de uitnodiging sturen.
     * $email leeg = het e-mailadres van het lid. Weigert als het lid al een account heeft of het adres al gebruikt wordt.
     * @return array{ok:bool,msg:string,uid:?int}
     */
    public static function inviteMember(int $memberId, string $email = '', string $context = 'Je kunt er je eigen kalender en groepen bekijken.'): array
    {
        $member = Database::one('SELECT id, first_name, last_name, email FROM members WHERE id = ?', [$memberId]);
        if (!$member) {
            return ['ok' => false, 'msg' => 'Dit lid bestaat niet.', 'uid' => null];
        }
        $email = strtolower(trim($email !== '' ? $email : (string) $member['email']));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'msg' => 'Geen geldig e-mailadres om een account aan te maken.', 'uid' => null];
        }
        if (Database::val('SELECT COUNT(*) FROM users WHERE member_id = ?', [$memberId])) {
            return ['ok' => false, 'msg' => 'Dit lid heeft al een eigen account.', 'uid' => null];
        }
        if (Database::val('SELECT COUNT(*) FROM users WHERE email = ?', [$email])) {
            return ['ok' => false, 'msg' => "Het e-mailadres $email wordt al gebruikt door een ander account (bv. dat van een ouder). Gebruik een eigen e-mailadres.", 'uid' => null];
        }
        $uid = Database::tx(fn() => self::createPendingUser($email, $member['first_name'] . ' ' . $member['last_name'], 'SWIMMER', $memberId));
        [$sent, $link] = self::sendInvite($uid, $context);
        audit('INVITE', 'user', $uid);
        $msg = $sent ? "Uitnodiging verstuurd naar $email." : "Account aangemaakt, maar de uitnodiging kon niet verstuurd worden naar $email. Controleer de mailinstellingen.";
        if (Mailer::driver() === 'log') {
            $msg .= " (Testmodus: geen echte mail; zie storage/mail. Link: $link)";
        }
        return ['ok' => $sent, 'msg' => $msg, 'uid' => $uid];
    }

    /** Stuurt een uitnodiging aan een bestaand, nog niet geactiveerd account. @return array{0:bool,1:string} [verstuurd, link] */
    public static function sendInvite(int $userId, string $context): array
    {
        $user = Database::one('SELECT id, email, display_name FROM users WHERE id = ?', [$userId]);
        $link = self::link(self::createToken($userId, 'INVITE'));
        [$subject, $body] = self::inviteMessage($user, $link, $context);
        return [Mailer::send($user['email'], $subject, $body), $link];
    }
}
