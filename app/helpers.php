<?php
function e($v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function config(string $key, $default = null)
{
    $cur = $GLOBALS['CONFIG'] ?? [];
    foreach (explode('.', $key) as $part) {
        if (!is_array($cur) || !array_key_exists($part, $cur)) {
            return $default;
        }
        $cur = $cur[$part];
    }
    return $cur;
}

/** Map waarin de site staat, zonder slash op het einde ('' = hoofdmap). Houdt rekening met een doorverwijzing via /public. */
function base_path(): string
{
    static $b = null;
    if ($b === null) {
        $b = rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/'))), '/');
        $uri = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
        if (str_ends_with($b, '/public') && !str_starts_with($uri, $b . '/')) {
            $b = substr($b, 0, -7);
        }
    }
    return $b;
}

/** Vaste, leesbare adressen voor de publieke site (route => pad). Pagina's staan op /<adres>, nieuws op /nieuws/<adres>. */
const PRETTY_ROUTES = ['agenda' => 'kalender', 'trainingstimes' => 'trainingsuren', 'swimtest' => 'zwemtest', 'register' => 'inschrijven', 'login' => 'aanmelden', 'activate' => 'activeren', 'forgot' => 'wachtwoord-vergeten'];
/** Routes die bewust op ?r= blijven (bevestigingslinks in mails, formulierdoel). */
const PLAIN_ROUTES = ['swimtest_confirm', 'register_confirm', 'contact_send', 'news'];
/**
 * Leesbare adressen voor de beheer-app (onder /app): route => [adres met id, adres zonder id (nieuw)].
 * Alle andere app-routes (vooral POST-acties) krijgen /app/<route-met-streepjes>.
 */
const APP_PATHS = [
    'dashboard' => ['app'],
    'members' => ['app/leden'], 'member' => ['app/leden/{id}'], 'member_form' => ['app/leden/{id}/wijzigen', 'app/leden/nieuw'],
    'groups' => ['app/groepen'], 'group' => ['app/groepen/{id}'], 'group_form' => ['app/groepen/{id}/wijzigen', 'app/groepen/nieuw'],
    'users' => ['app/gebruikers'], 'user_form' => ['app/gebruikers/{id}/wijzigen', 'app/gebruikers/nieuw'],
    'calendar' => ['app/kalender'], 'activity' => ['app/kalender/{id}'], 'activity_form' => ['app/kalender/{id}/wijzigen', 'app/kalender/nieuw'],
    'series' => ['app/reeksen'], 'series_show' => ['app/reeksen/{id}'], 'series_form' => ['app/reeksen/{id}/wijzigen', 'app/reeksen/nieuw'], 'series_bulk' => ['app/reeksen/schrappen'],
    'registrations' => ['app/inschrijvingen'], 'registration' => ['app/inschrijvingen/{id}'],
    'swimtests' => ['app/wachtlijst'], 'swimtest_show' => ['app/wachtlijst/{id}'],
    'messages' => ['app/berichten'], 'stats' => ['app/statistieken'],
    'content' => ['app/website'], 'post_form' => ['app/website/berichten/{id}', 'app/website/berichten/nieuw'], 'page_form' => ['app/website/paginas/{id}', 'app/website/paginas/nieuw'],
];
/** Adressen die een pagina niet mag gebruiken (ze zijn al in gebruik door de app of de server). */
const RESERVED_SLUGS = ['activeren', 'wachtwoord-vergeten', 'kalender', 'trainingsuren', 'zwemtest', 'inschrijven', 'aanmelden', 'nieuws', 'assets', 'uploads', 'install', 'index', 'public', 'app', 'storage', 'db', 'docs', 'tools'];

/**
 * Adres voor een route, relatief aan de basis (de layouts zetten <base href>), dus zonder voorloopslash.
 * Publieke pagina's krijgen een leesbaar adres (/zwemschool); de rest van de app blijft ?r=route.
 */
function url(string $route = 'dashboard', array $params = []): string
{
    $path = null;
    if ($route === 'home') {
        $path = '';
    } elseif ($route === 'page' && !empty($params['slug'])) {
        $path = rawurlencode((string) $params['slug']);
        unset($params['slug']);
    } elseif ($route === 'post' && !empty($params['slug'])) {
        $path = 'nieuws/' . rawurlencode((string) $params['slug']);
        unset($params['slug']);
    } elseif (isset(PRETTY_ROUTES[$route])) {
        $path = PRETTY_ROUTES[$route];
    }
    if ($path !== null) {
        return $path . ($params ? '?' . http_build_query($params) : '') ?: './';
    }
    if (in_array($route, PLAIN_ROUTES, true) || $route === 'home') {
        return '?' . http_build_query(array_merge(['r' => $route], $params));
    }
    // beheer-app
    if (isset(APP_PATHS[$route])) {
        [$with, $without] = APP_PATHS[$route] + [1 => null];
        if (str_contains($with, '{id}')) {
            $id = (int) ($params['id'] ?? 0);
            unset($params['id']);
            $path = $id > 0 ? str_replace('{id}', (string) $id, $with) : $without;
            if ($path === null) {
                return '?' . http_build_query(array_merge(['r' => $route], $params, $id ? ['id' => $id] : []));
            }
        } else {
            $path = $with;
        }
    } else {
        $path = 'app/' . str_replace('_', '-', $route);
    }
    return $path . ($params ? '?' . http_build_query($params) : '');
}

/** Zoekt bij een pad onder /app de route (en eventuele id). @return array{0:string,1:array}|null */
function app_route_from_path(string $path, array $routes): ?array
{
    if ($path === 'app') {
        return ['dashboard', []];
    }
    foreach (APP_PATHS as $route => $paths) {
        foreach ($paths as $i => $pattern) {
            $rx = '#^' . str_replace('\\{id\\}', '(\d+)', preg_quote($pattern, '#')) . '$#';
            if (preg_match($rx, $path, $m)) {
                return [$route, isset($m[1]) ? ['id' => $m[1]] : []];
            }
        }
    }
    if (preg_match('#^app/([a-z0-9-]+)$#', $path, $m)) {
        $route = str_replace('-', '_', $m[1]);
        if (isset($routes[$route]) && !isset(APP_PATHS[$route]) && !isset(PRETTY_ROUTES[$route]) && !in_array($route, PLAIN_ROUTES, true)) {
            return [$route, []];
        }
    }
    return null;
}

/** Adres van de huidige pagina (voor #anker-links, omdat <base> die anders naar de startpagina zou sturen). */
function self_url(): string
{
    return (string) strtok((string) ($_SERVER['REQUEST_URI'] ?? ''), '#');
}

/** Zet een intern adres uit een tekst (?r=page&slug=x) om naar het leesbare adres. */
function pretty_internal(string $href): string
{
    if (!str_starts_with($href, '?r=')) {
        return $href;
    }
    parse_str(substr($href, 1), $q);
    $route = (string) ($q['r'] ?? '');
    unset($q['r']);
    return $route === '' ? $href : url($route, $q);
}

function redirect(string $to): void
{
    $to = str_replace(["\r", "\n"], '', $to);
    // Adressen zijn relatief aan de basis van de site, een Location-header niet: maak ze absoluut.
    if (!preg_match('#^([a-z][a-z0-9+.-]*:|/)#i', $to)) {
        $to = base_path() . '/' . ($to === './' ? '' : $to);
    }
    header('Location: ' . $to);
    exit;
}

/** Adres van het privacybeleid: het ingestelde adres, anders de gepubliceerde pagina 'privacybeleid' (leeg als die ontbreekt). */
function privacy_href(): string
{
    $c = trim((string) config('privacy_url', ''));
    if ($c !== '') {
        return $c;
    }
    static $h = null;
    if ($h === null) {
        try {
            $h = Database::val("SELECT 1 FROM pages WHERE slug = 'privacybeleid' AND status = 'PUBLISHED'") ? url('page', ['slug' => 'privacybeleid']) : '';
        } catch (Throwable $e) {
            $h = '';
        }
    }
    return $h;
}

/** Korte versie-aanduiding (branch en commit uit .git, indien aanwezig) voor beheerders, om te zien welke code er draait. */
function app_build(): string
{
    $head = @file_get_contents(APP_ROOT . '/.git/HEAD');
    if ($head === false) {
        return '';
    }
    $head = trim($head);
    if (!str_starts_with($head, 'ref: ')) {
        return substr($head, 0, 7);
    }
    $ref = substr($head, 5);
    $sha = @file_get_contents(APP_ROOT . '/.git/' . $ref);
    if ($sha === false) {
        foreach (@file(APP_ROOT . '/.git/packed-refs', FILE_IGNORE_NEW_LINES) ?: [] as $l) {
            if (str_ends_with($l, ' ' . $ref)) {
                $sha = substr($l, 0, 40);
                break;
            }
        }
    }
    return basename($ref) . ' ' . substr(trim((string) $sha), 0, 7);
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

function today(): string
{
    return date('Y-m-d');
}

/* ---------- invoer ---------- */
function input(string $key, string $default = ''): string
{
    $v = $_POST[$key] ?? $default;
    return is_array($v) ? '' : trim((string) $v);
}

function valid_date(string $d): bool
{
    $x = DateTime::createFromFormat('!Y-m-d', $d);
    return $x !== false && $x->format('Y-m-d') === $d;
}

/** Accepteert "Y-m-dTH:i" (datetime-local) en geeft "Y-m-d H:i:00" terug, of null. */
function parse_datetime_local(string $v): ?string
{
    $x = DateTime::createFromFormat('Y-m-d\TH:i', $v);
    if ($x === false || $x->format('Y-m-d\TH:i') !== $v) {
        return null;
    }
    return $x->format('Y-m-d H:i:00');
}

/* ---------- CSRF & flash ---------- */
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $sent = $_POST['_csrf'] ?? '';
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        abort(419, 'Je sessie is verlopen of het formulier is ongeldig. Ga terug en probeer opnieuw.');
    }
}

function flash(string $type, string $msg): void
{
    $_SESSION['flash'][] = [$type, $msg];
}

function pull_flash(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

/* ---------- weergave ---------- */
function render(string $view, array $data = [], string $title = ''): void
{
    extract($data, EXTR_SKIP);
    ob_start();
    require APP_ROOT . '/app/views/' . $view . '.php';
    $content = ob_get_clean();
    require APP_ROOT . '/app/views/layout.php';
}

/** Weergave binnen de publieke website-layout. */
function render_public(string $view, array $data = [], string $title = ''): void
{
    extract($data, EXTR_SKIP);
    ob_start();
    require APP_ROOT . '/app/views/' . $view . '.php';
    $content = ob_get_clean();
    require APP_ROOT . '/app/views/layout_public.php';
}

function abort(int $code, string $message = ''): void
{
    http_response_code($code);
    $titles = [403 => 'Geen toegang', 404 => 'Niet gevonden', 405 => 'Methode niet toegestaan', 419 => 'Formulier verlopen'];
    $titles[500] = 'Er ging iets mis';
    // Bezoekers zonder login krijgen de foutpagina in de stijl van de website.
    if (Auth::user()) {
        render('error', ['code' => $code, 'message' => $message], $titles[$code] ?? 'Fout');
    } else {
        render_public('error_public', ['code' => $code, 'message' => $message], $titles[$code] ?? 'Fout');
    }
    exit;
}

const MONTHS_NL = ['', 'januari', 'februari', 'maart', 'april', 'mei', 'juni', 'juli', 'augustus', 'september', 'oktober', 'november', 'december'];
const MONTHS_NL_SHORT = ['', 'jan', 'feb', 'mrt', 'apr', 'mei', 'jun', 'jul', 'aug', 'sep', 'okt', 'nov', 'dec'];
const DAYS_NL_SHORT = ['', 'ma', 'di', 'wo', 'do', 'vr', 'za', 'zo'];

function fmt_date(?string $d): string
{
    if (!$d) {
        return '';
    }
    $t = new DateTime(substr($d, 0, 10));
    return DAYS_NL_SHORT[(int) $t->format('N')] . ' ' . $t->format('j') . ' ' . MONTHS_NL_SHORT[(int) $t->format('n')] . ' ' . $t->format('Y');
}

function fmt_time(string $dt): string
{
    return substr($dt, 11, 5);
}

function fmt_dt(string $dt): string
{
    return fmt_date($dt) . ' ' . fmt_time($dt);
}

function attendance_label(string $s): string
{
    return ['UNKNOWN' => 'Onbekend', 'PRESENT' => 'Aanwezig', 'ABSENT' => 'Afwezig'][$s] ?? $s;
}

/** Korte aanvulling bij een lidnaam in keuzelijsten, om gelijknamige leden te onderscheiden: " (geb. 2010)". */
function member_hint(array $m): string
{
    return !empty($m['birth_date']) ? ' (geb. ' . substr($m['birth_date'], 0, 4) . ')' : '';
}

/** Titel voor de ledenpagina, afhankelijk van wie kijkt. */
function members_label(): string
{
    if (Scope::clubWide() || Auth::hasRole('TRAINER')) {
        return 'Leden';
    }
    return Auth::hasRole('PARENT') ? 'Mijn kinderen' : 'Mijn gegevens';
}

function role_label(string $code): string
{
    return ['ADMIN' => 'Beheerder', 'BOARD' => 'Bestuur', 'TRAINER' => 'Trainer', 'SWIMMER' => 'Zwemmer', 'PARENT' => 'Ouder'][$code] ?? $code;
}

function audit(string $action, string $entity, ?int $entityId = null, ?string $detail = null): void
{
    $u = Auth::user();
    Database::exec(
        'INSERT INTO audit_log (user_id, action, entity, entity_id, detail, created_at) VALUES (?,?,?,?,?,?)',
        [$u['id'] ?? null, $action, $entity, $entityId, $detail, now()]
    );
}

/** Groepscategorieën: zwemschool of competitie. */
function group_categories(): array
{
    return ['SWIM_SCHOOL' => 'Zwemschool', 'COMPETITION' => 'Competitie'];
}

function group_category_label(?string $code): string
{
    return group_categories()[$code ?? ''] ?? 'Zwemschool';
}

/** Filtervelden Zwemschool/Competitie + groep (voor ledenlijst en kalender). */
function group_filter_fields(string $cat, int $gid, array $groupOptions): string
{
    $h = '<select name="cat" aria-label="Soort groep"><option value="">Zwemschool + competitie</option>';
    foreach (group_categories() as $code => $label) {
        $h .= '<option value="' . e($code) . '"' . ($cat === $code ? ' selected' : '') . '>' . e($label) . '</option>';
    }
    $h .= '</select><select name="g" aria-label="Groep"><option value="0">Alle groepen</option>';
    $last = null;
    foreach ($groupOptions as $g) {
        if ($last !== $g['category']) {
            if ($last !== null) {
                $h .= '</optgroup>';
            }
            $last = $g['category'];
            $h .= '<optgroup label="' . e(group_category_label($g['category'])) . '">';
        }
        $h .= '<option value="' . (int) $g['id'] . '"' . ((int) $g['id'] === $gid ? ' selected' : '') . '>' . e($g['name']) . '</option>';
    }
    if ($last !== null) {
        $h .= '</optgroup>';
    }
    return $h . '</select>';
}

/** Hoofdpagina's uit het menu: daar is geen "Terug" nodig. */
const BACK_TOP_ROUTES = ['dashboard', 'members', 'groups', 'users', 'calendar', 'series', 'registrations', 'swimtests', 'messages', 'content', 'stats'];

/** Onthoudt de laatst bekeken filters/maand/tab van een lijstpagina, zodat "Terug" er precies naar terugkeert. */
function remember_list_context(string $route, array $get): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET' || !in_array($route, BACK_TOP_ROUTES, true)) {
        return;
    }
    unset($get['r']);
    $_SESSION['list_ctx'][$route] = array_filter($get, fn($v) => !is_array($v) && $v !== '');
}

/** URL van een lijstpagina met de laatst gebruikte filters. */
function list_url(string $route): string
{
    return url($route, $_SESSION['list_ctx'][$route] ?? []);
}

/** Doelpagina van de knop "Terug": altijd de bovenliggende pagina in de app (met behoud van filters), niet de browsergeschiedenis. */
function back_url(string $route, array $get): string
{
    $id = (int) ($get['id'] ?? 0);
    if ($route === 'activity') {
        $when = $id ? Database::val('SELECT starts_at FROM activities WHERE id = ?', [$id]) : null;
        $ctx = $_SESSION['list_ctx']['calendar'] ?? [];
        if ($when) {
            $ctx['m'] = substr((string) $when, 0, 7);
        }
        return url('calendar', $ctx);
    }
    return match ($route) {
        'member' => list_url('members'),
        'member_form', 'member_save' => $id ? url('member', ['id' => $id]) : list_url('members'),
        'group' => list_url('groups'),
        'group_form', 'group_save' => $id ? url('group', ['id' => $id]) : list_url('groups'),
        'user_form', 'user_save', 'invite_bulk' => list_url('users'),
        'registration' => list_url('registrations'),
        'swimtest_show' => list_url('swimtests'),
        'activity_form', 'activity_save' => $id ? url('activity', ['id' => $id]) : list_url('calendar'),
        'series_show' => list_url('series'),
        'series_form', 'series_save' => $id ? url('series_show', ['id' => $id]) : list_url('series'),
        'series_bulk' => list_url('series'),
        'post_form', 'post_save' => url('content', ['tab' => 'news']),
        'page_form', 'page_save' => url('content', ['tab' => 'pages']),
        default => url('dashboard'),
    };
}

const GROUP_COLORS = 12;

/** Geeft elke groep zonder kleur een vaste kleur (0..11): eerst een nog ongebruikte, anders de minst gebruikte. Bestaande kleuren veranderen nooit. */
function ensure_group_colors(): void
{
    $rows = Database::all('SELECT id, color FROM `groups` ORDER BY id');
    $used = array_fill(0, GROUP_COLORS, 0);
    foreach ($rows as $r) {
        if ($r['color'] !== null && (int) $r['color'] >= 0 && (int) $r['color'] < GROUP_COLORS) {
            $used[(int) $r['color']]++;
        }
    }
    foreach ($rows as $r) {
        if ($r['color'] !== null && (int) $r['color'] >= 0 && (int) $r['color'] < GROUP_COLORS) {
            continue;
        }
        $pick = array_keys($used, min($used))[0];
        Database::exec('UPDATE `groups` SET color = ? WHERE id = ?', [$pick, $r['id']]);
        $used[$pick]++;
    }
}

/** naam => kleurindex (voor kleurstippen bij groepsnamen). */
function group_color_map(): array
{
    static $map = null;
    if ($map === null) {
        $map = [];
        foreach (Database::all('SELECT name, color FROM `groups`') as $r) {
            $map[$r['name']] = (int) ($r['color'] ?? 0);
        }
    }
    return $map;
}

/** Groepsnaam met kleurstip, in dezelfde kleur als in de kalender. */
function group_chip(string $name): string
{
    $c = group_color_map()[$name] ?? null;
    $dot = $c === null ? '' : '<span class="swatch g-' . (int) $c . '"></span>';
    return $dot . e($name);
}
