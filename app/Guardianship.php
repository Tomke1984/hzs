<?php
/**
 * Regel: een actief lid jonger dan 18 moet minstens één ouder of voogd hebben
 * (een rij in guardian_links). Leden zonder geboortedatum vallen buiten de regel.
 */
final class Guardianship
{
    public const ADULT_AGE = 18;

    /** Wie na deze datum geboren is, is jonger dan 18. */
    public static function cutoff(): string
    {
        return (new DateTimeImmutable('today'))->modify('-' . self::ADULT_AGE . ' years')->format('Y-m-d');
    }

    public static function isMinor(?string $birthDate): bool
    {
        return $birthDate !== null && $birthDate !== '' && valid_date($birthDate) && $birthDate > self::cutoff();
    }

    public static function count(int $memberId): int
    {
        return (int) Database::val('SELECT COUNT(*) FROM guardian_links WHERE member_id = ?', [$memberId]);
    }

    /** Heeft dit (actieve) lid een ouder nodig en nog geen? */
    public static function missing(array $member): bool
    {
        return ($member['status'] ?? 'ACTIVE') === 'ACTIVE'
            && self::isMinor($member['birth_date'] ?? null)
            && self::count((int) $member['id']) === 0;
    }

    /** SQL-voorwaarde voor "minderjarig, actief en zonder ouder"; parameter: self::cutoff(). */
    public static function missingSql(string $alias = 'm'): string
    {
        return "($alias.status = 'ACTIVE' AND $alias.birth_date IS NOT NULL AND $alias.birth_date > ?
                 AND NOT EXISTS (SELECT 1 FROM guardian_links gl WHERE gl.member_id = $alias.id))";
    }

    public static function missingCount(): int
    {
        return (int) Database::val('SELECT COUNT(*) FROM members m WHERE ' . self::missingSql('m'), [self::cutoff()]);
    }

    /** @return int[] ids van leden die een ouder missen */
    public static function missingIds(): array
    {
        return array_map('intval', array_column(
            Database::all('SELECT m.id FROM members m WHERE ' . self::missingSql('m'), [self::cutoff()]),
            'id'
        ));
    }
}
