<?php
/**
 * Berichten van trainers en bestuur naar groepen, trainingsblokken en deelnemers van een activiteit.
 * Wie ontvangt wat wordt altijd berekend binnen de scope van de afzender (een trainer bereikt enkel de eigen groepen).
 */
final class Mailing
{
    public const MAX_RECIPIENTS = 400;

    /** Leden die de afzender mag bereiken (null = alle leden). */
    private static function reach(): ?array
    {
        return Scope::clubWide() ? null : (Scope::coachMemberIds() ?? []);
    }

    /** @return array<string,array{0:string,1:string}> sleutel => [groepslabel, optielabel] */
    public static function options(): array
    {
        $opts = [];
        if (Scope::clubWide()) {
            $opts['club'] = ['Hele club', 'Alle actieve leden'];
        }
        $gids = Scope::clubWide() ? null : (Auth::hasRole('TRAINER') ? Scope::groupIds() : []);
        if ($gids === null || $gids) {
            $where = $gids === null ? '1=1' : 'id IN (' . Scope::in($gids) . ')';
            foreach (Database::all("SELECT id, name, category FROM `groups` WHERE is_active = 1 AND $where ORDER BY category, name", $gids ?? []) as $g) {
                $opts['g:' . $g['id']] = ['Groepen', $g['name']];
            }
            $sw = $gids === null ? '1=1' : 'EXISTS (SELECT 1 FROM activity_series_groups x WHERE x.series_id = s.id AND x.group_id IN (' . Scope::in($gids) . '))';
            foreach (Database::all("SELECT s.id, s.title FROM activity_series s WHERE s.is_active = 1 AND s.end_date >= ? AND $sw ORDER BY s.title", array_merge([today()], $gids ?? [])) as $s) {
                $slots = Database::all('SELECT * FROM activity_series_slots WHERE series_id = ? ORDER BY weekday', [$s['id']]);
                $opts['s:' . $s['id']] = ['Trainingsblokken', ucfirst(SeriesService::summary($slots)) . ' – ' . $s['title']];
            }
        }
        return $opts;
    }

    /** @return array{0:string,1:int[]}|null [label, lid-ids] of null als de afzender dit publiek niet mag bereiken */
    public static function audience(string $key): ?array
    {
        $reach = self::reach();
        $ids = null;
        $label = '';
        if ($key === 'club' && Scope::clubWide()) {
            $label = 'Hele club';
            $ids = array_map('intval', array_column(Database::all("SELECT id FROM members WHERE status = 'ACTIVE'"), 'id'));
        } elseif (preg_match('/^g:(\d+)$/', $key, $m) && Scope::canSeeGroup((int) $m[1])) {
            $g = Database::one('SELECT name FROM `groups` WHERE id = ?', [(int) $m[1]]);
            if ($g && ($reach === null || Auth::hasRole('TRAINER'))) {
                $label = 'Groep ' . $g['name'];
                $ids = array_map('intval', array_column(Database::all(
                    "SELECT m.id FROM members m JOIN group_memberships gm ON gm.member_id = m.id WHERE gm.group_id = ? AND m.status = 'ACTIVE'",
                    [(int) $m[1]]
                ), 'id'));
            }
        } elseif (preg_match('/^s:(\d+)$/', $key, $m)) {
            $s = Database::one('SELECT id, title FROM activity_series WHERE id = ?', [(int) $m[1]]);
            $allowed = $s && ($reach === null || (int) Database::val(
                'SELECT COUNT(*) FROM activity_series_groups WHERE series_id = ? AND group_id IN (' . Scope::in(Scope::groupIds() ?: [0]) . ')',
                array_merge([$s['id']], Scope::groupIds() ?: [0])
            ) > 0);
            if ($allowed) {
                $slots = Database::all('SELECT * FROM activity_series_slots WHERE series_id = ? ORDER BY weekday', [$s['id']]);
                $label = 'Blok ' . SeriesService::summary($slots) . ' (' . $s['title'] . ')';
                if (SeriesService::isSwim((int) $s['id'])) {
                    $ids = array_map('intval', array_column(SeriesService::blockMembers((int) $s['id']), 'id'));
                } else {
                    $ids = array_map('intval', array_column(Database::all(
                        "SELECT DISTINCT m.id FROM members m JOIN group_memberships gm ON gm.member_id = m.id
                         JOIN activity_series_groups sg ON sg.group_id = gm.group_id WHERE sg.series_id = ? AND m.status = 'ACTIVE'",
                        [$s['id']]
                    ), 'id'));
                }
            }
        } elseif (preg_match('/^a:(\d+)$/', $key, $m)) {
            $a = Scope::findActivity((int) $m[1]);
            if ($a) {
                $label = 'Deelnemers van ' . $a['title'] . ' (' . fmt_dt($a['starts_at']) . ')';
                $ids = array_map('intval', array_column(Scope::targetMembers($a), 'id'));
            }
        }
        if ($ids === null) {
            return null;
        }
        if ($reach !== null) {
            $ids = array_values(array_intersect($ids, $reach));
        }
        return [$label, $ids];
    }

    /** E-mailadressen van de leden zelf en van hun ouders/voogden (actieve accounts), ontdubbeld. @return array<string,string> e-mail => naam */
    public static function recipients(array $memberIds): array
    {
        $out = [];
        $memberIds = array_values(array_unique(array_map('intval', $memberIds)));
        foreach (array_chunk($memberIds, 500) as $chunk) {
            $in = Scope::in($chunk);
            foreach (Database::all("SELECT email, first_name, last_name FROM members WHERE id IN ($in) AND email IS NOT NULL AND email <> ''", $chunk) as $m) {
                $out[strtolower($m['email'])] = trim($m['first_name'] . ' ' . $m['last_name']);
            }
            foreach (Database::all(
                "SELECT u.email, u.display_name FROM guardian_links gl JOIN users u ON u.id = gl.user_id WHERE gl.member_id IN ($in) AND u.is_active = 1",
                $chunk
            ) as $u) {
                $out[strtolower($u['email'])] = $u['display_name'];
            }
        }
        foreach (array_keys($out) as $mail) {
            if (!filter_var($mail, FILTER_VALIDATE_EMAIL)) {
                unset($out[$mail]);
            }
        }
        return $out;
    }

    /** Verstuurt het bericht naar elk adres afzonderlijk en bewaart een logregel. @return array{0:int,1:int} [verstuurd, mislukt] */
    public static function send(string $kind, string $audience, string $subject, string $body, array $emails): array
    {
        $u = Auth::user();
        $footer = "\n\n--\nVerstuurd via de Clubapp van Hasseltse Zwemvereniging Spartacus door " . ($u['display_name'] ?? 'het bestuur')
            . (!empty($u['email']) ? ' (' . $u['email'] . ')' : '') . '.';
        $messages = [];
        foreach (array_keys($emails) as $to) {
            $messages[] = [$to, $subject, $body . $footer];
        }
        $res = $messages ? Mailer::sendMany($messages) : [];
        $ok = count(array_filter($res));
        Database::exec(
            'INSERT INTO broadcasts (kind, sender_id, subject, body, audience, recipient_count, created_at) VALUES (?,?,?,?,?,?,?)',
            [$kind, $u['id'] ?? null, mb_substr($subject, 0, 190), $body, mb_substr($audience, 0, 190), $ok, now()]
        );
        return [$ok, count($messages) - $ok];
    }
}
