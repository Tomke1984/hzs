<?php
/**
 * Kleine end-to-end testomgeving zonder externe bibliotheken.
 * Maakt een kopie van de app in een tijdelijke map, installeert ze met SQLite en demodata, start `php -S`
 * en praat er via HTTP mee (met cookies en CSRF), zoals een browser.
 */
final class T
{
    public static string $root;
    public static string $dir;
    public static string $base;
    public static int $pass = 0;
    public static array $fails = [];
    private static $server;

    public static function setup(): void
    {
        self::$root = dirname(__DIR__, 2);
        self::$dir = sys_get_temp_dir() . '/hzs-e2e-' . getmypid();
        self::rcopy(self::$root, self::$dir, ['.git', 'tests', 'config.php', 'storage/data', 'storage/mail', 'public/uploads']);
        foreach (['storage/data', 'storage/mail', 'public/uploads'] as $d) {
            @mkdir(self::$dir . '/' . $d, 0777, true);
        }
        $port = self::freePort();
        self::$base = "http://127.0.0.1:$port";
        $db = self::$dir . '/storage/data/test.sqlite';
        file_put_contents(self::$dir . '/config.php', '<?php return ' . var_export([
            'app_name' => 'HZS Clubapp', 'timezone' => 'Europe/Brussels', 'debug' => false, 'cookie_secure' => false,
            'app_url' => self::$base, 'mail' => ['driver' => 'log', 'from_email' => 'noreply@example.be', 'from_name' => 'HZS Clubapp'],
            'db' => ['driver' => 'sqlite', 'path' => $db],
            // tests versturen formulieren meteen; de minimumtijd van de controlevraag test v25_captcha apart
            'captcha' => ['min_seconds' => 0],
        ], true) . ';');
        $install = <<<'PHP'
            require $argv[1] . '/app/bootstrap.php';
            Installer::createSchema();
            Installer::seedReferenceData();
            Installer::createUser('admin@demo.local', 'Demo1234!', 'Ad Beheer', 'ADMIN');
            Installer::seedDemo();
            echo "ok";
            PHP;
        $out = shell_exec(PHP_BINARY . ' -r ' . escapeshellarg($install) . ' ' . escapeshellarg(self::$dir) . ' 2>&1');
        if (trim((string) $out) !== 'ok') {
            fwrite(STDERR, "Installatie mislukt: $out\n");
            exit(1);
        }
        $cmd = [PHP_BINARY, '-d', 'opcache.enable=0', '-d', 'opcache.enable_cli=0', '-S', "127.0.0.1:$port", '-t', self::$dir . '/public'];
        self::$server = proc_open($cmd, [1 => ['file', '/dev/null', 'w'], 2 => ['file', self::$dir . '/server.log', 'w']], $pipes, self::$dir);
        for ($i = 0; $i < 50; $i++) {
            if (@fsockopen('127.0.0.1', $port)) {
                break;
            }
            usleep(100000);
        }
        register_shutdown_function([self::class, 'teardown']);
    }

    public static function teardown(): void
    {
        if (self::$server) {
            proc_terminate(self::$server);
            self::$server = null;
        }
        if (getenv('KEEP') !== '1') {
            exec('rm -rf ' . escapeshellarg(self::$dir));
        } else {
            echo 'Testmap bewaard: ' . self::$dir . "\n";
        }
    }

    /** Instelling(en) in config.php van de testkopie aanpassen, bv. ['features' => ['attendance' => true]]. */
    public static function config(array $over): void
    {
        $f = self::$dir . '/config.php';
        $cfg = array_replace_recursive(require $f, $over);
        file_put_contents($f, '<?php return ' . var_export($cfg, true) . ';');
        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($f, true);
        }
    }

    /**
     * Doet alsof de database van een oudere versie komt: zonder app_meta (schemaversie) en zonder de indexen van v0.19.
     * Het volgende verzoek voert dan de upgrades uit, zoals na een update op de server.
     */
    public static function oldDatabase(): void
    {
        foreach (self::all("SELECT name FROM sqlite_master WHERE type = 'index' AND name LIKE 'idx_%'") as $r) {
            self::exec('DROP INDEX ' . $r['name']);
        }
        self::exec('DROP TABLE IF EXISTS app_meta');
        self::exec('DROP TABLE IF EXISTS schema_migrations');
    }

    public static function pdo(): PDO
    {
        static $p = null;
        if (!$p) {
            $p = new PDO('sqlite:' . self::$dir . '/storage/data/test.sqlite');
            $p->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $p->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        }
        return $p;
    }

    public static function val(string $sql, array $p = [])
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($p);
        return $st->fetchColumn();
    }

    public static function all(string $sql, array $p = []): array
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($p);
        return $st->fetchAll();
    }

    public static function exec(string $sql, array $p = []): void
    {
        self::pdo()->prepare($sql)->execute($p);
    }

    /** Logboek van de verstuurde mails (driver 'log'): "datum | aan x | onderwerp" + tekst. */
    public static function outbox(): string
    {
        return (string) @file_get_contents(self::$dir . '/storage/mail/outbox.log');
    }

    public static function clearMails(): void
    {
        foreach (glob(self::$dir . '/storage/mail/*') ?: [] as $f) {
            unlink($f);
        }
    }

    /** Onthoudt dat de automatische herinnering net gelopen heeft (of wist dat, met $ran = false). */
    public static function autoRan(bool $ran = true): void
    {
        $f = self::$dir . '/storage/coverage-last-run';
        $ran ? touch($f) : @unlink($f);
    }

    public static function ok(bool $cond, string $label): void
    {
        if ($cond) {
            self::$pass++;
        } else {
            self::$fails[] = $label;
            echo "  FOUT: $label\n";
        }
    }

    public static function report(): int
    {
        $log = @file_get_contents(self::$dir . '/server.log') ?: '';
        if (preg_match_all('/(PHP (Fatal|Warning|Notice|Deprecated)[^\n]*)/', $log, $m)) {
            foreach (array_unique($m[1]) as $l) {
                self::$fails[] = 'serverlog: ' . $l;
                echo "  FOUT serverlog: $l\n";
            }
        }
        echo "\n" . self::$pass . ' geslaagd, ' . count(self::$fails) . " mislukt\n";
        return self::$fails ? 1 : 0;
    }

    private static function freePort(): int
    {
        $s = stream_socket_server('tcp://127.0.0.1:0');
        $port = (int) substr(strrchr(stream_socket_get_name($s, false), ':'), 1);
        fclose($s);
        return $port;
    }

    private static function rcopy(string $src, string $dst, array $skip, string $rel = ''): void
    {
        @mkdir($dst, 0777, true);
        foreach (scandir($src) as $f) {
            if ($f === '.' || $f === '..' || in_array(ltrim("$rel/$f", '/'), $skip, true)) {
                continue;
            }
            is_dir("$src/$f") ? self::rcopy("$src/$f", "$dst/$f", $skip, "$rel/$f") : copy("$src/$f", "$dst/$f");
        }
    }
}

/** Een "browser": onthoudt cookies, volgt geen redirects (die controleren we zelf). */
final class Browser
{
    private array $cookies = [];
    public int $status = 0;
    public string $body = '';
    public string $location = '';
    /** Extra headers bij elk verzoek, bv. ['X-Forwarded-Proto: https']. */
    public array $headers = [];
    /** Ruwe Set-Cookie-headers van het laatste antwoord. */
    public array $setCookies = [];

    public static function login(string $email): self
    {
        $b = new self();
        $b->get('/aanmelden');
        $b->post('/aanmelden', ['email' => $email, 'password' => 'Demo1234!', 'captcha' => $b->captcha()], '?r=login');
        if ($b->status !== 302) {
            throw new RuntimeException("Aanmelden mislukt voor $email (status {$b->status})");
        }
        return $b;
    }

    /** Antwoord op de controlevraag van de laatst geladen pagina ("Hoeveel is drie plus 5?"). */
    public function captcha(): string
    {
        $w = ['een' => 1, 'twee' => 2, 'drie' => 3, 'vier' => 4, 'vijf' => 5, 'zes' => 6, 'zeven' => 7, 'acht' => 8, 'negen' => 9];
        if (!preg_match('/Hoeveel is (\w+) plus (\w+)\?/', $this->body, $m)) {
            return '';
        }
        $n = fn(string $x): int => ctype_digit($x) ? (int) $x : ($w[$x] ?? 0);
        return (string) ($n($m[1]) + $n($m[2]));
    }

    public function get(string $path): self
    {
        return $this->request('GET', $path);
    }

    /** POST met automatisch het CSRF-token van de sessie. */
    public function post(string $path, array $data, ?string $tokenFrom = null): self
    {
        if (!isset($data['_csrf'])) {
            $data['_csrf'] = $this->csrf();
        }
        return $this->request('POST', $path, http_build_query($data));
    }

    public function csrf(): string
    {
        if (!preg_match('/name="_csrf" value="([a-f0-9]+)"/', $this->body, $m)) {
            $this->get('/app/account');
            preg_match('/name="_csrf" value="([a-f0-9]+)"/', $this->body, $m);
        }
        return $m[1] ?? '';
    }

    /** Volgt één redirect (na een POST) en geeft de pagina terug. */
    public function follow(): self
    {
        $loc = $this->location;
        if ($loc === '') {
            return $this;
        }
        if (preg_match('#^https?://[^/]+(/.*)$#', $loc, $m)) {
            $loc = $m[1];
        }
        return $this->get(str_starts_with($loc, '/') ? $loc : '/' . $loc);
    }

    private function request(string $method, string $path, string $body = ''): self
    {
        $headers = array_merge(['Host: ' . parse_url(T::$base, PHP_URL_HOST) . ':' . parse_url(T::$base, PHP_URL_PORT)], $this->headers);
        $this->setCookies = [];
        if ($this->cookies) {
            $headers[] = 'Cookie: ' . implode('; ', array_map(fn($k, $v) => "$k=$v", array_keys($this->cookies), $this->cookies));
        }
        if ($method === 'POST') {
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        }
        $ctx = stream_context_create(['http' => [
            'method' => $method, 'header' => implode("\r\n", $headers), 'content' => $body,
            'follow_location' => 0, 'ignore_errors' => true, 'timeout' => 30,
        ]]);
        $this->body = (string) @file_get_contents(T::$base . $path, false, $ctx);
        $this->status = 0;
        $this->location = '';
        foreach ($http_response_header ?? [] as $h) {
            if (preg_match('#^HTTP/\S+ (\d+)#', $h, $m)) {
                $this->status = (int) $m[1];
            } elseif (stripos($h, 'Set-Cookie:') === 0 && preg_match('/^Set-Cookie:\s*([^=]+)=([^;]*)/i', $h, $m)) {
                $this->setCookies[] = $h;
                if ($m[2] === '' || $m[2] === 'deleted') {
                    unset($this->cookies[$m[1]]);
                } else {
                    $this->cookies[$m[1]] = $m[2];
                }
            } elseif (stripos($h, 'Location:') === 0) {
                $this->location = trim(substr($h, 9));
            }
        }
        return $this;
    }

    public function has(string $needle): bool
    {
        return str_contains($this->body, $needle);
    }
}
