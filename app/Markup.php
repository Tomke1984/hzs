<?php
/**
 * Eenvoudige, veilige opmaak voor websiteteksten (alles wordt eerst ge-escaped):
 *   ## Kop, ### Subkop, - opsomming, lege regel = nieuwe alinea,
 *   **vet**, *cursief*, [tekst](adres), ![omschrijving](uploads/afbeelding.jpg)
 */
final class Markup
{
    /** Plakken uit chats, webpagina's of tekstverwerkers brengt onzichtbare of "mooie" tekens mee (vaste spatie, zero-width spatie, BOM, ＃, •). Die laten een kop of opsomming onopgemerkt; ze worden opgeschoond. */
    public static function clean(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $out = preg_replace_callback('/^([ \t]*)(\x{FF03}+)/mu', fn($m) => $m[1] . str_repeat('#', strlen($m[2]) / 3), $text);
        $out = preg_replace(['/[\x{00A0}\x{1680}\x{2000}-\x{200A}\x{202F}\x{205F}\x{3000}]/u', '/[\x{200B}-\x{200D}\x{2060}\x{FEFF}\x{00AD}]/u', '/^([ \t]*)\x{2022}\s/mu'], [' ', '', '$1- '], $out ?? $text);
        return $out ?? $text;
    }

    public static function render(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = trim(self::clean($text));
        if ($text === '') {
            return '';
        }
        // Tolerant bij plakken: koppen staan altijd op een eigen blok en een opsomming start ook na een gewone regel.
        $prev = '';
        $norm = [];
        foreach (explode("\n", $text) as $line) {
            $isHead = (bool) preg_match('/^#{2,3}\s+\S/', $line);
            $isItem = (bool) preg_match('/^[-*]\s+\S/', $line);
            $prevHead = (bool) preg_match('/^#{2,3}\s+\S/', $prev);
            $prevItem = (bool) preg_match('/^[-*]\s+\S/', $prev);
            if (trim($prev) !== '' && trim($line) !== '' && ($isHead || $prevHead || ($isItem && !$prevItem))) {
                $norm[] = '';
            }
            $norm[] = $line;
            $prev = $line;
        }
        $text = implode("\n", $norm);
        $out = [];
        foreach (preg_split("/\n{2,}/", $text) as $block) {
            $lines = explode("\n", trim($block));
            if (preg_match('/^(#{2,3})\s+(.+)$/', $lines[0], $m) && count($lines) === 1) {
                $n = strlen($m[1]);
                $out[] = "<h$n>" . self::inline($m[2]) . "</h$n>";
            } elseif (preg_match('/^[-*]\s+/', $lines[0])) {
                $items = [];
                foreach ($lines as $l) {
                    $items[] = '<li>' . self::inline(preg_replace('/^[-*]\s+/', '', $l)) . '</li>';
                }
                $out[] = '<ul>' . implode('', $items) . '</ul>';
            } elseif (preg_match('/^!\[[^\]]*\]\([^)]+\)$/', $lines[0]) && count($lines) === 1) {
                $out[] = '<p class="md-figure">' . self::inline($lines[0]) . '</p>';
            } else {
                $out[] = '<p>' . implode('<br>', array_map([self::class, 'inline'], $lines)) . '</p>';
            }
        }
        return implode("\n", $out);
    }

    public static function inline(string $s): string
    {
        $s = e($s);
        $s = preg_replace_callback('/!\[([^\]]*)\]\(([^)\s]+)\)/', function ($m) {
            $src = html_entity_decode($m[2], ENT_QUOTES);
            $ok = preg_match('#^(uploads/[A-Za-z0-9._-]+|https://[^\s"<>*]+)$#', $src);
            return $ok ? '<img src="' . e($src) . '" alt="' . $m[1] . '" loading="lazy">' : $m[0];
        }, $s);
        $s = preg_replace_callback('/\[([^\]]+)\]\(([^)\s]+)\)/', function ($m) {
            $href = html_entity_decode($m[2], ENT_QUOTES);
            $ok = preg_match('#^(https?://[^\s"<>*]+|mailto:[^\s"<>*]+|uploads/[A-Za-z0-9._-]+|\?r=[A-Za-z0-9_&=%.-]+)$#', $href);
            if (!$ok) {
                return $m[0];
            }
            $ext = str_starts_with($href, 'http') ? ' target="_blank" rel="noopener"' : '';
            return '<a href="' . e(pretty_internal($href)) . '"' . $ext . '>' . $m[1] . '</a>';
        }, $s);
        $s = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $s);
        $s = preg_replace('/(?<![\w*])\*(?!\s)(.+?)(?<!\s)\*(?![\w*])/s', '<em>$1</em>', $s);
        return $s;
    }

    public static function slug(string $s): string
    {
        $s = strtolower(trim($s));
        $t = @iconv('UTF-8', 'ASCII//TRANSLIT', $s);
        $s = $t !== false ? $t : $s;
        $s = trim(preg_replace('/[^a-z0-9]+/', '-', $s), '-');
        return $s === '' ? 'pagina' : substr($s, 0, 120);
    }

    /** Uniek slug in tabel $table (posts of pages), eventueel met -2, -3, ... */
    public static function uniqueSlug(string $table, string $base, int $ignoreId = 0): string
    {
        $slug = $base;
        if ($table === 'pages' && in_array($slug, RESERVED_SLUGS, true)) {
            $slug = $base . '-2';
        }
        for ($i = 2; Database::val("SELECT COUNT(*) FROM $table WHERE slug = ? AND id != ?", [$slug, $ignoreId]) > 0; $i++) {
            $slug = $base . '-' . $i;
        }
        return $slug;
    }
}
