<h1>Welkom, <?= e(Auth::user()['display_name']) ?></h1>

<?php if ($noTfa): ?>
<div class="alert alert-info">Je account heeft toegang tot persoonsgegevens van minderjarigen. <a href="<?= e(url('account')) ?>#2fa">Zet tweestapsverificatie aan →</a></div>
<?php endif; ?>

<?php if ($coverShort): ?>
<section class="card cover-problems">
  <div class="row-between"><h2>Trainer gezocht <span class="badge"><?= count($coverShort) ?></span></h2><a href="<?= e(url('coverage', ['show' => 'problems'])) ?>">Trainersplanning →</a></div>
  <ul class="list">
  <?php foreach ($coverShort as $c): ?>
    <li><a href="<?= e(url('coverage', ['show' => 'problems'])) ?>#a<?= (int) $c['id'] ?>"><strong><?= e(fmt_date($c['starts_at'])) ?> <?= e(fmt_time($c['starts_at'])) ?></strong> · <?= e($c['title']) ?></a>
      <span class="muted"><?= e($c['gnames']) ?> · <?= (int) $c['present'] ?> van <?= (int) $c['required'] ?> trainer(s)</span></li>
  <?php endforeach; ?>
  </ul>
</section>
<?php endif; ?>

<?php if ($coverView): ?>
<section class="card">
  <div class="row-between"><h2>Jij geeft training (komende 7 dagen)</h2><a href="<?= e(url('coverage', ['show' => 'mine'])) ?>">Mijn planning →</a></div>
  <?php if (!$coverMine): ?>
    <p class="muted">Je staat de komende 7 dagen nergens ingepland.</p>
  <?php else: ?>
  <ul class="list">
  <?php foreach ($coverMine as $c): ?>
    <li><a href="<?= e(url('activity', ['id' => $c['id']])) ?>#trainers"><strong><?= e(fmt_date($c['starts_at'])) ?> <?= e(fmt_time($c['starts_at'])) ?></strong> · <?= e($c['title']) ?></a>
      <span class="muted"><?= e($c['gnames']) ?></span></li>
  <?php endforeach; ?>
  </ul>
  <?php endif; ?>
</section>
<?php endif; ?>

<?php if ($unfilled): ?>
<section class="card">
  <div class="row-between"><h2>Aanwezigheid nog in te vullen</h2><a href="<?= e(url('attendance_report')) ?>">Rapport →</a></div>
  <ul class="list">
  <?php foreach ($unfilled as $a): ?>
    <li><a href="<?= e(url('activity', ['id' => $a['id']])) ?>"><strong><?= e($a['title']) ?></strong></a> <span class="muted"><?= e(fmt_dt($a['starts_at'])) ?></span></li>
  <?php endforeach; ?>
  </ul>
</section>
<?php endif; ?>

<?php if ($absences): ?>
<section class="card">
  <h2>Afmeldingen voor de komende 2 weken</h2>
  <ul class="list">
  <?php foreach ($absences as $r): ?>
    <li><strong><?= e($r['first_name'] . ' ' . $r['last_name']) ?></strong> — <a href="<?= e(url('activity', ['id' => $r['activity_id']])) ?>"><?= e($r['title']) ?></a>
      <span class="muted"><?= e(fmt_dt($r['starts_at'])) ?><?= $r['reason'] ? ' · ' . e($r['reason']) : '' ?></span></li>
  <?php endforeach; ?>
  </ul>
</section>
<?php endif; ?>

<?php if ($guardianMissing > 0): ?>
<div class="alert alert-warning">
  <strong><?= (int) $guardianMissing ?> <?= $guardianMissing === 1 ? 'lid' : 'leden' ?> jonger dan 18</strong> zonder ouder of voogd.
  <a href="<?= e(url('members', ['missing' => 1])) ?>">Bekijk de lijst →</a>
</div>
<?php endif; ?>

<?php if ($stats): ?>
<div class="stats">
  <div class="stat"><strong><?= $stats['members'] ?></strong><span>actieve leden</span></div>
  <div class="stat"><strong><?= $stats['groups'] ?></strong><span>actieve groepen</span></div>
  <div class="stat"><strong><?= $stats['activities'] ?></strong><span>geplande activiteiten</span></div>
</div>
<?php endif; ?>

<?php if (Auth::can('activities.read')): ?>
<section class="card">
  <div class="row-between">
    <h2><?= $mode === 'week' ? 'Activiteiten' : 'Eerstvolgende activiteiten' ?></h2>
    <span class="actions-inline">
      <span class="segmented" role="group" aria-label="Weergave">
        <a href="<?= e(url('dashboard', ['view' => 'list'])) ?>"<?= $mode === 'list' ? ' aria-current="page"' : '' ?>>Lijst</a>
        <a href="<?= e(url('dashboard', ['view' => 'week'])) ?>"<?= $mode === 'week' ? ' aria-current="page"' : '' ?>>Weekkalender</a>
      </span>
      <a href="<?= e(url('calendar')) ?>">Hele kalender →</a>
    </span>
  </div>
  <?php if ($mode === 'week'): ?>
    <div class="cal-nav week-nav">
      <a class="btn btn-ghost btn-small" href="<?= e(url('dashboard', array_filter(['view' => 'week', 'w' => $offset - 1 ?: '']))) ?>" aria-label="Vorige week">← Vorige</a>
      <strong class="cal-title">Week van <?= e(fmt_date($monday)) ?></strong>
      <span class="actions-inline">
        <?php if ($offset !== 0): ?><a class="btn btn-ghost btn-small" href="<?= e(url('dashboard', ['view' => 'week'])) ?>">Deze week</a><?php endif; ?>
        <a class="btn btn-ghost btn-small" href="<?= e(url('dashboard', array_filter(['view' => 'week', 'w' => $offset + 1 ?: '']))) ?>" aria-label="Volgende week">Volgende →</a>
      </span>
    </div>
    <div class="week-grid week-plain">
      <?php for ($i = 0; $i < 7; $i++): $day = date('Y-m-d', strtotime("$monday +$i days")); $items = $byDay[$day] ?? []; ?>
        <section class="week-day<?= $day === today() ? ' is-today' : '' ?><?= $items ? ' has-events' : '' ?>">
          <h3 class="week-head"><?= e(fmt_date($day)) ?></h3>
          <?php if (!$items): ?><p class="muted small week-empty">—</p><?php endif; ?>
          <?php foreach ($items as $a): ?>
            <a class="week-item type-<?= e(strtolower($a['type_code'])) ?><?= $a['ends_at'] <= now() ? ' is-past' : '' ?><?= $a['mine'] ? ' is-mine' : '' ?>" href="<?= e(url('activity', ['id' => $a['id']])) ?><?= $a['mine'] ? '#trainers' : '' ?>">
              <?php if ($a['mine']): ?><span class="chip mine-chip"><?= $a['type_code'] === 'TRAINING' ? 'Jij geeft training' : 'Jij gaat mee' ?></span><?php endif; ?>
              <span class="week-time"><?= e(fmt_time($a['starts_at'])) ?>–<?= e(fmt_time($a['ends_at'])) ?></span>
              <span class="week-title"><?= e($a['title']) ?></span>
              <span class="chip type-<?= e(strtolower($a['type_code'])) ?>"><?= e($a['type_name']) ?></span>
              <?php if ($a['groups']): ?><span class="week-trainers"><?php foreach ($a['groups'] as $g): ?><span><?= group_chip($g) ?></span><?php endforeach; ?></span><?php endif; ?>
              <?php if ($a['location']): ?><span class="muted small"><?= e($a['location']) ?></span><?php endif; ?>
            </a>
          <?php endforeach; ?>
        </section>
      <?php endfor; ?>
    </div>
    <?php if (!$byDay): ?><p class="muted">Geen activiteiten deze week.</p><?php endif; ?>
  <?php elseif (!$upcoming): ?>
    <p class="muted">Geen geplande activiteiten.</p>
  <?php else: ?>
    <ul class="list">
      <?php foreach ($upcoming as $a): ?>
        <li>
          <a href="<?= e(url('activity', ['id' => $a['id']])) ?>">
            <?php if (!empty($a['mine'])): ?><span class="chip mine-chip"><?= $a['type_code'] === 'TRAINING' ? 'Jij geeft training' : 'Jij gaat mee' ?></span><?php endif; ?>
            <span class="chip type-<?= e(strtolower($a['type_code'])) ?>"><?= e($a['type_name']) ?></span>
            <strong><?= e($a['title']) ?></strong>
          </a>
          <span class="muted"><?= e(fmt_dt($a['starts_at'])) ?><?= $a['location'] ? ' · ' . e($a['location']) : '' ?></span>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>
<?php endif; ?>

<?php if (Auth::can('activities.write')): ?>
  <p><a class="btn" href="<?= e(url('activity_form')) ?>">+ Nieuwe activiteit</a></p>
<?php endif; ?>
