<?php
/**
 * Fotogalerij. Een foto is enkel publiek zichtbaar als het album gepubliceerd is, een beheerder bevestigd heeft dat de toestemming in orde is
 * en alle getagde leden toestemming voor foto's hebben (members.photo_consent). Trekt een lid de toestemming in, dan verdwijnt de foto meteen.
 */
final class GalleryController
{
    /** SQL-voorwaarde: foto $p mag publiek getoond worden. */
    private const VISIBLE = "p.consent_ok = 1 AND NOT EXISTS (SELECT 1 FROM photo_members pm JOIN members m ON m.id = pm.member_id WHERE pm.photo_id = p.id AND m.photo_consent = 0)";

    public static function hasPublic(): bool
    {
        return (bool) Database::val("SELECT 1 FROM gallery_albums a JOIN gallery_photos p ON p.album_id = a.id WHERE a.status = 'PUBLISHED' AND " . self::VISIBLE . ' LIMIT 1');
    }

    public static function publicIndex(): void
    {
        $albums = Database::all(
            "SELECT a.*, (SELECT COUNT(*) FROM gallery_photos p WHERE p.album_id = a.id AND " . self::VISIBLE . ") AS n,
                    (SELECT md.path FROM gallery_photos p JOIN media md ON md.id = p.media_id WHERE p.album_id = a.id AND " . self::VISIBLE . " ORDER BY p.id LIMIT 1) AS cover
             FROM gallery_albums a WHERE a.status = 'PUBLISHED' ORDER BY a.id DESC"
        );
        $albums = array_values(array_filter($albums, fn($a) => (int) $a['n'] > 0));
        $album = null;
        $photos = [];
        $sel = (int) ($_GET['album'] ?? 0);
        if ($sel) {
            foreach ($albums as $a) {
                if ((int) $a['id'] === $sel) {
                    $album = $a;
                }
            }
            if (!$album) {
                abort(404, 'Dit album bestaat niet.');
            }
            $photos = Database::all(
                'SELECT p.*, md.path FROM gallery_photos p JOIN media md ON md.id = p.media_id WHERE p.album_id = ? AND ' . self::VISIBLE . ' ORDER BY p.id',
                [$album['id']]
            );
        }
        render_public('gallery/public', compact('albums', 'album', 'photos'), $album ? $album['title'] : 'Foto\'s');
    }

    /* ---------------- beheer ---------------- */
    public static function admin(): void
    {
        $albums = Database::all("SELECT a.*, (SELECT COUNT(*) FROM gallery_photos p WHERE p.album_id = a.id) AS n FROM gallery_albums a ORDER BY a.id DESC");
        render('gallery/index', compact('albums'), 'Fotogalerij');
    }

    public static function albumSave(): void
    {
        $id = (int) input('id', '0');
        $title = input('title');
        $desc = input('description');
        if ($title === '' || mb_strlen($title) > 150 || mb_strlen($desc) > 300) {
            flash('error', 'Geef het album een titel (max. 150 tekens); de omschrijving mag max. 300 tekens zijn.');
            redirect($id ? url('gallery_album', ['id' => $id]) : url('gallery_admin'));
        }
        if ($id) {
            self::album($id);
            Database::exec('UPDATE gallery_albums SET title = ?, description = ? WHERE id = ?', [$title, $desc ?: null, $id]);
        } else {
            $id = Database::insert('INSERT INTO gallery_albums (title, description, created_at) VALUES (?,?,?)', [$title, $desc ?: null, now()]);
        }
        audit('SAVE', 'gallery_album', $id);
        flash('success', 'Album opgeslagen.');
        redirect(url('gallery_album', ['id' => $id]));
    }

    private static function album(int $id): array
    {
        $a = Database::one('SELECT * FROM gallery_albums WHERE id = ?', [$id]);
        if (!$a) {
            abort(404, 'Album niet gevonden.');
        }
        return $a;
    }

    public static function albumShow(): void
    {
        $a = self::album((int) ($_GET['id'] ?? 0));
        $photos = Database::all('SELECT p.*, md.path FROM gallery_photos p JOIN media md ON md.id = p.media_id WHERE p.album_id = ? ORDER BY p.id', [$a['id']]);
        $tags = [];
        foreach (Database::all('SELECT pm.photo_id, m.id, m.first_name, m.last_name, m.photo_consent FROM photo_members pm JOIN members m ON m.id = pm.member_id WHERE pm.photo_id IN (SELECT id FROM gallery_photos WHERE album_id = ?) ORDER BY m.first_name', [$a['id']]) as $t) {
            $tags[(int) $t['photo_id']][] = $t;
        }
        $members = Database::all("SELECT id, first_name, last_name, photo_consent FROM members WHERE status = 'ACTIVE' ORDER BY first_name, last_name");
        render('gallery/album', compact('a', 'photos', 'tags', 'members'), $a['title']);
    }

    public static function photoUpload(): void
    {
        $a = self::album((int) input('album_id', '0'));
        [$path, $err] = Uploads::store($_FILES['file'] ?? []);
        if ($err || !$path) {
            flash('error', $err ?: 'Kies een foto om te uploaden.');
            redirect(url('gallery_album', ['id' => $a['id']]));
        }
        $name = mb_substr(basename((string) ($_FILES['file']['name'] ?? 'foto')), 0, 190);
        $mid = Database::insert('INSERT INTO media (path, original_name, uploaded_by, created_at) VALUES (?,?,?,?)', [$path, $name, Auth::user()['id'], now()]);
        $pid = Database::insert('INSERT INTO gallery_photos (album_id, media_id, created_at) VALUES (?,?,?)', [$a['id'], $mid, now()]);
        audit('CREATE', 'gallery_photo', $pid);
        flash('success', 'Foto toegevoegd. Tag de leden die erop staan, of bevestig dat er niemand herkenbaar op staat.');
        redirect(url('gallery_album', ['id' => $a['id']]) . '#foto-' . $pid);
    }

    private static function photo(int $id): array
    {
        $p = Database::one('SELECT p.*, md.path FROM gallery_photos p JOIN media md ON md.id = p.media_id WHERE p.id = ?', [$id]);
        if (!$p) {
            abort(404, 'Foto niet gevonden.');
        }
        return $p;
    }

    /** Bijschrift, getagde leden en toestemming. */
    public static function photoSave(): void
    {
        $p = self::photo((int) input('id', '0'));
        $caption = input('caption');
        $raw = $_POST['members'] ?? [];
        $ids = is_array($raw) ? array_values(array_unique(array_filter(array_map('intval', $raw)))) : [];
        if ($ids) {
            $valid = (int) Database::val('SELECT COUNT(*) FROM members WHERE id IN (' . Scope::in($ids) . ')', $ids);
            if ($valid !== count($ids)) {
                abort(422, 'Een gekozen lid bestaat niet.');
            }
        }
        if (mb_strlen($caption) > 200) {
            flash('error', 'Bijschrift is te lang (max. 200 tekens).');
            redirect(url('gallery_album', ['id' => $p['album_id']]) . '#foto-' . $p['id']);
        }
        // Toestemming: met getagde leden volstaat hun eigen toestemming; zonder tags bevestigt een beheerder dat niemand herkenbaar is of dat de toestemming geregeld is.
        $consent = $ids ? 1 : (input('no_one') === '1' ? 1 : 0);
        Database::tx(function () use ($p, $caption, $ids, $consent) {
            Database::exec('DELETE FROM photo_members WHERE photo_id = ?', [$p['id']]);
            foreach ($ids as $mid) {
                Database::exec('INSERT INTO photo_members (photo_id, member_id) VALUES (?,?)', [$p['id'], $mid]);
            }
            Database::exec('UPDATE gallery_photos SET caption = ?, consent_ok = ? WHERE id = ?', [$caption ?: null, $consent, $p['id']]);
        });
        $missing = $ids ? (int) Database::val('SELECT COUNT(*) FROM members WHERE photo_consent = 0 AND id IN (' . Scope::in($ids) . ')', $ids) : 0;
        audit('UPDATE', 'gallery_photo', (int) $p['id']);
        flash($missing ? 'warning' : 'success', $missing ? "Opgeslagen, maar $missing getagd(e) lid/leden hebben geen toestemming: deze foto blijft verborgen op de website." : 'Foto opgeslagen.');
        redirect(url('gallery_album', ['id' => $p['album_id']]) . '#foto-' . $p['id']);
    }

    public static function photoDelete(): void
    {
        $p = self::photo((int) input('id', '0'));
        Database::tx(function () use ($p) {
            Database::exec('DELETE FROM photo_members WHERE photo_id = ?', [$p['id']]);
            Database::exec('DELETE FROM gallery_photos WHERE id = ?', [$p['id']]);
            Database::exec('DELETE FROM media WHERE id = ?', [$p['media_id']]);
        });
        Uploads::delete($p['path']);
        audit('DELETE', 'gallery_photo', (int) $p['id']);
        flash('success', 'Foto verwijderd.');
        redirect(url('gallery_album', ['id' => $p['album_id']]));
    }

    public static function publish(): void
    {
        $a = self::album((int) input('id', '0'));
        $publish = input('publish') === '1';
        if ($publish) {
            $bad = (int) Database::val('SELECT COUNT(*) FROM gallery_photos p WHERE p.album_id = ? AND NOT (' . self::VISIBLE . ')', [$a['id']]);
            $n = (int) Database::val('SELECT COUNT(*) FROM gallery_photos WHERE album_id = ?', [$a['id']]);
            if ($n === 0) {
                flash('error', 'Voeg eerst foto\'s toe.');
                redirect(url('gallery_album', ['id' => $a['id']]));
            }
            if ($bad) {
                flash('error', "Publiceren kan niet: bij $bad foto('s) is de toestemming niet in orde (lid zonder toestemming, of nog niet bevestigd).");
                redirect(url('gallery_album', ['id' => $a['id']]));
            }
        }
        Database::exec('UPDATE gallery_albums SET status = ? WHERE id = ?', [$publish ? 'PUBLISHED' : 'DRAFT', $a['id']]);
        audit($publish ? 'PUBLISH' : 'UNPUBLISH', 'gallery_album', (int) $a['id']);
        flash('success', $publish ? 'Album staat online.' : 'Album is offline gehaald.');
        redirect(url('gallery_album', ['id' => $a['id']]));
    }

    public static function albumDelete(): void
    {
        $a = self::album((int) input('id', '0'));
        $files = Database::all('SELECT p.id, p.media_id, md.path FROM gallery_photos p JOIN media md ON md.id = p.media_id WHERE p.album_id = ?', [$a['id']]);
        Database::tx(function () use ($a, $files) {
            foreach ($files as $f) {
                Database::exec('DELETE FROM photo_members WHERE photo_id = ?', [$f['id']]);
                Database::exec('DELETE FROM gallery_photos WHERE id = ?', [$f['id']]);
                Database::exec('DELETE FROM media WHERE id = ?', [$f['media_id']]);
            }
            Database::exec('DELETE FROM gallery_albums WHERE id = ?', [$a['id']]);
        });
        foreach ($files as $f) {
            Uploads::delete($f['path']);
        }
        audit('DELETE', 'gallery_album', (int) $a['id']);
        flash('success', 'Album verwijderd.');
        redirect(url('gallery_admin'));
    }
}
