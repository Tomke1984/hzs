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
        $rows = Database::all(
            "SELECT a.id, a.title, a.starts_at, a.ends_at, a.status, a.status_note, a.location, t.code AS type_code, t.name AS type_name
             FROM activities a JOIN activity_types t ON t.id = a.type_id
             WHERE a.starts_at >= ? AND a.starts_at < ? AND $sql
             ORDER BY a.starts_at",
            array_merge([$first->format('Y-m-d 00:00:00'), $next->format('Y-m-d 00:00:00')], $params)
        );

        $byDay = [];
        foreach ($rows as $r) {
            $byDay[substr($r['starts_at'], 0, 10)][] = $r;
        }

        render('activities/calendar', [
            'first' => $first,
            'prev' => $first->modify('-1 month')->format('Y-m'),
            'next' => $next->format('Y-m'),
            'byDay' => $byDay,
            'cat' => $cat,
            'gid' => $gid,
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
        $canRegister = $registrable && $activity['status'] === 'PLANNED';
        $canSeeAttendance = Auth::can('attendance.read');

        render('activities/show', compact('activity', 'groups', 'roster', 'statusByMember', 'canRegister', 'canSeeAttendance', 'registrable'), $activity['title']);
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
                'audience' => 'GROUPS', 'status' => 'PLANNED', 'is_public' => 0,
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
            'is_public' => input('is_public') === '1' ? 1 : 0,
        ];
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
            self::renderForm($activity, $selected, $errors, $id ? 'Activiteit wijzigen' : 'Nieuwe activiteit');
            return;
        }

        $groupIds = $activity['audience'] === 'GROUPS' ? $selected : [];
        $desc = $activity['description'] === '' ? null : $activity['description'];
        $loc = $activity['location'] === '' ? null : $activity['location'];

        $id = Database::tx(function () use ($id, $activity, $start, $end, $desc, $loc, $groupIds) {
            if ($id) {
                Database::exec(
                    'UPDATE activities SET type_id=?, title=?, description=?, location=?, starts_at=?, ends_at=?, audience=?, status=?, is_public=? WHERE id=?',
                    [$activity['type_id'], $activity['title'], $desc, $loc, $start, $end, $activity['audience'], $activity['status'], $activity['is_public'], $id]
                );
                Database::exec('DELETE FROM activity_groups WHERE activity_id = ?', [$id]);
                // Individueel gewijzigd: de reeks overschrijft deze training niet meer.
                Database::exec('UPDATE activities SET is_custom = 1 WHERE id = ? AND series_id IS NOT NULL', [$id]);
                if ($activity['status'] === 'PLANNED') {
                    Database::exec('UPDATE activities SET status_note = NULL WHERE id = ?', [$id]);
                }
            } else {
                $id = Database::insert(
                    'INSERT INTO activities (type_id, title, description, location, starts_at, ends_at, audience, status, created_by, created_at, is_public)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?)',
                    [$activity['type_id'], $activity['title'], $desc, $loc, $start, $end, $activity['audience'], 'PLANNED', Auth::user()['id'], now(), $activity['is_public']]
                );
            }
            foreach ($groupIds as $gid) {
                Database::exec('INSERT INTO activity_groups (activity_id, group_id) VALUES (?,?)', [$id, $gid]);
            }
            return $id;
        });

        audit($activity['id'] ? 'UPDATE' : 'CREATE', 'activity', $id);
        flash('success', $activity['id'] ? 'Activiteit bijgewerkt.' : 'Activiteit aangemaakt.');
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

    private static function renderForm(array $activity, array $selected, array $errors, string $title): void
    {
        render('activities/form', [
            'activity' => $activity,
            'selected' => $selected,
            'errors' => $errors,
            'types' => Database::all('SELECT id, name FROM activity_types ORDER BY id'),
            'groups' => Database::all('SELECT id, name, category FROM `groups` WHERE is_active = 1 ORDER BY category, name'),
        ], $title);
    }
}
