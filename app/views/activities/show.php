<div class="row-between">
  <h1><?= e($activity['title']) ?></h1>
  <?php if (Auth::can('activities.write')): ?>
    <a class="btn btn-ghost" href="<?= e(url('activity_form', ['id' => $activity['id']])) ?>">Wijzigen</a>
  <?php endif; ?>
</div>

<section class="card">
  <p>
    <span class="chip type-<?= e(strtolower($activity['type_code'])) ?>"><?= e($activity['type_name']) ?></span>
    <?php if ($activity['status'] === 'CANCELLED'): ?><span class="chip off">Geschrapt</span><?php if (!empty($activity['status_note'])): ?> <span class="muted"><?= e($activity['status_note']) ?></span><?php endif; ?><?php endif; ?>
    <?php if (!empty($activity['series_id']) && Auth::can('activities.write')): ?> <a class="small" href="<?= e(url('series_show', ['id' => $activity['series_id']])) ?>">Deel van een reeks<?= !empty($activity['is_custom']) ? ' (individueel aangepast)' : '' ?></a><?php endif; ?>
  </p>
  <dl class="facts">
    <dt>Wanneer</dt>
    <dd><?= e(fmt_dt($activity['starts_at'])) ?> tot <?= e(substr($activity['starts_at'], 0, 10) === substr($activity['ends_at'], 0, 10) ? fmt_time($activity['ends_at']) : fmt_dt($activity['ends_at'])) ?></dd>
    <?php if ($activity['location']): ?><dt>Locatie</dt><dd><?= e($activity['location']) ?></dd><?php endif; ?>
    <dt>Voor</dt>
    <dd>
      <?php if ($activity['audience'] === 'CLUB'): ?>
        Hele club
      <?php else: ?>
        <?= e(implode(', ', array_column($groups, 'name'))) ?>
      <?php endif; ?>
    </dd>
  </dl>
  <?php if ($activity['description']): ?><p><?= nl2br(e($activity['description'])) ?></p><?php endif; ?>
  <?php if (Auth::can('activities.write')): ?>
  <form method="post" action="<?= e(url('occurrence')) ?>" class="inline-form" <?= $activity['status'] === 'CANCELLED' ? '' : 'data-confirm="Deze activiteit annuleren en verwijderen? Dit kan niet ongedaan worden gemaakt."' ?>>
    <?= csrf_field() ?>
    <input type="hidden" name="activity_id" value="<?= (int) $activity['id'] ?>">
    <?php if ($activity['status'] === 'CANCELLED'): ?>
      <input type="hidden" name="do" value="restore">
      <button class="btn btn-small btn-ghost" type="submit">Herstellen</button>
    <?php else: ?>
      <input type="hidden" name="do" value="cancel">
            <button class="btn btn-small btn-ghost" type="submit">Schrappen</button>
    <?php endif; ?>
  </form>
  <?php endif; ?>
</section>

<?php if ($canSeeAttendance): ?>
<section class="card">
  <h2>Aanwezigheid</h2>

  <?php if (!$roster): ?>
    <p class="muted">Geen leden voor deze activiteit<?= Scope::clubWide() ? ' (de doelgroep bevat geen actieve leden)' : ' binnen jouw toegang' ?>.</p>

  <?php elseif ($canRegister): ?>
    <form method="post" action="<?= e(url('attendance_save')) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="activity_id" value="<?= (int) $activity['id'] ?>">
      <div class="table-wrap">
      <table class="table attendance">
        <thead><tr><th>Lid</th><th>Onbekend</th><th>Aanwezig</th><th>Afwezig</th></tr></thead>
        <tbody>
        <?php foreach ($roster as $m):
            $cur = $statusByMember[$m['id']] ?? 'UNKNOWN'; ?>
          <tr>
            <td><?= e($m['last_name']) ?> <?= e($m['first_name']) ?><?php if (isset($absences[(int) $m['id']])): ?> <span class="chip warn">Afgemeld</span><?php if ($absences[(int) $m['id']]['reason']): ?> <span class="muted small"><?= e($absences[(int) $m['id']]['reason']) ?></span><?php endif; endif; ?></td>
            <?php if (in_array((int) $m['id'], $registrable, true)): ?>
            <?php foreach (['UNKNOWN', 'PRESENT', 'ABSENT'] as $s): ?>
              <td class="center">
                <label class="radio radio-<?= strtolower($s) ?>">
                  <input type="radio" name="status[<?= (int) $m['id'] ?>]" value="<?= $s ?>" <?= $cur === $s ? 'checked' : '' ?>
                         aria-label="<?= e(attendance_label($s) . ': ' . $m['first_name'] . ' ' . $m['last_name']) ?>">
                </label>
              </td>
            <?php endforeach; ?>
            <?php else: ?>
              <td colspan="3" class="center"><span class="chip att-<?= strtolower($cur) ?>"><?= e(attendance_label($cur)) ?></span></td>
            <?php endif; ?>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      </div>
      <div class="actions">
        <button class="btn" type="submit">Aanwezigheden opslaan</button>
        <button class="btn btn-ghost" type="button" data-mark-all="PRESENT">Iedereen aanwezig</button>
      </div>
    </form>

  <?php else: ?>
    <div class="table-wrap">
    <table class="table">
      <thead><tr><th>Lid</th><th>Status</th><th>Afmelding</th></tr></thead>
      <tbody>
      <?php foreach ($roster as $m):
          $cur = $statusByMember[$m['id']] ?? 'UNKNOWN'; $abs = $absences[(int) $m['id']] ?? null; $mine = in_array((int) $m['id'], $ownIds, true); ?>
        <tr>
          <td><?= e($m['last_name']) ?> <?= e($m['first_name']) ?></td>
          <td><span class="chip att-<?= strtolower($cur) ?>"><?= e(attendance_label($cur)) ?></span></td>
          <td>
            <?php if ($abs): ?><span class="chip warn">Afgemeld</span> <?php if ($abs['reason']): ?><span class="muted small"><?= e($abs['reason']) ?></span> <?php endif; ?><?php endif; ?>
            <?php if ($mine && $canAbsence): ?>
              <form class="inline-form" method="post" action="<?= e(url('absence')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="activity_id" value="<?= (int) $activity['id'] ?>">
                <input type="hidden" name="member_id" value="<?= (int) $m['id'] ?>">
                <?php if ($abs): ?>
                  <input type="hidden" name="do" value="remove">
                  <button class="btn btn-small btn-ghost" type="submit">Afmelding intrekken</button>
                <?php else: ?>
                  <input name="reason" maxlength="200" placeholder="Reden (optioneel)" aria-label="Reden van afwezigheid">
                  <button class="btn btn-small" type="submit">Kan niet komen</button>
                <?php endif; ?>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
    <?php if ($activity['status'] === 'CANCELLED'): ?><p class="muted small">Deze activiteit is geannuleerd; aanwezigheid kan niet meer geregistreerd worden.</p><?php endif; ?>
  <?php endif; ?>
</section>
<?php endif; ?>

<?php if ($isMeet && $roster): ?>
<section class="card" id="deelname">
  <h2>Deelname aan de wedstrijd</h2>
  <?php $cnt = ['YES' => 0, 'NO' => 0, 'MAYBE' => 0]; foreach ($roster as $m) { $x = $entries[(int) $m['id']]['answer'] ?? null; if ($x) { $cnt[$x]++; } } ?>
  <p class="muted small"><?= (int) $cnt['YES'] ?> ja · <?= (int) $cnt['MAYBE'] ?> misschien · <?= (int) $cnt['NO'] ?> nee · <?= count($roster) - array_sum($cnt) ?> nog geen antwoord</p>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Lid</th><th>Antwoord</th><th>Afstanden</th><?php if ($canMeetEdit): ?><th></th><?php endif; ?></tr></thead>
    <tbody>
    <?php foreach ($roster as $m): $en = $entries[(int) $m['id']] ?? null;
        $mayEdit = $canMeetEdit && (in_array((int) $m['id'], $ownIds, true) || ($canResults && ($coachIds === null || in_array((int) $m['id'], $coachIds, true)))); ?>
      <tr>
        <td><?= e($m['last_name'] . ' ' . $m['first_name']) ?></td>
        <?php if ($mayEdit): ?>
        <td colspan="3">
          <form class="inline-form" method="post" action="<?= e(url('meet_answer')) ?>">
            <?= csrf_field() ?><input type="hidden" name="activity_id" value="<?= (int) $activity['id'] ?>"><input type="hidden" name="member_id" value="<?= (int) $m['id'] ?>">
            <select name="answer" aria-label="Antwoord voor <?= e($m['first_name']) ?>">
              <?php foreach (['YES' => 'Ja, doet mee', 'MAYBE' => 'Misschien', 'NO' => 'Nee'] as $k => $l): ?><option value="<?= $k ?>" <?= ($en['answer'] ?? '') === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?>
            </select>
            <input name="events" maxlength="200" value="<?= e((string) ($en['events'] ?? '')) ?>" placeholder="Afstanden (bv. 100 vrij, 50 rug)" aria-label="Afstanden">
            <button class="btn btn-small" type="submit">Bewaren</button>
          </form>
        </td>
        <?php else: ?>
        <td><?= $en ? '<span class="chip ' . ($en['answer'] === 'YES' ? 'ok' : ($en['answer'] === 'MAYBE' ? 'warn' : 'off')) . '">' . e(['YES' => 'Ja', 'MAYBE' => 'Misschien', 'NO' => 'Nee'][$en['answer']]) . '</span>' : '<span class="muted">—</span>' ?></td>
        <td class="muted"><?= e((string) ($en['events'] ?? '')) ?></td>
        <?php if ($canMeetEdit): ?><td></td><?php endif; ?>
        <?php endif; ?>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</section>
<?php endif; ?>

<?php if ($isMeet && ($meetResults || $canResults)): ?>
<section class="card" id="resultaten">
  <h2>Resultaten</h2>
  <?php if ($meetResults): ?>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Lid</th><th>Afstand</th><th>Tijd</th><th>Opmerking</th><?php if ($canResults): ?><th></th><?php endif; ?></tr></thead>
    <tbody>
    <?php foreach ($meetResults as $r): ?>
      <tr><td><a href="<?= e(url('member', ['id' => $r['member_id']])) ?>#resultaten"><?= e($r['last_name'] . ' ' . $r['first_name']) ?></a></td><td><?= e($r['event']) ?></td><td><strong><?= e(Results::fmt((int) $r['time_cs'])) ?></strong></td><td class="muted small"><?= e((string) $r['note']) ?></td>
        <?php if ($canResults): ?><td class="right"><?php if ($coachIds === null || in_array((int) $r['member_id'], $coachIds, true)): ?><form method="post" action="<?= e(url('result_delete')) ?>" data-confirm="Dit resultaat verwijderen?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><button class="btn btn-small btn-ghost" type="submit">Verwijderen</button></form><?php endif; ?></td><?php endif; ?></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php else: ?><p class="muted">Nog geen resultaten ingevoerd.</p><?php endif; ?>
  <?php if ($canResults && $activity['starts_at'] <= now()): ?>
  <form class="inline-form" method="post" action="<?= e(url('result_save')) ?>">
    <?= csrf_field() ?><input type="hidden" name="activity_id" value="<?= (int) $activity['id'] ?>"><input type="hidden" name="swum_on" value="<?= e(substr($activity['starts_at'], 0, 10)) ?>">
    <label>Lid
      <select name="member_id" required><option value="">— kies —</option>
        <?php foreach ($roster as $m): if ($coachIds !== null && !in_array((int) $m['id'], $coachIds, true)) { continue; } ?><option value="<?= (int) $m['id'] ?>"><?= e($m['last_name'] . ' ' . $m['first_name']) ?></option><?php endforeach; ?></select>
    </label>
    <label>Afstand <input name="event" list="events" maxlength="40" required placeholder="100 vrij"></label>
    <label>Tijd <input name="time" required placeholder="1:05,32" pattern="[0-9:.,]+" maxlength="9"></label>
    <label>Opmerking <input name="note" maxlength="120"></label>
    <button class="btn" type="submit">Resultaat toevoegen</button>
    <datalist id="events"><?php foreach (Results::EVENTS as $ev): ?><option value="<?= e($ev) ?>"><?php endforeach; ?></datalist>
  </form>
  <?php elseif ($canResults): ?><p class="muted small">Resultaten kan je invoeren zodra de wedstrijd begonnen is.</p><?php endif; ?>
</section>
<?php endif; ?>

<?php if ($medical): ?>
<section class="card">
  <h2>Noodgegevens deelnemers</h2>
  <p class="muted small">Enkel voor trainers en bestuur. Deze gegevens staan nooit in een export.</p>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Lid</th><th>Noodcontact</th><th>Medische opmerking</th></tr></thead>
    <tbody>
    <?php foreach ($medical as $m): ?>
      <tr><td><?= e($m['last_name']) ?> <?= e($m['first_name']) ?></td>
        <td><?= e(trim(($m['emergency_name'] ?? '') . ' ' . ($m['emergency_phone'] ?? ''))) ?: '—' ?></td>
        <td><?= e($m['medical_note'] ?? '') ?: '—' ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</section>
<?php endif; ?>

<?php if (Auth::can('mail.send') && $activity['status'] === 'PLANNED'): ?>
  <p><a class="btn btn-ghost btn-small" href="<?= e(url('mail', ['to' => 'a:' . $activity['id']])) ?>">✉ Bericht aan de deelnemers</a></p>
<?php endif; ?>

<?php if (Auth::can('activities.write') && ($activity['signup_capacity'] ?? null) !== null): ?>
  <p><a class="btn btn-ghost btn-small" href="<?= e(url('event_signups', ['id' => $activity['id']])) ?>">Online inschrijvingen (<?= (int) EventController::taken((int) $activity['id']) ?> pers.)</a></p>
<?php endif; ?>

<p><a href="<?= e(url('calendar', ['m' => substr($activity['starts_at'], 0, 7)])) ?>">← Terug naar de kalender</a></p>
