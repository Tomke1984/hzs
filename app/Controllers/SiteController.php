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
        $events = Database::all(
            "SELECT a.id, a.title, a.starts_at, a.ends_at, a.location, a.status, t.code AS type_code
             FROM activities a JOIN activity_types t ON t.id = a.type_id
             WHERE (a.is_public = 1 OR t.code IN ('COMPETITION', 'CLUB')) AND a.ends_at >= ? ORDER BY a.starts_at LIMIT 6",
            [now()]
        );
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
        render_public('site/page', compact('p', 'parent', 'side', 'goalGroups'), $p['title']);
    }

    public static function agenda(): void
    {
        $ym = (string) ($_GET['m'] ?? date('Y-m'));
        $first = DateTimeImmutable::createFromFormat('!Y-m', $ym);
        if ($first === false || $first->format('Y-m') !== $ym) {
            $first = new DateTimeImmutable('first day of this month');
        }
        $next = $first->modify('+1 month');
        $events = Database::all(
            "SELECT a.id, a.title, a.description, a.starts_at, a.ends_at, a.location, a.status, a.status_note, t.code AS type_code, t.name AS type_name
             FROM activities a JOIN activity_types t ON t.id = a.type_id
             WHERE (a.is_public = 1 OR t.code IN ('COMPETITION', 'CLUB')) AND a.starts_at >= ? AND a.starts_at < ? ORDER BY a.starts_at",
            [$first->format('Y-m-d 00:00:00'), $next->format('Y-m-d 00:00:00')]
        );
        $byDay = [];
        foreach ($events as $ev) {
            $byDay[substr($ev['starts_at'], 0, 10)][] = $ev;
        }
        render_public('site/agenda', [
            'byDay' => $byDay, 'prev' => $first->modify('-1 month')->format('Y-m'), 'next' => $next->format('Y-m'),
            'monthLabel' => ucfirst(MONTHS_NL[(int) $first->format('n')]) . ' ' . $first->format('Y'),
        ], 'Kalender');
    }

    public static function trainingTimes(): void
    {
        $series = Database::all(
            "SELECT s.*, t.name AS type_name FROM activity_series s JOIN activity_types t ON t.id = s.type_id
             WHERE s.is_public = 1 AND s.is_active = 1 AND s.end_date >= ? ORDER BY s.title",
            [today()]
        );
        $byCat = [];
        foreach ($series as $s) {
            $s['slots'] = Database::all('SELECT * FROM activity_series_slots WHERE series_id = ? ORDER BY weekday', [$s['id']]);
            $s['groups'] = Database::all('SELECT g.name, g.category FROM activity_series_groups sg JOIN `groups` g ON g.id = sg.group_id WHERE sg.series_id = ? ORDER BY g.name', [$s['id']]);
            foreach ($s['groups'] as $g) {
                $byCat[$g['category'] ?? 'SWIM_SCHOOL'][$g['name']][] = $s;
            }
        }
        render_public('site/training_times', compact('byCat'), 'Trainingsuren');
    }
}
