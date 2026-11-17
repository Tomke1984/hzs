<?php
/**
 * Waar staat config.php? 1) omgevingsvariabele HZS_CONFIG (containers: Coolify/Railpack, zodat het bestand op het
 * blijvende volume staat), 2) config.php in de projectmap (lokaal, FTP-hosting), 3) storage/config.php.
 */
function config_file(): string
{
    foreach ([getenv('HZS_CONFIG'), $_SERVER['HZS_CONFIG'] ?? null, $_ENV['HZS_CONFIG'] ?? null] as $env) {
        if (is_string($env) && $env !== '') {
            return $env;
        }
    }
    if (!is_file(APP_ROOT . '/config.php') && is_file(APP_ROOT . '/storage/config.php')) {
        return APP_ROOT . '/storage/config.php';
    }
    return APP_ROOT . '/config.php';
}

/**
 * Staat storage/ (database, config.php, sleutel, back-ups) in een container zonder blijvend volume? Dan is alles weg na
 * de volgende deploy. true = zeker in een container (Coolify/Railpack: app in /app) en storage/ is geen aparte mount.
 * false = in orde of niet te bepalen (gewone hosting, lokaal).
 */
function storage_not_persistent(?array $mountinfo = null, ?string $root = null): bool
{
    $root ??= APP_ROOT;
    if ($mountinfo === null) {
        if ($root !== '/app' || !is_readable('/proc/self/mountinfo')) {
            return false;
        }
        $mountinfo = @file('/proc/self/mountinfo', FILE_IGNORE_NEW_LINES) ?: [];
    }
    $target = $root . '/storage';
    foreach ($mountinfo as $line) {
        $point = explode(' ', (string) $line)[4] ?? '';
        if ($point === $target) {
            return false;
        }
    }
    return true;
}

/**
 * Waar schrijft de installer een nieuwe config.php? HZS_CONFIG als die gezet is; anders een bestaande config.php in de
 * projectmap; anders storage/config.php. storage/ is in Coolify het blijvende volume (de projectmap is na elke deploy
 * weer leeg) en is op Apache-hosting afgeschermd met .htaccess.
 */
function config_write_file(): string
{
    $read = config_file();
    if ($read !== APP_ROOT . '/config.php' || is_file($read)) {
        return $read;
    }
    return APP_ROOT . '/storage/config.php';
}


function e($v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Staat een (tijdelijk verborgen) onderdeel aan? Instellen in config.php: 'features' => ['attendance' => true].
 * Standaard uit. Onderdelen: attendance (presentielijst, aanwezigheidsrapport, "nog in te vullen").
 */
function feature(string $name): bool
{
    return (bool) config("features.$name", false);
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
const PRETTY_ROUTES = ['login' => 'aanmelden', 'activate' => 'activeren', 'forgot' => 'wachtwoord-vergeten'];
/** Routes die bewust op ?r= blijven (bevestigingslinks in mails, formulierdoel). */
const PLAIN_ROUTES = ['weekplanning', 'coverage_take'];
/**
 * Leesbare adressen voor de beheer-app (onder /app): route => [adres met id, adres zonder id (nieuw)].
 * Alle andere app-routes (vooral POST-acties) krijgen /app/<route-met-streepjes>.
 */
const APP_PATHS = [
    'dashboard' => ['app'],
    'members' => ['app/leden'], 'member' => ['app/leden/{id}'], 'member_form' => ['app/leden/{id}/wijzigen', 'app/leden/nieuw'], 'child_form' => ['app/leden/{id}/mijn-gegevens'],
    'groups' => ['app/groepen'], 'group' => ['app/groepen/{id}'], 'group_form' => ['app/groepen/{id}/wijzigen', 'app/groepen/nieuw'],
    'users' => ['app/gebruikers'], 'mail_settings' => ['app/instellingen/mail'], 'user_form' => ['app/gebruikers/{id}/wijzigen', 'app/gebruikers/nieuw'],
    'calendar' => ['app/kalender'], 'activity' => ['app/kalender/{id}'], 'activity_form' => ['app/kalender/{id}/wijzigen', 'app/kalender/nieuw'],
    'series' => ['app/reeksen'], 'series_show' => ['app/reeksen/{id}'], 'series_form' => ['app/reeksen/{id}/wijzigen', 'app/reeksen/nieuw'], 'series_bulk' => ['app/reeksen/schrappen'],
    'coverage' => ['app/trainersplanning'], 'coverage_settings' => ['app/trainersplanning/instellingen'], 'coverage_bulk_form' => ['app/trainersplanning/meerdere'], 'coverage_away' => ['app/trainersplanning/afwezig'],
    'attendance_report' => ['app/aanwezigheidsrapport'], 'audit' => ['app/auditlog'], 'search' => ['app/zoeken'], 'mail' => ['app/bericht-sturen'],
    'account' => ['app/account'],
];
/**
 * Adres voor een route, relatief aan de basis (de layouts zetten <base href>), dus zonder voorloopslash.
 * Aanmelden e.d. krijgen een leesbaar adres (/aanmelden), de app /app/...; enkele routes blijven ?r=route.
 */
function url(string $route = 'dashboard', array $params = []): string
{
    if (isset(PRETTY_ROUTES[$route])) {
        return PRETTY_ROUTES[$route] . ($params ? '?' . http_build_query($params) : '');
    }
    if (in_array($route, PLAIN_ROUTES, true)) {
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

/** Adres van het privacybeleid van de club (config privacy_url), leeg als het niet ingesteld is. */
function privacy_href(): string
{
    return trim((string) config('privacy_url', ''));
}

/**
 * Versienummer van de app (bestand VERSION in de hoofdmap, bv. "0.27.0" op main of "0.28.0-dev" op dev).
 * Werkt ook op de hosting, waar geen .git-map staat. Leeg als het bestand ontbreekt.
 */
function app_version(): string
{
    static $v = null;
    return $v ??= trim((string) @file_get_contents(APP_ROOT . '/VERSION'));
}

/**
 * Staat het SQLite-bestand binnen de documentroot van de webserver? Dan hangt de bescherming enkel af van .htaccess
 * (werkt niet op nginx, of als AllowOverride uit staat) en kan iemand de hele database downloaden.
 */
function db_inside_webroot(): bool
{
    $db = config('db');
    if (!is_array($db) || ($db['driver'] ?? 'sqlite') !== 'sqlite' || empty($db['path'])) {
        return false;
    }
    $docroot = realpath((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''));
    $file = realpath((string) $db['path']);
    if (!$docroot || !$file) {
        return false;
    }
    $docroot = rtrim(str_replace('\\', '/', $docroot), '/') . '/';
    return str_starts_with(str_replace('\\', '/', $file), $docroot);
}

/** Verzoek op deze computer (localhost)? Dan gelden enkele strengere standaarden niet, om lokaal testen eenvoudig te houden. */
function is_local_request(): bool
{
    $host = strtolower((string) preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')));
    return PHP_SAPI === 'cli' || in_array($host, ['localhost', '127.0.0.1', '[::1]'], true) || str_ends_with($host, '.localhost') || str_ends_with($host, '.test');
}

/** Loopt dit verzoek over HTTPS (ook achter een proxy van de hosting)? */
function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443
        || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
}

/**
 * Logt dat iemand medische info bekeek (hooguit één keer per dag per gebruiker en lid/activiteit, om de auditlog leesbaar te houden).
 */
function audit_medical_view(string $entity, int $id): void
{
    $key = $entity . ':' . $id;
    if (($_SESSION['medical_seen'][$key] ?? '') === today()) {
        return;
    }
    $_SESSION['medical_seen'][$key] = today();
    audit('VIEW_MEDICAL', $entity, $id);
}

/** Hoe lang een deellink weekplanning geldig is (config links.share_days, standaard 365 dagen). */
function share_link_days(): int
{
    return max(7, (int) config('links.share_days', 365));
}

/** Na hoeveel dagen zonder gebruik een iCal-link vervalt (config links.ical_idle_days, standaard 180). */
function ical_idle_days(): int
{
    return max(30, (int) config('links.ical_idle_days', 180));
}

/** Adres van een bestand in public/assets met de wijzigingsdatum erachter, zodat browsers het lang mogen bewaren
 *  en na een update toch meteen de nieuwe versie ophalen. */
/**
 * Adres van een bestand in public/assets met een versie op basis van de inhoud, zodat de browser na een update
 * meteen het nieuwe bestand laadt. Niet op de wijzigingsdatum: bij een build in een container (Coolify/Railpack)
 * krijgen alle bestanden dezelfde datum, waardoor de browser na een deploy de oude CSS bleef gebruiken.
 */
function asset(string $file): string
{
    static $cache = [];
    if (!isset($cache[$file])) {
        $hash = @md5_file(APP_ROOT . '/public/assets/' . $file);
        $cache[$file] = 'assets/' . $file . ($hash ? '?v=' . substr($hash, 0, 10) : '');
    }
    return $cache[$file];
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

/** Foutpagina tonen en stoppen. */
function abort(int $code, string $message = ''): void
{
    http_response_code($code);
    $titles = [403 => 'Geen toegang', 404 => 'Niet gevonden', 405 => 'Methode niet toegestaan', 419 => 'Formulier verlopen'];
    $titles[500] = 'Er ging iets mis';
    render('error', ['code' => $code, 'message' => $message], $titles[$code] ?? 'Fout');
    exit;
}

const MONTHS_NL = ['', 'januari', 'februari', 'maart', 'april', 'mei', 'juni', 'juli', 'augustus', 'september', 'oktober', 'november', 'december'];
const MONTHS_NL_SHORT = ['', 'jan', 'feb', 'mrt', 'apr', 'mei', 'jun', 'jul', 'aug', 'sep', 'okt', 'nov', 'dec'];
const DAYS_NL_SHORT = ['', 'ma', 'di', 'wo', 'do', 'vr', 'za', 'zo'];

/** "ma 9 nov" (dit jaar) of "ma 9 nov 2027" (ander jaar). */
function fmt_date(?string $d): string
{
    if (!$d) {
        return '';
    }
    $t = new DateTime(substr($d, 0, 10));
    $year = $t->format('Y') === date('Y') ? '' : ' ' . $t->format('Y');
    return DAYS_NL_SHORT[(int) $t->format('N')] . ' ' . $t->format('j') . ' ' . MONTHS_NL_SHORT[(int) $t->format('n')] . $year;
}

/** Korte dagkop in een weekkalender: "ma 9". */
function fmt_day(string $d): string
{
    $t = new DateTime(substr($d, 0, 10));
    return DAYS_NL_SHORT[(int) $t->format('N')] . ' ' . $t->format('j');
}

/** Opsomming in het Nederlands: "A", "A en B", "A, B en C". */
function nl_list(array $items): string
{
    $last = array_pop($items);
    return $items ? implode(', ', $items) . ' en ' . $last : (string) $last;
}

/** Dag met maand: "ma 23 nov". */
function fmt_day_month(string $d): string
{
    $t = new DateTime(substr($d, 0, 10));
    return fmt_day($d) . ' ' . MONTHS_NL_SHORT[(int) $t->format('n')];
}

/** Titel van een week: "9–15 nov" of "30 nov – 6 dec" (jaar enkel als het niet dit jaar is). */
function fmt_week_range(string $monday): string
{
    $a = new DateTime($monday);
    $b = (clone $a)->modify('+6 days');
    $year = $b->format('Y') === date('Y') ? '' : ' ' . $b->format('Y');
    if ($a->format('n') === $b->format('n')) {
        return $a->format('j') . '–' . $b->format('j') . ' ' . MONTHS_NL_SHORT[(int) $b->format('n')] . $year;
    }
    return $a->format('j') . ' ' . MONTHS_NL_SHORT[(int) $a->format('n')] . ' – ' . $b->format('j') . ' ' . MONTHS_NL_SHORT[(int) $b->format('n')] . $year;
}

/**
 * Maandag van de week die een weekkalender toont. Standaard kijkt die vooruit:
 * op zaterdag en zondag is de week bijna voorbij, dan tonen we de volgende week.
 */
function week_monday(int $offset = 0): string
{
    $base = date('Y-m-d', strtotime('monday this week', strtotime(today() . ' +2 days')));
    return date('Y-m-d', strtotime("$base $offset weeks"));
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
    return ['ADMIN' => 'Beheerder', 'BOARD' => 'Bestuur', 'TRAINER' => 'Trainer', 'COORDINATOR' => 'Coördinator competitie', 'SWIMMER' => 'Zwemmer', 'PARENT' => 'Ouder'][$code] ?? $code;
}

function audit(string $action, string $entity, ?int $entityId = null, ?string $detail = null): void
{
    $u = Auth::user();
    Database::exec(
        'INSERT INTO audit_log (user_id, action, entity, entity_id, detail, created_at) VALUES (?,?,?,?,?,?)',
        [$u['id'] ?? null, $action, $entity, $entityId, $detail, now()]
    );
}

/** Sinds v0.14 zijn alle groepen competitiegroepen (de zwemschool zit niet meer in de app). */
function group_categories(): array
{
    return ['COMPETITION' => 'Competitie'];
}

function group_category_label(?string $code): string
{
    return 'Competitie';
}

/** Filterveld groep (ledenlijst, kalender, reeksen, aanwezigheden). $cat wordt niet meer gebruikt. */
function group_filter_fields(string $cat, int $gid, array $groupOptions): string
{
    $h = '<select name="g" aria-label="Groep"><option value="0">Alle groepen</option>';
    foreach ($groupOptions as $g) {
        $h .= '<option value="' . (int) $g['id'] . '"' . ((int) $g['id'] === $gid ? ' selected' : '') . '>' . e($g['name']) . '</option>';
    }
    return $h . '</select>';
}

/** Hoofdpagina's uit het menu: daar is geen "Terug" nodig. */
const BACK_TOP_ROUTES = ['dashboard', 'members', 'groups', 'users', 'calendar', 'series', 'attendance_report', 'audit', 'search', 'mail', 'account', 'coverage'];

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
        'member' => family_single_member() ? url('dashboard') : list_url('members'),
        'member_form', 'member_save', 'child_form', 'child_save' => $id ? url('member', ['id' => $id]) : list_url('members'),
        'group' => list_url('groups'),
        'group_form', 'group_save' => $id ? url('group', ['id' => $id]) : list_url('groups'),
        'user_form', 'user_save', 'invite_bulk' => list_url('users'),
        'activity_form', 'activity_save' => $id ? url('activity', ['id' => $id]) : list_url('calendar'),
        'series_show' => list_url('series'),
        'series_form', 'series_save' => $id ? url('series_show', ['id' => $id]) : list_url('series'),
        'series_bulk' => list_url('series'),
        'coverage_settings' => list_url('coverage'),
        'coverage_bulk_form' => list_url('coverage'),
        'coverage_away' => list_url('coverage'),
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

/** Leeg veld op een detailpagina: een uitnodiging om aan te vullen (wie mag aanpassen) of een streepje. */
function missing_field(?string $href, string $verb = 'toevoegen', string $anchor = '', bool $important = false): string
{
    if ($href === null) {
        return '<span class="muted">—</span>';
    }
    return '<span class="' . ($important ? 'missing is-important' : 'missing') . '">Nog niet ingevuld · <a href="' . e($href . $anchor) . '">' . e($verb) . '</a></span>';
}

/**
 * Mag de aangemelde gebruiker dit lid afmelden? Ouders voor hun kinderen en volwassen leden voor zichzelf.
 * Een minderjarig lid met een eigen account niet: afmelden blijft bij de ouders.
 */
function can_report_absence(int $memberId): bool
{
    $u = Auth::user();
    if (!$u || !in_array($memberId, Scope::ownMemberIds(), true)) {
        return false;
    }
    if ((int) ($u['member_id'] ?? 0) === $memberId
        && !Database::val('SELECT 1 FROM guardian_links WHERE user_id = ? AND member_id = ?', [(int) $u['id'], $memberId])) {
        return !Guardianship::isMinor((string) Database::val('SELECT birth_date FROM members WHERE id = ?', [$memberId]) ?: null);
    }
    return true;
}

/** Ouder of zwemmer (geen trainer of beheer): ziet enkel de eigen kinderen of zichzelf. */
function is_family_view(): bool
{
    return Auth::user() !== null && Scope::ownMemberIds() && !Auth::hasRole('TRAINER') && !Auth::can('activities.write') && !Auth::can('members.write');
}

/** Ouder met precies één kind (of zwemmer met enkel zichzelf): het lid-id, zodat "Mijn kinderen" meteen die pagina opent. */
function family_single_member(): ?int
{
    if (!is_family_view()) {
        return null;
    }
    $ids = Scope::ownMemberIds();
    return count($ids) === 1 ? (int) $ids[0] : null;
}
