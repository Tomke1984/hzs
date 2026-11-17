<?php
/**
 * v0.25: tweestapsverificatie (TOTP) is vervangen door een controlevraag bij het aanmelden.
 * De geheimen en de vlag verdwijnen uit de database.
 */
return function (): void {
    Migrator::dropColumn('users', 'totp_secret');
    if (!Migrator::dropColumn('users', 'totp_enabled', false)) {
        Database::exec('UPDATE users SET totp_enabled = 0'); // oude SQLite: kolom blijft, maar staat overal uit
    }
};
