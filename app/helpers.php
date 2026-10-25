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

function url(string $route = 'dashboard', array $params = []): string
{
    return '?' . http_build_query(array_merge(['r' => $route], $params));
}

function redirect(string $to): void
{
    header('Location: ' . $to);
    exit;
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
    render('error', ['code' => $code, 'message' => $message], $titles[$code] ?? 'Fout');
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

/** Doelpagina van de knop "Terug": altijd de bovenliggende pagina in de app, niet de browsergeschiedenis. */
function back_url(string $route, array $get): string
{
    $id = (int) ($get['id'] ?? 0);
    return match ($route) {
        'member' => url('members'),
        'member_form', 'member_save' => $id ? url('member', ['id' => $id]) : url('members'),
        'group' => url('groups'),
        'group_form', 'group_save' => $id ? url('group', ['id' => $id]) : url('groups'),
        'user_form', 'user_save' => url('users'),
        'registration' => url('registrations'),
        'swimtest_show' => url('swimtests'),
        'activity' => url('calendar'),
        'activity_form', 'activity_save' => $id ? url('activity', ['id' => $id]) : url('calendar'),
        'series_show' => url('series'),
        'series_form', 'series_save' => $id ? url('series_show', ['id' => $id]) : url('series'),
        'post_form', 'post_save' => url('content', ['tab' => 'news']),
        'page_form', 'page_save' => url('content', ['tab' => 'pages']),
        default => url('dashboard'),
    };
}
