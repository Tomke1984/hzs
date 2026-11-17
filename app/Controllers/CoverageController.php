<?php
/**
 * Trainersplanning competitie (/app/trainersplanning): overzicht van trainingen en wedstrijden met hun trainers,
 * afmelden, overnemen en toewijzen. Toegang: coördinator, bestuur, beheerder (coverage.manage) en elke trainer van
 * een groep (zij kunnen de coördinatie overnemen wanneer nodig). De vaste trainers zijn de groepstrainers; het minimum per
 * groep, deellinks en herinneringen vragen coverage.manage.
 */
final class CoverageController
{
    public static function index(): void
    {
        self::requireView();
        $weeks = max(1, min(8, (int) ($_GET['weeks'] ?? 4)));
        $show = in_array($_GET['show'] ?? '', ['problems', 'mine'], true) ? $_GET['show'] : '';
        // Weergave: lijst (per week, met acties) of weekkalender. De laatste keuze wordt onthouden.
        $view = in_array($_GET['view'] ?? '', ['list', 'week'], true) ? $_GET['view'] : ($_SESSION['coverage_view'] ?? 'week');
        $_SESSION['coverage_view'] = $view;
        $offset = max(-8, min(52, (int) ($_GET['w'] ?? 0)));
        $monday = week_monday($offset);
        if ($view === 'week') {
            $from = $monday . ' 00:00:00';
            $to = date('Y-m-d 00:00:00', strtotime("$monday +7 days"));
        } else {
            $from = today() . ' 00:00:00';
            $to = date('Y-m-d 00:00:00', strtotime("+$weeks weeks"));
        }
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
            $wk = date('Y-m-d', strtotime('monday this week', strtotime(substr($a['starts_at'], 0, 10))));
            $byWeek[$wk][] = $a;
        }
        $byDay = [];
        foreach ($rows as $a) {
            $byDay[substr($a['starts_at'], 0, 10)][] = $a;
        }
        render('coverage/index', [
            'byWeek' => $byWeek,
            'byDay' => $byDay,
            'mode' => $view,
            'offset' => $offset,
            'monday' => $monday,
            'shortages' => Coverage::shortages(),
            'nextShort' => Coverage::shortages() ? null : Coverage::nextShortage(),
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

        // Meerdere trainers tegelijk inplannen (vooral voor wedstrijden): user_ids[]
        $many = $do === 'assign' && (isset($_POST['user_ids']) || input('multi') === '1') ? array_values(array_unique(array_map('intval', (array) ($_POST['user_ids'] ?? [])))) : null;
        if ($many !== null) {
            $done = [];
            $notes = [];
            $clashes = 0;
            foreach ($many as $uid) {
                if (!isset($pool[$uid])) {
                    continue;
                }
                if (isset($current[$uid]) && !$current[$uid]['absent']) {
                    $notes[] = $pool[$uid] . ' staat al ingepland.';
                    continue;
                }
                if ($c = Coverage::conflict($a, $uid)) {
                    $notes[] = 'Niet ingepland: ' . $c;
                    continue;
                }
                Coverage::isDefault($a, $uid) ? Coverage::set($aid, $uid, null) : Coverage::set($aid, $uid, 'ASSIGNED');
                audit('COVERAGE_ASSIGN', 'activity', $aid, $pool[$uid]);
                $done[] = $pool[$uid];
                if ($a['type_code'] === 'COMPETITION') {
                    $clashes += self::meetClashes($a, $uid);
                }
            }
            if (!$done && !$notes) {
                flash('error', 'Vink minstens één trainer aan.');
                redirect($back);
            }
            if (!$done) {
                flash('error', implode(' ', $notes));
                redirect($back);
            }
            $msg = implode(', ', $done) . (count($done) === 1 ? ' is' : ' zijn') . ' ingepland.' . ($notes ? ' ' . implode(' ', $notes) : '');
            if ($clashes) {
                $msg .= " Voor $clashes training(en) op hetzelfde moment staan ze nu als \"op wedstrijd\" afgemeld.";
            }
            $do = 'many';
        }

        $msg ??= '';
        switch ($do) {
            case 'many':
                break;
            case 'absent':
                if (!isset($current[$target]) || $current[$target]['absent']) {
                    flash('error', 'Deze trainer staat niet (meer) ingepland.');
                    redirect($back);
                }
                // Vervanger: verplicht kiezen als er anders een trainer tekort is (een collega of bewust "nog geen vervanger").
                $rep = input('replacement');
                $short = $a['present'] - 1 < $a['required'];
                if ($rep === '' && $short) {
                    flash('error', 'Kies wie de training overneemt, of kies "Nog geen vervanger". Er is anders een trainer te weinig.');
                    redirect($back);
                }
                $repId = ctype_digit($rep) ? (int) $rep : 0;
                if ($repId) {
                    if (!isset($pool[$repId]) || $repId === $target || (isset($current[$repId]) && !$current[$repId]['absent'])) {
                        flash('error', 'Kies als vervanger een andere trainer die nog niet ingepland is.');
                        redirect($back);
                    }
                    if ($c = Coverage::conflict($a, $repId)) {
                        flash('error', 'Vervanger niet mogelijk: ' . $c);
                        redirect($back);
                    }
                }
                $reason = mb_substr(input('note'), 0, 200);
                Database::tx(function () use ($aid, $a, $target, $reason, $repId) {
                    Coverage::set($aid, $target, 'ABSENT', $reason);
                    if ($repId) {
                        Coverage::isDefault($a, $repId) ? Coverage::set($aid, $repId, null) : Coverage::set($aid, $repId, 'ASSIGNED');
                    }
                });
                $msg = ($self ? 'Je bent afgemeld.' : "$name is afgemeld.") . ($repId ? ' ' . $pool[$repId] . ' neemt over.' : '');
                if ($repId) {
                    audit('COVERAGE_ASSIGN', 'activity', $aid, $pool[$repId] . ' (vervanger)');
                }
                break;
            case 'back':
                if (!isset($current[$target]) || !$current[$target]['absent']) {
                    flash('error', 'Deze trainer is niet afgemeld.');
                    redirect($back);
                }
                if ($current[$target]['auto']) {
                    flash('error', "$name staat op dat moment op een wedstrijd. Haal hem eerst bij de wedstrijd weg.");
                    redirect($back);
                }
                if ($c = Coverage::conflict($a, $target)) {
                    flash('error', $c);
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
                if ($c = Coverage::conflict($a, $target)) {
                    flash('error', 'Niet ingepland: ' . $c);
                    redirect($back);
                }
                $isDefault ? Coverage::set($aid, $target, null) : Coverage::set($aid, $target, 'ASSIGNED');
                $msg = $self ? 'Bedankt! Je staat ingepland.' : "$name is ingepland.";
                if ($a['type_code'] === 'COMPETITION' && ($n = self::meetClashes($a, $target))) {
                    $msg .= " Voor $n training(en) op hetzelfde moment staat " . ($self ? 'je' : $name) . ' nu als "op wedstrijd" afgemeld.';
                }
                break;
            case 'remove':
                if (!isset($current[$target]) || $current[$target]['default']) {
                    flash('error', 'Een vaste trainer van de groep haal je weg met "Afmelden".');
                    redirect($back);
                }
                Coverage::set($aid, $target, null);
                $msg = "$name is weggehaald.";
                break;
            default:
                abort(400, 'Onbekende actie.');
        }
        if ($do !== 'many') {
            audit('COVERAGE_' . strtoupper($do), 'activity', $aid, $name);
        }

        $after = Coverage::one($aid);
        $mailed = Coverage::remind(); // ook tekorten elders (bv. trainingen tijdens een wedstrijd)
        if ($after && $after['missing'] > 0) {
            $soon = $after['starts_at'] < date('Y-m-d H:i:s', strtotime('+' . Coverage::remindDays() . ' days'));
            $msg .= $soon
                ? ($mailed ? ' Er ontbreekt nu een trainer: de coördinator en de competitietrainers kregen een mail.' : ' Er ontbreekt nog een trainer.')
                : ' Er ontbreekt nog een trainer; staat dat binnen ' . Coverage::remindDays() . ' dagen nog open, dan volgt automatisch een mail.';
        } elseif ($mailed) {
            $msg .= ' Er ontbreekt nu een trainer bij een training op hetzelfde moment: de coördinator en de competitietrainers kregen een mail.';
        }
        flash('success', $msg);
        redirect($back);
    }

    /** Aantal trainingen waar de trainer door deze wedstrijd nu "op wedstrijd" staat. */
    private static function meetClashes(array $meet, int $userId): int
    {
        $ids = array_map('intval', array_column(Database::all(
            "SELECT id FROM activities WHERE id <> ? AND status = 'PLANNED' AND starts_at < ? AND ends_at > ?",
            [(int) $meet['id'], $meet['ends_at'], $meet['starts_at']]
        ), 'id'));
        $n = 0;
        foreach ($ids ? Coverage::list('0000-00-00', '9999-12-31', $ids) : [] as $o) {
            foreach ($o['trainers'] as $t) {
                $n += ($t['id'] === $userId && $t['auto']) ? 1 : 0;
            }
        }
        return $n;
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
                    $a['skip'] = $cur && !$cur['absent'] ? 'staat al ingepland' : (Coverage::conflict($a, $target) ? 'staat dan al op een andere training of wedstrijd' : null);
                } else {
                    $a['skip'] = !$cur || $cur['absent'] ? ($cur ? 'al afgemeld' : 'staat niet ingepland') : null;
                }
                $rows[] = $a;
            }
        }
        render('coverage/bulk', ['pool' => $pool, 'f' => $f, 'free' => $pool, 'groups' => $groups, 'rows' => $rows, 'shown' => isset($_GET['show']) && isset($pool[$target])], 'Meerdere tegelijk');
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
        $rep = $do === 'absent' ? input('replacement') : '';
        if ($do === 'absent' && $rep === '') {
            flash('error', 'Kies wie overneemt, of kies "Nog geen vervanger".');
            redirect($back);
        }
        $repId = ctype_digit($rep) ? (int) $rep : 0;
        if ($repId && (!isset($pool[$repId]) || $repId === $target)) {
            flash('error', 'Kies als vervanger een andere trainer.');
            redirect($back);
        }
        $done = 0;
        $clash = [];
        $repDone = 0;
        $repFail = [];
        Database::tx(function () use ($ids, $do, $target, $note, $repId, &$done, &$clash, &$repDone, &$repFail) {
            foreach (Coverage::list('0000-00-00', '9999-12-31', $ids) as $a) {
                if ($a['ends_at'] <= now()) {
                    continue;
                }
                $cur = null;
                $repCur = null;
                foreach ($a['trainers'] as $t) {
                    $cur = $t['id'] === $target ? $t : $cur;
                    $repCur = $t['id'] === $repId ? $t : $repCur;
                }
                $when = fmt_date($a['starts_at']) . ' ' . fmt_time($a['starts_at']);
                if ($do === 'assign') {
                    if ($cur && !$cur['absent']) {
                        continue;
                    }
                    if (Coverage::conflict($a, $target)) {
                        $clash[] = $when;
                        continue;
                    }
                    Coverage::isDefault($a, $target) ? Coverage::set((int) $a['id'], $target, null) : Coverage::set((int) $a['id'], $target, 'ASSIGNED');
                } else {
                    if (!$cur || $cur['absent']) {
                        continue;
                    }
                    Coverage::set((int) $a['id'], $target, 'ABSENT', $note);
                    if ($repId) {
                        if (($repCur && !$repCur['absent']) || Coverage::conflict($a, $repId)) {
                            $repFail[] = $when;
                        } else {
                            Coverage::isDefault($a, $repId) ? Coverage::set((int) $a['id'], $repId, null) : Coverage::set((int) $a['id'], $repId, 'ASSIGNED');
                            $repDone++;
                        }
                    }
                }
                $done++;
            }
        });
        $skipped = count($ids) - $done - count($clash);
        audit('COVERAGE_BULK_' . strtoupper($do), 'user', $target, $done . ' activiteiten' . ($repId ? ', vervanger ' . $pool[$repId] : ''));
        $name = $pool[$target];
        $msg = $do === 'assign'
            ? "$name is ingepland voor $done training(en) of wedstrijd(en)."
            : "$name is afgemeld voor $done training(en) of wedstrijd(en).";
        if ($repId && $repDone) {
            $msg .= ' ' . $pool[$repId] . " neemt $repDone daarvan over.";
        }
        if ($skipped) {
            $msg .= " $skipped overgeslagen (" . ($do === 'assign' ? 'al ingepland' : 'niet ingepland') . ', voorbij of niet in de planning).';
        }
        if ($clash) {
            $msg .= ' Niet ingepland wegens een andere training of wedstrijd op hetzelfde moment: ' . implode(', ', $clash) . '.';
        }
        if ($repFail) {
            $msg .= ' ' . $pool[$repId] . ' kon niet overnemen op ' . implode(', ', $repFail) . ' (staat dan al elders): daar wordt nog een trainer gezocht.';
        }
        if (Coverage::remind()) {
            $msg .= ' Er ontbreekt nu binnenkort een trainer: de coördinator en de competitietrainers kregen een mail.';
        }
        flash($done ? 'success' : 'info', $msg);
        redirect($back);
    }

    public static function settings(): void
    {
        $groups = Database::all("SELECT id, name, min_trainers, share_token, share_expires, color FROM `groups` WHERE is_active = 1 ORDER BY name");
        foreach ($groups as &$g) {
            $g['trainers'] = array_column(Database::all(
                'SELECT u.display_name FROM group_trainers gt JOIN users u ON u.id = gt.user_id WHERE gt.group_id = ? AND u.is_active = 1 ORDER BY u.display_name',
                [$g['id']]
            ), 'display_name');
        }
        unset($g);
        render('coverage/settings', [
            'groups' => $groups,
            'base' => Invites::baseUrl(),
            'remindDays' => Coverage::remindDays(),
        ], 'Trainersplanning: instellingen');
    }

    /** Minimum aantal trainers per groep. (De vaste trainers zijn de trainers van de groep: in te stellen bij Groep wijzigen.) */
    public static function settingsSave(): void
    {
        $min = $_POST['min'] ?? [];
        if (!is_array($min)) {
            abort(400, 'Ongeldige aanvraag.');
        }
        $known = array_map('intval', array_column(Database::all('SELECT id FROM `groups`'), 'id'));
        foreach ($min as $gid => $v) {
            $v = trim((string) $v);
            if (!in_array((int) $gid, $known, true) || ($v !== '' && (!ctype_digit($v) || (int) $v < 1 || (int) $v > 9))) {
                flash('error', 'Het minimum aantal trainers is een getal van 1 tot 9 (leeg = 1).');
                redirect(url('coverage_settings'));
            }
        }
        Database::tx(function () use ($min) {
            foreach ($min as $gid => $v) {
                $v = trim((string) $v);
                Database::exec('UPDATE `groups` SET min_trainers = ? WHERE id = ?', [$v === '' ? null : (int) $v, (int) $gid]);
            }
        });
        audit('COVERAGE_SETTINGS', 'coverage', null, 'minimum per groep');
        flash('success', 'Instellingen opgeslagen.');
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
        Database::exec('UPDATE `groups` SET share_token = ?, share_expires = ? WHERE id = ?', $off
            ? [null, null, $gid]
            : [bin2hex(random_bytes(16)), date('Y-m-d', strtotime('+' . share_link_days() . ' days')), $gid]);
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
        $g = preg_match('/^[a-f0-9]{32}$/', $t) ? Database::one("SELECT id, name, color, share_expires FROM `groups` WHERE share_token = ? AND category = 'COMPETITION' AND is_active = 1", [$t]) : null;
        if (!$g) {
            abort(404, 'Deze link werkt niet (meer). Vraag een nieuwe link aan de trainer of coördinator.');
        }
        if ($g['share_expires'] !== null && $g['share_expires'] < today()) {
            abort(410, 'Deze link is verlopen. Vraag een nieuwe link aan de trainer of coördinator.');
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
