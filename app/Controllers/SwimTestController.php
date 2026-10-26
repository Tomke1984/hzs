<?php
/**
 * Zwemtest: openbare aanvraag voor de zwemschool (met bevestiging per e-mail), wachtlijst en opvolging door het bestuur.
 * Een kind dat de test heeft gedaan, wordt vanuit de wachtlijst als lid ingeschreven via het gewone inschrijvingsproces.
 */
final class SwimTestController
{
    private const CONFIRM_DAYS = 7;

    /** Zwemmomenten waaruit ouders kiezen. Er wordt de tekst bewaard, zodat een latere wijziging oude aanvragen niet breekt. */
    public const SLOTS = [
        'di-1730' => 'Dinsdag 17u30–18u30',
        'do-1630' => 'Donderdag 16u30–17u30',
        'vr-1630' => 'Vrijdag 16u30–17u30',
        'vr-1730' => 'Vrijdag 17u30–18u30',
        'za-0800' => 'Zaterdag 8u–9u',
        'za-0900' => 'Zaterdag 9u–10u',
    ];
    public const DIPLOMAS = [
        'NONE' => 'Nog geen diploma',
        'ORKA' => 'Orka (Stad Hasselt)',
        'FRED' => 'Fred-brevet (nieuwe leerlijn zwemmen)',
        'OTHER' => 'Ander diploma',
    ];
    public const STATUSES = [
        'WAITING' => 'Wachtlijst',
        'INVITED' => 'Uitgenodigd',
        'TESTED' => 'Test gedaan',
        'ENROLLED' => 'Ingeschreven',
        'DECLINED' => 'Geweigerd',
    ];

    // ------------------------------------------------------------------ openbaar

    private static function blank(): array
    {
        return [
            'child_first_name' => '', 'child_last_name' => '', 'child_birth_date' => '', 'diploma' => 'NONE', 'experience' => '',
            'slots' => [], 'contact_name' => '', 'relationship' => '', 'contact_email' => '', 'contact_phone' => '',
            'street' => '', 'postal_code' => '', 'city' => '', 'remarks' => '',
        ];
    }

    public static function showForm(): void
    {
        render_public('swimtest/form', ['old' => self::blank(), 'errors' => []], 'Zwemtest aanvragen');
    }

    public static function submit(): void
    {
        // Verborgen veld dat enkel robots invullen: doe alsof het gelukt is.
        if (input('website') !== '') {
            render_public('swimtest/done', ['state' => 'sent', 'email' => '', 'link' => null], 'Bijna klaar');
            return;
        }

        $old = self::blank();
        foreach (['child_first_name', 'child_last_name', 'child_birth_date', 'diploma', 'experience', 'contact_name', 'relationship', 'contact_email', 'contact_phone', 'street', 'postal_code', 'city', 'remarks'] as $k) {
            $old[$k] = input($k);
        }
        $old['contact_email'] = strtolower($old['contact_email']);
        $posted = $_POST['slots'] ?? [];
        $old['slots'] = is_array($posted) ? array_values(array_filter(array_map('strval', $posted), fn($c) => isset(self::SLOTS[$c]))) : [];

        $errors = [];
        if ($old['child_first_name'] === '' || $old['child_last_name'] === '' || mb_strlen($old['child_first_name']) > 80 || mb_strlen($old['child_last_name']) > 80) {
            $errors[] = 'Voornaam en achternaam van het kind zijn verplicht (max. 80 tekens).';
        }
        if (!valid_date($old['child_birth_date']) || $old['child_birth_date'] > today()) {
            $errors[] = 'De geboortedatum van het kind is ongeldig.';
        } else {
            $age = (int) (new DateTimeImmutable($old['child_birth_date']))->diff(new DateTimeImmutable(today()))->y;
            if ($age < 4 || $age >= 18) {
                $errors[] = 'De zwemtest is bedoeld voor kinderen vanaf 5 jaar. Neem contact op met de club als je kind jonger of ouder is.';
            }
        }
        if (!isset(self::DIPLOMAS[$old['diploma']])) {
            $errors[] = 'Kies een diploma.';
        }
        if (mb_strlen($old['experience']) > 300) {
            $errors[] = 'De zwemervaring is te lang (max. 300 tekens).';
        }
        if ($old['contact_name'] === '' || mb_strlen($old['contact_name']) > 120) {
            $errors[] = 'Je naam (van de ouder of voogd) is verplicht.';
        }
        if (mb_strlen($old['relationship']) > 40) {
            $errors[] = 'De relatie tot het kind is te lang.';
        }
        if (!filter_var($old['contact_email'], FILTER_VALIDATE_EMAIL) || mb_strlen($old['contact_email']) > 190) {
            $errors[] = 'Vul een geldig e-mailadres in.';
        }
        if ($old['contact_phone'] === '' || mb_strlen($old['contact_phone']) > 40) {
            $errors[] = 'Een telefoonnummer is verplicht.';
        }
        if ($old['street'] === '' || mb_strlen($old['street']) > 120) {
            $errors[] = 'Straat en nummer zijn verplicht.';
        }
        if (!preg_match('/^[0-9A-Za-z][0-9A-Za-z \-]{1,9}$/', $old['postal_code'])) {
            $errors[] = 'Vul een geldige postcode in.';
        }
        if ($old['city'] === '' || mb_strlen($old['city']) > 80) {
            $errors[] = 'Gemeente is verplicht.';
        }
        if (mb_strlen($old['remarks']) > 500) {
            $errors[] = 'De opmerking is te lang (max. 500 tekens).';
        }
        if (input('consent') !== '1') {
            $errors[] = 'Je moet akkoord gaan met de verwerking van de gegevens.';
        }

        if (!$errors) {
            $dup = Database::val(
                "SELECT COUNT(*) FROM swimtest_requests WHERE LOWER(child_first_name) = ? AND LOWER(child_last_name) = ? AND child_birth_date = ? AND status IN ('WAITING','INVITED','TESTED')",
                [mb_strtolower($old['child_first_name']), mb_strtolower($old['child_last_name']), $old['child_birth_date']]
            );
            if ((int) $dup > 0) {
                $errors[] = 'Dit kind staat al op de wachtlijst. Je hoeft het niet opnieuw aan te melden.';
            }
        }
        // Tegen misbruik: beperk het aantal aanvragen per e-mailadres en in totaal per uur.
        if (!$errors) {
            $since = date('Y-m-d H:i:s', time() - 3600);
            if ((int) Database::val('SELECT COUNT(*) FROM swimtest_requests WHERE contact_email = ? AND created_at > ?', [$old['contact_email'], $since]) >= 5
                || (int) Database::val('SELECT COUNT(*) FROM swimtest_requests WHERE created_at > ?', [$since]) >= 100) {
                $errors[] = 'Er zijn te veel aanvragen. Probeer het over een uur opnieuw of neem contact op met de club.';
            }
        }
        if ($errors) {
            http_response_code(422);
            render_public('swimtest/form', ['old' => $old, 'errors' => array_values(array_unique($errors))], 'Zwemtest aanvragen');
            return;
        }

        // Onbevestigde aanvragen van meer dan een week oud opruimen.
        Database::exec("DELETE FROM swimtest_requests WHERE status = 'UNCONFIRMED' AND created_at < ?", [date('Y-m-d H:i:s', time() - self::CONFIRM_DAYS * 86400)]);

        $token = bin2hex(random_bytes(32));
        $slotText = implode(', ', array_map(fn($c) => self::SLOTS[$c], $old['slots']));
        Database::insert(
            'INSERT INTO swimtest_requests (status, child_first_name, child_last_name, child_birth_date, diploma, experience, availability, contact_name, contact_email, contact_phone, relationship, street, postal_code, city, remarks, consent_at, confirm_token_hash, created_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
            ['UNCONFIRMED', $old['child_first_name'], $old['child_last_name'], $old['child_birth_date'], $old['diploma'],
             $old['experience'] !== '' ? $old['experience'] : null, $slotText !== '' ? $slotText : null,
             $old['contact_name'], $old['contact_email'], $old['contact_phone'], $old['relationship'] !== '' ? $old['relationship'] : null,
             $old['street'], $old['postal_code'], $old['city'], $old['remarks'] !== '' ? $old['remarks'] : null, now(), hash('sha256', $token), now()]
        );

        $link = Invites::baseUrl() . '/?' . http_build_query(['r' => 'swimtest_confirm', 'token' => $token]);
        $child = $old['child_first_name'] . ' ' . $old['child_last_name'];
        $sent = Mailer::send(
            $old['contact_email'],
            'Bevestig de aanvraag voor de zwemtest bij HZS',
            "Dag {$old['contact_name']},\n\nBedankt voor de aanvraag van de zwemtest voor $child bij Hasseltse Zwemvereniging Spartacus.\n\n"
            . "Bevestig eerst je e-mailadres met deze link (geldig " . self::CONFIRM_DAYS . " dagen):\n$link\n\n"
            . "Daarna komt $child op de wachtlijst. We nodigen je uit voor de zwemtest zodra de club die organiseert, meestal tijdens de vakantieperiodes.\n\n"
            . "Heb je dit niet aangevraagd? Dan kun je deze mail negeren.\n\nHasseltse Zwemvereniging Spartacus"
        );
        render_public('swimtest/done', [
            'state' => $sent ? 'sent' : 'mailfail',
            'email' => $old['contact_email'],
            'link'  => Mailer::driver() === 'log' ? $link : null,
        ], 'Bijna klaar');
    }

    public static function confirm(): void
    {
        $token = (string) ($_GET['token'] ?? '');
        $req = preg_match('/^[a-f0-9]{64}$/', $token)
            ? Database::one("SELECT id, created_at FROM swimtest_requests WHERE confirm_token_hash = ? AND status = 'UNCONFIRMED'", [hash('sha256', $token)])
            : null;
        if (!$req || strtotime($req['created_at']) < time() - self::CONFIRM_DAYS * 86400) {
            http_response_code(410);
            render_public('swimtest/done', ['state' => 'invalid', 'email' => '', 'link' => null], 'Link niet geldig');
            return;
        }
        Database::exec("UPDATE swimtest_requests SET status = 'WAITING', confirmed_at = ?, status_changed_at = ?, confirm_token_hash = NULL WHERE id = ?", [now(), now(), $req['id']]);

        $notify = trim((string) config('registration.notify_email', ''));
        if ($notify !== '') {
            Mailer::send($notify, 'Nieuwe aanvraag zwemtest bij HZS', "Er staat een nieuw kind op de wachtlijst voor de zwemtest:\n" . Invites::baseUrl() . '/?' . http_build_query(['r' => 'swimtest_show', 'id' => $req['id']]) . "\n");
        }
        render_public('swimtest/done', ['state' => 'confirmed', 'email' => '', 'link' => null], 'Bedankt');
    }

    // ------------------------------------------------------------------ bestuur

    public static function index(): void
    {
        $s = (string) ($_GET['s'] ?? 'WAITING');
        if (!isset(self::STATUSES[$s])) {
            $s = 'WAITING';
        }
        $rows = Database::all(
            'SELECT * FROM swimtest_requests WHERE status = ? ORDER BY confirmed_at ASC, id ASC LIMIT 500',
            [$s]
        );
        $counts = [];
        foreach (array_keys(self::STATUSES) as $st) {
            $counts[$st] = (int) Database::val('SELECT COUNT(*) FROM swimtest_requests WHERE status = ?', [$st]);
        }
        render('swimtests/index', [
            'rows' => $rows, 's' => $s, 'counts' => $counts,
            'unconfirmed' => (int) Database::val("SELECT COUNT(*) FROM swimtest_requests WHERE status = 'UNCONFIRMED'"),
            'followUp' => self::followUp(),
        ], 'Zwemtest');
    }

    /** Dagen na de laatste statuswijziging waarna een aanvraag opvolging nodig heeft (uitgenodigd of getest, zonder vervolg). */
    public const FOLLOW_UP_DAYS = ['INVITED' => 10, 'TESTED' => 7];

    /** Kan er nog een herinnering gestuurd worden? (maximaal 3, minstens 7 dagen na de vorige) */
    public static function canRemind(array $r): bool
    {
        return in_array($r['status'], ['WAITING', 'INVITED', 'TESTED'], true) && (int) ($r['reminder_count'] ?? 0) < 3
            && (empty($r['reminder_at']) || strtotime($r['reminder_at']) < strtotime('-7 days'));
    }

    /** Aanvragen die te lang op een vervolg wachten. */
    public static function followUp(): array
    {
        $out = [];
        foreach (self::FOLLOW_UP_DAYS as $st => $days) {
            foreach (Database::all(
                'SELECT * FROM swimtest_requests WHERE status = ? AND COALESCE(status_changed_at, confirmed_at, created_at) < ? ORDER BY status_changed_at',
                [$st, date('Y-m-d H:i:s', strtotime("-$days days"))]
            ) as $r) {
                $out[] = $r;
            }
        }
        return $out;
    }

    /** Herinnering aan de ouder, passend bij de status (laat weten of het past / wil je inschrijven / ben je nog geïnteresseerd). */
    public static function remind(): void
    {
        $r = self::load((int) input('id', '0'));
        $back = url('swimtest_show', ['id' => $r['id']]);
        if (!self::canRemind($r)) {
            flash('error', 'Voor deze aanvraag kan nu geen herinnering verstuurd worden (max. 3, minstens 7 dagen na de vorige).');
            redirect(input('return') === 'list' ? url('swimtests', ['s' => $r['status']]) : $back);
        }
        $child = $r['child_first_name'] . ' ' . $r['child_last_name'];
        $intro = "Dag {$r['contact_name']},\n\n";
        $outro = "\n\nAntwoord gerust op deze mail.\n\nHasseltse Zwemvereniging Spartacus";
        if ($r['status'] === 'INVITED') {
            $subject = 'Herinnering: zwemtest bij HZS';
            $body = $intro . "We nodigden $child uit voor de zwemtest" . ($r['invite_info'] ? ":\n{$r['invite_info']}" : '.') . "\nLaat je ons weten of dit past?" . $outro;
        } elseif ($r['status'] === 'TESTED') {
            $subject = 'Inschrijven bij HZS?';
            $body = $intro . "$child heeft de zwemtest gedaan. Wil je graag inschrijven? Dat kan via " . Invites::baseUrl() . '/inschrijven' . $outro;
        } else {
            $subject = 'Staat jullie aanvraag voor de zwemtest nog open?';
            $body = $intro . "$child staat op onze wachtlijst voor de zwemtest. Is jullie interesse er nog? Laat het ons weten, dan houden we de aanvraag actief." . $outro;
        }
        $sent = Mailer::send($r['contact_email'], $subject, $body);
        if ($sent) {
            Database::exec('UPDATE swimtest_requests SET reminder_at = ?, reminder_count = reminder_count + 1 WHERE id = ?', [now(), $r['id']]);
            audit('REMIND', 'swimtest', (int) $r['id'], $r['status']);
        }
        flash($sent ? 'success' : 'error', $sent ? 'Herinnering verstuurd naar ' . $r['contact_email'] . '.' : 'De herinnering kon niet verstuurd worden.');
        redirect(input('return') === 'list' ? url('swimtests', ['s' => $r['status']]) : $back);
    }

    public static function show(): void
    {
        $r = self::load((int) ($_GET['id'] ?? 0));
        $position = null;
        if ($r['status'] === 'WAITING') {
            $position = 1 + (int) Database::val(
                "SELECT COUNT(*) FROM swimtest_requests WHERE status = 'WAITING' AND (confirmed_at < ? OR (confirmed_at = ? AND id < ?))",
                [$r['confirmed_at'], $r['confirmed_at'], $r['id']]
            );
        }
        $siblings = Database::all(
            "SELECT id, child_first_name, child_last_name, status FROM swimtest_requests WHERE contact_email = ? AND id <> ? AND status <> 'UNCONFIRMED' ORDER BY id",
            [$r['contact_email'], $r['id']]
        );
        $by = $r['status_changed_by'] ? Database::val('SELECT display_name FROM users WHERE id = ?', [$r['status_changed_by']]) : null;
        render('swimtests/show', ['r' => $r, 'position' => $position, 'siblings' => $siblings, 'by' => $by], 'Aanvraag zwemtest');
    }

    public static function setStatus(): void
    {
        $r = self::load((int) input('id', '0'));
        $back = url('swimtest_show', ['id' => $r['id']]);
        $status = input('status');
        if (!isset(self::STATUSES[$status])) {
            flash('error', 'Kies een geldige status.');
            redirect($back);
        }
        if ($r['status'] === 'ENROLLED') {
            flash('error', 'Dit kind is al als lid ingeschreven; de status kan niet meer gewijzigd worden.');
            redirect($back);
        }
        if ($status === 'ENROLLED') {
            flash('error', 'Gebruik de knop "Als lid inschrijven" om een kind in te schrijven.');
            redirect($back);
        }
        $info = input('invite_info');
        $reason = input('decline_reason');
        $notify = input('notify') === '1';
        if (mb_strlen($info) > 300 || mb_strlen($reason) > 500) {
            flash('error', 'De tekst is te lang.');
            redirect($back);
        }
        if ($status === 'INVITED' && $notify && $info === '') {
            flash('error', 'Vul in wanneer en waar de zwemtest plaatsvindt, of vink het versturen van de mail uit.');
            redirect($back);
        }

        Database::exec(
            'UPDATE swimtest_requests SET status = ?, status_changed_at = ?, status_changed_by = ?, invite_info = ?, decline_reason = ? WHERE id = ?',
            [$status, now(), Auth::user()['id'], $status === 'INVITED' && $info !== '' ? $info : $r['invite_info'],
             $status === 'DECLINED' && $reason !== '' ? $reason : $r['decline_reason'], $r['id']]
        );
        audit('STATUS', 'swimtest', (int) $r['id'], $status);

        $msg = 'Status gewijzigd naar ' . mb_strtolower(self::STATUSES[$status]) . '.';
        $child = $r['child_first_name'] . ' ' . $r['child_last_name'];
        if ($status !== $r['status'] && $notify && in_array($status, ['INVITED', 'DECLINED', 'TESTED'], true)) {
            $body = $status === 'TESTED'
                ? "Dag {$r['contact_name']},\n\nBedankt voor de zwemtest van $child bij Hasseltse Zwemvereniging Spartacus. Wil je graag inschrijven? Dat kan via " . Invites::baseUrl() . "/inschrijven\n\nVragen? Antwoord gerust op deze mail.\n\nHasseltse Zwemvereniging Spartacus"
                : ($status === 'INVITED'
                ? "Dag {$r['contact_name']},\n\n$child is uitgenodigd voor de zwemtest bij Hasseltse Zwemvereniging Spartacus.\n\n$info\n\nKan je kind niet komen? Laat het ons dan zo snel mogelijk weten.\n\nHasseltse Zwemvereniging Spartacus"
                : "Dag {$r['contact_name']},\n\nHelaas kunnen we $child niet opnemen in de zwemschool van Hasseltse Zwemvereniging Spartacus."
                    . ($reason !== '' ? "\n\nReden: $reason" : '') . "\n\nHeb je vragen? Neem gerust contact op met de club.\n\nHasseltse Zwemvereniging Spartacus");
            $sent = Mailer::send($r['contact_email'], ['INVITED' => 'Uitnodiging voor de zwemtest bij HZS', 'TESTED' => 'Je zwemtest bij HZS: inschrijven?', 'DECLINED' => 'Je aanvraag voor de zwemtest bij HZS'][$status], $body);
            $msg .= $sent ? ' De ouder is verwittigd per mail.' : ' De mail aan de ouder kon niet verstuurd worden.';
        }
        flash('success', $msg);
        redirect($back);
    }

    public static function saveNote(): void
    {
        $r = self::load((int) input('id', '0'));
        $note = input('internal_note');
        if (mb_strlen($note) > 500) {
            flash('error', 'De notitie is te lang (max. 500 tekens).');
            redirect(url('swimtest_show', ['id' => $r['id']]));
        }
        Database::exec('UPDATE swimtest_requests SET internal_note = ? WHERE id = ?', [$note !== '' ? $note : null, $r['id']]);
        flash('success', 'Notitie bewaard.');
        redirect(url('swimtest_show', ['id' => $r['id']]));
    }

    /** Zet het kind door naar een gewone inschrijving; daar kiest het bestuur de groep en keurt het goed. */
    public static function enroll(): void
    {
        $r = self::load((int) input('id', '0'));
        $back = url('swimtest_show', ['id' => $r['id']]);
        if ($r['status'] === 'ENROLLED' || $r['registration_id']) {
            flash('error', 'Dit kind is al doorgezet naar een inschrijving.');
            redirect($back);
        }
        if ($r['status'] === 'DECLINED') {
            flash('error', 'Dit kind is geweigerd. Zet de status eerst terug.');
            redirect($back);
        }
        $parts = ['Via zwemtest: diploma ' . (self::DIPLOMAS[$r['diploma']] ?? $r['diploma'])];
        if ($r['experience']) {
            $parts[] = 'ervaring: ' . $r['experience'];
        }
        if ($r['remarks']) {
            $parts[] = 'opmerking: ' . $r['remarks'];
        }
        $remarks = mb_substr(implode('; ', $parts), 0, 500);

        $regId = Database::tx(function () use ($r, $remarks) {
            $id = Database::insert(
                'INSERT INTO registrations (status, applicant_type, contact_name, contact_email, contact_phone, relationship, street, postal_code, city, remarks, consent_at, created_at, confirmed_at)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',
                ['PENDING', 'PARENT', $r['contact_name'], $r['contact_email'], $r['contact_phone'], $r['relationship'], $r['street'],
                 $r['postal_code'], $r['city'], $remarks, $r['consent_at'], now(), now()]
            );
            Database::exec(
                'INSERT INTO registration_persons (registration_id, first_name, last_name, birth_date) VALUES (?,?,?,?)',
                [$id, $r['child_first_name'], $r['child_last_name'], $r['child_birth_date']]
            );
            Database::exec(
                "UPDATE swimtest_requests SET status = 'ENROLLED', registration_id = ?, status_changed_at = ?, status_changed_by = ? WHERE id = ?",
                [$id, now(), Auth::user()['id'], $r['id']]
            );
            return $id;
        });
        audit('ENROLL', 'swimtest', (int) $r['id'], 'inschrijving ' . $regId);
        flash('success', 'Doorgezet naar een inschrijving. Kies hier de groep en keur de inschrijving goed om het lid en het account aan te maken.');
        redirect(url('registration', ['id' => $regId]));
    }

    private static function load(int $id): array
    {
        $r = $id ? Database::one('SELECT * FROM swimtest_requests WHERE id = ?', [$id]) : null;
        if (!$r || $r['status'] === 'UNCONFIRMED') {
            abort(404, 'Deze aanvraag bestaat niet.');
        }
        return $r;
    }
}
