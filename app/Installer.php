<?php
final class Installer
{
    public const PERMISSIONS = [
        'members.read'    => 'Leden bekijken (binnen scope)',
        'members.write'   => 'Leden toevoegen en wijzigen',
        'members.contact' => 'Contactgegevens van leden zien',
        'groups.read'     => 'Groepen bekijken (binnen scope)',
        'groups.write'    => 'Groepen en groepslidmaatschappen beheren',
        'activities.read' => 'Kalender en activiteiten bekijken',
        'activities.write' => 'Activiteiten aanmaken en wijzigen',
        'attendance.read' => 'Aanwezigheden bekijken (binnen scope)',
        'attendance.write' => 'Aanwezigheden registreren',
        'registrations.manage' => 'Inschrijvingen beoordelen (goedkeuren of afwijzen)',
        'content.manage'  => 'Website beheren (nieuws, pagina\'s, afbeeldingen)',
        'stats.read'      => 'Statistieken over bezoekers en verkeer bekijken (enkel beheerders)',
        'users.manage'    => 'Gebruikers, rollen en koppelingen (ouder-kind, trainer-groep) beheren',
        'mail.send'       => 'Berichten sturen naar groepen, blokken of deelnemers (binnen scope)',
        'export.data'     => 'Gegevens exporteren naar CSV (leden, aanwezigheden, aanmeldingen)',
        'fees.manage'     => 'Lidgeld en seizoenen beheren',
        'members.medical' => 'Noodcontact en medische opmerkingen zien (binnen scope)',
        'audit.read'      => 'Auditlog bekijken (enkel beheerders)',
    ];

    public const ROLES = [
        'ADMIN'   => ['Beheerder', ['*']],
        'BOARD'   => ['Bestuur', ['members.read', 'members.write', 'members.contact', 'groups.read', 'groups.write',
                                  'activities.read', 'activities.write', 'attendance.read', 'attendance.write', 'registrations.manage', 'content.manage',
                                  'mail.send', 'export.data', 'fees.manage', 'members.medical']],
        'TRAINER' => ['Trainer', ['members.read', 'groups.read', 'activities.read', 'attendance.read', 'attendance.write', 'mail.send', 'members.medical']],
        'SWIMMER' => ['Zwemmer', ['members.read', 'activities.read', 'attendance.read']],
        'PARENT'  => ['Ouder', ['members.read', 'activities.read', 'attendance.read']],
    ];

    public static function createSchema(): void
    {
        $sql = file_get_contents(APP_ROOT . '/db/schema.sql');
        $mysql = Database::driver() === 'mysql';
        $sql = str_replace(
            ['{{PK}}', '{{ENGINE}}'],
            [
                $mysql ? 'INT AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT',
                $mysql ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '',
            ],
            $sql
        );
        foreach (array_filter(array_map('trim', explode(";\n", $sql))) as $stmt) {
            Database::pdo()->exec($stmt);
        }
    }

    /**
     * Vult ontbrekende permissies en rol-koppelingen aan in een bestaande database
     * (bv. na een update die een nieuwe permissie toevoegt). Doet niets als alles er al is.
     */
    /** Eenvoudige, draagbare schema-upgrades voor bestaande databases (SQLite en MySQL). */
    public static function migrate(): void
    {
        foreach (['user_tokens', 'registrations', 'activity_series', 'posts', 'pages', 'media', 'swimtest_requests', 'contact_messages', 'login_attempts', 'activity_series_skips', 'member_series', 'page_views'] as $table) {
            try {
                Database::val("SELECT 1 FROM $table LIMIT 1");
            } catch (Throwable $e) {
                self::createSchema(); // alle tabellen zijn CREATE TABLE IF NOT EXISTS: enkel wat ontbreekt wordt aangemaakt
                break;
            }
        }
        try {
            Database::val('SELECT series_id FROM activities LIMIT 1');
        } catch (Throwable $e) {
            Database::exec('ALTER TABLE activities ADD COLUMN series_id INT NULL');
            Database::exec('ALTER TABLE activities ADD COLUMN is_custom TINYINT NOT NULL DEFAULT 0');
            Database::exec('ALTER TABLE activities ADD COLUMN status_note VARCHAR(200) NULL');
        }
        try {
            Database::val('SELECT goals FROM `groups` LIMIT 1');
        } catch (Throwable $e) {
            Database::exec('ALTER TABLE `groups` ADD COLUMN goals TEXT NULL');
        }
        // Doelstellingen horen bij de groep: de losse pagina's "Doelstellingen – <groep>" verhuizen naar de groep zelf.
        foreach (Database::all("SELECT id, title, body FROM pages WHERE slug LIKE 'doelstellingen-%'") as $dp) {
            $name = trim((string) preg_replace('/^Doelstellingen\s*[–-]\s*/u', '', (string) $dp['title']));
            $g = Database::one('SELECT id, goals FROM `groups` WHERE name = ?', [$name]);
            if ($g) {
                if ($g['goals'] === null || $g['goals'] === '') {
                    Database::exec('UPDATE `groups` SET goals = ? WHERE id = ?', [$dp['body'], $g['id']]);
                }
                Database::exec('DELETE FROM pages WHERE id = ?', [$dp['id']]);
            }
        }
        if (!Database::val("SELECT COUNT(*) FROM pages WHERE slug LIKE 'doelstellingen-%'")) {
            $intro = 'Per niveau werken de zwemmers naar vaste doelstellingen. Doorstromen gebeurt op basis van een evaluatie door de trainers.';
            Database::exec("UPDATE pages SET body = ? WHERE slug = 'doelstellingen' AND body LIKE '%doelstellingen-%'", [$intro]);
        }
        // Typfout in het gsm-nummer van de API (+32 96 → +32 496).
        Database::exec("UPDATE pages SET body = REPLACE(body, 'gsm +32 96 32 92 52', 'gsm +32 496 32 92 52') WHERE slug = 'ombudsman' AND body LIKE '%gsm +32 96 32 92 52%'");
        // De uitleg over de zwemtest verwijst naar het aparte zwemtestformulier.
        Database::exec(
            "UPDATE pages SET body = REPLACE(body, '[inschrijfformulier](?r=register)', '[formulier voor de zwemtest](?r=swimtest)') WHERE slug = 'inschrijven-zwemtest' AND body LIKE '%(?r=register)%'"
        );
        // De oorspronkelijke contactpagina (nog onaangepast) krijgt de contactgegevens van zwemschool en competitie.
        Database::exec(
            "UPDATE pages SET body = ? WHERE slug = 'contact' AND body LIKE '%Vragen over de **zwemschool** gaan naar de secretaris%'",
            [trim(SiteSeed::contactBody())]
        );
        // Het oorspronkelijke privacybeleid (nog onaangepast, overname uit 2018) krijgt de nieuwe AVG-tekst.
        Database::exec(
            "UPDATE pages SET body = ?, updated_at = ? WHERE slug = 'privacybeleid' AND body LIKE '%24 mei 2018%' AND body NOT LIKE '%26 oktober 2026%'",
            [SiteSeed::privacyBody(), now()]
        );
        // Groepslidmaatschap = enkel de huidige groep: einddatums/historiek en dubbele koppelingen opruimen.
        if (Database::val('SELECT COUNT(*) FROM group_memberships WHERE end_date IS NOT NULL') > 0) {
            Database::exec('DELETE FROM group_memberships WHERE end_date IS NOT NULL');
        }
        if (Database::one('SELECT member_id FROM group_memberships GROUP BY member_id HAVING COUNT(*) > 1 LIMIT 1')) {
            Database::exec('DELETE FROM group_memberships WHERE id NOT IN (SELECT keep_id FROM (SELECT MAX(id) AS keep_id FROM group_memberships GROUP BY member_id) k)');
        }
        // Trainertoewijzing = enkel de huidige groepen: beëindigde en dubbele rijen opruimen.
        Database::exec('DELETE FROM group_trainers WHERE end_date IS NOT NULL');
        if (Database::one('SELECT user_id FROM group_trainers GROUP BY user_id, group_id HAVING COUNT(*) > 1 LIMIT 1')) {
            Database::exec('DELETE FROM group_trainers WHERE id NOT IN (SELECT keep_id FROM (SELECT MAX(id) AS keep_id FROM group_trainers GROUP BY user_id, group_id) k)');
        }
        try {
            Database::val('SELECT color FROM `groups` LIMIT 1');
        } catch (Throwable $e) {
            Database::exec('ALTER TABLE `groups` ADD COLUMN color INT NULL');
        }
        ensure_group_colors();
        try {
            Database::val('SELECT weekday FROM member_series LIMIT 1');
        } catch (Throwable $e) {
            try {
                Database::exec('DROP TABLE member_series'); // nieuw, nog ongebruikt: opnieuw aanmaken met weekdag per keuze
            } catch (Throwable $e2) {
            }
            self::createSchema();
        }
        try {
            Database::val('SELECT is_public FROM activities LIMIT 1');
        } catch (Throwable $e) {
            Database::exec('ALTER TABLE activities ADD COLUMN is_public TINYINT NOT NULL DEFAULT 0');
            Database::exec('ALTER TABLE activity_series ADD COLUMN is_public TINYINT NOT NULL DEFAULT 0');
        }
        try {
            Database::val('SELECT street FROM members LIMIT 1');
        } catch (Throwable $e) {
            Database::exec('ALTER TABLE members ADD COLUMN street VARCHAR(120) NULL');
            Database::exec('ALTER TABLE members ADD COLUMN postal_code VARCHAR(10) NULL');
            Database::exec('ALTER TABLE members ADD COLUMN city VARCHAR(80) NULL');
        }
        try {
            Database::val('SELECT category FROM `groups` LIMIT 1');
        } catch (Throwable $e) {
            Database::exec("ALTER TABLE `groups` ADD COLUMN category VARCHAR(20) NOT NULL DEFAULT 'SWIM_SCHOOL'");
        }
        self::migrateV11();
    }

    /** Schemaversie van deze release; de sessie onthoudt dat de controle gedaan is, zodat ze niet bij elk verzoek loopt. */
    public const SCHEMA = 11;

    /** Nieuwe tabellen en kolommen van v0.11 (rapporten, lidgeld, wachtlijst, resultaten, nieuwsbrief, galerij, 2FA). */
    private static function migrateV11(): void
    {
        if ((int) ($_SESSION['schema'] ?? 0) >= self::SCHEMA) {
            return;
        }
        foreach (['absence_reports', 'broadcasts', 'seasons', 'dues', 'group_waitlist', 'meet_entries', 'results', 'event_signups', 'subscribers', 'gallery_albums', 'gallery_photos', 'photo_members'] as $table) {
            try {
                Database::val("SELECT 1 FROM $table LIMIT 1");
            } catch (Throwable $e) {
                self::createSchema();
                break;
            }
        }
        $cols = [
            ['users', 'ical_token', 'VARCHAR(40) NULL'], ['users', 'totp_secret', 'VARCHAR(64) NULL'], ['users', 'totp_enabled', 'TINYINT NOT NULL DEFAULT 0'],
            ['members', 'status_reason', 'VARCHAR(60) NULL'], ['members', 'status_date', 'VARCHAR(10) NULL'],
            ['members', 'emergency_name', 'VARCHAR(120) NULL'], ['members', 'emergency_phone', 'VARCHAR(40) NULL'],
            ['members', 'medical_note', 'VARCHAR(500) NULL'], ['members', 'photo_consent', 'TINYINT NOT NULL DEFAULT 0'],
            ['groups', 'capacity', 'INT NULL'], ['groups', 'fee_cents', 'INT NULL'], ['groups', 'next_group_id', 'INT NULL'],
            ['activity_series', 'capacity', 'INT NULL'], ['activities', 'signup_capacity', 'INT NULL'],
            ['posts', 'newsletter_sent_at', 'VARCHAR(19) NULL'],
            ['swimtest_requests', 'reminder_at', 'VARCHAR(19) NULL'], ['swimtest_requests', 'reminder_count', 'INT NOT NULL DEFAULT 0'],
        ];
        foreach ($cols as [$table, $col, $def]) {
            try {
                Database::val("SELECT $col FROM " . ($table === 'groups' ? '`groups`' : $table) . ' LIMIT 1');
            } catch (Throwable $e) {
                Database::exec('ALTER TABLE ' . ($table === 'groups' ? '`groups`' : $table) . " ADD COLUMN $col $def");
            }
        }
        // Privacytekst: enkel de ongewijzigde tekst van vóór v0.11 wordt aangevuld; een door de club aangepaste tekst blijft staan.
        try {
            $pg = Database::one("SELECT id, body FROM pages WHERE slug = 'privacybeleid'");
            $norm = fn(string $t) => trim(str_replace("\r\n", "\n", $t));
            if ($pg && $norm((string) $pg['body']) === $norm(SiteSeed::privacyBodyPrev())) {
                Database::exec('UPDATE pages SET body = ?, updated_at = ? WHERE id = ?', [SiteSeed::privacyBody(), now(), $pg['id']]);
            }
        } catch (Throwable $e) {
            // pagina's bestaan nog niet: niets te doen
        }
        $_SESSION['schema'] = self::SCHEMA;
    }

    public static function syncReferenceData(): void
    {
        if ((int) Database::val('SELECT COUNT(*) FROM roles') === 0) {
            return; // nog niet geïnstalleerd
        }
        $permCount = (int) Database::val('SELECT COUNT(*) FROM permissions');
        $mapCount = (int) Database::val('SELECT COUNT(*) FROM role_permissions');
        $expectedMap = 0;
        foreach (self::ROLES as [, $perms]) {
            $expectedMap += $perms === ['*'] ? count(self::PERMISSIONS) : count($perms);
        }
        $roleCount = (int) Database::val('SELECT COUNT(*) FROM roles');
        if ($roleCount >= count(self::ROLES) && $permCount >= count(self::PERMISSIONS) && $mapCount >= $expectedMap) {
            return; // snelle weg: niets te doen
        }

        Database::tx(function () {
            foreach (self::PERMISSIONS as $code => $name) {
                if (!Database::val('SELECT id FROM permissions WHERE code = ?', [$code])) {
                    Database::exec('INSERT INTO permissions (code, name) VALUES (?, ?)', [$code, $name]);
                }
            }
            foreach (self::ROLES as $roleCode => [, $perms]) {
                $roleId = Database::val('SELECT id FROM roles WHERE code = ?', [$roleCode]);
                if (!$roleId) {
                    $roleId = Database::insert('INSERT INTO roles (code, name) VALUES (?, ?)', [$roleCode, self::ROLES[$roleCode][0]]);
                }
                $list = $perms === ['*'] ? array_keys(self::PERMISSIONS) : $perms;
                foreach ($list as $p) {
                    $permId = Database::val('SELECT id FROM permissions WHERE code = ?', [$p]);
                    if ($permId && !Database::val('SELECT 1 FROM role_permissions WHERE role_id = ? AND permission_id = ?', [$roleId, $permId])) {
                        Database::exec('INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)', [$roleId, $permId]);
                    }
                }
            }
        });
    }

    public static function seedReferenceData(): void
    {
        Database::tx(function () {
            foreach (self::PERMISSIONS as $code => $name) {
                Database::exec('INSERT INTO permissions (code, name) VALUES (?, ?)', [$code, $name]);
            }
            foreach (self::ROLES as $code => [$name, $perms]) {
                $roleId = Database::insert('INSERT INTO roles (code, name) VALUES (?, ?)', [$code, $name]);
                $list = $perms === ['*'] ? array_keys(self::PERMISSIONS) : $perms;
                foreach ($list as $p) {
                    Database::exec(
                        'INSERT INTO role_permissions (role_id, permission_id) SELECT ?, id FROM permissions WHERE code = ?',
                        [$roleId, $p]
                    );
                }
            }
            foreach (['TRAINING' => 'Training', 'COMPETITION' => 'Wedstrijd', 'CLUB' => 'Clubactiviteit'] as $code => $name) {
                Database::exec('INSERT INTO activity_types (code, name) VALUES (?, ?)', [$code, $name]);
            }
        });
    }

    public static function createUser(string $email, string $password, string $name, string $roleCode, ?int $memberId = null): int
    {
        $id = Database::insert(
            'INSERT INTO users (email, password_hash, display_name, member_id, is_active, created_at) VALUES (?,?,?,?,1,?)',
            [strtolower($email), password_hash($password, PASSWORD_DEFAULT), $name, $memberId, now()]
        );
        Database::exec('INSERT INTO user_roles (user_id, role_id) SELECT ?, id FROM roles WHERE code = ?', [$id, $roleCode]);
        return $id;
    }

    /** Voorbeelddata om alle rollen lokaal te kunnen testen. Wachtwoord voor alle demo-accounts: Demo1234! */
    public static function seedDemo(): void
    {
        Database::tx(function () {
            $today = today();
            $ts = now();
            $groups = [];
            foreach ([
                'Beginners'      => ['Eerste stappen in het water', 'SWIM_SCHOOL'],
                'Gevorderden'    => ['Techniek en conditie', 'SWIM_SCHOOL'],
                'Wedstrijdploeg' => ['Training voor competitie', 'COMPETITION'],
            ] as $name => [$desc, $cat]) {
                $groups[$name] = Database::insert('INSERT INTO `groups` (name, description, category, is_active) VALUES (?,?,?,1)', [$name, $desc, $cat]);
            }
            ensure_group_colors();

            $names = [
                ['Emma', 'Peeters'], ['Lucas', 'Janssens'], ['Mila', 'Maes'], ['Noah', 'Jacobs'],
                ['Olivia', 'Mertens'], ['Liam', 'Willems'], ['Louise', 'Claes'], ['Finn', 'Goossens'],
                ['Julie', 'Wouters'], ['Arthur', 'De Smet'], ['Nora', 'Vermeulen'], ['Jules', 'Aerts'],
            ];
            $memberIds = [];
            foreach ($names as $i => [$first, $last]) {
                $memberIds[] = Database::insert(
                    "INSERT INTO members (member_no, first_name, last_name, birth_date, email, phone, status, created_at)
                     VALUES (?,?,?,?,?,?,'ACTIVE',?)",
                    [sprintf('HZS-%03d', $i + 1), $first, $last, (2008 + $i % 6) . '-0' . (1 + $i % 9) . '-1' . ($i % 9), strtolower($first) . '@voorbeeld.be', '0470 00 00 ' . sprintf('%02d', $i), $ts]
                );
            }
            // Eén inactief lid om het gedrag van inactieve leden te kunnen testen.
            $inactive = Database::insert(
                "INSERT INTO members (member_no, first_name, last_name, status, created_at) VALUES ('HZS-099','Oud','Lid','INACTIVE',?)",
                [$ts]
            );
            Database::exec('INSERT INTO group_memberships (member_id, group_id, start_date) VALUES (?,?,?)', [$inactive, $groups['Beginners'], '2024-09-01']);

            $assign = [
                'Beginners'      => array_slice($memberIds, 0, 4),
                'Gevorderden'    => array_slice($memberIds, 4, 4),
                'Wedstrijdploeg' => array_slice($memberIds, 8, 4),
            ];
            foreach ($assign as $g => $ids) {
                foreach ($ids as $mid) {
                    Database::exec('INSERT INTO group_memberships (member_id, group_id, start_date) VALUES (?,?,?)', [$mid, $groups[$g], '2025-09-01']);
                }
            }

            $trainer = self::createUser('trainer@demo.local', 'Demo1234!', 'Tina Trainer', 'TRAINER');
            Database::exec('INSERT INTO group_trainers (user_id, group_id, start_date) VALUES (?,?,?)', [$trainer, $groups['Beginners'], '2025-09-01']);
            Database::exec('INSERT INTO group_trainers (user_id, group_id, start_date) VALUES (?,?,?)', [$trainer, $groups['Gevorderden'], '2025-09-01']);

            // De demo-trainer zwemt zelf ook mee in de Wedstrijdploeg: rollen Trainer + Zwemmer, gekoppeld aan een eigen lid.
            $trainerMember = Database::insert(
                "INSERT INTO members (member_no, first_name, last_name, birth_date, email, status, created_at) VALUES ('HZS-098','Tina','Trainer','1988-04-12','tina@voorbeeld.be','ACTIVE',?)",
                [$ts]
            );
            Database::exec('UPDATE users SET member_id = ? WHERE id = ?', [$trainerMember, $trainer]);
            Database::exec(
                'INSERT INTO user_roles (user_id, role_id) VALUES (?, (SELECT id FROM roles WHERE code = ?))',
                [$trainer, 'SWIMMER']
            );
            Database::exec('INSERT INTO group_memberships (member_id, group_id, start_date) VALUES (?,?,?)', [$trainerMember, $groups['Wedstrijdploeg'], '2025-09-01']);

            self::createUser('bestuur@demo.local', 'Demo1234!', 'Bea Bestuur', 'BOARD');
            self::createUser('zwemmer@demo.local', 'Demo1234!', 'Emma Peeters', 'SWIMMER', $memberIds[0]);
            $parent = self::createUser('ouder@demo.local', 'Demo1234!', 'Ouder van Lucas', 'PARENT');
            Database::exec('INSERT INTO guardian_links (user_id, member_id, relationship) VALUES (?,?,?)', [$parent, $memberIds[1], 'ouder']);

            // Elk ander minderjarig demo-lid krijgt ook een ouderaccount (regel: jonger dan 18 = minstens één ouder of voogd).
            foreach ($memberIds as $mid) {
                $m = Database::one('SELECT first_name, last_name, birth_date FROM members WHERE id = ?', [$mid]);
                if (!Guardianship::isMinor($m['birth_date']) || Database::val('SELECT COUNT(*) FROM guardian_links WHERE member_id = ?', [$mid])) {
                    continue;
                }
                $slug = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($m['last_name'])), '-');
                $p = self::createUser('ouder.' . $slug . '@demo.local', 'Demo1234!', 'Ouder ' . $m['last_name'], 'PARENT');
                Database::exec('INSERT INTO guardian_links (user_id, member_id, relationship) VALUES (?,?,?)', [$p, $mid, 'ouder']);
            }

            $types = array_column(Database::all('SELECT id, code FROM activity_types'), 'id', 'code');
            $mk = function (string $type, string $title, string $loc, string $start, int $minutes, string $audience, array $gids) use ($types, $ts) {
                $end = (new DateTime($start))->modify("+$minutes minutes")->format('Y-m-d H:i:00');
                $id = Database::insert(
                    "INSERT INTO activities (type_id, title, description, location, starts_at, ends_at, audience, status, created_at)
                     VALUES (?,?,?,?,?,?,?,'PLANNED',?)",
                    [$types[$type], $title, null, $loc, $start, $end, $audience, $ts]
                );
                foreach ($gids as $g) {
                    Database::exec('INSERT INTO activity_groups (activity_id, group_id) VALUES (?,?)', [$id, $g]);
                }
            };

            $monday = new DateTime('monday this week');
            for ($w = -1; $w <= 4; $w++) {
                $d = (clone $monday)->modify(($w * 7) . ' days');
                $mk('TRAINING', 'Training Beginners', 'Zwembad Alverberg', $d->format('Y-m-d') . ' 18:00:00', 60, 'GROUPS', [$groups['Beginners']]);
                $mk('TRAINING', 'Training Gevorderden', 'Zwembad Alverberg', (clone $d)->modify('+2 days')->format('Y-m-d') . ' 19:00:00', 90, 'GROUPS', [$groups['Gevorderden']]);
                $mk('TRAINING', 'Training Wedstrijdploeg', 'Zwembad Alverberg', (clone $d)->modify('+3 days')->format('Y-m-d') . ' 06:30:00', 90, 'GROUPS', [$groups['Wedstrijdploeg']]);
            }
            $mk('COMPETITION', 'Clubkampioenschap', 'Zwembad Alverberg', (clone $monday)->modify('+12 days')->format('Y-m-d') . ' 14:00:00', 180, 'CLUB', []);
            $mk('COMPETITION', 'Interclubwedstrijd', 'Provinciaal Zwembad', (clone $monday)->modify('+19 days')->format('Y-m-d') . ' 09:00:00', 240, 'GROUPS', [$groups['Gevorderden'], $groups['Wedstrijdploeg']]);
            $mk('CLUB', 'Clubfeest', 'Clubhuis', (clone $monday)->modify('+25 days')->format('Y-m-d') . ' 19:00:00', 180, 'CLUB', []);
        });
    }
}
