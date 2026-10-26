<?php
/** Berichten sturen naar een groep, een trainingsblok of de deelnemers van een activiteit. */
final class MailController
{
    public static function compose(): void
    {
        $key = (string) ($_GET['to'] ?? '');
        self::form($key, '', '', []);
    }

    private static function form(string $key, string $subject, string $body, array $errors): void
    {
        $options = Mailing::options();
        // Deelnemers van één activiteit (vanaf de activiteitpagina) komen niet in de lijst maar via ?to=a:ID.
        $extra = null;
        if (preg_match('/^a:\d+$/', $key) && ($aud = Mailing::audience($key))) {
            $extra = $aud[0];
        }
        $recent = Database::all(
            "SELECT b.*, u.display_name FROM broadcasts b LEFT JOIN users u ON u.id = b.sender_id
             WHERE b.kind = 'GROUP'" . (Scope::clubWide() ? '' : ' AND b.sender_id = ?') . ' ORDER BY b.id DESC LIMIT 15',
            Scope::clubWide() ? [] : [Auth::user()['id']]
        );
        render('mail/compose', compact('options', 'key', 'subject', 'body', 'errors', 'extra', 'recent'), 'Bericht sturen');
    }

    /** Terug naar het formulier met de ingevulde tekst (knop "Aanpassen" op de controlepagina). */
    public static function edit(): void
    {
        self::form(input('to'), input('subject'), trim((string) ($_POST['body'] ?? '')), []);
    }

    public static function send(): void
    {
        $key = input('to');
        $subject = input('subject');
        $body = trim((string) ($_POST['body'] ?? ''));
        $step = input('step', 'preview');
        $errors = [];
        $aud = Mailing::audience($key);
        if (!$aud) {
            $errors[] = 'Kies aan wie je het bericht wilt sturen.';
        }
        if ($subject === '' || mb_strlen($subject) > 150) {
            $errors[] = 'Geef een onderwerp op (max. 150 tekens).';
        }
        if ($body === '' || mb_strlen($body) > 5000) {
            $errors[] = 'Schrijf een bericht (max. 5000 tekens).';
        }
        $emails = $aud ? Mailing::recipients($aud[1]) : [];
        if ($aud && !$emails) {
            $errors[] = 'Er is geen enkel e-mailadres bekend voor dit publiek.';
        }
        if (count($emails) > Mailing::MAX_RECIPIENTS) {
            $errors[] = 'Dit zijn meer dan ' . Mailing::MAX_RECIPIENTS . ' ontvangers. Kies een kleiner publiek.';
        }
        if (!$errors && $step === 'send') {
            $recent = (int) Database::val("SELECT COUNT(*) FROM broadcasts WHERE kind = 'GROUP' AND sender_id = ? AND created_at > ?", [Auth::user()['id'], date('Y-m-d H:i:s', time() - 3600)]);
            if ($recent >= 20) {
                $errors[] = 'Je hebt het afgelopen uur al 20 berichten verstuurd. Probeer later opnieuw.';
            }
        }
        if ($errors) {
            http_response_code(422);
            self::form($key, $subject, $body, $errors);
            return;
        }
        if ($step !== 'send') {
            render('mail/preview', ['key' => $key, 'subject' => $subject, 'body' => $body, 'label' => $aud[0], 'emails' => $emails, 'members' => count($aud[1])], 'Controleer je bericht');
            return;
        }
        [$ok, $failed] = Mailing::send('GROUP', $aud[0], $subject, $body, $emails);
        audit('SEND', 'broadcast', null, mb_substr($aud[0], 0, 120) . ': ' . $ok . ' ontvangers');
        flash($failed ? 'warning' : 'success', "Bericht verstuurd naar $ok ontvangers" . ($failed ? ", $failed mislukt." : '.'));
        redirect(url('mail'));
    }
}
