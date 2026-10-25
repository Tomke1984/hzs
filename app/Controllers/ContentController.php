<?php
/** Beheer van de website: nieuws, pagina's en afbeeldingen (permissie content.manage). */
final class ContentController
{
    /** Pagina's waarvan (een deel van) de inhoud uit de backend komt: slug => [bron, route, enkel inleiding bewerkbaar]. */
    public const DYNAMIC = [
        'trainingsuren-competitie' => ['de trainingsuren komen uit Reeksen', 'series', true],
        'doelstellingen' => ['de groepen en hun doelstellingen komen uit Groepen', 'groups', true],
        'contact' => ['het contactformulier en de berichten komen uit de app (menu Berichten)', 'messages', false],
    ];

    public static function index(): void
    {
        $tab = in_array($_GET['tab'] ?? '', ['news', 'pages', 'media'], true) ? $_GET['tab'] : 'news';
        $posts = Database::all('SELECT id, title, slug, status, published_at, updated_at FROM posts ORDER BY COALESCE(published_at, created_at) DESC, id DESC');
        $pages = Database::all('SELECT p.*, par.title AS parent_title FROM pages p LEFT JOIN pages par ON par.id = p.parent_id ORDER BY COALESCE(par.sort_order, p.sort_order), COALESCE(par.title, p.title), p.parent_id IS NOT NULL, p.sort_order, p.title');
        $media = Database::all('SELECT * FROM media ORDER BY id DESC');
        global $routes;
        $check = $tab === 'pages' ? PageCheck::run($routes) : ['issues' => [], 'removable' => []];
        render('content/index', compact('tab', 'posts', 'pages', 'media', 'check'), 'Website');
    }

    /* ---------------- nieuws ---------------- */
    public static function postForm(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        $p = $id ? Database::one('SELECT * FROM posts WHERE id = ?', [$id]) : null;
        if ($id && !$p) {
            abort(404, 'Bericht niet gevonden.');
        }
        $p ??= ['id' => 0, 'title' => '', 'summary' => '', 'body' => '', 'image' => null, 'status' => 'DRAFT', 'slug' => ''];
        render('content/post_form', ['p' => $p, 'errors' => []], $id ? 'Bericht wijzigen' : 'Nieuw bericht');
    }

    public static function postSave(): void
    {
        $id = (int) input('id', '0');
        $old = $id ? Database::one('SELECT * FROM posts WHERE id = ?', [$id]) : null;
        if ($id && !$old) {
            abort(404, 'Bericht niet gevonden.');
        }
        $p = [
            'id' => $id, 'title' => input('title'), 'summary' => input('summary'), 'body' => (string) ($_POST['body'] ?? ''),
            'status' => input('status') === 'PUBLISHED' ? 'PUBLISHED' : 'DRAFT', 'image' => $old['image'] ?? null, 'slug' => $old['slug'] ?? '',
        ];
        $errors = [];
        if ($p['title'] === '' || mb_strlen($p['title']) > 190) {
            $errors[] = 'Titel is verplicht (max. 190 tekens).';
        }
        if (mb_strlen($p['summary']) > 300) {
            $errors[] = 'De samenvatting is te lang (max. 300 tekens).';
        }
        if (mb_strlen($p['body']) > 60000) {
            $errors[] = 'De tekst is te lang.';
        }
        [$newImage, $imgErr] = Uploads::store($_FILES['image'] ?? []);
        if ($imgErr) {
            $errors[] = $imgErr;
        }
        if ($errors) {
            Uploads::delete($newImage);
            http_response_code(422);
            render('content/post_form', ['p' => $p, 'errors' => $errors], $id ? 'Bericht wijzigen' : 'Nieuw bericht');
            return;
        }
        $image = $old['image'] ?? null;
        if ($newImage) {
            Uploads::delete($image);
            $image = $newImage;
        } elseif ($image && input('remove_image') === '1') {
            Uploads::delete($image);
            $image = null;
        }
        $summary = $p['summary'] === '' ? null : $p['summary'];
        if ($id) {
            $pub = $old['published_at'] ?: ($p['status'] === 'PUBLISHED' ? now() : null);
            Database::exec('UPDATE posts SET title=?, summary=?, body=?, image=?, status=?, published_at=?, updated_at=? WHERE id=?',
                [$p['title'], $summary, $p['body'], $image, $p['status'], $pub, now(), $id]);
        } else {
            $slug = Markup::uniqueSlug('posts', Markup::slug($p['title']));
            $id = Database::insert('INSERT INTO posts (title, slug, summary, body, image, status, published_at, author_id, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?)',
                [$p['title'], $slug, $summary, $p['body'], $image, $p['status'], $p['status'] === 'PUBLISHED' ? now() : null, Auth::user()['id'], now(), now()]);
        }
        audit($old ? 'UPDATE' : 'CREATE', 'post', $id);
        flash('success', $p['status'] === 'PUBLISHED' ? 'Bericht opgeslagen en gepubliceerd.' : 'Bericht opgeslagen als concept.');
        redirect(url('content', ['tab' => 'news']));
    }

    public static function postDelete(): void
    {
        $p = Database::one('SELECT id, image FROM posts WHERE id = ?', [(int) input('id', '0')]);
        if (!$p) {
            abort(404, 'Bericht niet gevonden.');
        }
        Database::exec('DELETE FROM posts WHERE id = ?', [$p['id']]);
        Uploads::delete($p['image']);
        audit('DELETE', 'post', (int) $p['id']);
        flash('success', 'Bericht verwijderd.');
        redirect(url('content', ['tab' => 'news']));
    }

    /* ---------------- pagina's ---------------- */
    public static function pageForm(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        $p = $id ? Database::one('SELECT * FROM pages WHERE id = ?', [$id]) : null;
        if ($id && !$p) {
            abort(404, 'Pagina niet gevonden.');
        }
        $p ??= ['id' => 0, 'title' => '', 'slug' => '', 'body' => '', 'parent_id' => null, 'sort_order' => 0, 'status' => 'DRAFT', 'in_menu' => 1];
        self::renderPageForm($p, []);
    }

    public static function pageSave(): void
    {
        $id = (int) input('id', '0');
        $old = $id ? Database::one('SELECT * FROM pages WHERE id = ?', [$id]) : null;
        if ($id && !$old) {
            abort(404, 'Pagina niet gevonden.');
        }
        $dyn = $old ? (self::DYNAMIC[$old['slug']] ?? null) : null;
        $parent = (int) input('parent_id', '0');
        $p = [
            'id' => $id, 'title' => input('title'), 'slug' => input('slug'), 'body' => (string) ($_POST['body'] ?? ''),
            'parent_id' => $parent ?: null, 'sort_order' => (int) input('sort_order', '0'),
            'status' => input('status') === 'PUBLISHED' ? 'PUBLISHED' : 'DRAFT', 'in_menu' => input('in_menu') === '1' ? 1 : 0,
        ];
        if ($dyn) {
            $p['slug'] = $old['slug'];
            $p['parent_id'] = $old['parent_id'];
            $p['status'] = 'PUBLISHED';
            if ($dyn[2]) {
                $p['body'] = trim((string) preg_split('/^#{2,3}\s/m', $p['body'], 2)[0]);
            }
        }
        $errors = [];
        if ($p['title'] === '' || mb_strlen($p['title']) > 190) {
            $errors[] = 'Titel is verplicht (max. 190 tekens).';
        }
        if (mb_strlen($p['body']) > 60000) {
            $errors[] = 'De tekst is te lang.';
        }
        if ($parent) {
            $par = Database::one('SELECT id, parent_id FROM pages WHERE id = ?', [$parent]);
            if (!$par || $par['parent_id'] || $parent === $id) {
                $errors[] = 'Kies een geldige bovenliggende pagina (maximaal één niveau diep).';
            } elseif ($id && Database::val('SELECT COUNT(*) FROM pages WHERE parent_id = ?', [$id]) > 0) {
                $errors[] = 'Deze pagina heeft zelf subpagina\'s en kan dus niet onder een andere pagina komen.';
            }
        }
        if ($errors) {
            http_response_code(422);
            self::renderPageForm($p, $errors);
            return;
        }
        $slug = Markup::uniqueSlug('pages', Markup::slug($p['slug'] !== '' ? $p['slug'] : $p['title']), $id);
        if ($id) {
            Database::exec('UPDATE pages SET title=?, slug=?, body=?, parent_id=?, sort_order=?, status=?, in_menu=?, updated_at=? WHERE id=?',
                [$p['title'], $slug, $p['body'], $p['parent_id'], $p['sort_order'], $p['status'], $p['in_menu'], now(), $id]);
        } else {
            $id = Database::insert('INSERT INTO pages (title, slug, body, parent_id, sort_order, status, in_menu, updated_at) VALUES (?,?,?,?,?,?,?,?)',
                [$p['title'], $slug, $p['body'], $p['parent_id'], $p['sort_order'], $p['status'], $p['in_menu'], now()]);
        }
        audit($old ? 'UPDATE' : 'CREATE', 'page', $id);
        flash('success', 'Pagina opgeslagen.');
        redirect(url('content', ['tab' => 'pages']));
    }

    /** Startinhoud van de vorige website (khzs.be) importeren; voegt enkel ontbrekende pagina's toe. */
    public static function seedPages(): void
    {
        $r = SiteSeed::import();
        audit('IMPORT', 'page', null, json_encode($r));
        flash('success', $r['added'] . ' pagina\'s toegevoegd' . ($r['skipped'] ? ', ' . $r['skipped'] . ' bestonden al en bleven ongemoeid' : '') . '.');
        redirect(url('content', ['tab' => 'pages']));
    }

    public static function pageDelete(): void
    {
        $id = (int) input('id', '0');
        if (!Database::val('SELECT COUNT(*) FROM pages WHERE id = ?', [$id])) {
            abort(404, 'Pagina niet gevonden.');
        }
        if (isset(self::DYNAMIC[(string) Database::val('SELECT slug FROM pages WHERE id = ?', [$id])])) {
            flash('error', 'Deze pagina haalt zijn inhoud uit de app en kan niet verwijderd worden.');
            redirect(url('content', ['tab' => 'pages']));
        }
        if (Database::val('SELECT COUNT(*) FROM pages WHERE parent_id = ?', [$id]) > 0) {
            flash('error', 'Deze pagina heeft subpagina\'s. Verwijder of verplaats die eerst.');
            redirect(url('content', ['tab' => 'pages']));
        }
        Database::exec('DELETE FROM pages WHERE id = ?', [$id]);
        audit('DELETE', 'page', $id);
        flash('success', 'Pagina verwijderd.');
        redirect(url('content', ['tab' => 'pages']));
    }

    /** Verwijdert enkel pagina's die de controle als verouderd of leeg aanmerkt. */
    public static function pageCleanup(): void
    {
        global $routes;
        $n = 0;
        foreach (PageCheck::run($routes)['removable'] as $id) {
            $slug = (string) Database::val('SELECT slug FROM pages WHERE id = ?', [$id]);
            if (!isset(self::DYNAMIC[$slug]) && Database::val('SELECT COUNT(*) FROM pages WHERE parent_id = ?', [$id]) == 0) {
                Database::exec('DELETE FROM pages WHERE id = ?', [$id]);
                audit('DELETE', 'page', $id, 'opruiming');
                $n++;
            }
        }
        flash('success', $n ? $n . ' overbodige pagina\'s verwijderd.' : 'Er was niets op te ruimen.');
        redirect(url('content', ['tab' => 'pages']));
    }

    private static function renderPageForm(array $p, array $errors): void
    {
        $parents = Database::all('SELECT id, title FROM pages WHERE parent_id IS NULL AND id != ? ORDER BY title', [$p['id']]);
        render('content/page_form', ['p' => $p, 'errors' => $errors, 'parents' => $parents], $p['id'] ? 'Pagina wijzigen' : 'Nieuwe pagina');
    }

    /* ---------------- afbeeldingen ---------------- */
    public static function mediaUpload(): void
    {
        [$path, $err] = Uploads::store($_FILES['file'] ?? []);
        if ($err || !$path) {
            flash('error', $err ?: 'Kies een afbeelding om te uploaden.');
            redirect(url('content', ['tab' => 'media']));
        }
        $name = mb_substr(basename((string) ($_FILES['file']['name'] ?? 'afbeelding')), 0, 190);
        $id = Database::insert('INSERT INTO media (path, original_name, uploaded_by, created_at) VALUES (?,?,?,?)', [$path, $name, Auth::user()['id'], now()]);
        audit('CREATE', 'media', $id);
        flash('success', 'Afbeelding geüpload. Kopieer de code hieronder om ze in een tekst te plaatsen.');
        redirect(url('content', ['tab' => 'media']));
    }

    public static function mediaDelete(): void
    {
        $m = Database::one('SELECT * FROM media WHERE id = ?', [(int) input('id', '0')]);
        if (!$m) {
            abort(404, 'Afbeelding niet gevonden.');
        }
        Database::exec('DELETE FROM media WHERE id = ?', [$m['id']]);
        Uploads::delete($m['path']);
        audit('DELETE', 'media', (int) $m['id']);
        flash('success', 'Afbeelding verwijderd.');
        redirect(url('content', ['tab' => 'media']));
    }
}
