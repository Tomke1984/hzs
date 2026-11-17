<?php
/**
 * Wachtwoordregels: minstens 10 tekens, en geen wachtwoord dat in elke kraaklijst bovenaan staat of dat je
 * uit je naam, e-mailadres of de club kan raden. Werkt volledig lokaal (geen externe dienst).
 */
final class PasswordPolicy
{
    public const MIN_LENGTH = 10;

    /** Veelgebruikte wachtwoorden van 10+ tekens (uit publieke lekken), plus voor de hand liggende clubwoorden. */
    private const COMMON = [
        '1234567890', '0123456789', '12345678910', '123456789a', 'a123456789', '1q2w3e4r5t', '1q2w3e4r5t6y',
        'qwertyuiop', 'azertyuiop', 'qwerty1234', 'azerty1234', 'qwerty123456', 'azerty123456', '1qaz2wsx3edc',
        'password12', 'password123', 'password1234', 'password1!', 'passw0rd123', 'p@ssw0rd123', 'p@ssword123',
        'wachtwoord', 'wachtwoord1', 'wachtwoord12', 'wachtwoord123', 'wachtwoord!', 'motdepasse1', 'motdepasse123',
        'iloveyou12', 'iloveyou123', 'football123', 'baseball123', 'princess123', 'sunshine123', 'superman123',
        'welcome123', 'welkom1234', 'welkom12345', 'letmein123', 'trustno1234', 'starwars123', 'computer123',
        'administrator', 'admin12345', 'admin123456', 'changeme123', 'zaq12wsxcvb', 'abcdefghij', 'abcd1234!@',
        'aaaaaaaaaa', '1111111111', '0000000000', '9876543210', 'zwemmen123', 'zwemclub123', 'spartacus1',
        'spartacus12', 'spartacus123', 'spartacus2025', 'spartacus2026', 'hasselt123', 'hasselt2026', 'demo1234!',
    ];

    private const SEQUENCES = ['0123456789', '9876543210', 'abcdefghijklmnopqrstuvwxyz', 'qwertyuiop', 'azertyuiop', 'asdfghjkl', 'qsdfghjklm', 'zxcvbnm', 'wxcvbn'];

    /**
     * @param string[] $personal naam, e-mailadres, ... van de gebruiker
     * @return string|null foutmelding, of null als het wachtwoord in orde is
     */
    public static function problem(string $password, array $personal = []): ?string
    {
        if (mb_strlen($password) < self::MIN_LENGTH) {
            return 'Het wachtwoord moet minstens ' . self::MIN_LENGTH . ' tekens hebben.';
        }
        if (mb_strlen($password) > 200) {
            return 'Het wachtwoord is te lang (max. 200 tekens).';
        }
        $lower = mb_strtolower($password);
        $core = preg_replace('/[^a-z0-9]/', '', $lower) ?? '';
        if (in_array($lower, self::COMMON, true) || in_array($core, self::COMMON, true)) {
            return 'Dit wachtwoord komt voor in lijsten van veelgebruikte wachtwoorden. Kies een ander, bv. drie losse woorden.';
        }
        if (count(array_unique(mb_str_split($lower))) < 4) {
            return 'Het wachtwoord heeft te weinig verschillende tekens.';
        }
        foreach (self::SEQUENCES as $seq) {
            if (strlen($core) >= 8 && str_contains($seq, $core)) {
                return 'Een reeks toetsen of cijfers na elkaar is te makkelijk te raden.';
            }
        }
        foreach ($personal as $p) {
            // hele naam of e-mailadres (zonder spaties en leestekens), bv. "ouder.van.lucas" voor "Ouder van Lucas"
            $whole = preg_replace('/[^a-z0-9]/', '', mb_strtolower((string) $p)) ?? '';
            if (strlen($whole) >= 4 && str_contains($core, $whole) && strlen($core) - strlen($whole) < 6) {
                return 'Het wachtwoord lijkt te veel op je naam of e-mailadres.';
            }
            foreach (preg_split('/[\s@._\-]+/', mb_strtolower((string) $p)) ?: [] as $part) {
                if (mb_strlen($part) >= 4 && !in_array($part, ['demo', 'local', 'gmail', 'hotmail', 'outlook', 'telenet', 'skynet', 'yahoo'], true)
                    && str_contains($lower, $part) && mb_strlen($lower) - mb_strlen($part) < 6) {
                    return 'Het wachtwoord lijkt te veel op je naam of e-mailadres.';
                }
            }
        }
        return null;
    }
}
