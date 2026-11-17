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

$GLOBALS['CONFIG'] = is_file(config_file())
    ? require config_file()
    : ['app_name' => 'HZS Clubapp', 'timezone' => 'Europe/Brussels', 'debug' => true, 'cookie_secure' => false, 'db' => null];

date_default_timezone_set(config('timezone', 'Europe/Brussels'));
ini_set('display_errors', config('debug', false) ? '1' : '0');
ini_set('log_errors', '1');

set_exception_handler(function (Throwable $e): void {
    error_log('Onverwachte fout: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    if (config('debug', false)) {
        throw $e;
    }
    if (!headers_sent()) {
        http_response_code(500);
    }
    try {
        abort(500, 'Er ging iets mis aan onze kant. Probeer het later opnieuw.');
    } catch (Throwable $again) {
        echo 'Er ging iets mis. Probeer het later opnieuw.';
    }
});

header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('X-Frame-Options: DENY');
if (is_https() || config('cookie_secure', false)) {
    header('Strict-Transport-Security: max-age=31536000');
}
header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; form-action 'self'; frame-ancestors 'none'");

session_cache_limiter(''); // PHP stuurt anders overal 'no-store'; we bepalen dat zelf
header('Cache-Control: no-cache');
session_name('hzs_session');
// Eigen sessiemap: op shared hosting is de standaardmap (/tmp) vaak gedeeld met andere klanten, en PHP ruimt daar
// sessies al na 24 minuten op (gc_maxlifetime 1440) terwijl de app 12 uur belooft.
$sessDir = (string) config('session_path', APP_ROOT . '/storage/sessions');
if (is_dir($sessDir) || @mkdir($sessDir, 0700, true)) {
    if (is_writable($sessDir)) {
        session_save_path($sessDir);
    }
}
ini_set('session.gc_maxlifetime', '43200');
ini_set('session.gc_probability', '1');
ini_set('session.gc_divisor', '200');
ini_set('session.use_strict_mode', '1');
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => (bool) config('cookie_secure', false) || is_https(), // over HTTPS altijd een secure cookie
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

if (config('db') !== null) {
    Database::connect(config('db'));
    try {
        Installer::boot(); // upgrades en nieuwe permissies enkel als de database achterloopt op de code
    } catch (Throwable $e) {
        // tabellen bestaan nog niet (voor de installatie): negeren
    }
}
