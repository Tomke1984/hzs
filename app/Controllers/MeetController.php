<?php
/** Wedstrijden: wie doet mee (ouder/lid of trainer) en de behaalde resultaten. */
final class MeetController
{
    /** Mag de gebruiker resultaten en deelnames van dit lid beheren als trainer/bestuur? */
    private static function coachCan(int $memberId): bool
    {
        if (!Auth::can('attendance.write')) {
            return false;
        }
        $ids = Scope::coachMemberIds();
        return $ids === null || in_array($memberId, $ids, true);
    }

    public static function answer(): void
    {
        $a = Scope::findActivity((int) input('activity_id', '0'));
        if (!$a || $a['type_code'] !== 'COMPETITION') {
            abort(404, 'Deze wedstrijd bestaat niet of je hebt er geen toegang toe.');
        }
        $mid = (int) input('member_id', '0');
        $inTarget = in_array($mid, array_map('intval', array_column(Scope::targetMembers($a), 'id')), true);
        $allowed = $inTarget && (in_array($mid, Scope::ownMemberIds(), true) || self::coachCan($mid));
        if (!$allowed) {
            abort(403, 'Je kan enkel voor jezelf, je eigen kinderen of de leden van je groep antwoorden.');
        }
        $back = url('activity', ['id' => $a['id']]) . '#deelname';
        if ($a['status'] !== 'PLANNED' || $a['starts_at'] <= now()) {
            flash('error', 'Antwoorden kan enkel voor een geplande wedstrijd die nog niet begonnen is.');
            redirect($back);
        }
        $ans = input('answer');
        if (!in_array($ans, ['YES', 'NO', 'MAYBE'], true)) {
            abort(400, 'Ongeldig antwoord.');
        }
        $events = mb_substr(input('events'), 0, 200);
        Database::exec('DELETE FROM meet_entries WHERE activity_id = ? AND member_id = ?', [$a['id'], $mid]);
        Database::exec(
            'INSERT INTO meet_entries (activity_id, member_id, answer, events, answered_by, updated_at) VALUES (?,?,?,?,?,?)',
            [$a['id'], $mid, $ans, $events === '' ? null : $events, Auth::user()['id'], now()]
        );
        flash('success', 'Antwoord bewaard.');
        redirect($back);
    }

    public static function resultSave(): void
    {
        $mid = (int) input('member_id', '0');
        if (!Database::val('SELECT COUNT(*) FROM members WHERE id = ?', [$mid]) || !self::coachCan($mid)) {
            abort(403, 'Je mag voor dit lid geen resultaten invoeren.');
        }
        $actId = (int) input('activity_id', '0');
        if ($actId && !Scope::findActivity($actId)) {
            abort(404, 'Wedstrijd niet gevonden.');
        }
        $back = $actId ? url('activity', ['id' => $actId]) . '#resultaten' : url('member', ['id' => $mid]) . '#resultaten';
        $event = input('event');
        $cs = Results::parse(input('time'));
        $date = input('swum_on');
        if ($event === '' || mb_strlen($event) > 40 || $cs === null || !valid_date($date) || $date > today()) {
            flash('error', 'Controleer afstand, tijd (bv. 1:05,32) en datum.');
            redirect($back);
        }
        $best = Database::val('SELECT MIN(time_cs) FROM results WHERE member_id = ? AND event = ?', [$mid, $event]);
        Database::exec(
            'INSERT INTO results (member_id, activity_id, event, time_cs, swum_on, note, created_by, created_at) VALUES (?,?,?,?,?,?,?,?)',
            [$mid, $actId ?: null, $event, $cs, $date, mb_substr(input('note'), 0, 120) ?: null, Auth::user()['id'], now()]
        );
        audit('CREATE', 'result', $mid, "$event " . Results::fmt($cs));
        flash('success', $best === null || $best === false || $cs < (int) $best ? 'Resultaat bewaard: nieuw persoonlijk record! 🎉' : 'Resultaat bewaard.');
        redirect($back);
    }

    public static function resultDelete(): void
    {
        $r = Database::one('SELECT * FROM results WHERE id = ?', [(int) input('id', '0')]);
        if (!$r) {
            abort(404, 'Resultaat niet gevonden.');
        }
        if (!self::coachCan((int) $r['member_id'])) {
            abort(403, 'Je mag dit resultaat niet verwijderen.');
        }
        Database::exec('DELETE FROM results WHERE id = ?', [$r['id']]);
        audit('DELETE', 'result', (int) $r['member_id'], $r['event']);
        flash('success', 'Resultaat verwijderd.');
        redirect($r['activity_id'] && input('member_id') === '' ? url('activity', ['id' => $r['activity_id']]) . '#resultaten' : url('member', ['id' => $r['member_id']]) . '#resultaten');
    }
}
