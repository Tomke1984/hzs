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
        return [
            "($a.audience = 'CLUB' OR EXISTS (SELECT 1 FROM activity_groups ag WHERE ag.activity_id = $a.id AND ag.group_id IN (" . self::in($gids) . ')))',
            $gids,
        ];
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
        return Database::all(
            "SELECT DISTINCT m.* FROM members m
             JOIN group_memberships gm ON gm.member_id = m.id
             WHERE m.status = 'ACTIVE' AND gm.group_id IN (" . self::in($groupIds) . ')
             ORDER BY m.last_name, m.first_name',
            $groupIds
        );
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
                 WHERE gt.user_id = ? AND g.is_active = 1 AND gt.start_date <= ? AND (gt.end_date IS NULL OR gt.end_date >= ?)',
                [$u['id'], $today, $today]
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
