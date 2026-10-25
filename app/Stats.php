<?php
/**
 * Bezoekersstatistieken voor de website en de app. Privacyvriendelijk: geen cookies, geen IP-adres of gebruikers-id
 * opgeslagen. Een "bezoeker" is een afgekapte hash die elke dag verandert (dus enkel unieke bezoekers per dag te tellen).
 * Bots, POST-aanvragen, mislukte aanvragen, bezoekers met "Do Not Track" en beheerders op de website tellen niet mee.
 */
final class Stats
{
    public const RETENTION_DAYS = 400;
    /** Routes van de publieke website (de rest, mits ingelogd, is de app). */
    public const SITE_ROUTES = ['home', 'news', 'post', 'page', 'agenda', 'trainingstimes', 'login', 'forgot', 'activate', 'register', 'register_confirm', 'swimtest', 'swimtest_confirm'];

    /** Onthoudt een paginaweergave; wordt pas weggeschreven als de pagina met 200 is beantwoord. */
    public static function track(string $route): void
    {
        try {
            if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET' || $route === 'stats') {
                return;
            }
            $ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
            $user = Auth::user();
            $site = in_array($route, self::SITE_ROUTES, true);
            if (!$site && !$user) {
                return;
            }
            $skip = null; // reden waarom dit bezoek niet meetelt (wordt anoniem als teller bijgehouden)
            if (self::optedOut()) {
                $skip = 'DNT';
            } elseif ($ua === '' || self::isBot($ua)) {
                $skip = 'BOT';
            } elseif ($site && $user && Auth::can('content.manage') && !config('stats.count_staff', false)) {
                $skip = 'STAFF'; // eigen bezoek van beheerders telt niet mee
            }
            $day = today();
            if ($skip !== null) {
                $row = ['day' => $day, 'viewed_at' => now(), 'area' => 'SKIP', 'route' => $skip, 'path' => '', 'visitor' => '', 'device' => '', 'referrer' => ''];
            } else {
                $row = [
                    'day' => $day, 'viewed_at' => now(), 'area' => $site ? 'SITE' : 'APP', 'route' => substr($route, 0, 40),
                    'path' => $site ? self::sitePath($route) : '/app/' . $route,
                    'visitor' => self::visitor($day, $user && !$site ? 'u' . (int) $user['id'] : (string) ($_SERVER['REMOTE_ADDR'] ?? '') . '|' . $ua),
                    'device' => self::device($ua), 'referrer' => $site ? self::referrerHost() : '',
                ];
            }
            register_shutdown_function(static function () use ($row): void {
                $code = http_response_code();
                if ($code !== false && (int) $code !== 200) {
                    return; // omleidingen en fouten tellen niet (sommige servers geven geen code terug: dan is het 200)
                }
                try {
                    Database::exec(
                        'INSERT INTO page_views (day, viewed_at, area, route, path, visitor, device, referrer) VALUES (?,?,?,?,?,?,?,?)',
                        array_values($row)
                    );
                    if (random_int(1, 300) === 1) {
                        Database::exec('DELETE FROM page_views WHERE day < ?', [date('Y-m-d', strtotime('-' . self::RETENTION_DAYS . ' days'))]);
                    }
                } catch (Throwable $e) {
                    self::logError($e->getMessage());
                }
            });
        } catch (Throwable $e) {
            self::logError($e->getMessage());
        }
    }

    /** Statistieken mogen de site nooit breken, maar een fout hoort wel zichtbaar te zijn (serverlog en storage/stats-error.log). */
    private static function logError(string $msg): void
    {
        error_log('Stats: ' . $msg);
        @file_put_contents(APP_ROOT . '/storage/stats-error.log', date('Y-m-d H:i:s') . ' ' . substr(str_replace("\n", ' ', $msg), 0, 300) . "\n", FILE_APPEND);
    }

    /** Redenen waarom het huidige bezoek NIET zou meetellen (voor de uitleg op het dashboard). */
    public static function whyNotCounted(): array
    {
        $why = [];
        if (self::optedOut()) {
            $why[] = 'Je browser stuurt "Do Not Track" of "Global Privacy Control" mee (Brave en sommige Firefox- of privacy-instellingen doen dat standaard).';
        }
        $user = Auth::user();
        if ($user && Auth::can('content.manage') && !config('stats.count_staff', false)) {
            $why[] = 'Je bent ingelogd als beheerder of bestuurslid: je bezoek aan de website telt niet mee.';
        }
        if (self::isBot((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''))) {
            $why[] = 'Je browser lijkt een bot of automatisch programma.';
        }
        return $why;
    }

    private static function optedOut(): bool
    {
        return ($_SERVER['HTTP_DNT'] ?? '') === '1' || ($_SERVER['HTTP_SEC_GPC'] ?? '') === '1';
    }

    public static function isBot(string $ua): bool
    {
        return (bool) preg_match('/bot|crawl|spider|slurp|archiver|monitor|uptime|preview|fetch|scan|curl|wget|python|java\/|go-http|httpclient|headless|lighthouse|facebookexternalhit|pingdom|checker/i', $ua);
    }

    public static function device(string $ua): string
    {
        if (preg_match('/ipad|tablet|kindle|silk/i', $ua) || (preg_match('/android/i', $ua) && !preg_match('/mobile/i', $ua))) {
            return 'TABLET';
        }
        return preg_match('/mobi|iphone|ipod|android|phone/i', $ua) ? 'MOBILE' : 'DESKTOP';
    }

    private static function visitor(string $day, string $who): string
    {
        return substr(hash_hmac('sha256', $day . '|' . $who, self::salt()), 0, 16);
    }

    private static function salt(): string
    {
        $file = APP_ROOT . '/storage/stats.salt';
        $salt = is_file($file) ? trim((string) @file_get_contents($file)) : '';
        if ($salt === '') {
            $salt = bin2hex(random_bytes(32));
            @file_put_contents($file, $salt);
            @chmod($file, 0600);
        }
        return $salt;
    }

    private static function sitePath(string $route): string
    {
        $slug = preg_replace('/[^a-z0-9-]/', '', strtolower((string) ($_GET['slug'] ?? '')));
        return match (true) {
            $route === 'home' => '/',
            in_array($route, ['news', 'post'], true) => $slug !== '' ? '/nieuws/' . $slug : '/nieuws',
            $route === 'page' => '/' . $slug,
            default => '/' . (PRETTY_ROUTES[$route] ?? $route),
        };
    }

    private static function referrerHost(): string
    {
        $host = strtolower((string) parse_url((string) ($_SERVER['HTTP_REFERER'] ?? ''), PHP_URL_HOST));
        $own = strtolower(preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')));
        $host = preg_replace('/^www\./', '', $host);
        return ($host === '' || $host === preg_replace('/^www\./', '', $own)) ? '' : substr($host, 0, 120);
    }

    /* ---------- rapportage ---------- */

    /** Alle cijfers voor het dashboard over de laatste $days dagen (vandaag inbegrepen). */
    public static function report(int $days): array
    {
        $from = date('Y-m-d', strtotime('-' . ($days - 1) . ' days'));
        $out = ['from' => $from, 'days' => $days, 'areas' => []];
        foreach (['SITE', 'APP'] as $area) {
            $perDay = [];
            foreach (Database::all('SELECT day, COUNT(*) AS views, COUNT(DISTINCT visitor) AS visitors FROM page_views WHERE area = ? AND day >= ? GROUP BY day', [$area, $from]) as $r) {
                $perDay[$r['day']] = ['views' => (int) $r['views'], 'visitors' => (int) $r['visitors']];
            }
            $series = [];
            for ($i = $days - 1; $i >= 0; $i--) {
                $d = date('Y-m-d', strtotime("-$i days"));
                $series[$d] = $perDay[$d] ?? ['views' => 0, 'visitors' => 0];
            }
            $views = array_sum(array_column($series, 'views'));
            $visitors = array_sum(array_column($series, 'visitors'));
            $out['areas'][$area] = [
                'series' => $series, 'views' => $views, 'visitors' => $visitors,
                'avg' => round($visitors / $days, 1),
                'today' => $series[today()] ?? ['views' => 0, 'visitors' => 0],
                'top' => Database::all('SELECT path, MIN(route) AS route, COUNT(*) AS views, COUNT(DISTINCT visitor) AS visitors FROM page_views WHERE area = ? AND day >= ? GROUP BY path ORDER BY views DESC, path LIMIT 10', [$area, $from]),
                'devices' => Database::all('SELECT device, COUNT(*) AS views FROM page_views WHERE area = ? AND day >= ? GROUP BY device ORDER BY views DESC', [$area, $from]),
                'referrers' => $area === 'SITE' ? Database::all("SELECT referrer, COUNT(*) AS views FROM page_views WHERE area = 'SITE' AND day >= ? AND referrer <> '' GROUP BY referrer ORDER BY views DESC LIMIT 8", [$from]) : [],
                'direct' => $area === 'SITE' ? (int) Database::val("SELECT COUNT(*) FROM page_views WHERE area = 'SITE' AND day >= ? AND referrer = ''", [$from]) : 0,
            ];
        }
        $out['first'] = (string) Database::val('SELECT MIN(day) FROM page_views');
        $out['last'] = (string) Database::val("SELECT MAX(viewed_at) FROM page_views WHERE area <> 'SKIP'");
        $out['skipped'] = [];
        foreach (Database::all("SELECT route, COUNT(*) AS n FROM page_views WHERE area = 'SKIP' AND day >= ? GROUP BY route", [$from]) as $r) {
            $out['skipped'][$r['route']] = (int) $r['n'];
        }
        $log = APP_ROOT . '/storage/stats-error.log';
        $out['error'] = is_file($log) ? trim((string) array_slice(file($log, FILE_IGNORE_NEW_LINES) ?: [], -1)[0]) : '';
        return $out;
    }

    /** Leesbare naam van een app-route voor in de lijst "meest bezocht". */
    public static function appLabel(string $route): string
    {
        $labels = ['dashboard' => 'Overzicht', 'members' => 'Leden', 'member' => 'Ledenpagina', 'member_form' => 'Lid wijzigen', 'groups' => 'Groepen', 'group' => 'Groepspagina',
            'group_form' => 'Groep wijzigen', 'users' => 'Gebruikers', 'user_form' => 'Gebruiker wijzigen', 'calendar' => 'Kalender', 'activity' => 'Activiteit', 'activity_form' => 'Activiteit wijzigen',
            'series' => 'Trainingsreeksen', 'series_show' => 'Reeks of blok', 'series_form' => 'Reeks wijzigen', 'series_week' => 'Weekoverzicht zwemschool', 'series_bulk' => 'Schrappen / verplaatsen',
            'registrations' => 'Inschrijvingen', 'registration' => 'Inschrijving', 'swimtests' => 'Zwemtest', 'swimtest_show' => 'Zwemtestaanvraag', 'messages' => 'Berichten', 'content' => 'Website beheren',
            'post_form' => 'Nieuwsbericht bewerken', 'page_form' => 'Pagina bewerken'];
        return $labels[$route] ?? $route;
    }
}
