<?php
/**
 * Trainersplanning competitie: wie geeft welke training of begeleidt welke wedstrijd, en waar ontbreekt een trainer.
 *
 * Bezetting van een activiteit = vaste trainers (de trainers van de groepen, enkel bij trainingen)
 * + extra toegewezen trainers, min de trainers die zich afgemeld hebben. Afwijkingen per activiteit staan in
 * activity_trainers (ASSIGNED = vervanger/extra, ABSENT = afgemeld). Wijzigen de trainers van een groep,
 * dan geldt dat meteen voor alle komende trainingen; afwijkingen per training blijven staan.
 *
 * Alle groepen zijn competitiegroepen (sinds v0.14); trainingen en wedstrijden van actieve groepen en clubbrede wedstrijden tellen mee.
 */
final class Coverage
{
    /** Hoeveel dagen vooraf een mail vertrekt als er nog een trainer ontbreekt (config coverage.remind_days). */
    public static function remindDays(): int
    {
        return max(1, min(14, (int) config('coverage.remind_days', 3)));
    }

    /** Mag de ingelogde gebruiker de trainersplanning zien en gebruiken (overnemen, afmelden, toewijzen)? */
    public static function canView(): bool
    {
        $u = Auth::user();
        if (!$u) {
            return false;
        }
        return Auth::can('coverage.manage') || (Auth::hasRole('TRAINER') && self::isCompTrainer((int) $u['id']));
    }

    public static function isCompTrainer(int $userId): bool
    {
        return (bool) Database::val(
            "SELECT 1 FROM group_trainers gt JOIN `groups` g ON g.id = gt.group_id WHERE gt.user_id = ? AND g.category = 'COMPETITION' AND g.is_active = 1 LIMIT 1",
            [$userId]
        );
    }

    /** Trainers die je kan inplannen: actieve competitietrainers en coördinatoren. @return array<int,string> id => naam */
    public static function pool(): array
    {
        $rows = Database::all(
            "SELECT u.id, u.display_name FROM users u
             WHERE u.is_active = 1 AND (
               EXISTS (SELECT 1 FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = u.id AND r.code = 'COORDINATOR')
               OR (EXISTS (SELECT 1 FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = u.id AND r.code = 'TRAINER')
                   AND EXISTS (SELECT 1 FROM group_trainers gt JOIN `groups` g ON g.id = gt.group_id WHERE gt.user_id = u.id AND g.category = 'COMPETITION' AND g.is_active = 1)))
             ORDER BY u.display_name"
        );
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['id']] = $r['display_name'];
        }
        return $out;
    }

    /** SQL-voorwaarde voor activiteiten die in de trainersplanning horen (aliassen a = activities, t = activity_types). */
    private static function scopeSql(): string
    {
        // Trainingen en wedstrijden van competitiegroepen + wedstrijden voor de hele club (bv. clubkampioenschap).
        // Clubactiviteiten (samenkomst, pastadag, teambuilding, ...) hebben geen trainers nodig.
        return "a.status = 'PLANNED' AND t.code IN ('TRAINING','COMPETITION') AND (
                  EXISTS (SELECT 1 FROM activity_groups cg JOIN `groups` cgg ON cgg.id = cg.group_id
                          WHERE cg.activity_id = a.id AND cgg.category = 'COMPETITION' AND cgg.is_active = 1)
                  OR (t.code = 'COMPETITION' AND a.audience = 'CLUB'))";
    }

    public static function inScope(int $activityId): bool
    {
        return (bool) Database::val(
            'SELECT 1 FROM activities a JOIN activity_types t ON t.id = a.type_id WHERE a.id = ? AND ' . self::scopeSql(),
            [$activityId]
        );
    }

    /**
     * Activiteiten in de trainersplanning met begin in [from, to), met hun bezetting.
     * @param int[]|null $ids enkel deze activiteiten
     */
    public static function list(string $from, string $to, ?array $ids = null): array
    {
        $sql = 'SELECT a.*, t.code AS type_code, t.name AS type_name FROM activities a JOIN activity_types t ON t.id = a.type_id
                WHERE a.starts_at >= ? AND a.starts_at < ? AND ' . self::scopeSql();
        $params = [$from, $to];
        if ($ids !== null) {
            if (!$ids) {
                return [];
            }
            $sql .= ' AND a.id IN (' . Scope::in($ids) . ')';
            $params = array_merge($params, array_map('intval', $ids));
        }
        return self::enrich(Database::all($sql . ' ORDER BY a.starts_at, a.id', $params));
    }

    /** Eén activiteit met bezetting, of null als ze niet in de trainersplanning hoort. */
    public static function one(int $activityId): ?array
    {
        $rows = self::list('0000-00-00', '9999-12-31', [$activityId]);
        return $rows[0] ?? null;
    }

    /**
     * Voegt aan elke activiteit toe: groups, trainers (lijst), present, required, missing.
     * Een trainer: id, name, default (vaste trainer = groepstrainer), absent (afgemeld), auto (afgemeld omdat hij op een wedstrijd staat), note.
     */
    public static function enrich(array $rows): array
    {
        if (!$rows) {
            return [];
        }
        $ids = array_map('intval', array_column($rows, 'id'));
        $in = Scope::in($ids);

        $groups = [];
        foreach (Database::all(
            "SELECT ag.activity_id, g.id, g.name, g.color, g.category, g.min_trainers FROM activity_groups ag JOIN `groups` g ON g.id = ag.group_id
             WHERE ag.activity_id IN ($in) ORDER BY g.name",
            $ids
        ) as $g) {
            $groups[(int) $g['activity_id']][] = $g;
        }

        $users = [];
        foreach (Database::all('SELECT id, display_name FROM users WHERE is_active = 1') as $u) {
            $users[(int) $u['id']] = $u['display_name'];
        }

        // Vaste trainers = de trainers van de groep(en) van een training (wedstrijden hebben geen vaste trainers).
        $groupTrainers = [];
        foreach (Database::all(
            "SELECT ag.activity_id, gt.user_id FROM activity_groups ag JOIN group_trainers gt ON gt.group_id = ag.group_id WHERE ag.activity_id IN ($in)",
            $ids
        ) as $d) {
            $groupTrainers[(int) $d['activity_id']][(int) $d['user_id']] = true;
        }

        $over = [];
        foreach (Database::all("SELECT * FROM activity_trainers WHERE activity_id IN ($in) ORDER BY id", $ids) as $o) {
            $over[(int) $o['activity_id']][] = $o;
        }

        // Trainers die op een wedstrijd ingepland zijn, kunnen niet tegelijk op een training staan.
        $minStart = min(array_column($rows, 'starts_at'));
        $maxEnd = max(array_column($rows, 'ends_at'));
        $onMeet = [];
        foreach (Database::all(
            "SELECT at.user_id, a.id, a.title, a.starts_at, a.ends_at FROM activity_trainers at
             JOIN activities a ON a.id = at.activity_id JOIN activity_types t ON t.id = a.type_id
             WHERE at.status = 'ASSIGNED' AND t.code = 'COMPETITION' AND a.status = 'PLANNED' AND a.starts_at < ? AND a.ends_at > ?",
            [$maxEnd, $minStart]
        ) as $m) {
            $onMeet[(int) $m['user_id']][] = $m;
        }

        foreach ($rows as &$r) {
            $aid = (int) $r['id'];
            $list = [];
            if (($r['type_code'] ?? '') === 'TRAINING') {
                foreach (array_keys($groupTrainers[$aid] ?? []) as $uid) {
                    if (isset($users[$uid])) {
                        $list[$uid] = ['id' => $uid, 'name' => $users[$uid], 'default' => true, 'absent' => false, 'note' => null, 'auto' => false];
                    }
                }
            }
            foreach ($over[$aid] ?? [] as $o) {
                $uid = (int) $o['user_id'];
                if (!isset($users[$uid])) {
                    continue;
                }
                $list[$uid] ??= ['id' => $uid, 'name' => $users[$uid], 'default' => false, 'absent' => false, 'note' => null, 'auto' => false];
                $list[$uid]['absent'] = $o['status'] === 'ABSENT';
                $list[$uid]['note'] = $o['note'];
            }
            if (($r['type_code'] ?? '') !== 'COMPETITION') {
                foreach ($list as $uid => $t) {
                    foreach ($onMeet[$uid] ?? [] as $m) {
                        if (!$t['absent'] && (int) $m['id'] !== $aid && $m['starts_at'] < $r['ends_at'] && $m['ends_at'] > $r['starts_at']) {
                            $list[$uid]['absent'] = true;
                            $list[$uid]['auto'] = true;
                            $list[$uid]['note'] = 'Op wedstrijd: ' . $m['title'];
                            break;
                        }
                    }
                }
            }
            uasort($list, fn($x, $y) => [$x['absent'], $x['name']] <=> [$y['absent'], $y['name']]);
            $r['trainers'] = array_values($list);
            $r['groups'] = $groups[$aid] ?? [];
            $r['present'] = count(array_filter($list, fn($t) => !$t['absent']));
            $r['required'] = self::required($r, $r['groups']);
            $r['missing'] = max(0, $r['required'] - $r['present']);
            $r['gnames'] = $r['audience'] === 'CLUB' && !$r['groups'] ? 'Hele club' : implode(', ', array_column($r['groups'], 'name'));
        }
        unset($r);
        return $rows;
    }

    /** Aantal trainers nodig: eigen instelling van de activiteit, anders het hoogste minimum van de competitiegroepen (standaard 1). */
    public static function required(array $activity, array $groups): int
    {
        if (isset($activity['min_trainers']) && $activity['min_trainers'] !== '') {
            return max(0, (int) $activity['min_trainers']);
        }
        $n = 1;
        foreach ($groups as $g) {
            if ($g['category'] === 'COMPETITION' && $g['min_trainers'] !== null) {
                $n = max($n, (int) $g['min_trainers']);
            }
        }
        return $n;
    }

    /** Is deze trainer een vaste trainer van deze activiteit (trainer van een van de groepen van een training)? */
    public static function isDefault(array $activity, int $userId): bool
    {
        if (($activity['type_code'] ?? '') !== 'TRAINING') {
            return false;
        }
        return (bool) Database::val(
            'SELECT 1 FROM activity_groups ag JOIN group_trainers gt ON gt.group_id = ag.group_id WHERE ag.activity_id = ? AND gt.user_id = ?',
            [(int) $activity['id'], $userId]
        );
    }

    /**
     * Kan deze trainer niet bij deze activiteit zijn omdat hij op hetzelfde moment al elders staat? Geeft de reden of null.
     * Een wedstrijd gaat voor op een training: wie op een wedstrijd ingepland wordt, is voor overlappende trainingen
     * automatisch afgemeld ("op wedstrijd"); omgekeerd kan je hem niet op die training inplannen.
     */
    public static function conflict(array $activity, int $userId): ?string
    {
        $ids = array_map('intval', array_column(Database::all(
            "SELECT id FROM activities WHERE id <> ? AND status = 'PLANNED' AND starts_at < ? AND ends_at > ?",
            [(int) $activity['id'], $activity['ends_at'], $activity['starts_at']]
        ), 'id'));
        if (!$ids) {
            return null;
        }
        foreach (self::list('0000-00-00', '9999-12-31', $ids) as $o) {
            if ($activity['type_code'] === 'COMPETITION' && $o['type_code'] === 'TRAINING') {
                continue; // die training wordt automatisch "op wedstrijd"
            }
            foreach ($o['trainers'] as $t) {
                if ($t['id'] === $userId && !$t['absent']) {
                    return $t['name'] . ' staat op dat moment al op ' . $o['title'] . ' (' . fmt_date($o['starts_at']) . ' ' . fmt_time($o['starts_at']) . '–' . fmt_time($o['ends_at']) . ').';
                }
            }
        }
        return null;
    }

    /** Afwijking voor één trainer bij één activiteit vastleggen (ASSIGNED of ABSENT), of wissen met null. */
    public static function set(int $activityId, int $userId, ?string $status, ?string $note = null): void
    {
        Database::exec('DELETE FROM activity_trainers WHERE activity_id = ? AND user_id = ?', [$activityId, $userId]);
        if ($status !== null) {
            $by = Auth::user()['id'] ?? null;
            Database::exec(
                'INSERT INTO activity_trainers (activity_id, user_id, status, note, updated_by, updated_at) VALUES (?,?,?,?,?,?)',
                [$activityId, $userId, $status, $note === '' ? null : $note, $by, now()]
            );
        }
    }

    /** Opruimen bij het verwijderen van een activiteit (trainers, meldingen en afmeldingen van leden) of reeks. */
    public static function forgetActivity(int $activityId): void
    {
        Database::exec('DELETE FROM absence_reports WHERE activity_id = ?', [$activityId]);
        Database::exec('DELETE FROM activity_trainers WHERE activity_id = ?', [$activityId]);
        Database::exec('DELETE FROM coverage_alerts WHERE activity_id = ?', [$activityId]);
    }

    public static function forgetSeries(int $seriesId): void
    {
        Database::exec('DELETE FROM series_trainers WHERE series_id = ?', [$seriesId]);
    }

    /** Activiteiten waar nog een trainer ontbreekt, vanaf nu tot binnen $days dagen. */
    /** Eén periode voor het tekortsignaal: menubadge, overzicht en trainersplanning tonen hetzelfde getal. */
    public const SIGNAL_DAYS = 14;

    /** Tekorten in de signaalperiode (gecachet per request). */
    public static function shortages(): array
    {
        static $cache = null;
        return $cache ??= self::problems(self::SIGNAL_DAYS);
    }

    /** Eerstvolgende tekort na de signaalperiode (binnen een half jaar), of null. */
    public static function nextShortage(): ?array
    {
        $from = date('Y-m-d H:i:s', strtotime('+' . self::SIGNAL_DAYS . ' days'));
        foreach (self::list($from, date('Y-m-d H:i:s', strtotime('+183 days'))) as $a) {
            if ($a['missing'] > 0) {
                return $a;
            }
        }
        return null;
    }

    public static function problems(int $days): array
    {
        return array_values(array_filter(
            self::list(now(), date('Y-m-d H:i:s', strtotime("+$days days"))),
            fn($a) => $a['missing'] > 0
        ));
    }

    /** E-mailadressen die een herinnering krijgen: coördinatoren en competitietrainers (actief). @return array<int,string> id => e-mail */
    public static function recipients(): array
    {
        $ids = array_keys(self::pool());
        if (!$ids) {
            return [];
        }
        $out = [];
        foreach (Database::all('SELECT id, email FROM users WHERE id IN (' . Scope::in($ids) . ')', $ids) as $u) {
            if (filter_var($u['email'], FILTER_VALIDATE_EMAIL)) {
                $out[(int) $u['id']] = $u['email'];
            }
        }
        return $out;
    }

    /**
     * Mail naar coördinatoren en competitietrainers voor trainingen/wedstrijden in de komende dagen waar nog een
     * trainer ontbreekt. Elke activiteit wordt één keer gemeld; opnieuw als het tekort groter wordt, of na een
     * oplossing opnieuw ontstaat. $force = alle huidige tekorten opnieuw melden. Geeft het aantal gemelde activiteiten.
     */
    public static function remind(bool $force = false): int
    {
        $problems = self::problems(self::remindDays());
        $ids = array_map('intval', array_column($problems, 'id'));
        // Opgelost, geschrapt of voorbij: de melding vervalt (een nieuw tekort wordt dan opnieuw gemeld).
        if ($ids) {
            Database::exec('DELETE FROM coverage_alerts WHERE activity_id NOT IN (' . Scope::in($ids) . ')', $ids);
        } else {
            Database::exec('DELETE FROM coverage_alerts');
        }
        $sent = [];
        foreach (Database::all('SELECT activity_id, missing FROM coverage_alerts') as $al) {
            $sent[(int) $al['activity_id']] = (int) $al['missing'];
        }
        $new = array_values(array_filter($problems, fn($a) => $force || !isset($sent[(int) $a['id']]) || $sent[(int) $a['id']] < $a['missing']));
        if (!$new) {
            return 0;
        }
        $to = self::recipients();
        if ($to) {
            [$subject, $body] = self::mailText($new);
            Mailer::sendMany(array_map(fn($e) => [$e, $subject, $body], array_values($to)));
        }
        foreach ($new as $a) {
            Database::exec('DELETE FROM coverage_alerts WHERE activity_id = ?', [$a['id']]);
            Database::exec('INSERT INTO coverage_alerts (activity_id, missing, sent_at) VALUES (?,?,?)', [$a['id'], $a['missing'], now()]);
        }
        return count($new);
    }

    /** @return array{0:string,1:string} [onderwerp, tekst] */
    public static function mailText(array $items): array
    {
        if (count($items) === 1) {
            $a = $items[0];
            $subject = 'Trainer gezocht: ' . $a['title'] . ' ' . fmt_date($a['starts_at']) . ' ' . fmt_time($a['starts_at']);
        } else {
            $subject = 'Trainers gezocht voor ' . count($items) . ' trainingen of wedstrijden';
        }
        $lines = [];
        foreach ($items as $a) {
            $line = '- ' . fmt_date($a['starts_at']) . ' ' . fmt_time($a['starts_at']) . '–' . fmt_time($a['ends_at']) . ' · ' . $a['title'];
            if ($a['gnames'] !== '') {
                $line .= ' (' . $a['gnames'] . ')';
            }
            $line .= "\n  " . $a['present'] . ' van ' . $a['required'] . ' trainer(s) ingepland';
            $absent = array_filter($a['trainers'], fn($t) => $t['absent']);
            if ($absent) {
                $line .= '; afgemeld: ' . implode(', ', array_map(fn($t) => $t['name'] . ($t['note'] ? ' (' . $t['note'] . ')' : ''), $absent));
            }
            $lines[] = $line;
        }
        $link = Invites::baseUrl() . '/' . url('coverage');
        $body = "Hallo,\n\nVoor deze trainingen of wedstrijden van de competitie zijn nog niet genoeg trainers ingepland:\n\n"
            . implode("\n", $lines)
            . "\n\nKan jij invallen? Neem de plek over in de trainersplanning:\n$link\n\n"
            . "Lukt het niet, dan hoef je niets te doen; de coördinator volgt dit op.\n\n– " . config('app_name', 'HZS Clubapp');
        return [$subject, $body];
    }

    /**
     * Automatische herinneringen zonder cron: hooguit één keer per uur, uitgevoerd nadat het antwoord naar de
     * bezoeker vertrokken is. Uit te zetten met config coverage.auto_remind = false (gebruik dan tools/coverage-reminders.php).
     */
    public static function autoRemind(): void
    {
        if (!config('coverage.auto_remind', true)) {
            return;
        }
        $f = APP_ROOT . '/storage/coverage-last-run';
        if (is_file($f) && time() - (int) @filemtime($f) < 3600) {
            return;
        }
        if (!@touch($f)) {
            return;
        }
        register_shutdown_function(function (): void {
            if (function_exists('fastcgi_finish_request')) {
                @fastcgi_finish_request();
            }
            try {
                self::remind();
            } catch (Throwable $e) {
                error_log('Trainersplanning, herinneringen: ' . $e->getMessage());
            }
        });
    }
}
