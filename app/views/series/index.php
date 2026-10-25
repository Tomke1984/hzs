<div class="row-between">
  <h1>Trainingsreeksen</h1>
  <span class="actions-inline">
    <a class="btn btn-ghost" href="<?= e(url('series_bulk')) ?>">Schrappen / verplaatsen</a>
    <a class="btn" href="<?= e(url('series_form')) ?>">+ Nieuwe reeks</a>
  </span>
</div>
<form class="filters" method="get" action="">
  <?= group_filter_fields($cat, $gid, $groupOptions) ?>
  <button class="btn btn-small" type="submit">Filteren</button>
  <?php if ($cat !== '' || $gid): ?><a href="<?= e(url('series')) ?>">Wis filter</a><?php endif; ?>
</form>
<p class="muted">Een reeks maakt automatisch wekelijkse trainingen aan in de kalender voor één of meer groepen.</p>
<?php if (!$series && ($cat !== '' || $gid)): ?>
  <p class="card muted">Geen reeksen voor deze groep.</p>
<?php elseif (!$series): ?>
  <p class="card muted">Nog geen reeksen. Maak er één aan, bv. “Zwemtraining” voor een groep op di, wo en za.</p>
<?php endif; ?>
<?php $back = '?' . http_build_query(array_filter(['r' => 'series', 'cat' => $cat, 'g' => $gid])); ?>
<?php if ($series): ?>
<form class="card inline-form-card" method="post" action="<?= e(url('series_extend')) ?>" data-confirm="Alle getoonde reeksen verlengen?">
  <?= csrf_field() ?><input type="hidden" name="back" value="<?= e($back) ?>">
  <?php foreach ($series as $s): ?><input type="hidden" name="ids[]" value="<?= (int) $s['id'] ?>"><?php endforeach; ?>
  <label>Alle getoonde reeksen verlengen tot <input type="date" name="end_date" value="<?= e(date('Y-m-d', strtotime((max(array_column($series, 'end_date')) ?: today()) . ' +1 year'))) ?>" required></label>
  <button class="btn" type="submit">Verlengen</button>
</form>
<div class="actions-inline">
  <?php foreach ([1 => ['Alles op de website zetten', 'Alle getoonde reeksen op de website zetten?'], 0 => ['Alles van de website halen', 'Alle getoonde reeksen van de website halen?']] as $val => [$label, $ask]): ?>
  <form method="post" action="<?= e(url('series_public')) ?>" data-confirm="<?= e($ask) ?>">
    <?= csrf_field() ?><input type="hidden" name="public" value="<?= $val ?>"><input type="hidden" name="back" value="<?= e($back) ?>">
    <?php foreach ($series as $s): ?><input type="hidden" name="ids[]" value="<?= (int) $s['id'] ?>"><?php endforeach; ?>
    <button class="btn btn-small btn-ghost" type="submit"><?= e($label) ?></button>
  </form>
  <?php endforeach; ?>
</div>
<?php endif; ?>
<?php
// Per groep: onder de eerste groep van de reeks; groepen eerst op categorie, dan op naam.
$cats = array_keys(group_categories());
$catOf = array_column(Database::all('SELECT name, category FROM `groups`'), 'category', 'name');
$byGroup = [];
foreach ($series as $s) { $byGroup[$s['groups'][0] ?? 'Zonder groep'][] = $s; }
uksort($byGroup, function ($a, $b) use ($cats, $catOf) {
    $ca = array_search($catOf[$a] ?? '', $cats, true); $cb = array_search($catOf[$b] ?? '', $cats, true);
    return [$ca === false ? 99 : $ca, $a] <=> [$cb === false ? 99 : $cb, $b];
});
?>
<?php foreach ($byGroup as $gname => $list): ?>
<h2 class="section-title"><?= group_chip($gname) ?> <span class="muted small">(<?= count($list) ?>)</span></h2>
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
    <form method="post" action="<?= e(url('series_public')) ?>">
      <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $s['id'] ?>"><input type="hidden" name="back" value="<?= e($back) ?>">
      <?php if ((int) $s['is_public']): ?>
        <input type="hidden" name="public" value="0"><span class="chip ok">Op website</span> <button class="btn btn-small btn-ghost" type="submit">Verbergen</button>
      <?php else: ?>
        <input type="hidden" name="public" value="1"><span class="chip warn">Niet op website</span> <button class="btn btn-small" type="submit">Tonen</button>
      <?php endif; ?>
    </form>
  </div>
<?php endforeach; ?>
</div>
<?php endforeach; ?>
