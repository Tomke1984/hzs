<?php
/**
 * Eenvoudige, veilige opmaak voor websiteteksten (alles wordt eerst ge-escaped):
 *   ## Kop, ### Subkop, - opsomming, lege regel = nieuwe alinea,
 *   **vet**, *cursief*, [tekst](adres), ![omschrijving](uploads/afbeelding.jpg)
 */
final class Markup
{
    public static function render(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", trim($text));
        if ($text === '') {
            return '';
        }
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
