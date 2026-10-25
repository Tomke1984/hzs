<?php
define('APP_ROOT', dirname(__DIR__));

spl_autoload_register(function (string $class): void {
    foreach (['/app/', '/app/Controllers/'] as $dir) {
        $file = APP_ROOT . $dir . $class . '.php';
        if (is_file($file)) {
            require $file;
            return;
        }
    }
});

require APP_ROOT . '/app/helpers.php';

$GLOBALS['CONFIG'] = is_file(APP_ROOT . '/config.php')
    ? require APP_ROOT . '/config.php'
    : ['app_name' => 'HZS Clubapp', 'timezone' => 'Europe/Brussels', 'debug' => true, 'cookie_secure' => false, 'db' => null];

date_default_timezone_set(config('timezone', 'Europe/Brussels'));
ini_set('display_errors', config('debug', false) ? '1' : '0');
ini_set('log_errors', '1');

header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; form-action 'self'; frame-ancestors 'none'");

session_name('hzs_session');
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => (bool) config('cookie_secure', false),
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

if (config('db') !== null) {
    Database::connect(config('db'));
}
