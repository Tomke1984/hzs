<?php
/** Controle van de websitepagina's: verouderd, leeg, kapotte links, onbereikbaar. */
final class PageCheck
{
    /** @return array{issues: array<int, list<array{0:string,1:string}>>, removable: list<int>} per pagina-id: lijst [niveau(warn|info), tekst] */
    public static function run(array $routes): array
    {
        $pages = Database::all('SELECT id, title, slug, body, parent_id, status, in_menu FROM pages');
        $bySlug = [];
        $byId = [];
        $children = [];
        foreach ($pages as $p) {
            $bySlug[$p['slug']] = $p;
            $byId[(int) $p['id']] = $p;
            if ($p['parent_id']) {
                $children[(int) $p['parent_id']][] = $p;
            }
        }
        // Teksten waarin naar pagina's verwezen kan worden: pagina's, nieuws en groepsdoelstellingen.
        $texts = [];
        foreach ($pages as $p) {
            $texts[$p['id']] = (string) $p['body'];
        }
        $extra = implode("\n", array_merge(
            array_column(Database::all('SELECT body FROM posts'), 'body'),
            array_column(Database::all('SELECT goals FROM `groups`'), 'goals')
        ));
        $linked = [];
        foreach ($texts + ['x' => $extra] as $from => $t) {
            if (preg_match_all('/\?r=page&(?:amp;)?slug=([a-z0-9-]+)/i', $t, $m)) {
                foreach ($m[1] as $s) {
                    if ($from === 'x' || (int) ($bySlug[$s]["id"] ?? 0) !== $from) {
                        $linked[$s] = true;
                    }
                }
            }
        }
        $issues = [];
        $removable = [];
        foreach ($pages as $p) {
            $id = (int) $p['id'];
            $i = [];
            $body = trim((string) $p['body']);
            $kids = $children[$id] ?? [];
            if (str_starts_with($p['slug'], 'doelstellingen-')) {
                $i[] = ['warn', 'Verouderd: doelstellingen horen nu bij de groep zelf (Groepen → groep wijzigen).'];
                $removable[] = $id;
            }
            if ($body === '' && !$kids && $p['slug'] !== 'home') {
                $i[] = ['warn', 'De pagina is leeg.'];
                if (!in_array($id, $removable, true)) {
                    $removable[] = $id;
                }
            }
            if ($p['slug'] === 'trainingsuren-competitie' && preg_match('/^##\s/m', $body) && !empty(SiteController::timetable()['COMPETITION'])) {
                $i[] = ['info', 'De trainingsuren komen automatisch uit Reeksen; de vaste uren in deze tekst worden niet getoond (enkel de inleiding vóór de eerste kop). Je mag ze hier verwijderen.'];
            }
            if ($p['status'] !== 'PUBLISHED') {
                $i[] = ['info', 'Concept: niet zichtbaar op de website.'];
            }
            if ($p['slug'] !== 'home' && $p['status'] === 'PUBLISHED') {
                $parent = $p['parent_id'] ? ($byId[(int) $p['parent_id']] ?? null) : null;
                $inMenu = (int) $p['in_menu'] === 1 && (!$parent || ((int) $parent['in_menu'] === 1 && $parent['status'] === 'PUBLISHED'));
                if (!$inMenu && empty($linked[$p['slug']]) && !in_array($p['slug'], ['trainingsuren-competitie'], true)) {
                    $i[] = ['warn', 'Onbereikbaar: niet in het menu en nergens naar verwezen.'];
                }
            }
            if (preg_match_all('/\]\(\?r=([a-z_]+)((?:&(?:amp;)?[a-z_]+=[^)\s]*)*)\)/i', (string) $p['body'], $m, PREG_SET_ORDER)) {
                foreach ($m as $l) {
                    if (!isset($routes[$l[1]])) {
                        $i[] = ['warn', 'Kapotte link: onbekende route "' . $l[1] . '".'];
                    } elseif ($l[1] === 'page') {
                        preg_match('/slug=([a-z0-9-]+)/i', $l[2], $sm);
                        $t = $sm[1] ?? '';
                        if (!isset($bySlug[$t])) {
                            $i[] = ['warn', 'Kapotte link: pagina "' . $t . '" bestaat niet.'];
                        } elseif ($bySlug[$t]['status'] !== 'PUBLISHED') {
                            $i[] = ['warn', 'Link naar concept-pagina "' . $t . '" (niet zichtbaar).'];
                        }
                    }
                }
            }
            if ($i) {
                $issues[$id] = $i;
            }
        }
        return ['issues' => $issues, 'removable' => $removable];
    }
}
