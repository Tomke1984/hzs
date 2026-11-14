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

    public static function member(int $id): void
    {
        foreach (['attendance', 'absence_reports', 'group_memberships', 'guardian_links', 'member_series'] as $t) {
            Database::exec("DELETE FROM $t WHERE member_id = ?", [$id]);
        }
        foreach (self::LEGACY_MEMBER_TABLES as $t) {
            self::try("DELETE FROM $t WHERE member_id = ?", [$id]);
        }
        Database::exec('UPDATE users SET member_id = NULL WHERE member_id = ?', [$id]);
        Database::exec('DELETE FROM members WHERE id = ?', [$id]);
    }

    public static function user(int $id): void
    {
        foreach (['user_roles', 'user_tokens', 'guardian_links', 'group_trainers', 'series_trainers', 'activity_trainers'] as $t) {
            Database::exec("DELETE FROM $t WHERE user_id = ?", [$id]);
        }
        foreach ([['activities', 'created_by'], ['activity_series', 'created_by'], ['attendance', 'updated_by'],
                  ['absence_reports', 'reported_by'], ['activity_trainers', 'updated_by']] as [$t, $col]) {
            Database::exec("UPDATE $t SET $col = NULL WHERE $col = ?", [$id]);
        }
        Database::exec('UPDATE broadcasts SET sender_id = NULL WHERE sender_id = ?', [$id]);
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
