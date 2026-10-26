<?php
/** Online inschrijven voor activiteiten (proeflessen, clubevenementen) via de publieke website. */
final class EventController
{
    private static function open(int $id): array
    {
        $a = Database::one(
            "SELECT a.*, t.name AS type_name FROM activities a JOIN activity_types t ON t.id = a.type_id WHERE a.id = ? AND a.signup_capacity IS NOT NULL",
            [$id]
        );
        if (!$a) {
            abort(404, 'Voor deze activiteit kan je je niet online inschrijven.');
        }
        return $a;
    }

    /** Aantal ingeschreven personen. */
    public static function taken(int $activityId): int
    {
        return (int) Database::val('SELECT COALESCE(SUM(persons), 0) FROM event_signups WHERE activity_id = ?', [$activityId]);
    }

    /** Vrije plaatsen (null = onbeperkt). */
    public static function free(array $a): ?int
    {
        return (int) $a['signup_capacity'] > 0 ? max(0, (int) $a['signup_capacity'] - self::taken((int) $a['id'])) : null;
    }

    public static function show(): void
    {
        $a = self::open((int) ($_GET['id'] ?? 0));
        render_public('events/signup', ['a' => $a, 'free' => self::free($a), 'old' => ['name' => '', 'email' => '', 'phone' => '', 'persons' => 1, 'note' => ''], 'errors' => [], 'done' => false], $a['title']);
    }

    public static function signup(): void
    {
        $a = self::open((int) input('id', '0'));
        if (input('website') !== '') { // robot
            flash('success', 'Bedankt, je inschrijving is genoteerd.');
            redirect(url('event', ['id' => $a['id']]));
        }
        $old = ['name' => input('name'), 'email' => strtolower(input('email')), 'phone' => input('phone'), 'persons' => (int) input('persons', '1'), 'note' => input('note')];
        $errors = [];
        if ($old['name'] === '' || mb_strlen($old['name']) > 120) {
            $errors[] = 'Vul je naam in (max. 120 tekens).';
        }
        if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($old['email']) > 190) {
            $errors[] = 'Vul een geldig e-mailadres in.';
        }
        if (mb_strlen($old['phone']) > 40 || mb_strlen($old['note']) > 300) {
            $errors[] = 'Telefoon (max. 40) of opmerking (max. 300 tekens) is te lang.';
        }
        if ($old['persons'] < 1 || $old['persons'] > 10) {
            $errors[] = 'Het aantal personen moet tussen 1 en 10 liggen.';
        }
        if ($a['status'] !== 'PLANNED' || $a['ends_at'] < now()) {
            $errors[] = 'Inschrijven kan niet meer voor deze activiteit.';
        }
        if (!$errors) {
            $free = self::free($a);
            if ($free !== null && $old['persons'] > $free) {
                $errors[] = $free === 0 ? 'Deze activiteit is volzet.' : "Er zijn nog maar $free plaats(en) vrij.";
            }
            if (Database::val('SELECT 1 FROM event_signups WHERE activity_id = ? AND email = ?', [$a['id'], $old['email']])) {
                $errors[] = 'Met dit e-mailadres is al ingeschreven voor deze activiteit.';
            }
            $since = date('Y-m-d H:i:s', time() - 3600);
            if ((int) Database::val('SELECT COUNT(*) FROM event_signups WHERE email = ? AND created_at > ?', [$old['email'], $since]) >= 5
                || (int) Database::val('SELECT COUNT(*) FROM event_signups WHERE created_at > ?', [$since]) >= 200) {
                $errors[] = 'Er zijn te veel inschrijvingen. Probeer het over een uur opnieuw.';
            }
        }
        if ($errors) {
            http_response_code(422);
            render_public('events/signup', ['a' => $a, 'free' => self::free($a), 'old' => $old, 'errors' => $errors, 'done' => false], $a['title']);
            return;
        }
        Database::insert(
            'INSERT INTO event_signups (activity_id, name, email, phone, persons, note, created_at) VALUES (?,?,?,?,?,?,?)',
            [$a['id'], $old['name'], $old['email'], $old['phone'] ?: null, $old['persons'], $old['note'] ?: null, now()]
        );
        $when = fmt_dt($a['starts_at']) . ($a['location'] ? ' · ' . $a['location'] : '');
        Mailer::send(
            $old['email'],
            'Bevestiging inschrijving: ' . $a['title'],
            "Dag {$old['name']},\n\nJe inschrijving voor \"{$a['title']}\" is genoteerd ({$old['persons']} persoon/personen).\n$when\n\n"
            . "Kan je toch niet komen? Stuur ons een bericht via " . Invites::baseUrl() . "/contact.\n\nHasseltse Zwemvereniging Spartacus"
        );
        $to = trim((string) config('registration.notify_email', ''));
        if ($to !== '') {
            Mailer::send($to, 'Nieuwe inschrijving: ' . $a['title'], "{$old['name']} <{$old['email']}> ({$old['persons']} pers.) heeft zich ingeschreven voor \"{$a['title']}\".\nOverzicht: " . Invites::baseUrl() . '/' . url('event_signups', ['id' => $a['id']]));
        }
        render_public('events/signup', ['a' => $a, 'free' => self::free($a), 'old' => $old, 'errors' => [], 'done' => true], $a['title']);
    }

    /** Beheer: overzicht van inschrijvingen voor één activiteit (of CSV). */
    public static function admin(): void
    {
        $a = Database::one('SELECT a.* FROM activities a WHERE a.id = ?', [(int) ($_GET['id'] ?? 0)]);
        if (!$a) {
            abort(404, 'Activiteit niet gevonden.');
        }
        $rows = Database::all('SELECT * FROM event_signups WHERE activity_id = ? ORDER BY created_at, id', [$a['id']]);
        if (($_GET['csv'] ?? '') === '1') {
            audit('EXPORT', 'event_signups', (int) $a['id']);
            Csv::send('inschrijvingen-' . $a['id'] . '.csv', ['Naam', 'E-mail', 'Telefoon', 'Personen', 'Opmerking', 'Ingeschreven op'],
                array_map(fn($r) => [$r['name'], $r['email'], $r['phone'], $r['persons'], $r['note'], $r['created_at']], $rows));
        }
        render('events/admin', ['a' => $a, 'rows' => $rows, 'taken' => self::taken((int) $a['id'])], 'Inschrijvingen: ' . $a['title']);
    }

    public static function delete(): void
    {
        $r = Database::one('SELECT * FROM event_signups WHERE id = ?', [(int) input('id', '0')]);
        if (!$r) {
            abort(404, 'Inschrijving niet gevonden.');
        }
        Database::exec('DELETE FROM event_signups WHERE id = ?', [$r['id']]);
        audit('DELETE', 'event_signup', (int) $r['id'], $r['email']);
        flash('success', 'Inschrijving verwijderd.');
        redirect(url('event_signups', ['id' => $r['activity_id']]));
    }
}
