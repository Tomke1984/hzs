<?php
/** sitemap.xml en robots.txt voor zoekmachines. */
final class SeoController
{
    public static function sitemap(): void
    {
        $base = Invites::baseUrl();
        $urls = [[$base . '/', null]];
        foreach (['agenda', 'trainingstimes', 'swimtest', 'register'] as $r) {
            $urls[] = [$base . '/' . url($r), null];
        }
        foreach (Database::all("SELECT slug, updated_at FROM pages WHERE status = 'PUBLISHED' AND slug <> 'home' ORDER BY sort_order, title") as $p) {
            $urls[] = [$base . '/' . url('page', ['slug' => $p['slug']]), $p['updated_at'] ?? null];
        }
        foreach (Database::all("SELECT slug, COALESCE(published_at, created_at) AS d FROM posts WHERE status = 'PUBLISHED' ORDER BY id DESC LIMIT 500") as $p) {
            $urls[] = [$base . '/' . url('post', ['slug' => $p['slug']]), $p['d']];
        }
        if (GalleryController::hasPublic()) {
            $urls[] = [$base . '/' . url('gallery'), null];
        }
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: application/xml; charset=utf-8');
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($urls as [$loc, $mod]) {
            echo '  <url><loc>' . htmlspecialchars($loc, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</loc>' . ($mod ? '<lastmod>' . substr($mod, 0, 10) . '</lastmod>' : '') . "</url>\n";
        }
        echo "</urlset>\n";
        exit;
    }

    public static function robots(): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: text/plain; charset=utf-8');
        echo "User-agent: *\nDisallow: /app\nDisallow: /?r=\nDisallow: /aanmelden\nDisallow: /activeren\nDisallow: /wachtwoord-vergeten\n\nSitemap: " . Invites::baseUrl() . "/sitemap.xml\n";
        exit;
    }
}
