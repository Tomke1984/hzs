<?php
/**
 * v0.26: draaien op Coolify met Railpack (FrankenPHP/Caddy): eigen Caddyfile met public/ als webmap,
 * composer.json voor de PHP-versie, en config.php op een blijvende plek via HZS_CONFIG.
 */
T::autoRan();

echo "Caddyfile en composer.json (Railpack)\n";
$caddy = (string) @file_get_contents(T::$root . '/Caddyfile');
T::ok((bool) preg_match('#root \* \{\$HZS_PUBLIC_DIR:/app/public\}#', $caddy), 'Caddyfile: enkel public/ is de webmap (niet de hele projectmap)');
T::ok(str_contains($caddy, 'php_server') && str_contains($caddy, ':{$PORT:80}'), 'Caddyfile: PHP via FrankenPHP op poort 80');
T::ok(str_contains($caddy, '@hidden path */.*') && str_contains($caddy, 'immutable'), 'Caddyfile: verborgen bestanden geweigerd, assets lang in de cache');
T::ok(!str_contains($caddy, '{{'), 'Caddyfile zonder Go-templatecode (Railpack verwerkt het als template)');
$composer = json_decode((string) @file_get_contents(T::$root . '/composer.json'), true);
T::ok(is_array($composer) && str_starts_with((string) ($composer['require']['php'] ?? ''), '^8.'), 'composer.json legt de PHP-versie vast');
T::ok(count(array_filter(array_keys($composer['require'] ?? []), fn($k) => !str_starts_with($k, 'php') && !str_starts_with($k, 'ext-'))) === 0, 'geen externe pakketten nodig');

echo "Plaats van config.php\n";
$probe = function (array $env, string $root): string {
    $code = 'define("APP_ROOT", ' . var_export($root, true) . '); require APP_ROOT . "/app/helpers.php"; echo config_file();';
    $prefix = '';
    foreach ($env as $k => $v) {
        $prefix .= $k . '=' . escapeshellarg($v) . ' ';
    }
    return trim((string) shell_exec($prefix . escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($code)));
};
T::ok($probe([], T::$dir) === T::$dir . '/config.php', 'standaard: config.php in de projectmap');
T::ok($probe(['HZS_CONFIG' => '/data/hzs/config.php'], T::$dir) === '/data/hzs/config.php', 'HZS_CONFIG wijst naar een andere plek (bv. het volume in Coolify)');
$tmp = sys_get_temp_dir() . '/hzs-cfg-' . getmypid();
@mkdir("$tmp/storage", 0777, true);
@mkdir("$tmp/app", 0777, true);
copy(T::$dir . '/app/helpers.php', "$tmp/app/helpers.php");
file_put_contents("$tmp/storage/config.php", "<?php return [];");
T::ok($probe([], $tmp) === "$tmp/storage/config.php", 'zonder config.php in de projectmap: storage/config.php wordt gebruikt');
array_map('unlink', ["$tmp/storage/config.php", "$tmp/app/helpers.php"]);
@rmdir("$tmp/storage");
@rmdir("$tmp/app");
@rmdir($tmp);

// De app zelf werkt nog gewoon met config.php in de projectmap
$admin = Browser::login('admin@demo.local');
$admin->get('/app');
T::ok($admin->status === 200, 'app werkt');

echo "config.php kwijt (bv. na een deploy): bestaande database herkennen en instellingen herstellen\n";
$admin->get('/app/leden');
T::ok($admin->status === 200, 'voor het testen: app werkt');
// Situatie zoals in Coolify zonder blijvende config.php: database op het volume, config.php weg
copy(T::$dir . '/storage/data/test.sqlite', T::$dir . '/storage/data/clubapp.sqlite');
rename(T::$dir . '/config.php', T::$dir . '/config.php.weg');
$anon = new Browser();
$anon->get('/');
T::ok($anon->status === 302 && str_contains($anon->location, 'install.php'), 'zonder config.php: naar de installer');
$anon->get('/install.php');
T::ok($anon->has('Bestaande database gevonden') && $anon->has('Instellingen herstellen') && !$anon->has('name="admin_password"'), 'installer herkent de bestaande database en biedt geen nieuwe installatie aan');
T::ok($anon->has(T::$dir . '/storage/config.php'), 'toont waar de instellingen komen: storage/config.php (op het volume)');
$anon->post('/install.php', ['mode' => 'install', 'driver' => 'sqlite', 'admin_name' => 'X', 'admin_email' => 'x@voorbeeld.be', 'admin_password' => 'EenLangWachtwoord9!', 'admin_password2' => 'EenLangWachtwoord9!']);
T::ok($anon->has('Bestaande database gevonden') && (int) T::val("SELECT COUNT(*) FROM users WHERE email = 'x@voorbeeld.be'") === 0, 'een nieuwe installatie over de bestaande database kan niet');
$anon->get('/install.php');
$anon->post('/install.php', ['mode' => 'recover']);
T::ok($anon->has('Instellingen hersteld') && is_file(T::$dir . '/storage/config.php'), 'instellingen hersteld in storage/config.php');
T::ok(str_contains((string) file_get_contents(T::$dir . '/storage/config.php'), "__DIR__ . '/data/clubapp.sqlite'"), 'pad naar de database relatief, zodat het ook na een verhuis klopt');
$back = Browser::login('admin@demo.local');
$back->get('/app/leden');
T::ok($back->status === 200, 'aanmelden met het bestaande account werkt weer');
$back->get('/');
T::ok($back->status !== 302 || !str_contains($back->location, 'install.php'), 'geen installer meer bij een volgend bezoek');
// terugzetten voor de rest van de suite
unlink(T::$dir . '/storage/config.php');
rename(T::$dir . '/config.php.weg', T::$dir . '/config.php');

echo "Waarschuwing zonder blijvend volume (Coolify)\n";
$probe2 = function (array $lines): string {
    $code = 'define("APP_ROOT", "/app"); require ' . var_export(T::$dir . '/app/helpers.php', true) . '; echo storage_not_persistent(' . var_export($lines, true) . ', "/app") ? "ja" : "nee";';
    return (string) shell_exec(PHP_BINARY . ' -r ' . escapeshellarg($code));
};
$base = ['22 1 0:21 / / rw,relatime - overlay overlay rw', '23 22 0:22 / /proc rw - proc proc rw'];
T::ok($probe2($base) === 'ja', 'container zonder volume op /app/storage: waarschuwing');
T::ok($probe2(array_merge($base, ['40 22 8:1 /var/lib/docker/volumes/x/_data /app/storage rw,relatime - ext4 /dev/sda1 rw'])) === 'nee', 'met volume op /app/storage: geen waarschuwing');
T::ok(str_contains((string) file_get_contents(T::$dir . '/public/install.php'), 'storage_not_persistent()'), 'installer controleert de opslag');
