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
    <h2>Eerstvolgende activiteiten</h2>
    <a href="<?= e(url('calendar')) ?>">Hele kalender →</a>
  </div>
  <?php if (!$upcoming): ?>
    <p class="muted">Geen geplande activiteiten.</p>
  <?php else: ?>
    <ul class="list">
      <?php foreach ($upcoming as $a): ?>
        <li>
          <a href="<?= e(url('activity', ['id' => $a['id']])) ?>">
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
