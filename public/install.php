<?php
/**
 * Eenmalige installer. Maakt het schema, de rollen/permissies en het beheeraccount aan.
 * Blokkeert zichzelf zodra er een gebruiker bestaat. Verwijder dit bestand na installatie.
 */
require dirname(__DIR__) . '/app/bootstrap.php';

function installer_blocked(): bool
{
    if (config('db') === null) {
        return false;
    }
    try {
        return (int) Database::val('SELECT COUNT(*) FROM users') > 0;
    } catch (Throwable $e) {
        return false; // tabellen bestaan nog niet
    }
}

function page(string $title, string $body): void
{
    echo '<!doctype html><html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>' . e($title) . ' · HZS Clubapp</title><link rel="icon" type="image/png" href="' . e(asset('logo.png')) . '"><link rel="stylesheet" href="' . e(asset('style.css')) . '"><script src="' . e(asset('app.js')) . '" defer></script></head><body>'
        . '<header class="topbar"><div class="wrap bar"><span class="brand"><img class="logo" src="' . e(asset('logo.png')) . '" alt="HZS" width="52" height="46"><span>Clubapp · Installatie</span></span></div></header>'
        . '<main class="wrap">' . $body . '</main></body></html>';
    exit;
}

/**
 * Schrijft config.php (op de plek die config_file() aangeeft). @return array{0: bool, 1: string, 2: string} [gelukt, pad, inhoud]
 */
function write_config(array $dbCfg, string $defaultDbPath, string $appKey): array
{
    $dbExport = $dbCfg['driver'] === 'mysql'
        ? "[\n        'driver' => 'mysql',\n        'host' => " . var_export($dbCfg['host'], true) . ",\n        'port' => " . (int) $dbCfg['port'] . ",\n        'name' => " . var_export($dbCfg['name'], true) . ",\n        'user' => " . var_export($dbCfg['user'], true) . ",\n        'pass' => " . var_export($dbCfg['pass'], true) . ",\n    ]"
        : "[\n        'driver' => 'sqlite',\n        'path' => " . ($dbCfg['path'] === $defaultDbPath && config_write_file() === APP_ROOT . '/config.php' ? "__DIR__ . '/storage/data/clubapp.sqlite'"
            : ($dbCfg['path'] === $defaultDbPath && config_write_file() === APP_ROOT . '/storage/config.php' ? "__DIR__ . '/data/clubapp.sqlite'" : var_export($dbCfg['path'], true))) . ",\n    ]";
    $configPhp = "<?php\nreturn [\n    'app_name' => 'HZS Clubapp',\n    'timezone' => 'Europe/Brussels',\n    'debug' => " . (is_local_request() ? 'true' : 'false') . ",\n    'cookie_secure' => false,\n    'db' => " . $dbExport . ",\n    // Sleutel voor versleutelde velden (medische opmerking). Bewaar een kopie veilig: zonder sleutel zijn die velden onleesbaar.\n    'app_key' => " . var_export($appKey, true) . ",\n];\n";
    $cfgFile = config_write_file();
    if (!is_dir(dirname($cfgFile))) {
        @mkdir(dirname($cfgFile), 0750, true);
    }
    $written = @file_put_contents($cfgFile, $configPhp) !== false;
    @chmod($cfgFile, 0640);
    // Ook controleren dat de app het bestand bij het volgende bezoek terugvindt (anders: telkens opnieuw de installer).
    if ($written && config_file() !== $cfgFile) {
        $written = false;
    }
    return [$written, $cfgFile, $configPhp];
}

/**
 * Bestaande SQLite-database terugzetten (bv. van je eigen computer naar de server, zonder SSH).
 * Aanvaardt een .sqlite-bestand of een back-up van de app (.sqlite.gz). @return array{0: string[], 1: ?string} [fouten, resultaattekst]
 */
function import_database(string $dbPath, string $appKeyInput): array
{
    $f = $_FILES['database'] ?? null;
    if (!is_array($f) || ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return [['Kies een databasebestand (.sqlite of een back-up .sqlite.gz).'], null];
    }
    if ($f['error'] !== UPLOAD_ERR_OK) {
        return [['Opladen mislukt (code ' . (int) $f['error'] . ($f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE ? ': bestand te groot voor de server' : '') . ').'], null];
    }
    $data = (string) file_get_contents($f['tmp_name']);
    if (str_starts_with($data, "\x1f\x8b")) {
        $data = (string) @gzdecode($data);
    }
    if (!str_starts_with($data, "SQLite format 3\0")) {
        return [['Dit is geen SQLite-database (of back-up daarvan) van de clubapp.'], null];
    }
    $appKey = '';
    if ($appKeyInput !== '') {
        $k = base64_decode($appKeyInput, true);
        if ($k === false || strlen($k) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            return [["De sleutel (app_key) is ongeldig: verwacht base64 van 32 bytes, zoals in config.php."], null];
        }
        $appKey = $appKeyInput;
    }
    $dir = dirname($dbPath);
    if (!is_dir($dir) && !@mkdir($dir, 0750, true)) {
        return [["Map $dir kan niet aangemaakt worden."], null];
    }
    if (is_file($dbPath) && filesize($dbPath) > 0) {
        try {
            $existing = new PDO('sqlite:' . $dbPath);
            if ((int) $existing->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0) {
                return [['Op deze plek staat al een database met gebruikers; die wordt niet overschreven.'], null];
            }
        } catch (Throwable $e) {
            // leeg of onvolledig bestand van een vorige poging: mag vervangen worden
        }
        $existing = null;
    }
    $tmp = $dbPath . '.import-' . bin2hex(random_bytes(4));
    file_put_contents($tmp, $data);
    try {
        $pdo = new PDO('sqlite:' . $tmp);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        if ($pdo->query('PRAGMA integrity_check')->fetchColumn() !== 'ok') {
            throw new RuntimeException('Het databasebestand is beschadigd.');
        }
        $users = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
        $members = (int) $pdo->query('SELECT COUNT(*) FROM members')->fetchColumn();
        if ($users === 0) {
            throw new RuntimeException('Deze database heeft nog geen gebruikers; installeer dan gewoon opnieuw.');
        }
        $enc = $pdo->query("SELECT medical_note FROM members WHERE medical_note LIKE 'enc1:%' LIMIT 1")->fetchColumn();
        if ($enc !== false) {
            if ($appKey === '') {
                throw new RuntimeException('Deze database bevat versleutelde medische gegevens. Vul de sleutel (app_key) van de oorspronkelijke installatie in (config.php of storage/app.key).');
            }
            if (Crypto::decryptWith((string) $enc, (string) base64_decode($appKey)) === null) {
                throw new RuntimeException('Met deze sleutel kunnen de versleutelde gegevens niet gelezen worden. Controleer de app_key.');
            }
        }
        $pdo = null;
    } catch (Throwable $e) {
        $pdo = null;
        @unlink($tmp);
        return [[$e instanceof RuntimeException ? $e->getMessage() : 'Het bestand kan niet als database geopend worden.'], null];
    }
    foreach (['', '-wal', '-shm'] as $suffix) {
        @unlink($dbPath . $suffix);
    }
    if (!@rename($tmp, $dbPath)) {
        @unlink($tmp);
        return [["Kon de database niet in $dbPath zetten."], null];
    }
    @chmod($dbPath, 0640);
    return [[], "$users gebruikers en $members leden teruggezet. Meld aan met je bestaande account."];
}

/** Bestaande database met gebruikers op de standaardplek, terwijl config.php ontbreekt (bv. kwijt na een nieuwe deploy)? */
function orphan_database(string $path): ?array
{
    if (config('db') !== null || !is_file($path) || filesize($path) === 0) {
        return null;
    }
    try {
        $pdo = new PDO('sqlite:' . $path);
        $users = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
        $members = (int) $pdo->query('SELECT COUNT(*) FROM members')->fetchColumn();
        $enc = $pdo->query("SELECT medical_note FROM members WHERE medical_note LIKE 'enc1:%' LIMIT 1")->fetchColumn();
        return $users > 0 ? ['users' => $users, 'members' => $members, 'encrypted' => $enc !== false ? (string) $enc : null] : null;
    } catch (Throwable $e) {
        return null;
    }
}

if (installer_blocked()) {
    http_response_code(403);
    page('Reeds geïnstalleerd', '<div class="card narrow"><h1>Reeds geïnstalleerd</h1><p>De installer is vergrendeld omdat er al een gebruiker bestaat. Verwijder <code>public/install.php</code> van de server.</p><p><a class="btn" href="index.php">Naar de app</a></p></div>');
}

$errors = [];
$v = [
    'driver' => 'sqlite', 'host' => '127.0.0.1', 'port' => '3306', 'name' => '', 'user' => '',
    'admin_name' => '', 'admin_email' => '', 'demo' => '', 'db_path' => '',
];
$defaultDbPath = APP_ROOT . '/storage/data/clubapp.sqlite';

$importErrors = [];
$orphan = orphan_database($defaultDbPath);
$keyFile = APP_ROOT . '/storage/app.key';
$fileKey = is_file($keyFile) ? trim((string) file_get_contents($keyFile)) : '';
if ($orphan !== null) {
    // Er staat al een database met gebruikers: nooit een nieuwe installatie, enkel de instellingen herstellen.
    $recoverErrors = [];
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['mode'] ?? '') === 'recover') {
        verify_csrf();
        $key = trim((string) ($_POST['app_key'] ?? '')) ?: $fileKey;
        $raw = $key !== '' ? base64_decode($key, true) : false;
        if ($key !== '' && ($raw === false || strlen($raw) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES)) {
            $recoverErrors[] = 'De sleutel (app_key) is ongeldig: verwacht base64 van 32 bytes.';
        } elseif ($orphan['encrypted'] !== null && ($key === '' || Crypto::decryptWith($orphan['encrypted'], (string) $raw) === null)) {
            $recoverErrors[] = 'De database bevat versleutelde medische gegevens; vul de juiste sleutel (app_key) van de oorspronkelijke installatie in.';
        } else {
            [$written, $cfgFile, $configPhp] = write_config(['driver' => 'sqlite', 'path' => $defaultDbPath], $defaultDbPath, $key !== '' ? $key : Crypto::newKey());
            $body = '<div class="card narrow"><h1>Instellingen hersteld</h1><p>De app gebruikt weer de bestaande database (' . (int) $orphan['users'] . ' gebruikers, ' . (int) $orphan['members'] . ' leden). Meld aan met je bestaande account.</p>';
            if (!$written) {
                $body .= '<div class="alert alert-error">Kon <code>' . e($cfgFile) . '</code> niet wegschrijven (of de app vindt het daar niet terug). Maak het bestand zelf aan met deze inhoud:</div><pre>' . e($configPhp) . '</pre>';
            } else {
                $body .= '<p class="muted small">Opgeslagen in <code>' . e($cfgFile) . '</code>.</p>';
            }
            page('Instellingen hersteld', $body . '<p><a class="btn" href="aanmelden">Aanmelden</a></p></div>');
        }
    }
    page('Bestaande database gevonden', '<div class="card narrow"><h1>Bestaande database gevonden</h1>'
        . '<p>Op de server staat al een database met <strong>' . (int) $orphan['users'] . ' gebruikers</strong> en ' . (int) $orphan['members'] . ' leden, maar de instellingen (<code>config.php</code>) ontbreken. '
        . 'Dat gebeurt bijvoorbeeld als <code>config.php</code> buiten het blijvende volume stond en een nieuwe deploy het wiste. De database wordt niet aangeraakt.</p>'
        . ($recoverErrors ? '<div class="alert alert-error"><ul><li>' . implode('</li><li>', array_map('e', $recoverErrors)) . '</li></ul></div>' : '')
        . '<form method="post" action="install.php" class="form">' . csrf_field() . '<input type="hidden" name="mode" value="recover">'
        . ($orphan['encrypted'] !== null && $fileKey === ''
            ? '<label>Sleutel (app_key) van de oorspronkelijke installatie <input name="app_key" autocomplete="off" required></label><p class="muted small">Nodig om de versleutelde medische gegevens te lezen.</p>'
            : ($fileKey !== '' ? '<p class="muted small">De sleutel uit <code>storage/app.key</code> wordt gebruikt.</p>' : ''))
        . '<button class="btn" type="submit">Instellingen herstellen</button></form>'
        . '<p class="muted small">Nieuwe instellingen worden bewaard in <code>' . e(config_write_file()) . '</code>.</p></div>');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['mode'] ?? '') === 'import') {
    verify_csrf();
    $importPath = trim((string) ($_POST['import_path'] ?? '')) ?: $defaultDbPath;
    if (!str_ends_with($importPath, '.sqlite') || !preg_match('#^(/|[A-Za-z]:[\\\\/])#', $importPath)) {
        $importErrors[] = 'Geef een volledig pad op dat eindigt op .sqlite, of laat het veld leeg.';
    } else {
        [$importErrors, $result] = import_database($importPath, trim((string) ($_POST['app_key'] ?? '')));
        if (!$importErrors) {
            [$written, $cfgFile, $configPhp] = write_config(['driver' => 'sqlite', 'path' => $importPath], $defaultDbPath, trim((string) ($_POST['app_key'] ?? '')) ?: Crypto::newKey());
            $body = '<div class="card narrow"><h1>Database teruggezet</h1><p>' . e((string) $result) . '</p>';
            if (!$written) {
                $body .= '<div class="alert alert-error">Kon <code>' . e($cfgFile) . '</code> niet wegschrijven. Maak het bestand zelf aan met deze inhoud:</div><pre>' . e($configPhp) . '</pre>';
            }
            $body .= '<p class="muted small">De database wordt bij het eerste bezoek automatisch bijgewerkt naar deze versie van de app.</p><p><a class="btn" href="aanmelden">Aanmelden</a></p></div>';
            page('Database teruggezet', $body);
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    foreach (array_keys($v) as $k) {
        $v[$k] = input($k, $v[$k]);
    }
    $dbPass = (string) ($_POST['pass'] ?? '');
    $pw = (string) ($_POST['admin_password'] ?? '');
    $pw2 = (string) ($_POST['admin_password2'] ?? '');

    if (!in_array($v['driver'], ['sqlite', 'mysql'], true)) {
        $errors[] = 'Kies een databasetype.';
    }
    if ($v['driver'] === 'mysql' && ($v['host'] === '' || $v['name'] === '' || $v['user'] === '')) {
        $errors[] = 'Vul host, databasenaam en gebruiker in voor MySQL.';
    }
    $dbPath = $v['db_path'] === '' ? $defaultDbPath : $v['db_path'];
    if ($v['driver'] === 'sqlite' && (!str_ends_with($dbPath, '.sqlite') || !preg_match('#^(/|[A-Za-z]:[\\\\/])#', $dbPath))) {
        $errors[] = 'Geef een volledig pad op dat eindigt op .sqlite (bv. /home/club/hzs-data/clubapp.sqlite), of laat het veld leeg.';
    }
    if ($v['admin_name'] === '') {
        $errors[] = 'Naam van de beheerder is verplicht.';
    }
    if (!filter_var($v['admin_email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'E-mailadres van de beheerder is ongeldig.';
    }
    if ($problem = PasswordPolicy::problem($pw, [$v['admin_email'], $v['admin_name']])) {
        $errors[] = $problem;
    }
    if ($pw !== $pw2) {
        $errors[] = 'De wachtwoorden komen niet overeen.';
    }

    if (!$errors) {
        $dbCfg = $v['driver'] === 'mysql'
            ? ['driver' => 'mysql', 'host' => $v['host'], 'port' => (int) $v['port'], 'name' => $v['name'], 'user' => $v['user'], 'pass' => $dbPass]
            : ['driver' => 'sqlite', 'path' => $dbPath];

        try {
            Database::connect($dbCfg);
            Installer::createSchema();
            if ((int) Database::val('SELECT COUNT(*) FROM users') > 0) {
                throw new RuntimeException('Er bestaat al een gebruiker in deze database.');
            }
            if ((int) Database::val('SELECT COUNT(*) FROM roles') === 0) {
                Installer::seedReferenceData();
            }
            Installer::createUser($v['admin_email'], $pw, $v['admin_name'], 'ADMIN');
            if ($v['demo'] === '1') {
                Installer::seedDemo();
            }

            [$written, $cfgFile, $configPhp] = write_config($dbCfg, $defaultDbPath, Crypto::newKey());

            $body = '<div class="card narrow"><h1>Installatie voltooid</h1>';
            $body .= '<p>Het beheeraccount <strong>' . e($v['admin_email']) . '</strong> is aangemaakt.</p>';
            if (!$written) {
                $body .= '<div class="alert alert-error">Kon <code>' . e($cfgFile) . '</code> niet wegschrijven. Maak het bestand zelf aan met deze inhoud:</div><pre>' . e($configPhp) . '</pre>';
            }
            if ($v['demo'] === '1') {
                $body .= '<div class="alert alert-success"><strong>Demo-accounts</strong> (wachtwoord voor allemaal: <code>Demo1234!</code>)<br>'
                    . 'trainer@demo.local · zwemmer@demo.local · ouder@demo.local<br>Enkel voor lokaal testen.</div>';
            }
            $body .= '<p>Verwijder nu <code>public/install.php</code> van de server.</p><p><a class="btn" href="index.php">Naar de app</a></p></div>';
            page('Installatie voltooid', $body);
        } catch (Throwable $e) {
            $errors[] = 'Installatie mislukt: ' . $e->getMessage();
        }
    }
}

ob_start(); ?>
<div class="card narrow wide">
  <h1>Installatie</h1>
  <p class="muted small">De instellingen komen in <code><?= e(config_write_file()) ?></code><?= getenv('HZS_CONFIG') ? ' (HZS_CONFIG)' : '' ?>.
    Op Coolify moet dat op het blijvende volume (<code>/app/storage</code>) staan, anders vraagt de app na elke deploy opnieuw om te installeren.</p>
  <?php if ($errors): ?>
    <div class="alert alert-error"><ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
  <?php endif; ?>
  <form method="post" action="install.php" class="form">
    <?= csrf_field() ?>
    <fieldset>
      <legend>Database</legend>
      <label class="check"><input type="radio" name="driver" value="sqlite" <?= $v['driver'] === 'sqlite' ? 'checked' : '' ?>> SQLite — geen installatie nodig (aanbevolen voor lokaal testen)</label>
      <label class="check"><input type="radio" name="driver" value="mysql" <?= $v['driver'] === 'mysql' ? 'checked' : '' ?>> MySQL / MariaDB — voor shared hosting</label>
      <div class="grid2">
        <label>Host <input name="host" value="<?= e($v['host']) ?>"></label>
        <label>Poort <input name="port" value="<?= e($v['port']) ?>"></label>
        <label>Databasenaam <input name="name" value="<?= e($v['name']) ?>"></label>
        <label>Gebruiker <input name="user" value="<?= e($v['user']) ?>" autocomplete="off"></label>
      </div>
      <label>Wachtwoord <input type="password" name="pass" autocomplete="off"></label>
      <p class="muted small">De MySQL-velden zijn enkel nodig als je MySQL kiest. De database moet al bestaan.</p>
      <label>SQLite: pad naar het databasebestand <input name="db_path" value="<?= e($v['db_path']) ?>" placeholder="<?= e($defaultDbPath) ?>"></label>
      <p class="muted small">Op een server: kies een map <strong>buiten</strong> de webmap (bv. naast <code>www/</code>), zodat het bestand nooit te downloaden is. Leeg = <code>storage/data/</code> (prima om lokaal te testen).</p>
    </fieldset>

    <fieldset>
      <legend>Beheerder</legend>
      <label>Naam * <input name="admin_name" value="<?= e($v['admin_name']) ?>" required></label>
      <label>E-mailadres * <input type="email" name="admin_email" value="<?= e($v['admin_email']) ?>" required autocomplete="username"></label>
      <div class="grid2">
        <label>Wachtwoord * (min. 10 tekens) <input type="password" name="admin_password" required minlength="10" autocomplete="new-password"></label>
        <label>Herhaal wachtwoord * <input type="password" name="admin_password2" required minlength="10" autocomplete="new-password"></label>
      </div>
    </fieldset>

    <label class="check"><input type="checkbox" name="demo" value="1" <?= $v['demo'] === '1' ? 'checked' : '' ?>> Voorbeelddata toevoegen (groepen, leden, activiteiten en demo-accounts voor trainer, zwemmer en ouder)</label>
    <div class="actions"><button class="btn" type="submit">Installeren</button></div>
  </form>
</div>

<div class="card narrow wide" id="terugzetten">
  <h2>Of: bestaande database terugzetten</h2>
  <p>Heb je de app al ergens anders gebruikt (bv. lokaal op je computer)? Laad die SQLite-database hier op in plaats van opnieuw te beginnen. Je meldt daarna aan met je bestaande accounts.</p>
  <?php if ($importErrors): ?>
    <div class="alert alert-error"><ul><?php foreach ($importErrors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
  <?php endif; ?>
  <form method="post" action="install.php#terugzetten" class="form" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="hidden" name="mode" value="import">
    <label>Databasebestand * <input type="file" name="database" accept=".sqlite,.gz,application/gzip" required></label>
    <p class="muted small"><code>storage/data/clubapp.sqlite</code> (eerst een kopie maken terwijl de app niet draait, bv. <code>sqlite3 storage/data/clubapp.sqlite "VACUUM INTO '/tmp/clubapp.sqlite'"</code>) of een back-up van de app (<code>hzs-….sqlite.gz</code>).</p>
    <label>Sleutel (app_key) van de oorspronkelijke installatie <input name="app_key" autocomplete="off" spellcheck="false"></label>
    <p class="muted small">Enkel nodig als er versleutelde medische gegevens in staan (te vinden in <code>config.php</code> of <code>storage/app.key</code>). Leeg = een nieuwe sleutel.</p>
    <label>Plaats op de server <input name="import_path" placeholder="<?= e($defaultDbPath) ?>"></label>
    <div class="actions"><button class="btn" type="submit">Database terugzetten</button></div>
  </form>
</div>
<?php page('Installatie', ob_get_clean());
