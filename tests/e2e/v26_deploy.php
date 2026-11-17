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
