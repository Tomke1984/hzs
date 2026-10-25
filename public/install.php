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
        . '<title>' . e($title) . ' · HZS Clubapp</title><link rel="stylesheet" href="assets/style.css"><script src="assets/app.js" defer></script></head><body>'
        . '<header class="topbar"><div class="wrap bar"><span class="brand"><span class="wave">≈</span> HZS Clubapp · Installatie</span></div></header>'
        . '<main class="wrap">' . $body . '</main></body></html>';
    exit;
}

if (installer_blocked()) {
    http_response_code(403);
    page('Reeds geïnstalleerd', '<div class="card narrow"><h1>Reeds geïnstalleerd</h1><p>De installer is vergrendeld omdat er al een gebruiker bestaat. Verwijder <code>public/install.php</code> van de server.</p><p><a class="btn" href="index.php">Naar de app</a></p></div>');
}

$errors = [];
$v = [
    'driver' => 'sqlite', 'host' => '127.0.0.1', 'port' => '3306', 'name' => '', 'user' => '',
    'admin_name' => '', 'admin_email' => '', 'demo' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
    if ($v['admin_name'] === '') {
        $errors[] = 'Naam van de beheerder is verplicht.';
    }
    if (!filter_var($v['admin_email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'E-mailadres van de beheerder is ongeldig.';
    }
    if (strlen($pw) < 10) {
        $errors[] = 'Het wachtwoord moet minstens 10 tekens bevatten.';
    }
    if ($pw !== $pw2) {
        $errors[] = 'De wachtwoorden komen niet overeen.';
    }

    if (!$errors) {
        $dbCfg = $v['driver'] === 'mysql'
            ? ['driver' => 'mysql', 'host' => $v['host'], 'port' => (int) $v['port'], 'name' => $v['name'], 'user' => $v['user'], 'pass' => $dbPass]
            : ['driver' => 'sqlite', 'path' => APP_ROOT . '/storage/data/clubapp.sqlite'];

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

            $dbExport = $v['driver'] === 'mysql'
                ? "[\n        'driver' => 'mysql',\n        'host' => " . var_export($v['host'], true) . ",\n        'port' => " . (int) $v['port'] . ",\n        'name' => " . var_export($v['name'], true) . ",\n        'user' => " . var_export($v['user'], true) . ",\n        'pass' => " . var_export($dbPass, true) . ",\n    ]"
                : "[\n        'driver' => 'sqlite',\n        'path' => __DIR__ . '/storage/data/clubapp.sqlite',\n    ]";
            $configPhp = "<?php\nreturn [\n    'app_name' => 'HZS Clubapp',\n    'timezone' => 'Europe/Brussels',\n    'debug' => " . ($v['driver'] === 'sqlite' ? 'true' : 'false') . ",\n    'cookie_secure' => false,\n    'db' => " . $dbExport . ",\n];\n";

            $written = @file_put_contents(APP_ROOT . '/config.php', $configPhp) !== false;
            @chmod(APP_ROOT . '/config.php', 0640);

            $body = '<div class="card narrow"><h1>Installatie voltooid</h1>';
            $body .= '<p>Het beheeraccount <strong>' . e($v['admin_email']) . '</strong> is aangemaakt.</p>';
            if (!$written) {
                $body .= '<div class="alert alert-error">Kon <code>config.php</code> niet wegschrijven. Maak het bestand zelf aan in de projectmap met deze inhoud:</div><pre>' . e($configPhp) . '</pre>';
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
<?php page('Installatie', ob_get_clean());
