<div class="row-between">
  <h1><?= e($activity['title']) ?></h1>
  <?php if (Auth::can('activities.write')): ?>
    <a class="btn btn-ghost" href="<?= e(url('activity_form', ['id' => $activity['id']])) ?>">Wijzigen</a>
  <?php endif; ?>
</div>

<section class="card">
  <p>
    <span class="chip type-<?= e(strtolower($activity['type_code'])) ?>"><?= e($activity['type_name']) ?></span>
    <?php if ($activity['status'] === 'CANCELLED'): ?><span class="chip off">Geannuleerd</span><?php endif; ?>
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
            <td><?= e($m['last_name']) ?> <?= e($m['first_name']) ?></td>
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
      <thead><tr><th>Lid</th><th>Status</th></tr></thead>
      <tbody>
      <?php foreach ($roster as $m):
          $cur = $statusByMember[$m['id']] ?? 'UNKNOWN'; ?>
        <tr>
          <td><?= e($m['last_name']) ?> <?= e($m['first_name']) ?></td>
          <td><span class="chip att-<?= strtolower($cur) ?>"><?= e(attendance_label($cur)) ?></span></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
    <?php if ($activity['status'] === 'CANCELLED'): ?><p class="muted small">Deze activiteit is geannuleerd; aanwezigheid kan niet meer geregistreerd worden.</p><?php endif; ?>
  <?php endif; ?>
</section>
<?php endif; ?>

<p><a href="<?= e(url('calendar', ['m' => substr($activity['starts_at'], 0, 7)])) ?>">← Terug naar de kalender</a></p>
