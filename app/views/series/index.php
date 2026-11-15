<div class="row-between">
  <h1>Trainingsreeksen</h1>
  <span class="actions-inline">
    <a class="btn btn-ghost" href="<?= e(url('series_bulk')) ?>">Schrappen / verplaatsen</a>
    <a class="btn" href="<?= e(url('series_form')) ?>">+ Nieuwe reeks</a>
  </span>
</div>
<form class="filters" data-auto method="get" action="">
  <?= group_filter_fields($cat, $gid, $groupOptions) ?>
  <button class="btn btn-small" type="submit">Filteren</button>
  <?php if ($gid): ?><a href="<?= e(url('series')) ?>">Wis filter</a><?php endif; ?>
</form>
<p class="muted">Een reeks maakt automatisch wekelijkse trainingen aan in de kalender voor één of meer groepen.</p>
<?php if (!$series && $gid): ?>
  <p class="card muted">Geen reeksen voor deze groep.</p>
<?php elseif (!$series): ?>
  <p class="card muted">Nog geen reeksen. Maak er één aan, bv. “Zwemtraining” voor een groep op di, wo en za.</p>
<?php endif; ?>
<?php $back = '?' . http_build_query(array_filter(['r' => 'series', 'g' => $gid])); ?>
<?php if ($series): ?>
<form class="card inline-form-card" method="post" action="<?= e(url('series_extend')) ?>" data-confirm="Alle getoonde reeksen verlengen?">
  <?= csrf_field() ?><input type="hidden" name="back" value="<?= e($back) ?>">
  <?php foreach ($series as $s): ?><input type="hidden" name="ids[]" value="<?= (int) $s['id'] ?>"><?php endforeach; ?>
  <label>Alle getoonde reeksen verlengen tot <input type="date" name="end_date" value="<?= e(date('Y-m-d', strtotime((max(array_column($series, 'end_date')) ?: today()) . ' +1 year'))) ?>" required></label>
  <button class="btn" type="submit">Verlengen</button>
</form>
<?php endif; ?>
<?php
// Per groep (de eerste groep van de reeks).
$sections = [];
foreach ($series as $s) {
    $first = $s['groups'][0] ?? 'Zonder groep';
    $sections[$first][] = $s;
}
ksort($sections);
?>
<?php foreach ($sections as $gname => $list):
    usort($list, fn($x, $y) => $x['title'] <=> $y['title']); ?>
<h2 class="section-title"><?= $gname === 'Zonder groep' ? e($gname) : group_chip($gname) ?> <span class="muted small">(<?= count($list) ?>)</span></h2>
<div class="cards">
<?php foreach ($list as $s): ?>
  <div class="card <?= (int) $s['is_active'] ? '' : 'is-off' ?>">
    <a class="card-link" href="<?= e(url('series_show', ['id' => $s['id']])) ?>">
    <h2><?= e($s['title']) ?> <?php if (!(int) $s['is_active']): ?><span class="chip off">Gestopt</span><?php endif; ?>
      <?php if ($s['end_date'] < today()): ?><span class="chip off">Verlopen</span><?php endif; ?></h2>
    <?php if (count($s['groups']) > 1): ?><p class="small">Ook: <?= e(implode(', ', array_slice($s['groups'], 1))) ?></p><?php endif; ?>
    <p class="muted"><?= e(SeriesService::summary($s['slots'])) ?></p>
    <p class="muted small"><?= e(fmt_date($s['start_date'])) ?> – <?= e(fmt_date($s['end_date'])) ?> · <?= (int) $s['upcoming'] ?> komende trainingen</p>
    </a>
  </div>
<?php endforeach; ?>
</div>
<?php endforeach; ?>
