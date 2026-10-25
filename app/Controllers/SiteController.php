<?php
/** Publieke website (zonder login). */
final class SiteController
{
    private const PER_PAGE = 8;

    public static function home(): void
    {
        $intro = Database::one("SELECT * FROM pages WHERE slug = 'home' AND status = 'PUBLISHED'");
        $posts = Database::all("SELECT id, title, slug, summary, image, published_at FROM posts WHERE status = 'PUBLISHED' ORDER BY published_at DESC, id DESC LIMIT 4");
        $events = Database::all(
            "SELECT a.id, a.title, a.starts_at, a.ends_at, a.location, a.status, t.code AS type_code
             FROM activities a JOIN activity_types t ON t.id = a.type_id
             WHERE a.is_public = 1 AND a.ends_at >= ? ORDER BY a.starts_at LIMIT 6",
            [now()]
        );
        render_public('site/home', compact('intro', 'posts', 'events'), 'Hasseltse Zwemvereniging Spartacus');
    }

    public static function news(): void
    {
        $page = max(1, (int) ($_GET['p'] ?? 1));
        $total = (int) Database::val("SELECT COUNT(*) FROM posts WHERE status = 'PUBLISHED'");
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min($page, $pages);
        $posts = Database::all(
            "SELECT id, title, slug, summary, image, published_at FROM posts WHERE status = 'PUBLISHED' ORDER BY published_at DESC, id DESC LIMIT " . self::PER_PAGE . ' OFFSET ' . (($page - 1) * self::PER_PAGE)
        );
        render_public('site/news', compact('posts', 'page', 'pages'), 'Nieuws');
    }

    public static function post(): void
    {
        $p = Database::one('SELECT * FROM posts WHERE slug = ?', [(string) ($_GET['slug'] ?? '')]);
        if (!$p || ($p['status'] !== 'PUBLISHED' && !Auth::can('content.manage'))) {
            abort(404, 'Dit bericht bestaat niet.');
        }
        render_public('site/post', ['p' => $p], $p['title']);
    }

    public static function page(): void
    {
        $p = Database::one('SELECT * FROM pages WHERE slug = ?', [(string) ($_GET['slug'] ?? '')]);
        if (!$p || ($p['status'] !== 'PUBLISHED' && !Auth::can('content.manage'))) {
            abort(404, 'Deze pagina bestaat niet.');
        }
        $parent = $p['parent_id'] ? Database::one('SELECT title, slug FROM pages WHERE id = ?', [$p['parent_id']]) : null;
        $children = Database::all("SELECT title, slug FROM pages WHERE parent_id = ? AND status = 'PUBLISHED' ORDER BY sort_order, title", [$p['id']]);
        render_public('site/page', compact('p', 'parent', 'children'), $p['title']);
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
             WHERE a.is_public = 1 AND a.starts_at >= ? AND a.starts_at < ? ORDER BY a.starts_at",
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
