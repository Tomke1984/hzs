<?php
/** Lidgeld per seizoen en de seizoensovergang (doorstromen, stoppen, nieuwe rekeningen). */
final class FeeController
{
    public static function index(): void
    {
        $seasons = Database::all('SELECT * FROM seasons ORDER BY start_date DESC');
        $sid = (int) ($_GET['season'] ?? 0);
        $season = null;
        foreach ($seasons as $s) {
            if ((int) $s['id'] === $sid) {
                $season = $s;
            }
        }
        $season ??= Fees::currentSeason();
        $status = (string) ($_GET['status'] ?? '');
        $gid = (int) ($_GET['g'] ?? 0);
        $q = trim((string) ($_GET['q'] ?? ''));

        $rows = [];
        $sum = ['all' => [0, 0], 'PAID' => [0, 0], 'OPEN' => [0, 0], 'WAIVED' => [0, 0]];
        $noFee = 0;
        if ($season) {
            $where = ['d.season_id = ?'];
            $params = [$season['id']];
            if (in_array($status, ['OPEN', 'PAID', 'WAIVED'], true)) {
                $where[] = 'd.status = ?';
                $params[] = $status;
            }
            if ($gid) {
                $where[] = 'EXISTS (SELECT 1 FROM group_memberships x WHERE x.member_id = m.id AND x.group_id = ?)';
                $params[] = $gid;
            }
            foreach (preg_split('/\s+/', $q, -1, PREG_SPLIT_NO_EMPTY) as $w) {
                $where[] = '(m.first_name LIKE ? OR m.last_name LIKE ?)';
                array_push($params, "%$w%", "%$w%");
            }
            $rows = Database::all(
                "SELECT d.*, m.first_name, m.last_name,
                    (SELECT g.name FROM group_memberships gm JOIN `groups` g ON g.id = gm.group_id WHERE gm.member_id = m.id ORDER BY gm.id LIMIT 1) AS group_name
                 FROM dues d JOIN members m ON m.id = d.member_id WHERE " . implode(' AND ', $where) . ' ORDER BY m.last_name, m.first_name LIMIT 1000',
                $params
            );
            foreach (Database::all('SELECT status, COUNT(*) AS n, SUM(amount_cents) AS total FROM dues WHERE season_id = ? GROUP BY status', [$season['id']]) as $r) {
                $sum[$r['status']] = [(int) $r['n'], (int) $r['total']];
                $sum['all'][0] += (int) $r['n'];
                $sum['all'][1] += (int) $r['total'];
            }
            $noFee = (int) Database::val(
                "SELECT COUNT(*) FROM members m JOIN group_memberships gm ON gm.member_id = m.id JOIN `groups` g ON g.id = gm.group_id
                 WHERE m.status = 'ACTIVE' AND g.is_active = 1 AND NOT EXISTS (SELECT 1 FROM dues d WHERE d.member_id = m.id AND d.season_id = ?)",
                [$season['id']]
            );
        }
        render('fees/index', [
            'seasons' => $seasons, 'season' => $season, 'rows' => $rows, 'sum' => $sum, 'noFee' => $noFee,
            'status' => $status, 'gid' => $gid, 'q' => $q,
            'groups' => Database::all('SELECT id, name FROM `groups` ORDER BY category, name'),
            'defaultSeason' => Fees::seasonFor(today()),
            'noAmount' => Database::all("SELECT name FROM `groups` WHERE is_active = 1 AND fee_cents IS NULL ORDER BY name"),
        ], 'Lidgeld');
    }

    public static function seasonSave(): void
    {
        $name = input('name');
        $start = input('start_date');
        $end = input('end_date');
        if ($name === '' || mb_strlen($name) > 40 || !valid_date($start) || !valid_date($end) || $end < $start) {
            flash('error', 'Geef een naam en een geldige begin- en einddatum op.');
            redirect(url('fees'));
        }
        $id = (int) Database::val('SELECT id FROM seasons WHERE name = ?', [$name]);
        if (!$id) {
            $id = Database::insert('INSERT INTO seasons (name, start_date, end_date, is_current) VALUES (?,?,?,0)', [$name, $start, $end]);
            audit('CREATE', 'season', $id);
        }
        if (input('is_current') === '1' || !Database::val('SELECT COUNT(*) FROM seasons WHERE is_current = 1')) {
            Database::exec('UPDATE seasons SET is_current = CASE WHEN id = ? THEN 1 ELSE 0 END', [$id]);
        }
        flash('success', 'Seizoen ' . $name . ' bewaard.');
        redirect(url('fees', ['season' => $id]));
    }

    public static function generate(): void
    {
        $sid = (int) input('season_id', '0');
        if (!Database::val('SELECT COUNT(*) FROM seasons WHERE id = ?', [$sid])) {
            abort(404, 'Seizoen niet gevonden.');
        }
        $due = valid_date(input('due_date')) ? input('due_date') : null;
        [$created, $skipped] = Fees::generate($sid, $due);
        audit('CREATE', 'dues', $sid, "$created rekeningen");
        flash($created ? 'success' : 'warning', "$created rekening(en) aangemaakt" . ($skipped ? ", $skipped leden overgeslagen omdat hun groep geen lidgeldbedrag heeft" : '') . '.');
        redirect(url('fees', ['season' => $sid]));
    }

    public static function status(): void
    {
        $d = Database::one('SELECT * FROM dues WHERE id = ?', [(int) input('id', '0')]);
        if (!$d) {
            abort(404, 'Rekening niet gevonden.');
        }
        $do = input('do');
        if (!in_array($do, ['paid', 'waived', 'open'], true)) {
            abort(400, 'Ongeldige actie.');
        }
        $paidAt = valid_date(input('paid_at')) ? input('paid_at') : today();
        Database::exec(
            'UPDATE dues SET status = ?, paid_at = ? WHERE id = ?',
            [['paid' => 'PAID', 'waived' => 'WAIVED', 'open' => 'OPEN'][$do], $do === 'paid' ? $paidAt : null, $d['id']]
        );
        audit('UPDATE', 'due', (int) $d['id'], $do);
        flash('success', 'Rekening bijgewerkt.');
        redirect(url('fees', array_filter(['season' => $d['season_id'], 'status' => input('filter_status'), 'g' => input('filter_g'), 'q' => input('filter_q')])));
    }

    /** Herinneringsmail voor één rekening of voor alle open rekeningen (die niet de laatste 14 dagen al een herinnering kregen). */
    public static function remind(): void
    {
        $sid = (int) input('season_id', '0');
        $one = (int) input('id', '0');
        $where = "d.status = 'OPEN' AND d.season_id = ?";
        $params = [$sid];
        if ($one) {
            $where .= ' AND d.id = ?';
            $params[] = $one;
        } else {
            $where .= ' AND (d.reminder_at IS NULL OR d.reminder_at < ?)';
            $params[] = date('Y-m-d H:i:s', strtotime('-14 days'));
        }
        $sent = 0;
        $nobody = 0;
        foreach (Database::all(
            "SELECT d.*, m.first_name, m.last_name, s.name AS season_name FROM dues d JOIN members m ON m.id = d.member_id JOIN seasons s ON s.id = d.season_id WHERE $where",
            $params
        ) as $d) {
            $to = Mailing::recipients([(int) $d['member_id']]);
            if (!$to) {
                $nobody++;
                continue;
            }
            foreach ($to as $mail => $name) {
                [$subject, $body] = Fees::reminderMessage($d, $name);
                Mailer::send($mail, $subject, $body);
            }
            Database::exec('UPDATE dues SET reminder_at = ?, reminder_count = reminder_count + 1 WHERE id = ?', [now(), $d['id']]);
            $sent++;
        }
        audit('SEND', 'dues_reminder', $sid, "$sent herinneringen");
        flash($sent ? 'success' : 'warning', "$sent herinnering(en) verstuurd" . ($nobody ? ", $nobody overgeslagen (geen e-mailadres bekend)" : '') . '.');
        redirect(url('fees', ['season' => $sid]));
    }

    // ------------------------------------------------------------------ seizoensovergang

    public static function rollover(): void
    {
        $cur = Fees::currentSeason();
        $base = $cur ? date('Y-m-d', strtotime($cur['end_date'] . ' +1 day')) : today();
        [$name, $start, $end] = Fees::seasonFor($base);
        $groups = [];
        foreach (Database::all("SELECT * FROM `groups` WHERE is_active = 1 ORDER BY category, name") as $g) {
            $g['members'] = Database::all(
                "SELECT m.id, m.first_name, m.last_name, m.birth_date FROM group_memberships gm JOIN members m ON m.id = gm.member_id
                 WHERE gm.group_id = ? AND m.status = 'ACTIVE' ORDER BY m.last_name, m.first_name",
                [$g['id']]
            );
            if ($g['members']) {
                $groups[] = $g;
            }
        }
        render('fees/rollover', [
            'groups' => $groups, 'allGroups' => Database::all('SELECT id, name FROM `groups` WHERE is_active = 1 ORDER BY category, name'),
            'season' => ['name' => $name, 'start' => $start, 'end' => $end], 'cur' => $cur,
        ], 'Seizoensovergang');
    }

    public static function rolloverApply(): void
    {
        $name = input('name');
        $start = input('start_date');
        $end = input('end_date');
        if ($name === '' || mb_strlen($name) > 40 || !valid_date($start) || !valid_date($end) || $end < $start) {
            flash('error', 'Geef een naam en een geldige begin- en einddatum voor het nieuwe seizoen op.');
            redirect(url('season_rollover'));
        }
        $bulk = is_array($_POST['bulk'] ?? null) ? $_POST['bulk'] : [];
        $choice = is_array($_POST['choice'] ?? null) ? $_POST['choice'] : [];
        $stats = ['stay' => 0, 'moved' => 0, 'stopped' => 0];
        $errors = [];

        $sid = (int) Database::val('SELECT id FROM seasons WHERE name = ?', [$name]);
        if (!$sid) {
            $sid = Database::insert('INSERT INTO seasons (name, start_date, end_date, is_current) VALUES (?,?,?,0)', [$name, $start, $end]);
        }
        Database::exec('UPDATE seasons SET is_current = CASE WHEN id = ? THEN 1 ELSE 0 END', [$sid]);

        foreach (Database::all("SELECT gm.member_id, gm.group_id, g.next_group_id, m.first_name, m.last_name FROM group_memberships gm JOIN `groups` g ON g.id = gm.group_id JOIN members m ON m.id = gm.member_id WHERE m.status = 'ACTIVE' AND g.is_active = 1") as $r) {
            $mid = (int) $r['member_id'];
            $c = (string) ($choice[$mid] ?? '');
            if ($c === '' || $c === 'default') {
                $b = (string) ($bulk[$r['group_id']] ?? 'stay');
                $c = $b === 'next' ? ($r['next_group_id'] ? 'g:' . $r['next_group_id'] : 'stay') : $b;
            }
            if ($c === 'stop') {
                SeriesService::clearMemberBlocks($mid);
                Database::exec('DELETE FROM group_memberships WHERE member_id = ?', [$mid]);
                Database::exec('DELETE FROM group_waitlist WHERE member_id = ?', [$mid]);
                Database::exec("UPDATE members SET status = 'INACTIVE', status_reason = ?, status_date = ? WHERE id = ?", ['Gestopt', today(), $mid]);
                audit('UPDATE', 'member', $mid, 'seizoensovergang: gestopt');
                $stats['stopped']++;
            } elseif (preg_match('/^g:(\d+)$/', $c, $m) && (int) $m[1] !== (int) $r['group_id']) {
                $err = GroupController::placeMember($mid, (int) $m[1], true);
                if ($err) {
                    $errors[] = $r['first_name'] . ' ' . $r['last_name'] . ': ' . $err;
                } else {
                    $stats['moved']++;
                }
            } else {
                $stats['stay']++;
            }
        }
        $created = 0;
        if (input('generate') === '1') {
            [$created] = Fees::generate($sid, valid_date(input('due_date')) ? input('due_date') : null);
        }
        audit('UPDATE', 'season', $sid, "overgang: {$stats['moved']} verplaatst, {$stats['stopped']} gestopt, $created rekeningen");
        flash('success', "Seizoen $name is het huidige seizoen. {$stats['moved']} leden doorgestroomd, {$stats['stopped']} gestopt, {$stats['stay']} blijven" . ($created ? ", $created rekeningen aangemaakt" : '') . '.');
        foreach (array_slice($errors, 0, 5) as $e) {
            flash('warning', $e);
        }
        flash('warning', 'Vergeet de trainingsreeksen niet te verlengen of nieuwe te maken voor het nieuwe seizoen.');
        redirect(url('fees', ['season' => $sid]));
    }
}
