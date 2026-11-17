<?php
/**
 * "Ik ben geen robot"-vinkje bij aanmelden en "wachtwoord vergeten", tegen geautomatiseerde pogingen (bots).
 *
 * Werkt zonder externe dienst (geen Google reCAPTCHA of Cloudflare): geen cookies of tracking van derden, geen
 * sleutels, werkt op elke PHP-hosting (ook zonder HTTPS). Hoe het werkt (proof of work, zoals ALTCHA):
 *  - de server kiest een willekeurig getal n en geeft de browser enkel salt en sha256(salt . n);
 *  - wie het vinkje aanklikt, laat de browser n zoeken door te proberen (op een telefoon meestal minder dan een
 *    seconde); een bot moet dat rekenwerk voor elke poging opnieuw doen, wat massale pogingen duur maakt;
 *  - het antwoord hoort bij de sessie en is eenmalig; daarnaast een verborgen veld dat enkel bots invullen.
 * Zonder JavaScript verschijnt in de plaats een eenvoudige rekenvraag (noscript).
 * Samen met de bestaande limieten (5 foute wachtwoorden = 15 minuten blokkade).
 */
final class Captcha
{
    private const WORDS = ['nul', 'een', 'twee', 'drie', 'vier', 'vijf', 'zes', 'zeven', 'acht', 'negen', 'tien'];
    private const MAX_SECONDS = 3600;

    /** Moeilijkheid: n ligt tussen 0 en dit getal (gemiddeld de helft aan pogingen). */
    public static function maxNumber(): int
    {
        return max(1, (int) config('captcha.max_number', 60000));
    }

    /** Nieuwe uitdaging (en reservevraag zonder JavaScript) voor dit formulier. @return array{salt:string,challenge:string,question:string} */
    public static function create(string $form): array
    {
        $salt = bin2hex(random_bytes(12));
        $n = random_int(0, self::maxNumber());
        $a = random_int(1, 9);
        $b = random_int(1, 9);
        $_SESSION['captcha'][$form] = ['salt' => $salt, 'n' => $n, 'answer' => $a + $b, 'at' => time()];
        $left = random_int(0, 1) ? self::WORDS[$a] : (string) $a;
        $right = $left === (string) $a ? self::WORDS[$b] : (string) $b;
        return ['salt' => $salt, 'challenge' => hash('sha256', $salt . $n), 'question' => "Hoeveel is $left plus $right?"];
    }

    /** Controleert het vinkje (of de reservevraag) en het verborgen veld. De uitdaging is daarna verbruikt. */
    public static function check(string $form): bool
    {
        $c = $_SESSION['captcha'][$form] ?? null;
        unset($_SESSION['captcha'][$form]);
        if (!is_array($c) || trim((string) ($_POST['website'] ?? '')) !== '' || time() - (int) $c['at'] > self::MAX_SECONDS) {
            return false;
        }
        $pow = trim((string) ($_POST['captcha_n'] ?? ''));
        if ($pow !== '') {
            return ctype_digit($pow) && (int) $pow === (int) $c['n'];
        }
        // Reserve zonder JavaScript: rekenvraag (cijfers of woorden)
        $given = mb_strtolower(trim((string) ($_POST['captcha'] ?? '')));
        if ($given === '') {
            return false;
        }
        if (ctype_digit($given)) {
            return (int) $given === (int) $c['answer'];
        }
        $words = ['', 'een', 'twee', 'drie', 'vier', 'vijf', 'zes', 'zeven', 'acht', 'negen', 'tien', 'elf', 'twaalf', 'dertien', 'veertien', 'vijftien', 'zestien', 'zeventien', 'achttien'];
        return array_search($given, $words, true) === (int) $c['answer'];
    }

    /** HTML voor in een formulier: het vinkje, de reservevraag zonder JavaScript en het verborgen veld. */
    public static function field(string $form): string
    {
        $c = self::create($form);
        return '<div class="robot-check" data-captcha data-salt="' . e($c['salt']) . '" data-challenge="' . e($c['challenge']) . '" data-max="' . self::maxNumber() . '" hidden>'
            . '<button type="button" class="robot-box" role="checkbox" aria-checked="false"><span class="robot-tick" aria-hidden="true"></span>'
            . '<span class="robot-label">Ik ben geen robot</span></button>'
            . '<input type="hidden" name="captcha_n" value="">'
            . '</div>'
            . '<noscript><label>Controlevraag: ' . e($c['question'])
            . '<input name="captcha" inputmode="numeric" autocomplete="off" maxlength="12" required></label></noscript>'
            . '<div class="hp-field" aria-hidden="true"><label>Laat dit veld leeg <input name="website" tabindex="-1" autocomplete="off"></label></div>';
    }
}
