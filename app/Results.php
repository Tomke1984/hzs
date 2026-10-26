<?php
/** Wedstrijdresultaten: tijden in honderdsten van seconden, persoonlijke records per afstand. */
final class Results
{
    /** Standaardafstanden voor de keuzelijst; eigen tekst blijft mogelijk. */
    public const EVENTS = [
        '25 vrij', '50 vrij', '100 vrij', '200 vrij', '400 vrij', '800 vrij', '1500 vrij',
        '50 rug', '100 rug', '200 rug', '50 school', '100 school', '200 school',
        '50 vlinder', '100 vlinder', '200 vlinder', '100 wissel', '200 wissel', '400 wissel',
    ];

    /** "1:05,32", "1:05.32", "65,32" of "59" → centiseconden, of null als ongeldig. */
    public static function parse(string $t): ?int
    {
        $t = trim(str_replace(',', '.', $t));
        if (!preg_match('/^(?:(\d{1,2}):)?(\d{1,2})(?:\.(\d{1,2}))?$/', $t, $m)) {
            return null;
        }
        $cs = isset($m[3]) && $m[3] !== '' ? (int) str_pad($m[3], 2, '0') : 0;
        $total = ((int) ($m[1] ?? 0) * 60 + (int) $m[2]) * 100 + $cs;
        return $total > 0 && $total < 60 * 100 * 100 ? $total : null;
    }

    public static function fmt(int $cs): string
    {
        $min = intdiv($cs, 6000);
        $sec = intdiv($cs % 6000, 100);
        $h = $cs % 100;
        return ($min > 0 ? $min . ':' . str_pad((string) $sec, 2, '0', STR_PAD_LEFT) : (string) $sec) . ',' . str_pad((string) $h, 2, '0', STR_PAD_LEFT);
    }

    /** Beste tijd per afstand van een lid. @return array<string,array{time_cs:int,swum_on:string}> */
    public static function bests(int $memberId): array
    {
        $out = [];
        foreach (Database::all('SELECT event, time_cs, swum_on FROM results WHERE member_id = ? ORDER BY time_cs, swum_on', [$memberId]) as $r) {
            $out[$r['event']] ??= $r;
        }
        ksort($out);
        return $out;
    }
}
