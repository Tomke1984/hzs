<?php
/** Eigen account: wachtwoord, persoonlijke agenda-link en tweestapsverificatie. */
final class AccountController
{
    public static function index(): void
    {
        self::page();
    }

    private static function page(?string $setupSecret = null): void
    {
        $u = Database::one('SELECT id, email, display_name, ical_token, totp_enabled FROM users WHERE id = ?', [Auth::user()['id']]);
        $ical = null;
        if ($u['ical_token']) {
            $ical = Invites::baseUrl() . '/' . url('ical', ['t' => $u['ical_token']]);
        }
        render('account/index', [
            'u' => $u, 'ical' => $ical, 'setupSecret' => $setupSecret,
            'otpUri' => $setupSecret ? Totp::uri($u['email'], $setupSecret) : '',
            'roles' => array_map('role_label', Auth::roles()),
            'forced' => Auth::mustEnableTwoFactor(),
        ], 'Mijn account');
    }

    public static function password(): void
    {
        $u = Database::one('SELECT id, password_hash FROM users WHERE id = ?', [Auth::user()['id']]);
        $new = (string) ($_POST['new_password'] ?? '');
        if (!password_verify((string) ($_POST['current_password'] ?? ''), $u['password_hash'])) {
            flash('error', 'Je huidige wachtwoord klopt niet.');
        } elseif (mb_strlen($new) < 10) {
            flash('error', 'Het nieuwe wachtwoord moet minstens 10 tekens hebben.');
        } elseif ($new !== (string) ($_POST['new_password2'] ?? '')) {
            flash('error', 'De twee nieuwe wachtwoorden komen niet overeen.');
        } else {
            Database::exec('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), $u['id']]);
            audit('PASSWORD', 'user', (int) $u['id']);
            flash('success', 'Je wachtwoord is gewijzigd.');
        }
        redirect(url('account'));
    }

    public static function icalReset(): void
    {
        $token = bin2hex(random_bytes(20));
        Database::exec('UPDATE users SET ical_token = ? WHERE id = ?', [input('do') === 'remove' ? null : $token, Auth::user()['id']]);
        flash('success', input('do') === 'remove' ? 'Je agenda-link is uitgeschakeld.' : 'Je persoonlijke agenda-link is aangemaakt. Een eerdere link werkt niet meer.');
        redirect(url('account') . '#agenda');
    }

    public static function twoFactorStart(): void
    {
        $secret = Totp::newSecret();
        $_SESSION['totp_setup'] = $secret;
        self::page($secret);
    }

    public static function twoFactorEnable(): void
    {
        $secret = (string) ($_SESSION['totp_setup'] ?? '');
        if ($secret === '') {
            redirect(url('account'));
        }
        if (!Totp::verify($secret, (string) ($_POST['code'] ?? ''))) {
            http_response_code(422);
            flash('error', 'Die code klopt niet. Controleer de tijd op je telefoon en probeer de volgende code.');
            self::page($secret);
            return;
        }
        Database::exec('UPDATE users SET totp_secret = ?, totp_enabled = 1 WHERE id = ?', [$secret, Auth::user()['id']]);
        unset($_SESSION['totp_setup']);
        audit('2FA_ON', 'user', (int) Auth::user()['id']);
        flash('success', 'Tweestapsverificatie staat aan. Bij het volgende aanmelden vragen we de code uit je app.');
        redirect(url('account'));
    }

    public static function twoFactorDisable(): void
    {
        $u = Database::one('SELECT id, password_hash FROM users WHERE id = ?', [Auth::user()['id']]);
        if (!password_verify((string) ($_POST['current_password'] ?? ''), $u['password_hash'])) {
            flash('error', 'Je wachtwoord klopt niet; tweestapsverificatie blijft aan.');
        } else {
            Database::exec('UPDATE users SET totp_secret = NULL, totp_enabled = 0 WHERE id = ?', [$u['id']]);
            audit('2FA_OFF', 'user', (int) $u['id']);
            flash('success', 'Tweestapsverificatie is uitgezet.');
        }
        redirect(url('account'));
    }

    /** Agenda-abonnement: publiek adres met een geheime sleutel; toont enkel wat de eigenaar zelf in de app ziet. */
    public static function ical(): void
    {
        $t = (string) ($_GET['t'] ?? '');
        $u = preg_match('/^[a-f0-9]{40}$/', $t)
            ? Database::one('SELECT id, email, display_name, member_id, is_active FROM users WHERE ical_token = ?', [$t])
            : null;
        if (!$u || (int) $u['is_active'] !== 1) {
            http_response_code(404);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Deze agenda-link bestaat niet (meer).';
            return;
        }
        Auth::actAs($u);
        [$sql, $params] = Scope::activityFilter('a');
        if (Scope::groupIds() !== null) {
            $sql = "($sql OR t.code = 'COMPETITION')"; // zoals de kalender in de app: wedstrijden zijn voor iedereen zichtbaar
        }
        $rows = Database::all(
            "SELECT a.id, a.title, a.description, a.location, a.starts_at, a.ends_at, a.status, a.status_note, t.name AS type_name
             FROM activities a JOIN activity_types t ON t.id = a.type_id
             WHERE a.starts_at >= ? AND a.starts_at < ? AND $sql ORDER BY a.starts_at LIMIT 2000",
            array_merge([date('Y-m-d 00:00:00', strtotime('-30 days')), date('Y-m-d 00:00:00', strtotime('+400 days'))], $params)
        );
        $host = preg_replace('/[^A-Za-z0-9.-]/', '', (string) parse_url(Invites::baseUrl(), PHP_URL_HOST)) ?: 'localhost';
        $utc = new DateTimeZone('UTC');
        $local = new DateTimeZone(date_default_timezone_get());
        $fmt = function (string $dt) use ($utc, $local): string {
            return (new DateTimeImmutable($dt, $local))->setTimezone($utc)->format('Ymd\THis\Z');
        };
        $lines = ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//HZS Clubapp//NL', 'CALSCALE:GREGORIAN', 'METHOD:PUBLISH',
            'X-WR-CALNAME:' . self::esc('HZS – ' . $u['display_name']), 'X-WR-TIMEZONE:' . date_default_timezone_get(), 'REFRESH-INTERVAL;VALUE=DURATION:PT6H', 'X-PUBLISHED-TTL:PT6H'];
        foreach ($rows as $r) {
            $cancelled = $r['status'] === 'CANCELLED';
            $desc = trim(($r['description'] ?? '') . ($cancelled && $r['status_note'] ? "\n" . $r['status_note'] : ''));
            $lines[] = 'BEGIN:VEVENT';
            $lines[] = 'UID:act-' . (int) $r['id'] . '@' . $host;
            $lines[] = 'DTSTAMP:' . gmdate('Ymd\THis\Z');
            $lines[] = 'DTSTART:' . $fmt($r['starts_at']);
            $lines[] = 'DTEND:' . $fmt($r['ends_at']);
            $lines[] = 'SUMMARY:' . self::esc(($cancelled ? 'GESCHRAPT: ' : '') . $r['title']);
            if ($r['location']) {
                $lines[] = 'LOCATION:' . self::esc($r['location']);
            }
            if ($desc !== '') {
                $lines[] = 'DESCRIPTION:' . self::esc($desc);
            }
            $lines[] = 'CATEGORIES:' . self::esc($r['type_name']);
            $lines[] = 'STATUS:' . ($cancelled ? 'CANCELLED' : 'CONFIRMED');
            $lines[] = 'END:VEVENT';
        }
        $lines[] = 'END:VCALENDAR';
        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: inline; filename="hzs-agenda.ics"');
        header('X-Robots-Tag: noindex, nofollow');
        header('Cache-Control: private, max-age=900');
        echo implode("\r\n", array_map([self::class, 'fold'], $lines)) . "\r\n";
    }

    private static function esc(string $s): string
    {
        return str_replace(["\\", ';', ',', "\r\n", "\n", "\r"], ['\\\\', '\;', '\,', '\n', '\n', '\n'], $s);
    }

    /** Regels langer dan 75 bytes worden volgens RFC 5545 gevouwen (zonder een teken te breken). */
    private static function fold(string $line): string
    {
        if (strlen($line) <= 75) {
            return $line;
        }
        $out = '';
        $cur = '';
        foreach (preg_split('//u', $line, -1, PREG_SPLIT_NO_EMPTY) as $ch) {
            if (strlen($cur) + strlen($ch) > ($out === '' ? 75 : 74)) {
                $out .= ($out === '' ? '' : "\r\n ") . $cur;
                $cur = '';
            }
            $cur .= $ch;
        }
        return $out . ($cur !== '' ? "\r\n " . $cur : '');
    }
}
