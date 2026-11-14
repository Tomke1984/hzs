<?php
/**
 * Trainersplanning competitie (/app/trainersplanning): overzicht van trainingen en wedstrijden met hun trainers,
 * afmelden, overnemen en toewijzen. Toegang: coördinator, bestuur, beheerder (coverage.manage) en elke trainer van
 * een competitiegroep (zij kunnen de coördinatie overnemen wanneer nodig). Vaste trainers per blok, het minimum per
 * groep, deellinks en herinneringen vragen coverage.manage.
 */
final class CoverageController
{
    public static function index(): void
    {
        self::requireView();
        $weeks = max(1, min(8, (int) ($_GET['weeks'] ?? 4)));
        $show = in_array($_GET['show'] ?? '', ['problems', 'mine'], true) ? $_GET['show'] : '';
        $from = today() . ' 00:00:00';
        $to = date('Y-m-d 00:00:00', strtotime("+$weeks weeks"));
        $all = Coverage::list($from, $to);
        $uid = (int) Auth::user()['id'];

        $problems = array_values(array_filter($all, fn($a) => $a['missing'] > 0 && $a['ends_at'] > now()));
        $rows = $all;
        if ($show === 'problems') {
            $rows = $problems;
        } elseif ($show === 'mine') {
            $rows = array_values(array_filter($all, fn($a) => (bool) array_filter($a['trainers'], fn($t) => $t['id'] === $uid)));
        }
        $byWeek = [];
        foreach ($rows as $a) {
            $monday = date('Y-m-d', strtotime('monday this week', strtotime(substr($a['starts_at'], 0, 10))));
            $byWeek[$monday][] = $a;
        }
        render('coverage/index', [
            'byWeek' => $byWeek,
            'problems' => $problems,
            'pool' => Coverage::pool(),
            'uid' => $uid,
            'weeks' => $weeks,
            'show' => $show,
            'canManage' => Auth::can('coverage.manage'),
            'remindDays' => Coverage::remindDays(),
            'noCompGroups' => !(int) Database::val("SELECT COUNT(*) FROM `groups` WHERE category = 'COMPETITION' AND is_active = 1"),
        ], 'Trainersplanning competitie');
    }

    /** Afmelden, terugzetten, overnemen, toewijzen of weghalen voor één training of wedstrijd. */
    public static function action(): void
    {
        self::requireView();
        $aid = (int) input('activity_id', '0');
        $back = input('back') === 'activity' ? url('activity', ['id' => $aid]) . '#trainers' : url('coverage', array_filter(['weeks' => input('weeks'), 'show' => input('show')])) . '#a' . $aid;
        $a = Coverage::one($aid);
        if (!$a) {
            abort(404, 'Deze training of wedstrijd hoort niet bij de trainersplanning van de competitie.');
        }
        if ($a['ends_at'] <= now()) {
            flash('error', 'Deze training of wedstrijd is al voorbij.');
            redirect($back);
        }
        $me = (int) Auth::user()['id'];
        $do = input('do');
        $target = $do === 'claim' ? $me : (int) input('user_id', (string) $me);
        $pool = Coverage::pool();
        $current = [];
        foreach ($a['trainers'] as $t) {
            $current[$t['id']] = $t;
        }
        $name = $pool[$target] ?? ($current[$target]['name'] ?? '');
        $isDefault = Coverage::isDefault($a, $target);
        $self = $target === $me;

        switch ($do) {
            case 'absent':
                if (!isset($current[$target]) || $current[$target]['absent']) {
                    flash('error', 'Deze trainer staat niet (meer) ingepland.');
                    redirect($back);
                }
                $reason = mb_substr(input('note'), 0, 200);
                Coverage::set($aid, $target, 'ABSENT', $reason);
                $msg = $self ? 'Je bent afgemeld.' : "$name is afgemeld.";
                break;
            case 'back':
                if (!isset($current[$target]) || !$current[$target]['absent']) {
                    flash('error', 'Deze trainer is niet afgemeld.');
                    redirect($back);
                }
                $isDefault ? Coverage::set($aid, $target, null) : Coverage::set($aid, $target, 'ASSIGNED');
                $msg = $self ? 'Je staat weer ingepland.' : "$name staat weer ingepland.";
                break;
            case 'claim':
            case 'assign':
                if (!isset($pool[$target])) {
                    flash('error', 'Kies een trainer van de competitie.');
                    redirect($back);
                }
                if (isset($current[$target]) && !$current[$target]['absent']) {
                    flash('info', $self ? 'Je staat al ingepland.' : "$name staat al ingepland.");
                    redirect($back);
                }
                $isDefault ? Coverage::set($aid, $target, null) : Coverage::set($aid, $target, 'ASSIGNED');
                $msg = $self ? 'Bedankt! Je staat ingepland.' : "$name is ingepland.";
                break;
            case 'remove':
                if (!isset($current[$target]) || $current[$target]['default']) {
                    flash('error', 'Een vaste trainer van het blok haal je weg met "Afmelden".');
                    redirect($back);
                }
                Coverage::set($aid, $target, null);
                $msg = "$name is weggehaald.";
                break;
            default:
                abort(400, 'Onbekende actie.');
        }
        audit('COVERAGE_' . strtoupper($do), 'activity', $aid, $name);

        $after = Coverage::one($aid);
        if ($after && $after['missing'] > 0 && $after['starts_at'] < date('Y-m-d H:i:s', strtotime('+' . Coverage::remindDays() . ' days'))) {
            $n = Coverage::remind();
            $msg .= $n ? ' Er ontbreekt nu een trainer: de coördinator en de competitietrainers kregen een mail.' : ' Er ontbreekt nog een trainer.';
        } elseif ($after && $after['missing'] > 0) {
            $msg .= ' Er ontbreekt nog een trainer; staat dat binnen ' . Coverage::remindDays() . ' dagen nog open, dan volgt automatisch een mail.';
        }
        flash('success', $msg);
        redirect($back);
    }

    /**
     * Pagina "Meerdere tegelijk": stap 1 kies trainer + actie + welke trainingen (groep, dag, periode),
     * stap 2 een lijst met wat er zal veranderen (vooraf aangevinkt), stap 3 bevestigen (POST naar bulk()).
     */
    public static function bulkForm(): void
    {
        self::requireView();
        $pool = Coverage::pool();
        $f = self::bulkFilters($_GET);
        $uid = (int) Auth::user()['id'];
        if (!isset($_GET['user_id']) && isset($pool[$uid])) {
            $f['user_id'] = $uid;
        }
        $groups = Database::all("SELECT id, name FROM `groups` WHERE is_active = 1 ORDER BY name");
        $rows = [];
        $target = (int) ($f['user_id'] ?? 0);
        if (isset($pool[$target]) && isset($_GET['show'])) {
            foreach (Coverage::list($f['from'] . ' 00:00:00', date('Y-m-d', strtotime($f['to'] . ' +1 day')) . ' 00:00:00') as $a) {
                if ($a['ends_at'] <= now()) {
                    continue;
                }
                if ($f['g'] && !in_array($f['g'], array_map('intval', array_column($a['groups'], 'id')), true)) {
                    continue;
                }
                if ($f['d'] && (int) date('N', strtotime(substr($a['starts_at'], 0, 10))) !== $f['d']) {
                    continue;
                }
                $cur = null;
                foreach ($a['trainers'] as $t) {
                    if ($t['id'] === $target) {
                        $cur = $t;
                    }
                }
                if ($f['do'] === 'assign') {
                    $a['skip'] = $cur && !$cur['absent'] ? 'staat al ingepland' : null;
                } else {
                    $a['skip'] = !$cur || $cur['absent'] ? ($cur ? 'al afgemeld' : 'staat niet ingepland') : null;
                }
                $rows[] = $a;
            }
        }
        render('coverage/bulk', ['pool' => $pool, 'f' => $f, 'groups' => $groups, 'rows' => $rows, 'shown' => isset($_GET['show']) && isset($pool[$target])], 'Meerdere tegelijk');
    }

    /** Gevalideerde filters voor "Meerdere tegelijk" (uit GET of POST). */
    private static function bulkFilters(array $in): array
    {
        $from = valid_date((string) ($in['from'] ?? '')) ? (string) $in['from'] : today();
        $to = valid_date((string) ($in['to'] ?? '')) ? (string) $in['to'] : date('Y-m-d', strtotime('+4 weeks'));
        if ($to < $from) {
            $to = $from;
        }
        if ((strtotime($to) - strtotime($from)) / 86400 > 366) {
            $to = date('Y-m-d', strtotime($from . ' +1 year'));
        }
        $out = ['user_id' => (int) ($in['user_id'] ?? 0), 'do' => ($in['do'] ?? '') === 'absent' ? 'absent' : 'assign',
                'g' => (int) ($in['g'] ?? 0), 'd' => max(0, min(7, (int) ($in['d'] ?? 0))), 'from' => $from, 'to' => $to];
        return array_filter($out, fn($v) => $v !== 0) + ['g' => 0, 'd' => 0];
    }

    /**
     * Bulk: één trainer inplannen of afmelden voor meerdere trainingen/wedstrijden tegelijk (bv. een blok overnemen,
     * of afwezig tijdens een vakantie). Wat niet kan (voorbij, al ingepland, niet ingepland) wordt overgeslagen.
     */
    public static function bulk(): void
    {
        self::requireView();
        $back = url('coverage_bulk_form', self::bulkFilters($_POST) + ['show' => 1]);
        $ids = array_values(array_unique(array_filter(array_map('intval', (array) ($_POST['ids'] ?? [])))));
        $do = input('do');
        $target = (int) input('user_id', '0');
        $pool = Coverage::pool();
        if (!in_array($do, ['assign', 'absent'], true)) {
            abort(400, 'Onbekende actie.');
        }
        if (!$ids) {
            flash('error', 'Er was niets aangevinkt; er is niets gewijzigd.');
            redirect($back);
        }
        if (!isset($pool[$target])) {
            flash('error', 'Kies een trainer van de competitie.');
            redirect($back);
        }
        if (count($ids) > 200) {
            flash('error', 'Kies maximaal 200 trainingen of wedstrijden tegelijk.');
            redirect($back);
        }
        $note = mb_substr(input('note'), 0, 200);
        $done = 0;
        $alertSoon = false;
        $soon = date('Y-m-d H:i:s', strtotime('+' . Coverage::remindDays() . ' days'));
        Database::tx(function () use ($ids, $do, $target, $note, &$done) {
            foreach (Coverage::list('0000-00-00', '9999-12-31', $ids) as $a) {
                if ($a['ends_at'] <= now()) {
                    continue;
                }
                $cur = null;
                foreach ($a['trainers'] as $t) {
                    if ($t['id'] === $target) {
                        $cur = $t;
                    }
                }
                $default = Coverage::isDefault($a, $target);
                if ($do === 'assign') {
                    if ($cur && !$cur['absent']) {
                        continue;
                    }
                    $default ? Coverage::set((int) $a['id'], $target, null) : Coverage::set((int) $a['id'], $target, 'ASSIGNED');
                } else {
                    if (!$cur || $cur['absent']) {
                        continue;
                    }
                    Coverage::set((int) $a['id'], $target, 'ABSENT', $note);
                }
                $done++;
            }
        });
        $skipped = count($ids) - $done; // al (niet) ingepland, voorbij of niet in de planning
        audit('COVERAGE_BULK_' . strtoupper($do), 'user', $target, $done . ' activiteiten');
        $name = $pool[$target];
        $msg = $do === 'assign'
            ? "$name is ingepland voor $done training(en) of wedstrijd(en)."
            : "$name is afgemeld voor $done training(en) of wedstrijd(en).";
        if ($skipped) {
            $msg .= " $skipped overgeslagen (" . ($do === 'assign' ? 'al ingepland' : 'niet ingepland') . ', voorbij of niet in de planning).';
        }
        if ($do === 'absent' && $done) {
            foreach (Coverage::list(now(), $soon, $ids) as $a) {
                $alertSoon = $alertSoon || $a['missing'] > 0;
            }
            if ($alertSoon && Coverage::remind()) {
                $msg .= ' Er ontbreekt nu binnenkort een trainer: de coördinator en de competitietrainers kregen een mail.';
            }
        }
        flash($done ? 'success' : 'info', $msg);
        redirect($back);
    }

    public static function settings(): void
    {
        $groups = Database::all("SELECT id, name, min_trainers, share_token, color FROM `groups` WHERE category = 'COMPETITION' AND is_active = 1 ORDER BY name");
        $series = Database::all(
            "SELECT DISTINCT s.id, s.title, s.start_date, s.end_date FROM activity_series s
             JOIN activity_series_groups sg ON sg.series_id = s.id JOIN `groups` g ON g.id = sg.group_id
             WHERE s.is_active = 1 AND s.end_date >= ? AND g.category = 'COMPETITION' AND g.is_active = 1 ORDER BY s.title",
            [today()]
        );
        foreach ($series as &$s) {
            $s['slots'] = Database::all('SELECT weekday, start_time, end_time, location FROM activity_series_slots WHERE series_id = ? ORDER BY weekday', [$s['id']]);
            $s['groups'] = implode(', ', array_column(Database::all('SELECT g.name FROM activity_series_groups sg JOIN `groups` g ON g.id = sg.group_id WHERE sg.series_id = ? ORDER BY g.name', [$s['id']]), 'name'));
            $s['trainers'] = [];
            foreach (Database::all('SELECT weekday, user_id FROM series_trainers WHERE series_id = ?', [$s['id']]) as $t) {
                $s['trainers'][(int) $t['weekday']][] = (int) $t['user_id'];
            }
        }
        unset($s);
        render('coverage/settings', [
            'groups' => $groups,
            'series' => $series,
            'pool' => Coverage::pool(),
            'base' => Invites::baseUrl(),
            'remindDays' => Coverage::remindDays(),
        ], 'Trainersplanning: instellingen');
    }

    /** Vaste trainers per blok en het minimum aantal trainers per competitiegroep. */
    public static function settingsSave(): void
    {
        $pool = Coverage::pool();
        $min = $_POST['min'] ?? [];
        $slots = $_POST['slot'] ?? [];
        if (!is_array($min) || !is_array($slots)) {
            abort(400, 'Ongeldige aanvraag.');
        }
        $compGroups = array_map('intval', array_column(Database::all("SELECT id FROM `groups` WHERE category = 'COMPETITION'"), 'id'));
        $errors = [];
        foreach ($min as $gid => $v) {
            $v = trim((string) $v);
            if (!in_array((int) $gid, $compGroups, true) || ($v !== '' && (!ctype_digit($v) || (int) $v < 1 || (int) $v > 9))) {
                $errors[] = 'Het minimum aantal trainers is een getal van 1 tot 9 (leeg = 1).';
                break;
            }
        }
        $clean = [];
        foreach ($slots as $sid => $days) {
            $sid = (int) $sid;
            if (!is_array($days) || !Database::val(
                "SELECT 1 FROM activity_series_groups sg JOIN `groups` g ON g.id = sg.group_id WHERE sg.series_id = ? AND g.category = 'COMPETITION'",
                [$sid]
            )) {
                abort(400, 'Ongeldig trainingsblok.');
            }
            $valid = array_map('intval', array_column(Database::all('SELECT weekday FROM activity_series_slots WHERE series_id = ?', [$sid]), 'weekday'));
            $clean[$sid] = [];
            foreach ($days as $wd => $uids) {
                if (!in_array((int) $wd, $valid, true) || !is_array($uids)) {
                    abort(400, 'Ongeldige weekdag.');
                }
                foreach ($uids as $uid) {
                    if ($uid === '' || $uid === '0') {
                        continue;
                    }
                    if (!isset($pool[(int) $uid])) {
                        $errors[] = 'Enkel trainers van de competitie kunnen vaste trainer van een blok zijn.';
                        break 3;
                    }
                    $clean[$sid][(int) $wd][(int) $uid] = true;
                }
            }
        }
        if ($errors) {
            flash('error', $errors[0]);
            redirect(url('coverage_settings'));
        }
        Database::tx(function () use ($min, $clean) {
            foreach ($min as $gid => $v) {
                $v = trim((string) $v);
                Database::exec('UPDATE `groups` SET min_trainers = ? WHERE id = ?', [$v === '' ? null : (int) $v, (int) $gid]);
            }
            foreach ($clean as $sid => $days) {
                Database::exec('DELETE FROM series_trainers WHERE series_id = ?', [$sid]);
                foreach ($days as $wd => $uids) {
                    foreach (array_keys($uids) as $uid) {
                        Database::exec('INSERT INTO series_trainers (series_id, weekday, user_id) VALUES (?,?,?)', [$sid, $wd, $uid]);
                    }
                }
            }
        });
        audit('COVERAGE_SETTINGS', 'coverage', null, count($clean) . ' blokken');
        flash('success', 'Instellingen opgeslagen. De vaste trainers gelden meteen voor alle komende trainingen van die blokken.');
        redirect(url('coverage_settings'));
    }

    /** Deelbare weekplanning per competitiegroep aanmaken, vernieuwen of uitzetten. */
    public static function share(): void
    {
        $gid = (int) input('group_id', '0');
        if (Database::val('SELECT category FROM `groups` WHERE id = ?', [$gid]) !== 'COMPETITION') {
            abort(400, 'Enkel voor competitiegroepen.');
        }
        $off = input('do') === 'remove';
        Database::exec('UPDATE `groups` SET share_token = ? WHERE id = ?', [$off ? null : bin2hex(random_bytes(16)), $gid]);
        audit($off ? 'SHARE_OFF' : 'SHARE_ON', 'group', $gid);
        flash('success', $off ? 'De deellink werkt niet meer.' : 'Nieuwe deellink gemaakt. Een vorige link van deze groep werkt niet meer.');
        redirect(url('coverage_settings') . '#delen');
    }

    public static function remindNow(): void
    {
        $n = Coverage::remind(true);
        audit('COVERAGE_REMIND', 'coverage', null, (string) $n);
        flash($n ? 'success' : 'info', $n
            ? "Mail verstuurd voor $n training(en) of wedstrijd(en) zonder genoeg trainers."
            : 'Er ontbreekt geen trainer in de komende ' . Coverage::remindDays() . ' dagen; er is niets verstuurd.');
        redirect(url('coverage'));
    }

    /** Publieke, deelbare weekplanning van één competitiegroep (zonder namen van trainers of leden). */
    public static function week(): void
    {
        header('X-Robots-Tag: noindex, nofollow');
        header('Cache-Control: no-store');
        $t = (string) ($_GET['t'] ?? '');
        $g = preg_match('/^[a-f0-9]{32}$/', $t) ? Database::one("SELECT id, name, color FROM `groups` WHERE share_token = ? AND category = 'COMPETITION' AND is_active = 1", [$t]) : null;
        if (!$g) {
            abort(404, 'Deze link werkt niet (meer). Vraag een nieuwe link aan de trainer of coördinator.');
        }
        $rows = Database::all(
            "SELECT a.title, a.starts_at, a.ends_at, a.location, a.status, a.status_note, t.code AS type_code, t.name AS type_name
             FROM activities a JOIN activity_types t ON t.id = a.type_id
             WHERE a.starts_at >= ? AND a.starts_at < ? AND t.code IN ('TRAINING','COMPETITION','CLUB')
               AND (EXISTS (SELECT 1 FROM activity_groups ag WHERE ag.activity_id = a.id AND ag.group_id = ?) OR a.audience = 'CLUB')
             ORDER BY a.starts_at",
            [today() . ' 00:00:00', date('Y-m-d 00:00:00', strtotime('+3 weeks')), $g['id']]
        );
        $byWeek = [];
        foreach ($rows as $r) {
            $byWeek[date('Y-m-d', strtotime('monday this week', strtotime(substr($r['starts_at'], 0, 10))))][] = $r;
        }
        render('coverage/week', ['group' => $g, 'byWeek' => $byWeek], 'Weekplanning ' . $g['name']);
    }

    private static function requireView(): void
    {
        if (!Coverage::canView()) {
            abort(403, 'De trainersplanning is er voor de trainers en de coördinator van de competitie.');
        }
    }
}
