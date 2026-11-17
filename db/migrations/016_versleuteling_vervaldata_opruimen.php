<?php
/**
 * v0.24
 * - medische opmerking en TOTP-geheim versleutelen (Crypto); op MySQL eerst de kolommen groot genoeg maken
 * - vervaldatum voor de deellink weekplanning (groups.share_expires) en "laatst gebruikt" voor de iCal-link
 * - kolommen van verwijderde onderdelen opruimen (lidgeld, capaciteit, doelstellingen, doorstroomgroep, website)
 */
return function (): void {
    if (Database::driver() === 'mysql') {
        Database::pdo()->exec('ALTER TABLE members MODIFY medical_note TEXT NULL');
        Database::pdo()->exec('ALTER TABLE users MODIFY totp_secret VARCHAR(255) NULL');
    }
    foreach (Database::all("SELECT id, medical_note FROM members WHERE medical_note IS NOT NULL AND medical_note <> '' AND medical_note NOT LIKE 'enc1:%'") as $m) {
        Database::exec('UPDATE members SET medical_note = ? WHERE id = ?', [Crypto::encrypt($m['medical_note']), $m['id']]);
    }
    foreach (Database::all("SELECT id, totp_secret FROM users WHERE totp_secret IS NOT NULL AND totp_secret <> '' AND totp_secret NOT LIKE 'enc1:%'") as $u) {
        Database::exec('UPDATE users SET totp_secret = ? WHERE id = ?', [Crypto::encrypt($u['totp_secret']), $u['id']]);
    }

    Migrator::addColumn('groups', 'share_expires', 'VARCHAR(10) NULL');
    Migrator::addColumn('users', 'ical_last_used', 'VARCHAR(10) NULL');
    Database::exec('UPDATE `groups` SET share_expires = ? WHERE share_token IS NOT NULL AND share_expires IS NULL', [date('Y-m-d', strtotime('+' . share_link_days() . ' days'))]);
    Database::exec('UPDATE users SET ical_last_used = ? WHERE ical_token IS NOT NULL AND ical_last_used IS NULL', [today()]);

    foreach ([['groups', 'goals'], ['groups', 'capacity'], ['groups', 'fee_cents'], ['groups', 'next_group_id'],
              ['activities', 'is_public'], ['activities', 'signup_capacity'],
              ['activity_series', 'is_public'], ['activity_series', 'capacity']] as [$table, $column]) {
        Migrator::dropColumn($table, $column);
    }
};
