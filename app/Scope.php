<?php
/**
 * Centrale gegevensscope (MySQL/SQLite kennen geen Row Level Security).
 * Elke query op leden, groepen, activiteiten of aanwezigheden hoort deze klasse te gebruiken.
 *
 *  - beheerder en bestuur: clubbreed (null = geen beperking)
 *  - trainer:   leden en groepen van actieve toegewezen groepen
 *  - zwemmer:   zichzelf en de eigen actieve groepen
 *  - ouder:     gekoppelde kinderen en hun actieve groepen
 * Heeft iemand meerdere rollen, dan is de scope de unie van die rollen.
 */
final class Scope
{
    private static ?array $groupIds = null;
    private static ?array $memberIds = null;
    private static ?array $coachMembers = null;
    private static bool $computed = false;

    public static function clubWide(): bool
    {
        return Auth::hasRole('ADMIN') || Auth::hasRole('BOARD');
    }

    /** Placeholders voor een IN-lijst. */
    public static function in(array $ids): string
    {
        return implode(',', array_fill(0, count($ids), '?'));
    }

    /** @return int[]|null null = alle groepen */
    public static function groupIds(): ?array
    {
        self::compute();
        return self::$groupIds;
    }

    /** @return int[]|null null = alle leden */
    public static function memberIds(): ?array
    {
        self::compute();
        return self::$memberIds;
    }

    public static function canSeeMember(int $id): bool
    {
        $ids = self::memberIds();
        return $ids === null || in_array($id, $ids, true);
    }

    public static function canSeeGroup(int $id): bool
    {
        $ids = self::groupIds();
        return $ids === null || in_array($id, $ids, true);
    }

    /** @return array{0:string,1:array} SQL-voorwaarde + parameters voor activiteiten (alias $a) */
    public static function activityFilter(string $a = 'a'): array
    {
        $gids = self::groupIds();
        if ($gids === null) {
            return ['1=1', []];
        }
        if (!$gids) {
            return ["$a.audience = 'CLUB'", []];
        }
        $sql = "($a.audience = 'CLUB' OR EXISTS (SELECT 1 FROM activity_groups ag WHERE ag.activity_id = $a.id AND ag.group_id IN (" . self::in($gids) . ')))';
        $params = $gids;
        // Zwemschool: leden die op een vaste trainingsdag zwemmen zien de trainingen van de andere dagen niet.
        $wd = Database::driver() === 'mysql' ? '(WEEKDAY(SUBSTR(' . $a . '.starts_at,1,10)) + 1)' : "((CAST(strftime('%w', SUBSTR($a.starts_at,1,10)) AS INTEGER) + 6) % 7 + 1)";
        foreach (self::hiddenSlots() as [$sid, $day]) {
            // ints rechtstreeks in de query: een gebonden parameter is tekst en zou niet met de weekdag-expressie vergelijken
            $sql .= " AND NOT ($a.series_id = " . (int) $sid . " AND $wd = " . (int) $day . ')';
        }
        return [$sql, $params];
    }

    /**
     * Trainingsmomenten (reeks + weekdag) die voor deze gebruiker (als lid/ouder, niet als trainer) niet relevant zijn,
     * omdat al zijn leden in die groep aan andere momenten gekoppeld zijn.
     * @return array<int, array{0:int,1:int}>
     */
    public static function hiddenSlots(): array
    {
        static $cache = [];
        $u = Auth::user();
        if (!$u || self::clubWide()) {
            return [];
        }
        if (isset($cache[$u['id']])) {
            return $cache[$u['id']];
        }
        $own = [];
        if (Auth::hasRole('SWIMMER') && $u['member_id']) {
            $own[] = (int) $u['member_id'];
        }
        if (Auth::hasRole('PARENT')) {
            $own = array_merge($own, array_map('intval', array_column(Database::all('SELECT member_id FROM guardian_links WHERE user_id = ?', [$u['id']]), 'member_id')));
        }
        $own = array_values(array_unique($own));
        if (!$own) {
            return $cache[$u['id']] = [];
        }
        $trainerGroups = Auth::hasRole('TRAINER') ? array_map('intval', array_column(Database::all('SELECT group_id FROM group_trainers WHERE user_id = ?', [$u['id']]), 'group_id')) : [];
        $hidden = [];
        foreach (Database::all('SELECT DISTINCT group_id FROM group_memberships WHERE member_id IN (' . self::in($own) . ')', $own) as $g) {
            $gid = (int) $g['group_id'];
            if (in_array($gid, $trainerGroups, true)) {
                continue;
            }
            $slots = [];
            foreach (Database::all('SELECT sl.series_id, sl.weekday FROM activity_series_slots sl JOIN activity_series_groups sg ON sg.series_id = sl.series_id WHERE sg.group_id = ?', [$gid]) as $r) {
                $slots[(int) $r['series_id'] . ':' . (int) $r['weekday']] = [(int) $r['series_id'], (int) $r['weekday']];
            }
            if (!$slots) {
                continue;
            }
            $visible = [];
            $unrestricted = false;
            $swim = Database::val('SELECT category FROM `groups` WHERE id = ?', [$gid]) === 'SWIM_SCHOOL';
            foreach (Database::all('SELECT member_id FROM group_memberships WHERE group_id = ? AND member_id IN (' . self::in($own) . ')', array_merge([$gid], $own)) as $mm) {
                $rows = Database::all('SELECT series_id, weekday FROM member_series WHERE member_id = ?', [$mm['member_id']]);
                $mine = [];
                foreach ($rows as $r) {
                    foreach ($slots as $k => [$sid, $day]) {
                        if ($sid === (int) $r['series_id'] && ((int) $r['weekday'] === 0 || (int) $r['weekday'] === $day)) {
                            $mine[$k] = true;
                        }
                    }
                }
                if (!$mine) {
                    if ($swim) {
                        continue; // zwemschool: nog niet ingedeeld = geen enkel blok
                    }
                    $unrestricted = true;
                    break;
                }
                $visible += $mine;
            }
            if (!$unrestricted) {
                foreach ($slots as $k => $pair) {
                    if (!isset($visible[$k])) {
                        $hidden[$k] = $pair;
                    }
                }
            }
        }
        return $cache[$u['id']] = array_values($hidden);
    }

    public static function findActivity(int $id): ?array
    {
        [$sql, $params] = self::activityFilter('a');
        return Database::one(
            "SELECT a.*, t.code AS type_code, t.name AS type_name
             FROM activities a JOIN activity_types t ON t.id = a.type_id
             WHERE a.id = ? AND $sql",
            array_merge([$id], $params)
        );
    }

    /** Leden die volgens planning bij een activiteit horen (actief, op activiteitsdatum in de doelgroep). */
    public static function targetMembers(array $activity): array
    {
        if ($activity['audience'] === 'CLUB') {
            return Database::all("SELECT * FROM members WHERE status = 'ACTIVE' ORDER BY last_name, first_name");
        }
        $groupIds = array_column(Database::all('SELECT group_id FROM activity_groups WHERE activity_id = ?', [$activity['id']]), 'group_id');
        if (!$groupIds) {
            return [];
        }
        $sql = "SELECT DISTINCT m.* FROM members m
             JOIN group_memberships gm ON gm.member_id = m.id
             JOIN `groups` tg ON tg.id = gm.group_id
             WHERE m.status = 'ACTIVE' AND gm.group_id IN (" . self::in($groupIds) . ')';
        $params = $groupIds;
        // Reekstraining: zwemschoolleden staan enkel op de blokken waaraan ze gekoppeld zijn (niet-ingedeeld = nergens);
        // bij de competitie volgt iedereen het vaste schema van de groep, tenzij er toch een eigen keuze is.
        if (!empty($activity['series_id'])) {
            $sql .= " AND (EXISTS (SELECT 1 FROM member_series ms WHERE ms.member_id = m.id AND ms.series_id = ? AND (ms.weekday = 0 OR ms.weekday = ?))
                       OR (tg.category = 'COMPETITION' AND NOT EXISTS (SELECT 1 FROM member_series ms2
                            JOIN activity_series_groups asg ON asg.series_id = ms2.series_id
                            WHERE ms2.member_id = m.id AND asg.group_id = gm.group_id)))";
            $params[] = (int) $activity['series_id'];
            $params[] = (int) date('N', strtotime(substr($activity['starts_at'], 0, 10)));
        }
        return Database::all($sql . ' ORDER BY m.last_name, m.first_name', $params);
    }

    /** Doelgroep ingeperkt tot de leden die de ingelogde gebruiker mag zien. */
    public static function visibleTargetMembers(array $activity): array
    {
        $members = self::targetMembers($activity);
        $ids = self::memberIds();
        if ($ids === null) {
            return $members;
        }
        return array_values(array_filter($members, fn($m) => in_array((int) $m['id'], $ids, true)));
    }

    /**
     * Leden voor wie de gebruiker aanwezigheid mag registreren: enkel via de trainersrol
     * (leden van de eigen actieve trainersgroepen). Een trainer die zelf ook lid is, registreert dus niet
     * voor groepen waar hij enkel als lid in zit.
     * @return int[]|null null = alle leden (beheerder)
     */
    public static function coachMemberIds(): ?array
    {
        self::compute();
        return self::$coachMembers;
    }

    /** Doelgroep van een activiteit ingeperkt tot de leden die de gebruiker mag registreren. */
    public static function registrableMembers(array $activity): array
    {
        $members = self::targetMembers($activity);
        $ids = self::coachMemberIds();
        if ($ids === null) {
            return $members;
        }
        return array_values(array_filter($members, fn($m) => in_array((int) $m['id'], $ids, true)));
    }

    /** Groepen waar de ingelogde gebruiker zelf bij hoort (als trainer, als lid of via gekoppelde kinderen), ook voor beheerders. */
    public static function myGroupIds(): array
    {
        $u = Auth::user();
        if (!$u) {
            return [];
        }
        $groups = [];
        if (Auth::hasRole('TRAINER')) {
            $groups = array_column(Database::all(
                'SELECT gt.group_id FROM group_trainers gt JOIN `groups` g ON g.id = gt.group_id WHERE gt.user_id = ? AND g.is_active = 1',
                [$u['id']]
            ), 'group_id');
        }
        $own = [];
        if ($u['member_id']) {
            $own[] = (int) $u['member_id'];
        }
        $own = array_merge($own, array_column(Database::all('SELECT member_id FROM guardian_links WHERE user_id = ?', [$u['id']]), 'member_id'));
        if ($own) {
            $own = array_map('intval', $own);
            $groups = array_merge($groups, array_column(Database::all(
                'SELECT DISTINCT gm.group_id FROM group_memberships gm JOIN `groups` g ON g.id = gm.group_id WHERE gm.member_id IN (' . self::in($own) . ') AND g.is_active = 1',
                $own
            ), 'group_id'));
        }
        return array_values(array_unique(array_map('intval', $groups)));
    }

    private static function compute(): void
    {
        if (self::$computed) {
            return;
        }
        self::$computed = true;
        $u = Auth::user();
        if (!$u || self::clubWide()) {
            self::$groupIds = self::$memberIds = self::$coachMembers = $u ? null : [];
            return;
        }

        $today = today();
        $groups = [];
        $members = [];

        if (Auth::hasRole('TRAINER')) {
            $tg = array_column(Database::all(
                'SELECT gt.group_id FROM group_trainers gt JOIN `groups` g ON g.id = gt.group_id
                 WHERE gt.user_id = ? AND g.is_active = 1',
                [$u['id']]
            ), 'group_id');
            $groups = array_merge($groups, $tg);
            self::$coachMembers = [];
            if ($tg) {
                $members = array_merge($members, array_column(Database::all(
                    'SELECT DISTINCT member_id FROM group_memberships WHERE group_id IN (' . self::in($tg) . ')',
                    $tg
                ), 'member_id'));
                self::$coachMembers = array_map('intval', $members);
            }
        }

        $own = [];
        if (Auth::hasRole('SWIMMER') && $u['member_id']) {
            $own[] = (int) $u['member_id'];
        }
        if (Auth::hasRole('PARENT')) {
            $own = array_merge($own, array_column(Database::all('SELECT member_id FROM guardian_links WHERE user_id = ?', [$u['id']]), 'member_id'));
        }
        if ($own) {
            $own = array_map('intval', $own);
            $members = array_merge($members, $own);
            $groups = array_merge($groups, array_column(Database::all(
                'SELECT DISTINCT gm.group_id FROM group_memberships gm JOIN `groups` g ON g.id = gm.group_id
                 WHERE gm.member_id IN (' . self::in($own) . ') AND g.is_active = 1',
                $own
            ), 'group_id'));
        }

        self::$groupIds = array_values(array_unique(array_map('intval', $groups)));
        self::$memberIds = array_values(array_unique(array_map('intval', $members)));
    }
}
