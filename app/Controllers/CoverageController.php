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
        $uid = (int) Auth::user()['id'];
        $pool = Coverage::pool();
        $canManage = Auth::can('coverage.manage');
        // Weergave: Mijn trainingen (trainer), Raster (weken × groepen, coördinator), Week of Lijst. De laatste keuze wordt onthouden.
        $views = isset($pool[$uid]) ? ['mine', 'grid', 'week', 'list'] : ['grid', 'week', 'list'];
        $view = $_GET['view'] ?? ($_SESSION['coverage_view'] ?? ($canManage ? 'grid' : 'mine'));
        $view = in_array($view, $views, true) ? $view : $views[0];
        $_SESSION['coverage_view'] = $view;
        $offset = max(-8, min(52, (int) ($_GET['w'] ?? 0)));
        $monday = week_monday($offset);
        if ($view === 'mine') {
            self::mine($uid, $pool, $canManage);
            return;
        }
        if ($view === 'grid') {
            self::grid($offset, $monday, $pool, $canManage, isset($pool[$uid]));
            return;
        }
        if ($view === 'week') {
            $from = $monday . ' 00:00:00';
            $to = date('Y-m-d 00:00:00', strtotime("$monday +7 days"));
        } else {
            $from = today() . ' 00:00:00';
            $to = date('Y-m-d 00:00:00', strtotime("+$weeks weeks"));
        }
        $all = Coverage::list($from, $to);

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
            'pool' => $pool,
            'uid' => $uid,
            'weeks' => $weeks,
            'show' => $show,
            'canManage' => $canManage,
            'hasMine' => isset($pool[$uid]),
            'remindDays' => Coverage::remindDays(),
            'noCompGroups' => !(int) Database::val("SELECT COUNT(*) FROM `groups` WHERE category = 'COMPETITION' AND is_active = 1"),
        ], 'Trainersplanning competitie');
    }

    /** Hoe ver "Mijn trainingen" en "Hier kan je helpen" vooruit kijken. */
    private const MINE_WEEKS = 6;

    /**
     * Mijn trainingen: de komende trainingen en wedstrijden van de ingelogde trainer, elk met één knop "Ik kan niet",
     * en daaronder waar een trainer ontbreekt op een moment dat hij vrij is ("Hier kan je helpen").
     */
    private static function mine(int $uid, array $pool, bool $canManage): void
    {
        $all = Coverage::list(date('Y-m-d H:i:s'), date('Y-m-d 00:00:00', strtotime('+' . self::MINE_WEEKS . ' weeks')));
        $mine = [];
        $help = [];
        foreach ($all as $a) {
            $me = null;
            foreach ($a['trainers'] as $t) {
                if ($t['id'] === $uid) {
                    $me = $t;
                }
            }
            if ($me) {
                $a['me'] = $me;
                $a['others'] = array_values(array_map(fn($t) => $t['name'], array_filter($a['trainers'], fn($t) => $t['id'] !== $uid && !$t['absent'])));
                if (!$me['absent']) {
                    // Wie kan overnemen als ik niet kan?
                    $a['free'] = Coverage::free($a, $pool);
                    $a['shortIfOff'] = $a['present'] - 1 < $a['required'];
                }
                $mine[] = $a;
            } elseif ($a['missing'] > 0 && isset($pool[$uid]) && !isset(Coverage::busy($a)[$uid])) {
                $help[] = $a;
            }
        }
        render('coverage/mine', [
            'mine' => $mine,
            'help' => $help,
            'uid' => $uid,
            'pool' => $pool,
            'canManage' => $canManage,
            'cannot' => (int) ($_GET['cannot'] ?? 0),
            'weeksAhead' => self::MINE_WEEKS,
            'remindDays' => Coverage::remindDays(),
        ], 'Mijn trainingen');
    }

    /** Aantal weken in het raster. */
    private const GRID_WEEKS = 5;

    /**
     * Raster voor de coördinator: weken × groepen (plus een rij wedstrijden), gekleurd volgens de bezetting.
     * Klik op een training of wedstrijd: rechts staan wie ingepland is en wie op dat moment vrij is.
     */
    private static function grid(int $offset, string $monday, array $pool, bool $canManage, bool $hasMine): void
    {
        $weeks = [];
        for ($i = 0; $i < self::GRID_WEEKS; $i++) {
            $weeks[] = date('Y-m-d', strtotime("$monday +$i weeks"));
        }
        $all = Coverage::list($monday . ' 00:00:00', date('Y-m-d 00:00:00', strtotime("$monday +" . self::GRID_WEEKS . ' weeks')));
        $groups = Database::all("SELECT id, name, color FROM `groups` WHERE category = 'COMPETITION' AND is_active = 1 ORDER BY name");
        $cells = []; // [rij][maandag] => activiteiten; rij = groeps-id of 'meet'
        $short = 0;
        foreach ($all as $a) {
            $wk = date('Y-m-d', strtotime('monday this week', strtotime(substr($a['starts_at'], 0, 10))));
            if ($a['type_code'] === 'COMPETITION') {
                $cells['meet'][$wk][] = $a;
            } else {
                foreach ($a['groups'] as $g) {
                    $cells[(int) $g['id']][$wk][] = $a;
                }
            }
            if ($a['missing'] > 0 && $a['ends_at'] > now()) {
                $short++;
            }
        }
        $sel = null;
        $selFree = [];
        $selBusy = [];
        $selId = (int) ($_GET['sel'] ?? 0);
        if ($selId && ($sel = Coverage::one($selId))) {
            $busy = Coverage::busy($sel);
            $selFree = Coverage::free($sel, $pool, $busy);
            $listed = array_flip(array_map(fn($t) => $t['id'], $sel['trainers']));
            foreach ($pool as $id => $name) {
                if (isset($busy[$id]) && !isset($listed[$id])) {
                    $selBusy[] = $name . ' (' . $busy[$id] . ')';
                }
            }
        }
        render('coverage/grid', [
            'weeks' => $weeks,
            'groups' => $groups,
            'cells' => $cells,
            'short' => $short,
            'offset' => $offset,
            'monday' => $monday,
            'sel' => $sel,
            'selFree' => $selFree,
            'selBusy' => $selBusy,
            'pool' => $pool,
            'uid' => (int) Auth::user()['id'],
            'canManage' => $canManage,
            'hasMine' => $hasMine,
            'remindDays' => Coverage::remindDays(),
            'noCompGroups' => !$groups,
            'shortages' => Coverage::shortages(),
            'nextShort' => Coverage::shortages() ? null : Coverage::nextShortage(),
        ], 'Trainersplanning');
    }

    /** Afmelden, terugzetten, overnemen, toewijzen of weghalen voor één training of wedstrijd. */
    public static function action(): void
    {
        self::requireView();
        $aid = (int) input('activity_id', '0');
        $back = match (input('back')) {
            'activity' => url('activity', ['id' => $aid]) . '#trainers',
            'grid' => url('coverage', array_filter(['view' => 'grid', 'w' => input('w'), 'sel' => $aid])),
            'mine' => url('coverage', ['view' => 'mine']) . '#a' . $aid,
            default => url('coverage', array_filter(['weeks' => input('weeks'), 'show' => input('show')])) . '#a' . $aid,
        };
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
        $asked = false;
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
                $reason = mb_substr(input('note') !== '' ? input('note') : input('reason'), 0, 200);
                $ask = in_array($rep, ['ask', 'none'], true);
                Database::tx(function () use ($aid, $a, $target, $reason, $repId) {
                    Coverage::set($aid, $target, 'ABSENT', $reason);
                    if ($repId) {
                        Coverage::isDefault($a, $repId) ? Coverage::set($aid, $repId, null) : Coverage::set($aid, $repId, 'ASSIGNED');
                    }
                });
                $msg = ($self ? 'Je bent afgemeld.' : "$name is afgemeld.") . ($repId ? ' ' . $pool[$repId] . ' neemt over.' : '');
                if ($ask && ($n = Coverage::askGroup($aid, $me, $name, $reason))) {
                    $msg .= " We vroegen $n collega('s) die vrij zijn of ze kunnen overnemen; wie eerst klikt, staat ingepland.";
                    $asked = true;
                }
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
        if ($asked) {
            // de collega's kregen al een persoonlijke vraag
        } elseif ($after && $after['missing'] > 0) {
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

    /**
     * "Ik ben een tijd weg": periode en reden opgeven, de app toont welke trainingen en wedstrijden dat raakt.
     * Per training kies je een vervanger of "Vraag de groep"; waar er genoeg trainers overblijven, hoeft dat niet.
     */
    public static function away(): void
    {
        self::requireView();
        $uid = (int) Auth::user()['id'];
        $pool = Coverage::pool();
        $from = valid_date((string) ($_GET['from'] ?? '')) ? (string) $_GET['from'] : '';
        $to = valid_date((string) ($_GET['to'] ?? '')) ? (string) $_GET['to'] : '';
        if ($from !== '' && $to !== '' && $to < $from) {
            [$from, $to] = [$to, $from];
        }
        if ($from !== '' && $to !== '' && (strtotime($to) - strtotime($from)) / 86400 > 366) {
            $to = date('Y-m-d', strtotime($from . ' +1 year'));
        }
        $rows = [];
        if ($from !== '' && $to !== '') {
            foreach (Coverage::list($from . ' 00:00:00', date('Y-m-d', strtotime($to . ' +1 day')) . ' 00:00:00') as $a) {
                if ($a['ends_at'] <= now()) {
                    continue;
                }
                foreach ($a['trainers'] as $t) {
                    if ($t['id'] === $uid && !$t['absent']) {
                        $a['short'] = $a['present'] - 1 < $a['required'];
                        $a['free'] = $a['short'] ? Coverage::free($a, $pool) : [];
                        $rows[] = $a;
                    }
                }
            }
        }
        render('coverage/away', [
            'from' => $from ?: today(),
            'to' => $to ?: date('Y-m-d', strtotime('+1 week')),
            'reason' => mb_substr(trim((string) ($_GET['reason'] ?? '')), 0, 200),
            'rows' => $rows,
            'shown' => $from !== '' && $to !== '',
        ], 'Ik ben een tijd weg');
    }

    public static function awaySave(): void
    {
        self::requireView();
        $me = (int) Auth::user()['id'];
        $name = (string) Auth::user()['display_name'];
        $pool = Coverage::pool();
        $reason = mb_substr(input('reason'), 0, 200);
        $ids = array_values(array_unique(array_filter(array_map('intval', (array) ($_POST['ids'] ?? [])))));
        $reps = is_array($_POST['rep'] ?? null) ? $_POST['rep'] : [];
        if (!$ids) {
            flash('error', 'Er was geen training of wedstrijd om je voor af te melden.');
            redirect(url('coverage_away'));
        }
        $done = 0;
        $taken = [];
        $ask = [];
        $failed = [];
        Database::tx(function () use ($ids, $reps, $me, $reason, $pool, &$done, &$taken, &$ask, &$failed) {
            foreach (Coverage::list('0000-00-00', '9999-12-31', $ids) as $a) {
                $aid = (int) $a['id'];
                $mine = null;
                foreach ($a['trainers'] as $t) {
                    $mine = $t['id'] === $me ? $t : $mine;
                }
                if ($a['ends_at'] <= now() || !$mine || $mine['absent']) {
                    continue;
                }
                $rep = (string) ($reps[$aid] ?? 'ask');
                $repId = ctype_digit($rep) ? (int) $rep : 0;
                $free = $repId ? Coverage::free($a, $pool) : [];
                Coverage::set($aid, $me, 'ABSENT', $reason);
                $done++;
                $when = fmt_date($a['starts_at']);
                if ($repId && isset($free[$repId])) {
                    Coverage::isDefault($a, $repId) ? Coverage::set($aid, $repId, null) : Coverage::set($aid, $repId, 'ASSIGNED');
                    $taken[] = $pool[$repId] . ' ' . $when;
                } elseif ($repId) {
                    $failed[] = $when;
                    $ask[] = $aid;
                } elseif ($a['present'] - 1 < $a['required']) {
                    $ask[] = $aid;
                }
            }
        });
        $asked = 0;
        foreach ($ask as $aid) {
            $asked += Coverage::askGroup($aid, $me, $name, $reason) ? 1 : 0;
        }
        audit('COVERAGE_AWAY', 'user', $me, $done . ' activiteiten' . ($reason !== '' ? ', ' . $reason : ''));
        $msg = "Je bent afgemeld voor $done training(en) of wedstrijd(en).";
        if ($taken) {
            $msg .= ' Neemt over: ' . implode(', ', $taken) . '.';
        }
        if ($failed) {
            $msg .= ' De gekozen vervanger kon niet op ' . implode(', ', $failed) . ' (staat dan al elders).';
        }
        if ($asked) {
            $msg .= " Voor $asked daarvan vroegen we het aan de collega's die vrij zijn.";
        } elseif ($ask) {
            $msg .= ' Er is geen collega vrij om te vragen: de coördinator volgt dit op.';
        }
        Coverage::remind();
        flash($done ? 'success' : 'info', $msg);
        redirect(url('coverage', ['view' => 'mine']));
    }

    /** "Ik neem over" uit de mail: toont de training met één knop, zonder aan te melden (een GET verandert niets). */
    public static function take(): void
    {
        header('X-Robots-Tag: noindex, nofollow');
        header('Cache-Control: no-store');
        $token = (string) ($_GET['t'] ?? '');
        [$offer, $a, $problem] = self::takeCheck($token);
        render('coverage/take', ['offer' => $offer, 'a' => $a, 'problem' => $problem, 'token' => $token, 'done' => false], 'Kan jij overnemen?');
    }

    public static function takeSave(): void
    {
        header('X-Robots-Tag: noindex, nofollow');
        $token = input('t');
        [$offer, $a, $problem] = self::takeCheck($token);
        if ($problem === null && $offer && $a) {
            $uid = (int) $offer['user_id'];
            $aid = (int) $a['id'];
            Coverage::isDefault($a, $uid) ? Coverage::set($aid, $uid, null) : Coverage::set($aid, $uid, 'ASSIGNED');
            Database::exec('UPDATE coverage_offers SET used_at = ? WHERE activity_id = ? AND user_id = ? AND used_at IS NULL', [now(), $aid, $uid]);
            Database::exec(
                'INSERT INTO audit_log (user_id, action, entity, entity_id, detail, created_at) VALUES (?,?,?,?,?,?)',
                [$uid, 'COVERAGE_TAKE', 'activity', $aid, $offer['display_name'] . ' (via mail)', now()]
            );
            // Wie het vroeg, krijgt een bericht.
            if ($offer['asked_by'] && ($asker = Database::one('SELECT email, display_name FROM users WHERE id = ? AND is_active = 1', [(int) $offer['asked_by']])) && (int) $offer['asked_by'] !== $uid) {
                Mailer::send($asker['email'], $offer['display_name'] . ' neemt over: ' . $a['title'] . ' ' . fmt_date($a['starts_at']),
                    'Dag ' . explode(' ', trim((string) $asker['display_name']))[0] . ",\n\n" . $offer['display_name'] . ' neemt over: ' . $a['title'] . ', '
                    . fmt_day_month($a['starts_at']) . ' ' . fmt_time($a['starts_at']) . '–' . fmt_time($a['ends_at']) . ".\n\n– " . config('app_name', 'HZS Clubapp'));
            }
            $a = Coverage::one($aid);
        }
        render('coverage/take', ['offer' => $offer, 'a' => $a, 'problem' => $problem, 'token' => $token, 'done' => $problem === null], $problem === null ? 'Bedankt!' : 'Kan jij overnemen?');
    }

    /** @return array{0:?array,1:?array,2:?string} [aanbod, activiteit, probleem of null als overnemen kan] */
    private static function takeCheck(string $token): array
    {
        $offer = Coverage::offer($token);
        if (!$offer) {
            return [null, null, 'Deze link werkt niet (meer). Open de trainersplanning in de app.'];
        }
        $a = Coverage::one((int) $offer['activity_id']);
        if (!$a) {
            return [$offer, null, 'Deze training of wedstrijd staat niet meer in de planning (geschrapt of verwijderd).'];
        }
        if ($a['ends_at'] <= now()) {
            return [$offer, $a, 'Deze training of wedstrijd is al voorbij.'];
        }
        $uid = (int) $offer['user_id'];
        foreach ($a['trainers'] as $t) {
            if ($t['id'] === $uid && !$t['absent']) {
                return [$offer, $a, 'Je staat al ingepland. Bedankt!'];
            }
        }
        if ($a['missing'] < 1) {
            $by = Database::val(
                "SELECT u.display_name FROM activity_trainers at JOIN users u ON u.id = at.user_id WHERE at.activity_id = ? AND at.status = 'ASSIGNED' ORDER BY at.updated_at DESC, at.id DESC LIMIT 1",
                [(int) $a['id']]
            );
            return [$offer, $a, ($by ? "$by neemt deze training al over." : 'Er zijn al genoeg trainers.') . ' Bedankt om te helpen!'];
        }
        if ($c = Coverage::conflict($a, $uid)) {
            return [$offer, $a, 'Overnemen lukt niet: ' . str_replace($offer['display_name'] . ' staat', 'je staat', $c)];
        }
        return [$offer, $a, null];
    }

    private static function requireView(): void
    {
        if (!Coverage::canView()) {
            abort(403, 'De trainersplanning is er voor de trainers en de coördinator van de competitie.');
        }
    }
}
