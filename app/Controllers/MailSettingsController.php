<?php
/**
 * Mailinstellingen in de app (enkel beheerders): hoe de app mails verstuurt (uitnodigingen, wachtwoord vergeten,
 * herinneringen trainersplanning, berichten). Gaat voor op 'mail' in config.php, zodat dat bestand op de server niet
 * met de hand bewerkt moet worden. Het SMTP-wachtwoord wordt versleuteld bewaard.
 */
final class MailSettingsController
{
    public static function form(): void
    {
        render('settings/mail', ['m' => Mailer::settings(), 'errors' => []], 'Mailinstellingen');
    }

    public static function save(): void
    {
        $cur = Mailer::settings();
        $m = [
            'driver' => in_array(input('driver'), ['log', 'smtp', 'mail'], true) ? input('driver') : 'log',
            'from_email' => strtolower(input('from_email')),
            'from_name' => mb_substr(input('from_name'), 0, 80),
            'smtp' => [
                'host' => mb_substr(input('smtp_host'), 0, 120),
                'port' => (int) input('smtp_port', '587'),
                'encryption' => in_array(input('smtp_encryption'), ['tls', 'ssl', 'none'], true) ? input('smtp_encryption') : 'tls',
                'user' => mb_substr(input('smtp_user'), 0, 190),
                // leeg laten = bestaand wachtwoord behouden
                'pass' => (string) ($_POST['smtp_pass'] ?? '') !== '' ? (string) $_POST['smtp_pass'] : (string) ($cur['smtp']['pass'] ?? ''),
            ],
        ];
        $errors = [];
        if (!filter_var($m['from_email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Vul een geldig afzenderadres in (bv. noreply@khzs.be).';
        }
        if ($m['driver'] === 'smtp' && ($m['smtp']['host'] === '' || $m['smtp']['port'] < 1 || $m['smtp']['port'] > 65535)) {
            $errors[] = 'Vul de SMTP-server en een geldige poort in (meestal 587 met TLS, of 465 met SSL).';
        }
        if ($errors) {
            http_response_code(422);
            render('settings/mail', ['m' => $m, 'errors' => $errors], 'Mailinstellingen');
            return;
        }
        $store = $m;
        $store['smtp']['pass'] = Crypto::encrypt($m['smtp']['pass'] !== '' ? $m['smtp']['pass'] : null);
        Settings::set('mail', $store);
        Mailer::reset();
        audit('UPDATE', 'settings', 0, 'mail: ' . $m['driver']);
        flash('success', $m['driver'] === 'log' ? 'Opgeslagen. Testmodus: er worden geen echte mails verstuurd.' : 'Opgeslagen. Stuur hieronder een testmail om te controleren of het werkt.');
        redirect(url('mail_settings'));
    }

    public static function test(): void
    {
        $to = (string) Auth::user()['email'];
        if (Mailer::driver() === 'log') {
            flash('warning', 'De app staat in testmodus: er wordt geen echte mail verstuurd. Kies eerst SMTP of PHP mail().');
            redirect(url('mail_settings'));
        }
        $ok = Mailer::send($to, 'Testmail van de ' . config('app_name', 'HZS Clubapp'),
            "Dag,\n\nDit is een testmail. Krijg je dit bericht, dan zijn de mailinstellingen in orde.\n\nHasseltse Zwemvereniging Spartacus");
        audit('MAIL_TEST', 'settings', 0, $ok ? 'ok' : 'mislukt');
        flash($ok ? 'success' : 'error', $ok
            ? "Testmail verstuurd naar $to. Kijk ook in je ongewenste mail."
            : 'Testmail mislukt' . (Mailer::$lastError ? ': ' . Mailer::$lastError : '.')
                . (Mailer::driver() === 'mail'
                    ? ' Op deze server is geen mailprogramma beschikbaar (zoals in Coolify/Docker): kies SMTP met de gegevens van je mailprovider.'
                    : ' Controleer server, poort, beveiliging, gebruiker en wachtwoord.'));
        redirect(url('mail_settings'));
    }
}
