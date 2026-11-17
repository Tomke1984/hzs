<?php
final class ActivityController
{
    public static function calendar(): void
    {
        $ym = (string) ($_GET['m'] ?? date('Y-m'));
        $first = DateTimeImmutable::createFromFormat('!Y-m', $ym);
        if ($first === false || $first->format('Y-m') !== $ym) {
            $first = new DateTimeImmutable('first day of this month');
        }
        $next = $first->modify('+1 month');

        [$sql, $params] = Scope::activityFilter('a');
        $clubWide = Scope::groupIds() === null;
        if (!$clubWide) {
            // Wedstrijden van andere groepen zijn zichtbaar (in grijs), de rest enkel voor de eigen groepen.
            $sql = "($sql OR t.code = 'COMPETITION')";
        }
        [$cat, $gid] = GroupController::readFilter();
        if ($cat !== '' || $gid) {
            // Activiteiten voor de hele club blijven altijd zichtbaar; groepsactiviteiten worden gefilterd.
            $sql .= " AND (a.audience = 'CLUB' OR EXISTS (SELECT 1 FROM activity_groups fag JOIN `groups` fg ON fg.id = fag.group_id WHERE fag.activity_id = a.id";
            if ($cat !== '') {
                $sql .= ' AND fg.category = ?';
                $params[] = $cat;
            }
            if ($gid) {
                $sql .= ' AND fg.id = ?';
                $params[] = $gid;
            }
            $sql .= '))';
        }
        // Filter op soort activiteit (training, wedstrijd, ...)
        $types = Database::all('SELECT code, name FROM activity_types ORDER BY id');
        $type = in_array($_GET['t'] ?? '', array_column($types, 'code'), true) ? (string) $_GET['t'] : '';
        if ($type !== '') {
            $sql .= ' AND t.code = ?';
            $params[] = $type;
        }
        $rows = Database::all(
            "SELECT a.id, a.title, a.audience, a.starts_at, a.ends_at, a.status, a.status_note, a.location, t.code AS type_code, t.name AS type_name
             FROM activities a JOIN activity_types t ON t.id = a.type_id
             WHERE a.starts_at >= ? AND a.starts_at < ? AND $sql
             ORDER BY a.starts_at",
            array_merge([$first->format('Y-m-d 00:00:00'), $next->format('Y-m-d 00:00:00')], $params)
        );

        $mine = Scope::myGroupIds();
        $byDay = [];
        $evGroups = [];
        if ($rows) {
            $ids = array_map('intval', array_column($rows, 'id'));
            foreach (Database::all('SELECT ag.activity_id, g.id, g.name, g.color FROM activity_groups ag JOIN `groups` g ON g.id = ag.group_id WHERE ag.activity_id IN (' . Scope::in($ids) . ') ORDER BY g.name', $ids) as $g) {
                $evGroups[(int) $g['activity_id']][] = ['id' => (int) $g['id'], 'name' => $g['name'], 'color' => (int) ($g['color'] ?? 0)];
            }
        }
        $legend = [];
        foreach ($rows as $r) {
            // "mine": hoort bij jou (training/clubactiviteit altijd; wedstrijd enkel voor de eigen groepen)
            $r['mine'] = true;
            if ($r['type_code'] === 'COMPETITION' && $r['audience'] !== 'CLUB' && ($mine || !$clubWide)) {
                // groepen van de activiteit zijn al geladen ($evGroups): geen extra query per wedstrijd
                $r['mine'] = $mine && array_intersect(array_column($evGroups[(int) $r['id']] ?? [], 'id'), $mine) !== [];
            }
            $r['groups'] = $evGroups[(int) $r['id']] ?? [];
            $r['gnames'] = $r['audience'] === 'CLUB' ? 'Hele club' : implode(', ', array_column($r['groups'], 'name'));
            $r['gcolor'] = $r['audience'] === 'CLUB' || !$r['groups'] ? 'club' : (string) $r['groups'][0]['color'];
            if ($r['audience'] === 'CLUB' || !$r['groups']) {
                $legend['club'] = ['Hele club', 'club'];
            } else {
                $legend[$r['groups'][0]['name']] = [$r['groups'][0]['name'], (string) $r['groups'][0]['color']];
            }
            $r['open'] = $clubWide || $r['mine'] || $r['type_code'] !== 'COMPETITION';
            $byDay[substr($r['starts_at'], 0, 10)][] = $r;
        }

        // Trainersplanning: tekorten aan trainers in rood (enkel voor wie de trainersplanning ziet).
        $short = [];
        if ($rows && Coverage::canView()) {
            foreach (Coverage::list($first->format('Y-m-d 00:00:00'), $next->format('Y-m-d 00:00:00'), array_map('intval', array_column($rows, 'id'))) as $c) {
                if ($c['missing'] > 0 && $c['ends_at'] > now()) {
                    $short[(int) $c['id']] = $c['present'] . '/' . $c['required'];
                }
            }
            foreach ($byDay as &$evs) {
                foreach ($evs as &$ev) {
                    $ev['short'] = $short[(int) $ev['id']] ?? null;
                }
                unset($ev);
            }
            unset($evs);
        }

        ksort($legend);
        render('activities/calendar', [
            'hasShort' => (bool) $short,
            'legend' => $legend,
            'first' => $first,
            'prev' => $first->modify('-1 month')->format('Y-m'),
            'next' => $next->format('Y-m'),
            'byDay' => $byDay,
            'cat' => $cat,
            'gid' => $gid,
            'type' => $type,
            'types' => $types,
            'groupOptions' => GroupController::filterOptions(),
            'monthLabel' => MONTHS_NL[(int) $first->format('n')] . ' ' . $first->format('Y'),
        ], 'Kalender');
    }

    public static function show(): void
    {
        $activity = self::loadVisible((int) ($_GET['id'] ?? 0));

        $groups = Database::all(
            'SELECT g.id, g.name FROM activity_groups ag JOIN `groups` g ON g.id = ag.group_id WHERE ag.activity_id = ? ORDER BY g.name',
            [$activity['id']]
        );

        $attendanceRows = Database::all('SELECT member_id, status FROM attendance WHERE activity_id = ?', [$activity['id']]);
        $statusByMember = array_column($attendanceRows, 'status', 'member_id');

        // Alleen leden binnen de scope van de gebruiker: trainer = eigen groepen, zwemmer = zichzelf, ouder = kinderen.
        $roster = Scope::visibleTargetMembers($activity);
        $registrable = Auth::can('attendance.write') ? array_map('intval', array_column(Scope::registrableMembers($activity), 'id')) : [];
        // Aanwezigheden staan voorlopig uit (feature 'attendance'); afmelden door ouders/leden blijft altijd werken.
        $attendanceOn = feature('attendance');
        $canRegister = $attendanceOn && $registrable && $activity['status'] === 'PLANNED';
        $canSeeAttendance = Auth::can('attendance.read');

        $absences = [];
        foreach (Database::all('SELECT member_id, reason, created_at FROM absence_reports WHERE activity_id = ?', [$activity['id']]) as $r) {
            $absences[(int) $r['member_id']] = $r;
        }
        $ownIds = Scope::ownMemberIds();
        $canAbsence = $activity['status'] === 'PLANNED' && $activity['ends_at'] > now();
        $medical = [];
        if ($canRegister && Auth::can('members.medical')) {
            foreach ($roster as $m) {
                if (in_array((int) $m['id'], $registrable, true) && ($m['medical_note'] || $m['emergency_name'] || $m['emergency_phone'])) {
                    $m['medical_note'] = Crypto::decrypt($m['medical_note']);
                    $medical[] = $m;
                }
            }
            if (array_filter(array_column($medical, 'medical_note'))) {
                audit_medical_view('activity', (int) $activity['id']);
            }
        }

        // Trainersplanning competitie: wie geeft deze training / begeleidt deze wedstrijd.
        $coverage = Coverage::canView() ? Coverage::one((int) $activity['id']) : null;
        $coveragePool = $coverage ? Coverage::pool() : [];

        render('activities/show', compact('activity', 'groups', 'roster', 'statusByMember', 'canRegister', 'canSeeAttendance', 'attendanceOn', 'registrable', 'absences', 'ownIds', 'canAbsence', 'medical', 'coverage', 'coveragePool'), $activity['title']);
    }

    /** Ouder of lid meldt vooraf af voor één activiteit (of trekt de afmelding in). */
    public static function absence(): void
    {
        $activity = self::loadVisible((int) input('activity_id', '0'), true);
        $mid = (int) input('member_id', '0');
        $back = input('back') === 'dashboard' ? url('dashboard') : url('activity', ['id' => $activity['id']]);
        $inRoster = in_array($mid, array_map('intval', array_column(Scope::targetMembers($activity), 'id')), true);
        if (!can_report_absence($mid) || !$inRoster) {
            abort(403, 'Je kan enkel afmelden voor jezelf of je eigen kinderen. Ben je jonger dan 18, dan meldt je ouder je af.');
        }
        if ($activity['status'] !== 'PLANNED' || $activity['ends_at'] <= now()) {
            flash('error', 'Afmelden kan enkel voor een geplande activiteit die nog niet voorbij is.');
            redirect($back);
        }
        if (input('do') === 'remove') {
            Database::exec('DELETE FROM absence_reports WHERE activity_id = ? AND member_id = ?', [$activity['id'], $mid]);
            flash('success', 'Afmelding ingetrokken.');
            redirect($back);
        }
        // Reden: zelf getypt, anders de snelle keuze (Ziek, School, Op reis)
        $reason = trim(input('reason'));
        if ($reason === '') {
            $reason = in_array(input('reason_pick'), ['Ziek', 'School', 'Op reis'], true) ? input('reason_pick') : '';
        }
        $reason = mb_substr($reason, 0, 200);
        if (!Database::val('SELECT 1 FROM absence_reports WHERE activity_id = ? AND member_id = ?', [$activity['id'], $mid])) {
            Database::exec(
                'INSERT INTO absence_reports (activity_id, member_id, reported_by, reason, created_at) VALUES (?,?,?,?,?)',
                [$activity['id'], $mid, Auth::user()['id'], $reason === '' ? null : $reason, now()]
            );
        }
        $who = (string) Database::val('SELECT first_name FROM members WHERE id = ?', [$mid]);
        flash('success', sprintf(
            '%s is afgemeld voor %s. De trainer ziet dit bij de %s.',
            $who,
            fmt_date($activity['starts_at']) . ' ' . fmt_time($activity['starts_at']),
            feature('attendance') ? 'aanwezigheden' : 'training'
        ));
        redirect($back);
    }

    public static function form(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        if ($id) {
            $activity = self::loadVisible($id);
            $selected = array_map('intval', array_column(Database::all('SELECT group_id FROM activity_groups WHERE activity_id = ?', [$id]), 'group_id'));
            $activity['starts_local'] = str_replace(' ', 'T', substr($activity['starts_at'], 0, 16));
            $activity['ends_local'] = str_replace(' ', 'T', substr($activity['ends_at'], 0, 16));
        } else {
            $date = (string) ($_GET['date'] ?? '');
            $date = valid_date($date) ? $date : today();
            $type = Database::val("SELECT id FROM activity_types WHERE code = 'TRAINING'");
            $activity = [
                'id' => 0, 'type_id' => $type, 'title' => '', 'description' => '', 'location' => '',
                'starts_local' => $date . 'T18:00', 'ends_local' => $date . 'T19:00',
                'audience' => 'GROUPS', 'status' => 'PLANNED', 'min_trainers' => null,
            ];
            $selected = [];
        }
        self::renderForm($activity, $selected, [], $id ? 'Activiteit wijzigen' : 'Nieuwe activiteit');
    }

    public static function save(): void
    {
        $id = (int) input('id', '0');
        if ($id) {
            self::loadVisible($id);
        }

        $activity = [
            'id' => $id,
            'type_id' => (int) input('type_id', '0'),
            'title' => input('title'),
            'description' => input('description'),
            'location' => input('location'),
            'starts_local' => input('starts_at'),
            'ends_local' => input('ends_at'),
            'audience' => input('audience'),
            'status' => $id ? input('status', 'PLANNED') : 'PLANNED',
        ];
        $typeCode = Database::val('SELECT code FROM activity_types WHERE id = ?', [$activity['type_id']]);
        $withTrainers = in_array($typeCode, ['TRAINING', 'COMPETITION'], true);
        // Training en wedstrijd: geen aantal trainers vragen, wel meteen kiezen wie er is (trainersplanning).
        $pool = Coverage::pool();
        $trainerIds = $withTrainers ? array_values(array_intersect(array_unique(array_map('intval', (array) ($_POST['trainer_ids'] ?? []))), array_keys($pool))) : [];
        $rawGroups = $_POST['group_ids'] ?? [];
        $selected = is_array($rawGroups) ? array_values(array_unique(array_map('intval', $rawGroups))) : [];

        $errors = [];
        if (!Database::val('SELECT COUNT(*) FROM activity_types WHERE id = ?', [$activity['type_id']])) {
            $errors[] = 'Kies een type activiteit.';
        }
        if ($activity['title'] === '' || mb_strlen($activity['title']) > 150) {
            $errors[] = 'Titel is verplicht (max. 150 tekens).';
        }
        if (mb_strlen($activity['location']) > 150) {
            $errors[] = 'Locatie is te lang (max. 150 tekens).';
        }
        $start = parse_datetime_local($activity['starts_local']);
        $end = parse_datetime_local($activity['ends_local']);
        if (!$start || !$end) {
            $errors[] = 'Geef een geldig begin- en eindtijdstip op.';
        } elseif ($end <= $start) {
            $errors[] = 'Het einde moet na het begin liggen.';
        }
        if (!in_array($activity['audience'], ['CLUB', 'GROUPS'], true)) {
            $errors[] = 'Kies een doelgroep.';
        } elseif ($activity['audience'] === 'GROUPS') {
            if (!$selected) {
                $errors[] = 'Kies minstens één groep, of maak de activiteit clubbreed.';
            } else {
                $valid = (int) Database::val(
                    'SELECT COUNT(*) FROM `groups` WHERE is_active = 1 AND id IN (' . Scope::in($selected) . ')',
                    $selected
                );
                if ($valid !== count($selected)) {
                    $errors[] = 'Een gekozen groep bestaat niet of is niet actief.';
                }
            }
        }
        if (!in_array($activity['status'], ['PLANNED', 'CANCELLED'], true)) {
            $errors[] = 'Status is ongeldig.';
        }

        if ($errors) {
            http_response_code(422);
            self::renderForm($activity, $selected, $errors, $id ? 'Activiteit wijzigen' : 'Nieuwe activiteit', $withTrainers ? $trainerIds : null);
            return;
        }

        $groupIds = $activity['audience'] === 'GROUPS' ? $selected : [];
        $desc = $activity['description'] === '' ? null : $activity['description'];
        $loc = $activity['location'] === '' ? null : $activity['location'];

        $id = Database::tx(function () use ($id, $activity, $start, $end, $desc, $loc, $groupIds) {
            if ($id) {
                Database::exec(
                    'UPDATE activities SET type_id=?, title=?, description=?, location=?, starts_at=?, ends_at=?, audience=?, status=? WHERE id=?',
                    [$activity['type_id'], $activity['title'], $desc, $loc, $start, $end, $activity['audience'], $activity['status'], $id]
                );
                Database::exec('DELETE FROM activity_groups WHERE activity_id = ?', [$id]);
                // Individueel gewijzigd: de reeks overschrijft deze training niet meer.
                Database::exec('UPDATE activities SET is_custom = 1 WHERE id = ? AND series_id IS NOT NULL', [$id]);
                if ($activity['status'] === 'PLANNED') {
                    Database::exec('UPDATE activities SET status_note = NULL WHERE id = ?', [$id]);
                }
            } else {
                $id = Database::insert(
                    'INSERT INTO activities (type_id, title, description, location, starts_at, ends_at, audience, status, created_by, created_at)
                     VALUES (?,?,?,?,?,?,?,?,?,?)',
                    [$activity['type_id'], $activity['title'], $desc, $loc, $start, $end, $activity['audience'], 'PLANNED', Auth::user()['id'], now()]
                );
            }
            foreach ($groupIds as $gid) {
                Database::exec('INSERT INTO activity_groups (activity_id, group_id) VALUES (?,?)', [$id, $gid]);
            }
            // Het aantal trainers vraagt het formulier niet meer: het minimum van de groep geldt (wedstrijd: 1).
            Database::exec('UPDATE activities SET min_trainers = NULL WHERE id = ?', [$id]);
            return $id;
        });

        audit($activity['id'] ? 'UPDATE' : 'CREATE', 'activity', $id);
        $note = $withTrainers ? self::syncTrainers((int) $id, $trainerIds, $pool, input('trainers_sync') === '1') : '';
        flash('success', ($activity['id'] ? 'Activiteit bijgewerkt.' : 'Activiteit aangemaakt.') . $note);
        redirect(url('activity', ['id' => $id]));
    }

    public static function saveAttendance(): void
    {
        $activity = self::loadVisible((int) input('activity_id', '0'), true);
        if ($activity['status'] !== 'PLANNED') {
            flash('error', 'Voor een geannuleerde activiteit kan geen aanwezigheid geregistreerd worden.');
            redirect(url('activity', ['id' => $activity['id']]));
        }

        $allowed = Auth::can('attendance.write') ? array_map('intval', array_column(Scope::registrableMembers($activity), 'id')) : [];
        $submitted = $_POST['status'] ?? [];
        if (!is_array($submitted)) {
            abort(403, 'Ongeldige aanvraag.');
        }

        // Elk lid-id en elke status wordt server-side gecontroleerd; één afwijking weigert de volledige aanvraag.
        $clean = [];
        foreach ($submitted as $memberId => $status) {
            $mid = (int) $memberId;
            if (!in_array($mid, $allowed, true) || !in_array($status, ['UNKNOWN', 'PRESENT', 'ABSENT'], true)) {
                abort(403, 'Je mag de aanwezigheid van dit lid voor deze activiteit niet registreren.');
            }
            $clean[$mid] = $status;
        }

        Database::tx(function () use ($activity, $clean) {
            $uid = Auth::user()['id'];
            foreach ($clean as $mid => $status) {
                $exists = Database::val('SELECT id FROM attendance WHERE activity_id = ? AND member_id = ?', [$activity['id'], $mid]);
                if ($exists) {
                    Database::exec('UPDATE attendance SET status=?, updated_by=?, updated_at=? WHERE id=?', [$status, $uid, now(), $exists]);
                } else {
                    Database::exec(
                        'INSERT INTO attendance (activity_id, member_id, status, updated_by, updated_at) VALUES (?,?,?,?,?)',
                        [$activity['id'], $mid, $status, $uid, now()]
                    );
                }
            }
        });

        audit('SAVE', 'attendance', (int) $activity['id'], count($clean) . ' leden');
        flash('success', 'Aanwezigheden opgeslagen.');
        redirect(url('activity', ['id' => $activity['id']]));
    }

    private static function loadVisible(int $id, bool $forbidInsteadOfNotFound = false): array
    {
        $activity = $id ? Scope::findActivity($id) : null;
        if (!$activity) {
            $forbidInsteadOfNotFound
                ? abort(403, 'Je hebt geen toegang tot deze activiteit.')
                : abort(404, 'Deze activiteit bestaat niet of je hebt er geen toegang toe.');
        }
        return $activity;
    }

    /**
     * Trainers gekozen in het formulier (training of wedstrijd): aangevinkt = ingepland. Met $sync (het formulier
     * toonde de huidige bezetting) gaat wie uitgevinkt is eraf: een groepstrainer als afgemeld ("Niet ingepland"),
     * een extra trainer weg. Wie op dat moment al elders staat, wordt overgeslagen. Geeft een extra zin voor de melding.
     */
    private static function syncTrainers(int $id, array $want, array $pool, bool $sync): string
    {
        $a = Coverage::one($id);
        if (!$a) {
            return ''; // hoort niet bij de trainersplanning (bv. geschrapt of geen competitiegroep)
        }
        $have = [];
        foreach ($a['trainers'] as $t) {
            if (!$t['absent']) {
                $have[] = $t['id'];
            }
        }
        $skipped = [];
        $added = [];
        foreach (array_diff($want, $have) as $uid) {
            if ($c = Coverage::conflict($a, $uid)) {
                $skipped[] = $c;
                continue;
            }
            Coverage::isDefault($a, $uid) ? Coverage::set($id, $uid, null) : Coverage::set($id, $uid, 'ASSIGNED');
            $added[] = $pool[$uid];
            sort($added);
        }
        $removed = [];
        foreach ($sync ? array_diff($have, $want) : [] as $uid) {
            Coverage::isDefault($a, $uid) ? Coverage::set($id, $uid, 'ABSENT', 'Niet ingepland') : Coverage::set($id, $uid, null);
            $removed[] = $pool[$uid] ?? '';
        }
        if ($added || $removed) {
            audit('COVERAGE_ASSIGN', 'activity', $id, trim(($added ? 'erbij: ' . implode(', ', $added) : '') . ($removed ? ' eraf: ' . implode(', ', array_filter($removed)) : '')));
        }
        $isMeet = $a['type_code'] === 'COMPETITION';
        $msg = $added && $isMeet ? ' ' . nl_list($added) . (count($added) === 1 ? ' gaat' : ' gaan') . ' mee.' : ($added ? ' Ingepland: ' . nl_list($added) . '.' : '');
        if ($skipped) {
            $msg .= ' Niet ingepland: ' . implode(' ', $skipped);
        }
        $after = Coverage::one($id);
        if ($after && $after['missing'] > 0 && $after['ends_at'] > now()) {
            $msg .= $after['present'] === 0
                ? ' Er ' . ($isMeet ? 'gaat' : 'is') . ' nog geen trainer' . ($isMeet ? ' mee' : '') . ': die staat als trainer gezocht in de trainersplanning.'
                : ' Er is nog een trainer nodig: die staat als trainer gezocht in de trainersplanning.';
        }
        return $msg;
    }

    /** Wie nu op de activiteit staat (niet afgemeld). @return int[] */
    private static function presentTrainers(int $id): array
    {
        $a = $id ? Coverage::one($id) : null;
        if (!$a) {
            return [];
        }
        return array_values(array_map(fn($t) => $t['id'], array_filter($a['trainers'], fn($t) => !$t['absent'])));
    }

    /** Groepen per trainer (om bij een nieuwe training de groepstrainers voor te vinken). @return array<int,int[]> */
    private static function trainerGroups(): array
    {
        $out = [];
        foreach (Database::all('SELECT gt.user_id, gt.group_id FROM group_trainers gt JOIN `groups` g ON g.id = gt.group_id WHERE g.is_active = 1') as $r) {
            $out[(int) $r['user_id']][] = (int) $r['group_id'];
        }
        return $out;
    }

    private static function renderForm(array $activity, array $selected, array $errors, string $title, ?array $trainerIds = null): void
    {
        render('activities/form', [
            'activity' => $activity,
            'selected' => $selected,
            'errors' => $errors,
            'types' => Database::all('SELECT id, code, name FROM activity_types ORDER BY id'),
            'pool' => Coverage::pool(),
            'trainerIds' => $trainerIds ?? self::presentTrainers((int) $activity['id']),
            'trainerGroups' => self::trainerGroups(),
            'groups' => Database::all('SELECT id, name, category FROM `groups` WHERE is_active = 1 ORDER BY category, name'),
        ], $title);
    }
}
