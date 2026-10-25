<?php
/**
 * E-mail versturen zonder externe bibliotheken.
 * Drivers (config mail.driver): 'log' (bewaart mails in storage/mail), 'smtp', 'mail' (PHP mail()).
 */
final class Mailer
{
    public static function driver(): string
    {
        $d = (string) config('mail.driver', 'log');
        return in_array($d, ['log', 'smtp', 'mail'], true) ? $d : 'log';
    }

    public static function send(string $to, string $subject, string $body): bool
    {
        return self::sendMany([[$to, $subject, $body]])[0];
    }

    /** @param array<int,array{0:string,1:string,2:string}> $messages [aan, onderwerp, tekst] @return bool[] */
    public static function sendMany(array $messages): array
    {
        $result = [];
        $valid = [];
        foreach ($messages as $i => [$to, $subject, $body]) {
            if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
                $result[$i] = false;
                continue;
            }
            $valid[$i] = [$to, $subject, $body];
        }
        try {
            switch (self::driver()) {
                case 'smtp':
                    $result += self::viaSmtp($valid);
                    break;
                case 'mail':
                    foreach ($valid as $i => [$to, $subject, $body]) {
                        [$headers, $encoded] = self::build($to, $subject, $body, false);
                        $result[$i] = @mail($to, self::encodeHeader($subject), $encoded, $headers);
                    }
                    break;
                default:
                    foreach ($valid as $i => [$to, $subject, $body]) {
                        $result[$i] = self::viaLog($to, $subject, $body);
                    }
            }
        } catch (Throwable $e) {
            error_log('Mailer: ' . $e->getMessage());
            foreach ($valid as $i => $_) {
                $result[$i] = $result[$i] ?? false;
            }
        }
        ksort($result);
        return $result;
    }

    private static function fromEmail(): string
    {
        $f = (string) config('mail.from_email', 'noreply@localhost');
        return filter_var($f, FILTER_VALIDATE_EMAIL) ? $f : 'noreply@localhost';
    }

    private static function fromName(): string
    {
        return self::clean((string) config('mail.from_name', config('app_name', 'HZS Clubapp')));
    }

    /** Verwijdert regeleindes zodat er nooit extra headers ingespoten kunnen worden. */
    private static function clean(string $s): string
    {
        return trim(str_replace(["\r", "\n", "\0"], ' ', $s));
    }

    private static function encodeHeader(string $s): string
    {
        $s = self::clean($s);
        return preg_match('/^[\x20-\x7E]*$/', $s) ? $s : '=?UTF-8?B?' . base64_encode($s) . '?=';
    }

    /** @return array{0:string,1:string} [headers, body] (CRLF) */
    private static function build(string $to, string $subject, string $body, bool $withToSubject = true): array
    {
        $h = [
            'From: ' . self::encodeHeader(self::fromName()) . ' <' . self::fromEmail() . '>',
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
        ];
        if ($withToSubject) {
            array_unshift($h, 'Date: ' . date('r'), 'To: <' . self::clean($to) . '>', 'Subject: ' . self::encodeHeader($subject));
        }
        $body = str_replace(["\r\n", "\r"], "\n", $body);
        return [implode("\r\n", $h), rtrim(chunk_split(base64_encode($body), 76, "\r\n"))];
    }

    private static function viaLog(string $to, string $subject, string $body): bool
    {
        $dir = APP_ROOT . '/storage/mail';
        if (!is_dir($dir) && !@mkdir($dir, 0750, true)) {
            return false;
        }
        [$headers, $encoded] = self::build($to, $subject, $body);
        $file = $dir . '/' . date('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.eml';
        $ok = @file_put_contents($file, $headers . "\r\n\r\n" . $encoded . "\r\n") !== false;
        @file_put_contents(
            $dir . '/outbox.log',
            date('Y-m-d H:i:s') . ' | aan ' . $to . ' | ' . self::clean($subject) . "\n" . "    " . str_replace("\n", "\n    ", $body) . "\n\n",
            FILE_APPEND
        );
        return $ok;
    }

    /** Eén SMTP-verbinding voor alle berichten. */
    private static function viaSmtp(array $valid): array
    {
        $out = [];
        if (!$valid) {
            return $out;
        }
        $c = (array) config('mail.smtp', []);
        $enc = (string) ($c['encryption'] ?? 'tls');
        $host = (string) ($c['host'] ?? '');
        $port = (int) ($c['port'] ?? 587);
        $fp = @stream_socket_client(($enc === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port, $errno, $errstr, 15);
        if (!$fp) {
            throw new RuntimeException("SMTP-verbinding mislukt: $errstr");
        }
        stream_set_timeout($fp, 20);
        $say = function (?string $cmd, array $ok) use ($fp): string {
            if ($cmd !== null) {
                fwrite($fp, $cmd . "\r\n");
            }
            $text = '';
            do {
                $line = fgets($fp, 1024);
                if ($line === false) {
                    throw new RuntimeException('SMTP: verbinding verbroken');
                }
                $text .= $line;
            } while (strlen($line) >= 4 && $line[3] === '-');
            if (!in_array((int) substr($text, 0, 3), $ok, true)) {
                throw new RuntimeException('SMTP: onverwacht antwoord: ' . trim($text));
            }
            return $text;
        };
        try {
            $say(null, [220]);
            $name = preg_replace('/[^A-Za-z0-9.-]/', '', (string) ($_SERVER['SERVER_NAME'] ?? 'localhost')) ?: 'localhost';
            $say('EHLO ' . $name, [250]);
            if ($enc === 'tls') {
                $say('STARTTLS', [220]);
                if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new RuntimeException('SMTP: TLS starten mislukt');
                }
                $say('EHLO ' . $name, [250]);
            }
            if (($c['user'] ?? '') !== '') {
                $say('AUTH LOGIN', [334]);
                $say(base64_encode((string) $c['user']), [334]);
                $say(base64_encode((string) ($c['pass'] ?? '')), [235]);
            }
            foreach ($valid as $i => [$to, $subject, $body]) {
                try {
                    $say('MAIL FROM:<' . self::fromEmail() . '>', [250]);
                    $say('RCPT TO:<' . $to . '>', [250, 251]);
                    $say('DATA', [354]);
                    [$headers, $encoded] = self::build($to, $subject, $body);
                    $data = $headers . "\r\n\r\n" . $encoded;
                    $data = preg_replace('/^\./m', '..', $data); // dot-stuffing
                    fwrite($fp, $data . "\r\n.\r\n");
                    $say(null, [250]);
                    $out[$i] = true;
                } catch (RuntimeException $e) {
                    error_log('Mailer: ' . $e->getMessage());
                    $out[$i] = false;
                    @fwrite($fp, "RSET\r\n");
                    @fgets($fp, 1024);
                }
            }
            @fwrite($fp, "QUIT\r\n");
        } finally {
            fclose($fp);
        }
        return $out;
    }
}
