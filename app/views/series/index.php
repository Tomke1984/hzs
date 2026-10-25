<div class="row-between">
  <h1>Trainingsreeksen</h1>
  <span class="actions-inline">
    <a class="btn btn-ghost" href="<?= e(url('series_week')) ?>">Weekoverzicht zwemschool</a>
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
<?php if (!empty($unassigned) && $cat === '' && !$gid): ?>
<section class="card" id="niet-ingedeeld">
  <h2>Nog niet ingedeeld <span class="muted small">(<?= count($unassigned) ?>)</span></h2>
  <p class="muted small">Zwemschoolleden zonder zwemblok staan op geen enkele aanwezigheidslijst. Kies hier meteen een blok.</p>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Lid</th><th>Groep</th><th>Blok</th></tr></thead>
    <tbody>
    <?php foreach ($unassigned as $u): ?>
      <tr>
        <td><a href="<?= e(url('member', ['id' => $u['id']])) ?>"><?= e($u['last_name'] . ' ' . $u['first_name']) ?></a></td>
        <td><?= group_chip($u['group_name']) ?></td>
        <td>
        <?php if ($u['blocks']): ?>
          <form method="post" action="<?= e(url('series_member_add')) ?>" class="assign-form">
            <?= csrf_field() ?><input type="hidden" name="member_id" value="<?= (int) $u['id'] ?>"><input type="hidden" name="back" value="?r=series">
            <select name="series_id" aria-label="Blok voor <?= e($u['first_name'] . ' ' . $u['last_name']) ?>">
              <?php foreach ($u['blocks'] as $b): ?><option value="<?= (int) $b['id'] ?>"><?= e($b['label']) ?></option><?php endforeach; ?>
            </select>
            <button class="btn" type="submit">Indelen</button>
          </form>
        <?php else: ?><span class="muted small">Geen blok voor deze groep: maak er een.</span><?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</section>
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
// Competitie: per groep. Zwemschool: per weekdag (een reeks met meerdere weekdagen staat bij elke dag).
$cats = array_keys(group_categories());
$catOf = array_column(Database::all('SELECT name, category FROM `groups`'), 'category', 'name');
$sections = []; // [cat => [sleutel => ['label'=>html, 'order'=>, 'list'=>[]]]]
foreach ($series as $s) {
    $first = $s['groups'][0] ?? 'Zonder groep';
    $c = $catOf[$first] ?? '';
    if ($c === 'SWIM_SCHOOL' && $s['slots']) {
        foreach ($s['slots'] as $sl) {
            $d = (int) $sl['weekday'];
            $sections[$c][$d] ??= ['label' => e(ucfirst(SeriesService::DAYS[$d])), 'order' => $d, 'list' => []];
            $sections[$c][$d]['list'][$s['id']] = $s + ['sort' => $sl['start_time']];
        }
    } else {
        $sections[$c]['g' . $first] ??= ['label' => group_chip($first), 'order' => $first, 'list' => []];
        $sections[$c]['g' . $first]['list'][$s['id']] = $s + ['sort' => ''];
    }
}
uksort($sections, function ($a, $b) use ($cats) {
    $ca = array_search($a, $cats, true); $cb = array_search($b, $cats, true);
    return ($ca === false ? 99 : $ca) <=> ($cb === false ? 99 : $cb);
});
?>
<?php foreach ($sections as $cat2 => $secs):
    uasort($secs, fn($x, $y) => $x['order'] <=> $y['order']);
    $catIds = [];
    foreach ($secs as $sec) { foreach ($sec['list'] as $sid => $_) { $catIds[$sid] = true; } }
    $catCount = count($catIds); ?>
<h2 class="cat-banner cat-banner-<?= e(strtolower($cat2 ?: 'none')) ?>"><?= e($cat2 !== '' ? group_category_label($cat2) : 'Zonder categorie') ?> <span class="small">(<?= $catCount ?> <?= $catCount === 1 ? 'reeks' : 'reeksen' ?>)</span></h2>
<?php foreach ($secs as $sec):
    $list = array_values($sec['list']);
    usort($list, fn($x, $y) => [$x['sort'], $x['title']] <=> [$y['sort'], $y['title']]); ?>
<h3 class="section-title"><?= $sec['label'] ?> <span class="muted small">(<?= count($list) ?>)</span></h3>
<div class="cards">
<?php foreach ($list as $s): ?>
  <div class="card <?= (int) $s['is_active'] ? '' : 'is-off' ?>">
    <a class="card-link" href="<?= e(url('series_show', ['id' => $s['id']])) ?>">
    <h2><?= e($s['title']) ?> <?php if (!(int) $s['is_active']): ?><span class="chip off">Gestopt</span><?php endif; ?>
      <?php if ($s['end_date'] < today()): ?><span class="chip off">Verlopen</span><?php endif; ?></h2>
    <?php if ($cat2 === 'COMPETITION'): ?>
      <?php if (count($s['groups']) > 1): ?><p class="small">Ook: <?= e(implode(', ', array_slice($s['groups'], 1))) ?></p><?php endif; ?>
    <?php else: ?>
      <p class="small"><?= implode(', ', array_map('group_chip', $s['groups'])) ?></p>
    <?php endif; ?>
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
<?php endforeach; ?>
