<?php
/**
 * Definitief verwijderen van een lid of een gebruiker, met alles wat eraan vasthangt.
 * Verwijzingen die historiek zijn (wie maakte een activiteit, wie registreerde iets) worden leeggemaakt, niet gewist.
 * Ook rijen in oude tabellen van verwijderde onderdelen (lidgeld, resultaten, ...) worden opgeruimd als die tabellen
 * nog in de database staan, zodat er geen persoonsgegevens achterblijven. De aanroeper zorgt voor de transactie.
 */
final class Purge
{
    /** Oude tabellen (van vóór v0.13) met een member_id: rijen van het lid wissen als de tabel bestaat. */
    private const LEGACY_MEMBER_TABLES = ['dues', 'group_waitlist', 'meet_entries', 'results', 'photo_members', 'event_signups', 'registration_persons'];

    /**
     * Elke tabel met een verwijzing naar een lid of gebruiker moet hieronder staan. De e2e-test v24_onderhoud
     * controleert dat aan de hand van de foreign keys in het schema: een nieuwe tabel vergeten = test faalt.
     */
    public const MEMBER_DELETE = ['attendance', 'absence_reports', 'group_memberships', 'guardian_links', 'member_series'];
    public const MEMBER_NULL = [['users', 'member_id']];
    public const USER_DELETE = ['user_roles', 'user_tokens', 'guardian_links', 'group_trainers', 'series_trainers', 'activity_trainers'];
    public const USER_NULL = [['activities', 'created_by'], ['activity_series', 'created_by'], ['attendance', 'updated_by'],
                              ['absence_reports', 'reported_by'], ['activity_trainers', 'updated_by'], ['broadcasts', 'sender_id']];

    public static function member(int $id): void
    {
        foreach (self::MEMBER_DELETE as $t) {
            Database::exec("DELETE FROM $t WHERE member_id = ?", [$id]);
        }
        foreach (self::LEGACY_MEMBER_TABLES as $t) {
            self::try("DELETE FROM $t WHERE member_id = ?", [$id]);
        }
        foreach (self::MEMBER_NULL as [$t, $col]) {
            Database::exec("UPDATE $t SET $col = NULL WHERE $col = ?", [$id]);
        }
        Database::exec('DELETE FROM members WHERE id = ?', [$id]);
    }

    public static function user(int $id): void
    {
        foreach (self::USER_DELETE as $t) {
            Database::exec("DELETE FROM $t WHERE user_id = ?", [$id]);
        }
        foreach (self::USER_NULL as [$t, $col]) {
            Database::exec("UPDATE $t SET $col = NULL WHERE $col = ?", [$id]);
        }
        Database::exec('DELETE FROM users WHERE id = ?', [$id]);
    }

    /** Uitvoeren als de tabel/kolom bestaat (oude installaties); anders niets doen. */
    private static function try(string $sql, array $params): void
    {
        try {
            Database::exec($sql, $params);
        } catch (Throwable $e) {
            // tabel of kolom bestaat niet in deze database
        }
    }
}
