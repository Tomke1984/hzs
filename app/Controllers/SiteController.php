<?php
/** Publieke website (zonder login). */
final class SiteController
{
    private const PER_PAGE = 5;

    public static function home(): void
    {
        $intro = Database::one("SELECT * FROM pages WHERE slug = 'home' AND status = 'PUBLISHED'");
        $isEditor = Auth::can('content.manage');
        $statusSql = $isEditor ? "1=1" : "status = 'PUBLISHED'";
        $total = (int) Database::val("SELECT COUNT(*) FROM posts WHERE $statusSql");
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min($pages, max(1, (int) ($_GET['p'] ?? 1)));
        $posts = Database::all(
            "SELECT * FROM posts WHERE $statusSql ORDER BY COALESCE(published_at, created_at) DESC, id DESC LIMIT " . self::PER_PAGE . ' OFFSET ' . (($page - 1) * self::PER_PAGE)
        );
        $events = self::events(now(), null, 6);
        $lanes = [];
        $blurbs = ['zwemschool' => 'Leren zwemmen op je eigen niveau, van startersgroep tot pre-competitie.', 'competitie' => 'Trainen en wedstrijden zwemmen met de club.'];
        foreach ($blurbs as $slug => $blurb) {
            $lp = Database::one("SELECT title, slug FROM pages WHERE slug = ? AND status = 'PUBLISHED' AND parent_id IS NULL", [$slug]);
            if ($lp) {
                $lanes[] = $lp + ['blurb' => $blurb];
            }
        }
        render_public('site/home', compact('intro', 'posts', 'events', 'page', 'pages', 'lanes'), 'Hasseltse Zwemvereniging Spartacus');
    }

    /** Oude adressen van het nieuwsoverzicht en berichten sturen door naar de startpagina. */
    public static function news(): void
    {
        $slug = (string) ($_GET['slug'] ?? '');
        redirect(url('home') . ($slug !== '' ? '#bericht-' . rawurlencode($slug) : ''));
    }

    public static function page(): void
    {
        $p = Database::one('SELECT * FROM pages WHERE slug = ?', [(string) ($_GET['slug'] ?? '')]);
        if (!$p || ($p['status'] !== 'PUBLISHED' && !Auth::can('content.manage'))) {
            abort(404, 'Deze pagina bestaat niet.');
        }
        self::renderPage($p, ['old' => self::blankContact((string) ($_GET['topic'] ?? '')), 'errors' => []]);
    }

    private static function blankContact(string $topic = ''): array
    {
        return ['topic' => isset(self::TOPICS[$topic]) ? $topic : 'SWIM_SCHOOL', 'first_name' => '', 'last_name' => '', 'email' => '', 'message' => ''];
    }

    public const TOPICS = ['SWIM_SCHOOL' => 'De zwemschool', 'COMPETITION' => 'De competitie', 'OTHER' => 'Iets anders'];

    /** Contactformulier op de contactpagina. */
    public static function contactSend(): void
    {
        $p = Database::one("SELECT * FROM pages WHERE slug = 'contact'");
        if (!$p) {
            abort(404, 'Deze pagina bestaat niet.');
        }
        if (input('website') !== '') { // robot: doe alsof het gelukt is
            flash('success', 'Bedankt, je bericht is verstuurd.');
            redirect(url('page', ['slug' => 'contact']));
        }
        $old = self::blankContact(input('topic'));
        foreach (['first_name', 'last_name', 'email'] as $k) {
            $old[$k] = input($k);
        }
        $old['email'] = strtolower($old['email']);
        $old['message'] = trim((string) (is_array($_POST['message'] ?? null) ? '' : ($_POST['message'] ?? '')));
        $errors = [];
        if ($old['first_name'] === '' || $old['last_name'] === '' || mb_strlen($old['first_name']) > 80 || mb_strlen($old['last_name']) > 80) {
            $errors[] = 'Voornaam en achternaam zijn verplicht (max. 80 tekens).';
        }
        if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($old['email']) > 190) {
            $errors[] = 'Vul een geldig e-mailadres in.';
        }
        if ($old['message'] === '' || mb_strlen($old['message']) > 3000) {
            $errors[] = 'Schrijf een bericht (max. 3000 tekens).';
        }
        if (!$errors) {
            $since = date('Y-m-d H:i:s', time() - 3600);
            if ((int) Database::val('SELECT COUNT(*) FROM contact_messages WHERE email = ? AND created_at > ?', [$old['email'], $since]) >= 5
                || (int) Database::val('SELECT COUNT(*) FROM contact_messages WHERE created_at > ?', [$since]) >= 100) {
                $errors[] = 'Er zijn te veel berichten verstuurd. Probeer het over een uur opnieuw.';
            }
        }
        if ($errors) {
            http_response_code(422);
            self::renderPage($p, ['old' => $old, 'errors' => $errors]);
            return;
        }
        $id = Database::insert(
            'INSERT INTO contact_messages (topic, first_name, last_name, email, message, created_at) VALUES (?,?,?,?,?,?)',
            [$old['topic'], $old['first_name'], $old['last_name'], $old['email'], $old['message'], now()]
        );
        $key = ['SWIM_SCHOOL' => 'swimschool_email', 'COMPETITION' => 'competition_email', 'OTHER' => 'other_email'][$old['topic']];
        $to = trim((string) config('contact.' . $key, '')) ?: trim((string) config('registration.notify_email', ''));
        $sent = false;
        if ($to !== '') {
            $sent = Mailer::send(
                $to,
                'Bericht via de website: ' . self::TOPICS[$old['topic']],
                "Van: {$old['first_name']} {$old['last_name']} <{$old['email']}>\nOnderwerp: " . self::TOPICS[$old['topic']] . "\n\n{$old['message']}\n\n"
                . "Antwoord rechtstreeks op dit adres: {$old['email']}\nIn de app: " . Invites::baseUrl() . '/?r=messages'
            );
            Database::exec('UPDATE contact_messages SET mail_sent = ? WHERE id = ?', [$sent ? 1 : 0, $id]);
        }
        flash('success', 'Bedankt, je bericht is verstuurd. We antwoorden zo snel mogelijk op ' . $old['email'] . '.');
        redirect(url('page', ['slug' => 'contact']));
    }

    private static function renderPage(array $p, array $extra = []): void
    {
        $parent = $p['parent_id'] ? Database::one('SELECT title, slug FROM pages WHERE id = ?', [$p['parent_id']]) : null;
        $children = Database::all("SELECT title, slug FROM pages WHERE parent_id = ? AND status = 'PUBLISHED' AND in_menu = 1 ORDER BY sort_order, title", [$p['id']]);
        $root = $parent ?: $p;
        $items = $parent
            ? Database::all("SELECT title, slug FROM pages WHERE parent_id = ? AND status = 'PUBLISHED' AND in_menu = 1 ORDER BY sort_order, title", [$p['parent_id']])
            : $children;
        $side = $items ? ['title' => $root['title'], 'slug' => $root['slug'], 'items' => $items] : null;
        $goalGroups = $p['slug'] === 'doelstellingen'
            ? Database::all("SELECT id, name, goals FROM `groups` WHERE is_active = 1 AND category = 'SWIM_SCHOOL' AND goals IS NOT NULL AND goals <> '' ORDER BY id")
            : [];
        $timetable = $p['slug'] === 'trainingsuren-competitie' ? (self::timetable()['COMPETITION'] ?? []) : [];
        render_public('site/page', array_merge(compact('p', 'parent', 'side', 'goalGroups', 'timetable'), ['contact' => $p['slug'] === 'contact' ? $extra : null]), $p['title']);
    }

    public static function agenda(): void
    {
        $ym = (string) ($_GET['m'] ?? date('Y-m'));
        $first = DateTimeImmutable::createFromFormat('!Y-m', $ym);
        if ($first === false || $first->format('Y-m') !== $ym) {
            $first = new DateTimeImmutable('first day of this month');
        }
        $next = $first->modify('+1 month');
        $events = self::events($first->format('Y-m-d 00:00:00'), $next->format('Y-m-d 00:00:00'));
        $byDay = [];
        foreach ($events as $ev) {
            $byDay[substr($ev['starts_at'], 0, 10)][] = $ev;
        }
        render_public('site/agenda', [
            'byDay' => $byDay, 'prev' => $first->modify('-1 month')->format('Y-m'), 'next' => $next->format('Y-m'),
            'monthLabel' => ucfirst(MONTHS_NL[(int) $first->format('n')]) . ' ' . $first->format('Y'),
        ], 'Kalender');
    }

    /**
     * Activiteiten voor de publieke site. Bezoekers zien enkel wedstrijden en clubactiviteiten.
     * Wie aangemeld is, ziet ook de trainingen van de eigen groepen; wedstrijden voor andere groepen krijgen mine=false.
     * @param string      $from  starts_at >= (of ends_at >= als $to null is)
     */
    private static function events(string $from, ?string $to, int $limit = 0): array
    {
        $mine = Auth::user() ? Scope::myGroupIds() : [];
        $in = $mine ? Scope::in($mine) : '';
        $visible = "t.code IN ('COMPETITION', 'CLUB')";
        $params = [];
        if ($mine) {
            $visible .= " OR (t.code = 'TRAINING' AND (a.audience = 'CLUB' OR EXISTS (SELECT 1 FROM activity_groups ag WHERE ag.activity_id = a.id AND ag.group_id IN ($in))))";
            $params = $mine;
        }
        $time = $to === null ? 'a.ends_at >= ?' : 'a.starts_at >= ? AND a.starts_at < ?';
        $params[] = $from;
        if ($to !== null) {
            $params[] = $to;
        }
        $rows = Database::all(
            "SELECT a.id, a.title, a.description, a.starts_at, a.ends_at, a.location, a.status, a.status_note, a.audience, t.code AS type_code, t.name AS type_name
             FROM activities a JOIN activity_types t ON t.id = a.type_id
             WHERE ($visible) AND $time ORDER BY a.starts_at" . ($limit ? " LIMIT $limit" : ''),
            $params
        );
        foreach ($rows as &$r) {
            $r['mine'] = true;
            if ($mine && $r['type_code'] === 'COMPETITION' && $r['audience'] !== 'CLUB') {
                $r['mine'] = (bool) Database::val(
                    "SELECT COUNT(*) FROM activity_groups WHERE activity_id = ? AND group_id IN ($in)",
                    array_merge([$r['id']], $mine)
                );
            }
        }
        unset($r);
        return $rows;
    }

    /** Publieke trainingsreeksen, per soort groep en per groep, met de trainers van elke groep. */
    public static function timetable(): array
    {
        $series = Database::all(
            "SELECT s.*, t.name AS type_name FROM activity_series s JOIN activity_types t ON t.id = s.type_id
             WHERE s.is_public = 1 AND s.is_active = 1 AND s.end_date >= ? ORDER BY s.title",
            [today()]
        );
        $byCat = [];
        foreach ($series as $s) {
            $s['slots'] = Database::all('SELECT * FROM activity_series_slots WHERE series_id = ? ORDER BY weekday', [$s['id']]);
            $s['groups'] = Database::all('SELECT g.id, g.name, g.category FROM activity_series_groups sg JOIN `groups` g ON g.id = sg.group_id WHERE sg.series_id = ? ORDER BY g.name', [$s['id']]);
            foreach ($s['groups'] as $g) {
                $byCat[$g['category'] ?? 'SWIM_SCHOOL'][$g['name']]['series'][] = $s;
                $byCat[$g['category'] ?? 'SWIM_SCHOOL'][$g['name']]['id'] = (int) $g['id'];
            }
        }
        foreach ($byCat as $cat => $groups) {
            uasort($groups, fn($a, $b) => $a['id'] <=> $b['id']);
            $byCat[$cat] = $groups;
            foreach ($groups as $name => $g) {
                $byCat[$cat][$name]['trainers'] = array_column(Database::all(
                    'SELECT u.display_name FROM group_trainers gt JOIN users u ON u.id = gt.user_id WHERE gt.group_id = ? AND u.is_active = 1 ORDER BY u.display_name',
                    [$g['id']]
                ), 'display_name');
            }
        }
        return $byCat;
    }

    public static function trainingTimes(): void
    {
        render_public('site/training_times', ['byCat' => self::timetable()], 'Trainingsuren');
    }
}
