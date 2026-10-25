<h1>Welkom, <?= e(Auth::user()['display_name']) ?></h1>

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
