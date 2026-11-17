<?php
/** Eigen account: wachtwoord en persoonlijke agenda-link. */
final class AccountController
{
    public static function index(): void
    {
        self::page();
    }

    private static function page(): void
    {
        $u = Database::one('SELECT id, email, display_name, ical_token FROM users WHERE id = ?', [Auth::user()['id']]);
        $ical = null;
        if ($u['ical_token']) {
            $ical = Invites::baseUrl() . '/' . url('ical', ['t' => $u['ical_token']]);
        }
        render('account/index', [
            'u' => $u, 'ical' => $ical,
            'roles' => array_map('role_label', Auth::roles()),
        ], 'Mijn account');
    }

    public static function password(): void
    {
        $u = Database::one('SELECT id, email, display_name, password_hash FROM users WHERE id = ?', [Auth::user()['id']]);
        $new = (string) ($_POST['new_password'] ?? '');
        if (!password_verify((string) ($_POST['current_password'] ?? ''), $u['password_hash'])) {
            flash('error', 'Je huidige wachtwoord klopt niet.');
        } elseif ($problem = PasswordPolicy::problem($new, [$u['email'], $u['display_name']])) {
            flash('error', $problem);
        } elseif ($new !== (string) ($_POST['new_password2'] ?? '')) {
            flash('error', 'De twee nieuwe wachtwoorden komen niet overeen.');
        } else {
            Database::exec('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), $u['id']]);
            Auth::revokeSessions((int) $u['id']);
            audit('PASSWORD', 'user', (int) $u['id']);
            flash('success', 'Je wachtwoord is gewijzigd. Op andere toestellen moet je opnieuw aanmelden.');
        }
        redirect(url('account'));
    }

    public static function icalReset(): void
    {
        $token = bin2hex(random_bytes(20));
        Database::exec('UPDATE users SET ical_token = ?, ical_last_used = ? WHERE id = ?', input('do') === 'remove' ? [null, null, Auth::user()['id']] : [$token, today(), Auth::user()['id']]);
        flash('success', input('do') === 'remove' ? 'Je agenda-link is uitgeschakeld.' : 'Je persoonlijke agenda-link is aangemaakt. Een eerdere link werkt niet meer.');
        redirect(url('account') . '#agenda');
    }

    /** Agenda-abonnement: publiek adres met een geheime sleutel; toont enkel wat de eigenaar zelf in de app ziet. */
    public static function ical(): void
    {
        $t = (string) ($_GET['t'] ?? '');
        $u = preg_match('/^[a-f0-9]{40}$/', $t)
            ? Database::one('SELECT id, email, display_name, member_id, is_active, ical_last_used FROM users WHERE ical_token = ?', [$t])
            : null;
        // Een link die lang niet meer opgevraagd werd (bv. van een oud toestel), vervalt vanzelf.
        if ($u && $u['ical_last_used'] !== null && $u['ical_last_used'] < date('Y-m-d', strtotime('-' . ical_idle_days() . ' days'))) {
            Database::exec('UPDATE users SET ical_token = NULL, ical_last_used = NULL WHERE id = ?', [$u['id']]);
            $u = null;
        }
        if ($u && $u['ical_last_used'] !== today()) {
            Database::exec('UPDATE users SET ical_last_used = ? WHERE id = ?', [today(), $u['id']]);
        }
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
