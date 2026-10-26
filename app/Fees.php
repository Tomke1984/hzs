<?php
/** Lidgeld: bedragen per groep, rekeningen per seizoen en een gestructureerde mededeling (OGM, modulo 97). */
final class Fees
{
    public static function statusLabel(string $s): string
    {
        return ['OPEN' => 'Open', 'PAID' => 'Betaald', 'WAIVED' => 'Kwijtgescholden'][$s] ?? $s;
    }

    public static function money(int $cents): string
    {
        return '€ ' . number_format($cents / 100, 2, ',', '.');
    }

    /** Gestructureerde mededeling +++xxx/xxxx/xxxxx+++ (10 cijfers + 2 controlecijfers, rest bij deling door 97). */
    public static function reference(int $dueId): string
    {
        $base = str_pad((string) ($dueId % 10000000000), 10, '0', STR_PAD_LEFT);
        $check = (int) $base % 97;
        $d = $base . str_pad((string) ($check === 0 ? 97 : $check), 2, '0', STR_PAD_LEFT);
        return '+++' . substr($d, 0, 3) . '/' . substr($d, 3, 4) . '/' . substr($d, 7, 5) . '+++';
    }

    public static function currentSeason(): ?array
    {
        return Database::one('SELECT * FROM seasons WHERE is_current = 1 ORDER BY id DESC LIMIT 1')
            ?: Database::one('SELECT * FROM seasons ORDER BY start_date DESC LIMIT 1');
    }

    /** Naam, begin en einde van het seizoen waarin een datum valt (1 sep – 31 aug). @return array{0:string,1:string,2:string} */
    public static function seasonFor(string $date): array
    {
        $y = (int) substr($date, 0, 4);
        $start = (int) substr($date, 5, 2) >= 9 ? $y : $y - 1;
        return [$start . '-' . ($start + 1), $start . '-09-01', ($start + 1) . '-08-31'];
    }

    /** Maakt een rekening voor elk actief lid in een groep met een bedrag, voor wie er nog geen is. @return array{0:int,1:int} [aangemaakt, zonder bedrag] */
    public static function generate(int $seasonId, ?string $dueDate): array
    {
        $created = 0;
        $skipped = 0;
        foreach (Database::all(
            "SELECT m.id AS member_id, g.fee_cents FROM members m JOIN group_memberships gm ON gm.member_id = m.id JOIN `groups` g ON g.id = gm.group_id
             WHERE m.status = 'ACTIVE' AND g.is_active = 1 AND NOT EXISTS (SELECT 1 FROM dues d WHERE d.member_id = m.id AND d.season_id = ?)",
            [$seasonId]
        ) as $r) {
            if ($r['fee_cents'] === null) {
                $skipped++;
                continue;
            }
            Database::exec(
                'INSERT INTO dues (member_id, season_id, amount_cents, status, due_date, created_at) VALUES (?,?,?,?,?,?)',
                [$r['member_id'], $seasonId, (int) $r['fee_cents'], 'OPEN', $dueDate, now()]
            );
            $created++;
        }
        return [$created, $skipped];
    }

    /** Betaalinstructies uit de config (rekeningnummer en begunstigde). */
    public static function payInfo(): string
    {
        $iban = trim((string) config('fees.iban', ''));
        $name = trim((string) config('fees.beneficiary', 'Hasseltse Zwemvereniging Spartacus'));
        return $iban === '' ? '' : "Rekening: $iban ($name)";
    }

    /** Herinneringsmail voor één rekening. */
    public static function reminderMessage(array $due, string $recipientName): array
    {
        $body = "Dag $recipientName,\n\nVolgens onze administratie is het lidgeld voor {$due['first_name']} {$due['last_name']} (seizoen {$due['season_name']}) nog niet betaald.\n\n"
            . 'Bedrag: ' . self::money((int) $due['amount_cents']) . "\n"
            . ($due['due_date'] ? 'Te betalen voor: ' . fmt_date($due['due_date']) . "\n" : '')
            . 'Gestructureerde mededeling: ' . self::reference((int) $due['id']) . "\n"
            . (self::payInfo() !== '' ? self::payInfo() . "\n" : '')
            . "\nHeb je al betaald? Dan mag je deze mail negeren. Bij vragen kan je op deze mail antwoorden of het bestuur contacteren.\n\nSportieve groeten,\nHasseltse Zwemvereniging Spartacus";
        return ['Herinnering lidgeld ' . $due['season_name'] . ' – ' . $due['first_name'] . ' ' . $due['last_name'], $body];
    }
}
