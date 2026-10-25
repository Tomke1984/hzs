<?php
/** Berichten uit het contactformulier van de website: bewaard in de app, ook als de mail niet aankomt. */
final class ContactController
{
    public static function index(): void
    {
        $tab = (string) ($_GET['tab'] ?? 'open') === 'done' ? 'done' : 'open';
        $rows = Database::all(
            'SELECT * FROM contact_messages WHERE handled_at ' . ($tab === 'open' ? 'IS NULL ORDER BY id ASC' : 'IS NOT NULL ORDER BY handled_at DESC') . ' LIMIT 300'
        );
        render('messages/index', [
            'rows' => $rows, 'tab' => $tab,
            'counts' => [
                'open' => (int) Database::val('SELECT COUNT(*) FROM contact_messages WHERE handled_at IS NULL'),
                'done' => (int) Database::val('SELECT COUNT(*) FROM contact_messages WHERE handled_at IS NOT NULL'),
            ],
        ], 'Berichten');
    }

    public static function handle(): void
    {
        $m = self::load((int) input('id', '0'));
        $done = input('undo') !== '1';
        Database::exec('UPDATE contact_messages SET handled_at = ?, handled_by = ? WHERE id = ?', [$done ? now() : null, $done ? Auth::user()['id'] : null, $m['id']]);
        flash('success', $done ? 'Bericht afgehandeld.' : 'Bericht opnieuw geopend.');
        redirect(url('messages', ['tab' => $done ? 'open' : 'done']));
    }

    public static function delete(): void
    {
        $m = self::load((int) input('id', '0'));
        Database::exec('DELETE FROM contact_messages WHERE id = ?', [$m['id']]);
        audit('DELETE', 'contact_message', (int) $m['id']);
        flash('success', 'Bericht verwijderd.');
        redirect(url('messages', ['tab' => $m['handled_at'] ? 'done' : 'open']));
    }

    private static function load(int $id): array
    {
        $m = $id ? Database::one('SELECT * FROM contact_messages WHERE id = ?', [$id]) : null;
        if (!$m) {
            abort(404, 'Dit bericht bestaat niet.');
        }
        return $m;
    }
}
