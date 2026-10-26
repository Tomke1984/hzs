<?php
/** CSV-export voor Excel (puntkomma, UTF-8 met BOM). Cellen die met = + - @ beginnen krijgen een apostrof tegen formule-injectie. */
final class Csv
{
    public static function cell($v): string
    {
        $s = str_replace(["\r\n", "\r"], "\n", (string) $v);
        if ($s !== '' && strpbrk($s[0], "=+-@\t") !== false && !is_numeric($s)) {
            $s = "'" . $s;
        }
        return '"' . str_replace('"', '""', $s) . '"';
    }

    /** Stuurt het bestand naar de browser en beëindigt het verzoek. */
    public static function send(string $filename, array $header, array $rows): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . preg_replace('/[^A-Za-z0-9._-]/', '-', $filename) . '"');
        header('Cache-Control: no-store');
        echo "\xEF\xBB\xBF" . implode(';', array_map([self::class, 'cell'], $header)) . "\r\n";
        foreach ($rows as $r) {
            echo implode(';', array_map([self::class, 'cell'], $r)) . "\r\n";
        }
        exit;
    }
}
