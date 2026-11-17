<?php
/**
 * Controlevraag bij aanmelden en "wachtwoord vergeten", tegen geautomatiseerde pogingen (bots).
 *
 * Bewust zonder externe dienst (geen Google reCAPTCHA of Cloudflare): geen cookies of tracking van derden, geen
 * sleutels nodig en werkt op elke PHP-hosting. Drie lagen:
 *  - een eenvoudige rekenvraag ("Hoeveel is drie plus 5?"), deels in woorden; het antwoord staat enkel in de sessie;
 *  - een verborgen veld dat mensen niet zien maar bots invullen (honeypot);
 *  - het formulier moet minstens 2 seconden open staan (bots versturen meteen).
 * Elke vraag is eenmalig: na een poging (goed of fout) komt er een nieuwe.
 * Samen met de bestaande limieten (5 foute wachtwoorden = 15 minuten blokkade) houdt dit massale pogingen tegen.
 */
final class Captcha
{
    private const WORDS = ['nul', 'een', 'twee', 'drie', 'vier', 'vijf', 'zes', 'zeven', 'acht', 'negen', 'tien'];
    private const MIN_SECONDS = 2;
    private const MAX_SECONDS = 3600;

    /** Nieuwe vraag voor dit formulier; het antwoord gaat in de sessie. */
    public static function question(string $form): string
    {
        $a = random_int(1, 9);
        $b = random_int(1, 9);
        $_SESSION['captcha'][$form] = ['answer' => $a + $b, 'at' => time()];
        // Eén van beide getallen in woorden: lastiger voor eenvoudige bots, voor mensen even makkelijk.
        $left = random_int(0, 1) ? self::WORDS[$a] : (string) $a;
        $right = $left === (string) $a ? self::WORDS[$b] : (string) $b;
        return "Hoeveel is $left plus $right?";
    }

    /** Controleert het antwoord (en honeypot en tijd). De vraag is daarna verbruikt. */
    public static function check(string $form): bool
    {
        $c = $_SESSION['captcha'][$form] ?? null;
        unset($_SESSION['captcha'][$form]);
        if (!is_array($c) || trim((string) ($_POST['website'] ?? '')) !== '') {
            return false;
        }
        $age = time() - (int) $c['at'];
        if ($age < (int) config('captcha.min_seconds', self::MIN_SECONDS) || $age > self::MAX_SECONDS) {
            return false;
        }
        $given = mb_strtolower(trim((string) ($_POST['captcha'] ?? '')));
        if (ctype_digit($given)) {
            return (int) $given === (int) $c['answer'];
        }
        // ook in woorden ("zeventien") aanvaarden voor 1-18
        $words = ['', 'een', 'twee', 'drie', 'vier', 'vijf', 'zes', 'zeven', 'acht', 'negen', 'tien', 'elf', 'twaalf', 'dertien', 'veertien', 'vijftien', 'zestien', 'zeventien', 'achttien'];
        return $given !== '' && array_search($given, $words, true) === (int) $c['answer'];
    }

    /** HTML voor in een formulier: de vraag, het antwoordveld en het verborgen veld. */
    public static function field(string $form): string
    {
        $q = self::question($form);
        return '<label>Controlevraag: ' . e($q)
            . '<input name="captcha" inputmode="numeric" autocomplete="off" maxlength="12" required aria-describedby="captcha-help"></label>'
            . '<p class="small muted" id="captcha-help">Zo houden we automatische aanmeldpogingen tegen.</p>'
            . '<div class="hp-field" aria-hidden="true"><label>Laat dit veld leeg <input name="website" tabindex="-1" autocomplete="off"></label></div>';
    }
}
