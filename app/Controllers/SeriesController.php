<?php
final class SeriesController
{
    public static function index(): void
    {
        [$cat, $gid] = GroupController::readFilter();
        $where = '1=1';
        $fp = [];
        if ($cat !== '' || $gid) {
            $where = 'EXISTS (SELECT 1 FROM activity_series_groups fsg JOIN `groups` fg ON fg.id = fsg.group_id WHERE fsg.series_id = s.id';
            if ($cat !== '') {
                $where .= ' AND fg.category = ?';
                $fp[] = $cat;
            }
            if ($gid) {
                $where .= ' AND fg.id = ?';
                $fp[] = $gid;
            }
            $where .= ')';
        }
        $series = Database::all(
            "SELECT s.*, t.name AS type_name,
               (SELECT COUNT(*) FROM activities a WHERE a.series_id = s.id AND a.starts_at >= ? AND a.status = 'PLANNED') AS upcoming
             FROM activity_series s JOIN activity_types t ON t.id = s.type_id
             WHERE $where
             ORDER BY s.is_active DESC, s.title",
            array_merge([today() . ' 00:00:00'], $fp)
        );
        foreach ($series as &$s) {
            $s['groups'] = array_column(Database::all('SELECT g.name FROM activity_series_groups sg JOIN `groups` g ON g.id = sg.group_id WHERE sg.series_id = ? ORDER BY g.name', [$s['id']]), 'name');
            $s['slots'] = Database::all('SELECT * FROM activity_series_slots WHERE series_id = ?', [$s['id']]);
        }
        unset($s);
        render('series/index', ['series' => $series, 'cat' => $cat, 'gid' => $gid, 'groupOptions' => GroupController::filterOptions()], 'Trainingsreeksen');
    }

    public static function show(): void
    {
        $s = self::load((int) ($_GET['id'] ?? 0));
        $s['groups'] = Database::all('SELECT g.id, g.name FROM activity_series_groups sg JOIN `groups` g ON g.id = sg.group_id WHERE sg.series_id = ? ORDER BY g.name', [$s['id']]);
        $s['slots'] = Database::all('SELECT * FROM activity_series_slots WHERE series_id = ?', [$s['id']]);
        $occ = Database::all(
            'SELECT id, starts_at, ends_at, location, status, status_note, is_custom FROM activities WHERE series_id = ? AND starts_at >= ? ORDER BY starts_at LIMIT 80',
            [$s['id'], today() . ' 00:00:00']
        );
        render('series/show', compact('s', 'occ'), $s['title']);
    }

    public static function form(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        if ($id) {
            $s = self::load($id);
            $selected = array_map('intval', array_column(Database::all('SELECT group_id FROM activity_series_groups WHERE series_id = ?', [$id]), 'group_id'));
            $slots = [];
            foreach (Database::all('SELECT * FROM activity_series_slots WHERE series_id = ?', [$id]) as $sl) {
                $slots[(int) $sl['weekday']] = $sl;
            }
        } else {
            $s = [
                'id' => 0, 'type_id' => (int) Database::val("SELECT id FROM activity_types WHERE code = 'TRAINING'"),
                'title' => '', 'description' => '', 'location' => '',
                'start_date' => today(), 'end_date' => date('Y-m-d', strtotime('+6 months')), 'is_active' => 1,
            ];
            $selected = [];
            $slots = [];
        }
        self::renderForm($s, $selected, $slots, [], $id ? 'Reeks wijzigen' : 'Nieuwe reeks');
    }

    public static function save(): void
    {
        $id = (int) input('id', '0');
        if ($id) {
            self::load($id);
        }
        $s = [
            'id' => $id,
            'type_id' => (int) input('type_id', '0'),
            'title' => input('title'),
            'description' => input('description'),
            'location' => input('location'),
            'start_date' => input('start_date'),
            'end_date' => input('end_date'),
            'is_active' => $id ? (input('is_active') === '1' ? 1 : 0) : 1,
        ];
        $raw = $_POST['group_ids'] ?? [];
        $selected = is_array($raw) ? array_values(array_unique(array_map('intval', $raw))) : [];

        $slots = [];
        $errors = [];
        $on = $_POST['slot_on'] ?? [];
        $re = '/^([01]\d|2[0-3]):[0-5]\d$/';
        foreach (SeriesService::DAYS as $n => $label) {
            if (!is_array($on) || empty($on[$n])) {
                continue;
            }
            $st = trim((string) ($_POST['slot_start'][$n] ?? ''));
            $en = trim((string) ($_POST['slot_end'][$n] ?? ''));
            $lc = trim((string) ($_POST['slot_location'][$n] ?? ''));
            $slots[$n] = ['weekday' => $n, 'start_time' => $st, 'end_time' => $en, 'location' => $lc];
            if (!preg_match($re, $st) || !preg_match($re, $en) || $en <= $st) {
                $errors[] = "Ongeldig uur voor $label (het einde moet na het begin liggen).";
            }
            if (mb_strlen($lc) > 150) {
                $errors[] = "Locatie voor $label is te lang.";
            }
        }
        if (!$slots) {
            $errors[] = 'Kies minstens één trainingsdag.';
        }
        if (!Database::val('SELECT COUNT(*) FROM activity_types WHERE id = ?', [$s['type_id']])) {
            $errors[] = 'Kies een type.';
        }
        if ($s['title'] === '' || mb_strlen($s['title']) > 150) {
            $errors[] = 'Titel is verplicht (max. 150 tekens).';
        }
        if (mb_strlen($s['location']) > 150) {
            $errors[] = 'Locatie is te lang (max. 150 tekens).';
        }
        if (!valid_date($s['start_date']) || !valid_date($s['end_date'])) {
            $errors[] = 'Geef een geldige start- en einddatum op.';
        } elseif ($s['end_date'] < $s['start_date']) {
            $errors[] = 'De einddatum moet na de startdatum liggen.';
        } elseif ((strtotime($s['end_date']) - strtotime($s['start_date'])) / 86400 > SeriesService::MAX_DAYS) {
            $errors[] = 'Een reeks mag maximaal ongeveer 18 maanden lang zijn.';
        }
        if (!$selected) {
            $errors[] = 'Kies minstens één groep.';
        } elseif ((int) Database::val('SELECT COUNT(*) FROM `groups` WHERE is_active = 1 AND id IN (' . Scope::in($selected) . ')', $selected) !== count($selected)) {
            $errors[] = 'Een gekozen groep bestaat niet of is niet actief.';
        }
        if ($errors) {
            http_response_code(422);
            self::renderForm($s, $selected, $slots, $errors, $id ? 'Reeks wijzigen' : 'Nieuwe reeks');
            return;
        }

        $desc = $s['description'] === '' ? null : $s['description'];
        $loc = $s['location'] === '' ? null : $s['location'];
        $id = Database::tx(function () use ($id, $s, $desc, $loc, $slots, $selected) {
            if ($id) {
                Database::exec(
                    'UPDATE activity_series SET type_id=?, title=?, description=?, location=?, start_date=?, end_date=?, is_active=? WHERE id=?',
                    [$s['type_id'], $s['title'], $desc, $loc, $s['start_date'], $s['end_date'], $s['is_active'], $id]
                );
                Database::exec('DELETE FROM activity_series_slots WHERE series_id = ?', [$id]);
                Database::exec('DELETE FROM activity_series_groups WHERE series_id = ?', [$id]);
            } else {
                $id = Database::insert(
                    'INSERT INTO activity_series (type_id, title, description, location, start_date, end_date, is_active, created_by, created_at) VALUES (?,?,?,?,?,?,?,?,?)',
                    [$s['type_id'], $s['title'], $desc, $loc, $s['start_date'], $s['end_date'], 1, Auth::user()['id'], now()]
                );
            }
            foreach ($slots as $sl) {
                Database::exec(
                    'INSERT INTO activity_series_slots (series_id, weekday, start_time, end_time, location) VALUES (?,?,?,?,?)',
                    [$id, $sl['weekday'], $sl['start_time'], $sl['end_time'], $sl['location'] === '' ? null : $sl['location']]
                );
            }
            foreach ($selected as $gid) {
                Database::exec('INSERT INTO activity_series_groups (series_id, group_id) VALUES (?,?)', [$id, $gid]);
            }
            return $id;
        });
        $r = SeriesService::sync($id);
        audit($s['id'] ? 'UPDATE' : 'CREATE', 'activity_series', $id, json_encode($r));
        flash('success', sprintf(
            'Reeks opgeslagen: %d trainingen aangemaakt, %d bijgewerkt, %d verwijderd%s.',
            $r['created'], $r['updated'], $r['removed'],
            $r['kept'] ? ", {$r['kept']} individueel aangepaste of reeds geregistreerde ongemoeid gelaten" : ''
        ));
        redirect(url('series_show', ['id' => $id]));
    }

    /** Eén training schrappen of herstellen. */
    public static function occurrence(): void
    {
        $a = Database::one('SELECT id, series_id, starts_at FROM activities WHERE id = ?', [(int) input('activity_id', '0')]);
        if (!$a) {
            abort(404, 'Activiteit niet gevonden.');
        }
        $do = input('do');
        if (!in_array($do, ['cancel', 'restore'], true)) {
            abort(400, 'Ongeldige actie.');
        }
        $reason = mb_substr(input('reason'), 0, 200);
        if ($do === 'cancel') {
            Database::exec("UPDATE activities SET status='CANCELLED', status_note=? WHERE id=?", [$reason === '' ? null : $reason, $a['id']]);
        } else {
            Database::exec("UPDATE activities SET status='PLANNED', status_note=NULL WHERE id=?", [$a['id']]);
        }
        audit($do === 'cancel' ? 'CANCEL' : 'RESTORE', 'activity', (int) $a['id']);
        flash('success', $do === 'cancel' ? 'Training geschrapt.' : 'Training hersteld.');
        redirect($a['series_id'] && input('back') === 'series' ? url('series_show', ['id' => $a['series_id']]) : url('activity', ['id' => $a['id']]));
    }

    public static function bulkForm(): void
    {
        self::renderBulk(['from' => today(), 'to' => today(), 'scope' => 'all', 'do' => 'cancel', 'reason' => '', 'location' => ''], [], []);
    }

    public static function bulk(): void
    {
        $v = [
            'from' => input('from'), 'to' => input('to'), 'scope' => input('scope', 'all'),
            'do' => input('do'), 'reason' => mb_substr(input('reason'), 0, 200), 'location' => input('location'),
        ];
        $raw = $_POST['group_ids'] ?? [];
        $sel = is_array($raw) ? array_values(array_unique(array_map('intval', $raw))) : [];
        $errors = [];
        if (!valid_date($v['from']) || !valid_date($v['to']) || $v['to'] < $v['from']) {
            $errors[] = 'Geef een geldige periode op (tot en met mag niet voor van liggen).';
        }
        if (!in_array($v['do'], ['cancel', 'restore', 'location'], true)) {
            $errors[] = 'Kies een actie.';
        }
        if ($v['do'] === 'location' && ($v['location'] === '' || mb_strlen($v['location']) > 150)) {
            $errors[] = 'Geef de nieuwe locatie op (max. 150 tekens).';
        }
        if ($v['scope'] === 'groups' && !$sel) {
            $errors[] = 'Kies minstens één groep.';
        }
        if ($errors) {
            http_response_code(422);
            self::renderBulk($v, $sel, $errors);
            return;
        }
        $sql = 'series_id IS NOT NULL AND starts_at >= ? AND starts_at < ?';
        $p = [$v['from'] . ' 00:00:00', date('Y-m-d', strtotime($v['to'] . ' +1 day')) . ' 00:00:00'];
        if ($v['scope'] === 'groups') {
            $sql .= ' AND EXISTS (SELECT 1 FROM activity_groups ag WHERE ag.activity_id = activities.id AND ag.group_id IN (' . Scope::in($sel) . '))';
            $p = array_merge($p, $sel);
        }
        if ($v['do'] === 'cancel') {
            $n = Database::exec("UPDATE activities SET status='CANCELLED', status_note=? WHERE status='PLANNED' AND $sql", array_merge([$v['reason'] === '' ? null : $v['reason']], $p));
            $msg = '%d trainingen geschrapt.';
        } elseif ($v['do'] === 'restore') {
            $n = Database::exec("UPDATE activities SET status='PLANNED', status_note=NULL WHERE status='CANCELLED' AND $sql", $p);
            $msg = '%d trainingen hersteld.';
        } else {
            $n = Database::exec("UPDATE activities SET location=?, is_custom=1 WHERE $sql", array_merge([$v['location']], $p));
            $msg = 'Locatie aangepast voor %d trainingen.';
        }
        $n = is_int($n) ? $n : 0;
        audit('BULK', 'activity_series', null, json_encode($v + ['count' => $n]));
        flash('success', sprintf($msg, $n));
        redirect(url('series'));
    }

    private static function load(int $id): array
    {
        $s = $id ? Database::one('SELECT s.*, t.name AS type_name FROM activity_series s JOIN activity_types t ON t.id = s.type_id WHERE s.id = ?', [$id]) : null;
        if (!$s) {
            abort(404, 'Reeks niet gevonden.');
        }
        return $s;
    }

    private static function renderForm(array $s, array $selected, array $slots, array $errors, string $title): void
    {
        render('series/form', [
            's' => $s, 'selected' => $selected, 'slots' => $slots, 'errors' => $errors,
            'types' => Database::all('SELECT id, name FROM activity_types ORDER BY id'),
            'groups' => Database::all('SELECT id, name, category FROM `groups` WHERE is_active = 1 ORDER BY category, name'),
        ], $title);
    }

    private static function renderBulk(array $v, array $sel, array $errors): void
    {
        render('series/bulk', [
            'v' => $v, 'sel' => $sel, 'errors' => $errors,
            'groups' => Database::all('SELECT id, name, category FROM `groups` WHERE is_active = 1 ORDER BY category, name'),
        ], 'Trainingen schrappen of verplaatsen');
    }
}
