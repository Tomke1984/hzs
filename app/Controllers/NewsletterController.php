<?php
/** Nieuwsbrief: aanmelden met dubbele bevestiging, afmelden met één klik, en een nieuwsbericht doorsturen naar alle abonnees. */
final class NewsletterController
{
    public static function subscribe(): void
    {
        $back = url('home') . '#nieuwsbrief';
        if (input('website') !== '') {
            flash('success', 'Bedankt! Check je mailbox om je aanmelding te bevestigen.');
            redirect($back);
        }
        $email = strtolower(input('email'));
        $name = input('name');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190 || mb_strlen($name) > 120) {
            flash('error', 'Vul een geldig e-mailadres in.');
            redirect($back);
        }
        $since = date('Y-m-d H:i:s', time() - 3600);
        if ((int) Database::val('SELECT COUNT(*) FROM subscribers WHERE created_at > ?', [$since]) >= 100) {
            flash('error', 'Er zijn te veel aanmeldingen. Probeer het later opnieuw.');
            redirect($back);
        }
        $row = Database::one('SELECT * FROM subscribers WHERE email = ?', [$email]);
        if ($row && $row['confirmed_at'] && !$row['unsubscribed_at']) {
            flash('success', 'Je bent al aangemeld voor de nieuwsbrief.');
            redirect($back);
        }
        if ($row) {
            $token = $row['token'];
            Database::exec('UPDATE subscribers SET unsubscribed_at = NULL, confirmed_at = NULL, name = COALESCE(?, name) WHERE id = ?', [$name ?: null, $row['id']]);
        } else {
            $token = bin2hex(random_bytes(16));
            Database::insert('INSERT INTO subscribers (email, name, token, created_at) VALUES (?,?,?,?)', [$email, $name ?: null, $token, now()]);
        }
        Mailer::send(
            $email,
            'Bevestig je aanmelding voor de nieuwsbrief',
            "Dag,\n\nBevestig je aanmelding voor de nieuwsbrief van Hasseltse Zwemvereniging Spartacus via deze link:\n"
            . Invites::baseUrl() . '/' . url('newsletter_confirm', ['t' => $token]) . "\n\nHeb jij je niet aangemeld? Dan hoef je niets te doen."
        );
        flash('success', 'Bedankt! Check je mailbox om je aanmelding te bevestigen.');
        redirect($back);
    }

    public static function confirm(): void
    {
        $row = Database::one('SELECT * FROM subscribers WHERE token = ?', [(string) ($_GET['t'] ?? '')]);
        if (!$row) {
            abort(404, 'Deze link is niet (meer) geldig.');
        }
        Database::exec('UPDATE subscribers SET confirmed_at = COALESCE(confirmed_at, ?), unsubscribed_at = NULL WHERE id = ?', [now(), $row['id']]);
        flash('success', 'Bedankt, je bent aangemeld voor de nieuwsbrief.');
        redirect(url('home'));
    }

    public static function unsubscribe(): void
    {
        $row = Database::one('SELECT * FROM subscribers WHERE token = ?', [(string) ($_GET['t'] ?? '')]);
        if (!$row) {
            abort(404, 'Deze link is niet (meer) geldig.');
        }
        Database::exec('UPDATE subscribers SET unsubscribed_at = COALESCE(unsubscribed_at, ?) WHERE id = ?', [now(), $row['id']]);
        flash('success', 'Je bent afgemeld voor de nieuwsbrief.');
        redirect(url('home'));
    }

    /** Beheer: abonnees en verzenden van berichten. */
    public static function admin(): void
    {
        if (($_GET['csv'] ?? '') === '1') {
            audit('EXPORT', 'subscribers');
            Csv::send('nieuwsbrief-abonnees.csv', ['E-mail', 'Naam', 'Bevestigd op'],
                array_map(fn($r) => [$r['email'], $r['name'], $r['confirmed_at']], self::active()));
        }
        $posts = Database::all("SELECT id, title, published_at, created_at, newsletter_sent_at FROM posts WHERE status = 'PUBLISHED' ORDER BY COALESCE(published_at, created_at) DESC, id DESC LIMIT 20");
        $counts = Database::one("SELECT COUNT(*) AS total, SUM(CASE WHEN confirmed_at IS NOT NULL AND unsubscribed_at IS NULL THEN 1 ELSE 0 END) AS active,
            SUM(CASE WHEN confirmed_at IS NULL THEN 1 ELSE 0 END) AS pending, SUM(CASE WHEN unsubscribed_at IS NOT NULL THEN 1 ELSE 0 END) AS gone FROM subscribers");
        $sent = Database::all("SELECT * FROM broadcasts WHERE kind = 'NEWSLETTER' ORDER BY id DESC LIMIT 10");
        render('newsletter/index', compact('posts', 'counts', 'sent'), 'Nieuwsbrief');
    }

    private static function active(): array
    {
        return Database::all('SELECT * FROM subscribers WHERE confirmed_at IS NOT NULL AND unsubscribed_at IS NULL ORDER BY email');
    }

    public static function send(): void
    {
        $p = Database::one("SELECT * FROM posts WHERE id = ? AND status = 'PUBLISHED'", [(int) input('id', '0')]);
        if (!$p) {
            abort(404, 'Enkel een gepubliceerd bericht kan als nieuwsbrief verstuurd worden.');
        }
        if ($p['newsletter_sent_at'] && input('again') !== '1') {
            flash('error', 'Dit bericht is al verstuurd op ' . fmt_dt($p['newsletter_sent_at']) . '. Vink "opnieuw versturen" aan als je het zeker nog eens wil sturen.');
            redirect(url('newsletter'));
        }
        $subs = self::active();
        if (!$subs) {
            flash('error', 'Er zijn nog geen bevestigde abonnees.');
            redirect(url('newsletter'));
        }
        if (count($subs) > Mailing::MAX_RECIPIENTS) {
            flash('error', 'Te veel abonnees voor één verzending (max. ' . Mailing::MAX_RECIPIENTS . ').');
            redirect(url('newsletter'));
        }
        $link = Invites::baseUrl() . '/' . url('post', ['slug' => $p['slug']]);
        $intro = trim((string) $p['summary']) !== '' ? $p['summary'] : mb_substr(trim(strip_tags(Markup::render((string) $p['body']))), 0, 280);
        $msgs = [];
        foreach ($subs as $s) {
            $msgs[] = [$s['email'], $p['title'], ($s['name'] ? "Dag {$s['name']},\n\n" : "Dag,\n\n") . "$intro\n\nLees verder: $link\n\n--\nHasseltse Zwemvereniging Spartacus\nAfmelden: "
                . Invites::baseUrl() . '/' . url('newsletter_unsub', ['t' => $s['token']])];
        }
        $res = Mailer::sendMany($msgs);
        $ok = count(array_filter($res));
        Database::exec('UPDATE posts SET newsletter_sent_at = ? WHERE id = ?', [now(), $p['id']]);
        Database::insert(
            'INSERT INTO broadcasts (kind, sender_id, subject, body, audience, recipient_count, created_at) VALUES (?,?,?,?,?,?,?)',
            ['NEWSLETTER', Auth::user()['id'], $p['title'], $intro, 'Abonnees nieuwsbrief', $ok, now()]
        );
        audit('NEWSLETTER', 'post', (int) $p['id'], "$ok/" . count($subs));
        flash($ok === count($subs) ? 'success' : 'warning', "Nieuwsbrief verstuurd naar $ok van " . count($subs) . ' abonnees.');
        redirect(url('newsletter'));
    }
}
