<div class="row-between">
  <h1>Trainingsreeksen</h1>
  <span class="actions-inline">
    <a class="btn btn-ghost" href="<?= e(url('series_bulk')) ?>">Schrappen / verplaatsen</a>
    <a class="btn" href="<?= e(url('series_form')) ?>">+ Nieuwe reeks</a>
  </span>
</div>
<p class="muted">Een reeks maakt automatisch wekelijkse trainingen aan in de kalender voor één of meer groepen.</p>
<?php if (!$series): ?>
  <p class="card muted">Nog geen reeksen. Maak er één aan, bv. “Zwemtraining” voor een groep op di, wo en za.</p>
<?php endif; ?>
<div class="cards">
<?php foreach ($series as $s): ?>
  <a class="card card-link <?= (int) $s['is_active'] ? '' : 'is-off' ?>" href="<?= e(url('series_show', ['id' => $s['id']])) ?>">
    <h2><?= e($s['title']) ?> <?php if (!(int) $s['is_active']): ?><span class="chip off">Gestopt</span><?php endif; ?></h2>
    <p><?= e(implode(', ', $s['groups'])) ?></p>
    <p class="muted"><?= e(SeriesService::summary($s['slots'])) ?></p>
    <p class="muted small"><?= e(fmt_date($s['start_date'])) ?> – <?= e(fmt_date($s['end_date'])) ?> · <?= (int) $s['upcoming'] ?> komende trainingen</p>
  </a>
<?php endforeach; ?>
</div>
