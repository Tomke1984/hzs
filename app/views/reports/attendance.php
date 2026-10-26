<div class="row-between">
  <h1>Aanwezigheidsrapport</h1>
  <?php if (Auth::can('export.data')): ?>
    <a class="btn btn-ghost" href="<?= e(url('export_csv', ['what' => 'attendance', 'from' => $from, 'to' => $to, 'g' => $gid])) ?>">Exporteren (CSV)</a>
  <?php endif; ?>
</div>
<p class="muted">Per lid hoe vaak aanwezig of afwezig op de geregistreerde trainingen. Enkel registraties tellen mee; “onbekend” wordt genegeerd.</p>

<form class="filters" method="get" action="">
  <label class="small">Van <input type="date" name="from" value="<?= e($from) ?>"></label>
  <label class="small">Tot <input type="date" name="to" value="<?= e($to) ?>"></label>
  <?= group_filter_fields($cat, $gid, $groupOptions) ?>
  <select name="sort" aria-label="Sorteren">
    <option value="name" <?= $sort === 'name' ? 'selected' : '' ?>>Op naam</option>
    <option value="pct" <?= $sort === 'pct' ? 'selected' : '' ?>>Laagste aanwezigheid eerst</option>
  </select>
  <button class="btn btn-small" type="submit">Tonen</button>
</form>

<?php if ($unfilled): ?>
<section class="card">
  <h2>Nog in te vullen</h2>
  <p class="muted small">Trainingen van de laatste 3 weken zonder geregistreerde aanwezigheid.</p>
  <ul class="list">
    <?php foreach ($unfilled as $a): ?>
      <li><a href="<?= e(url('activity', ['id' => $a['id']])) ?>"><strong><?= e($a['title']) ?></strong></a> <span class="muted"><?= e(fmt_dt($a['starts_at'])) ?></span></li>
    <?php endforeach; ?>
  </ul>
</section>
<?php endif; ?>

<?php if (!$members): ?>
  <p class="card muted">Geen geregistreerde aanwezigheden in deze periode.</p>
<?php else: ?>
<?php if (count($byGroup) > 1): ?>
<section class="card">
  <h2>Per groep</h2>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Groep</th><th>Leden</th><th>Aanwezig</th><th>Afwezig</th><th>Aanwezigheid</th></tr></thead>
    <tbody>
    <?php foreach ($byGroup as $name => $g): $reg = $g['present'] + $g['absent']; ?>
      <tr><td><?= group_chip($name) ?></td><td><?= (int) $g['members'] ?></td><td><?= (int) $g['present'] ?></td><td><?= (int) $g['absent'] ?></td>
        <td><?= $reg ? (int) round($g['present'] * 100 / $reg) . '%' : '—' ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</section>
<?php endif; ?>

<div class="table-wrap">
<table class="table">
  <thead><tr><th>Lid</th><th>Groep</th><th>Aanwezig</th><th>Afwezig</th><th>%</th><th>Laatst aanwezig</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($members as $m): ?>
    <tr>
      <td><a href="<?= e(url('member', ['id' => $m['id']])) ?>"><?= e($m['name']) ?></a></td>
      <td><?= group_chip((string) $m['group']) ?></td>
      <td><?= (int) $m['present'] ?></td><td><?= (int) $m['absent'] ?></td>
      <td><?= $m['pct'] === null ? '—' : (int) $m['pct'] . '%' ?>
        <?php if ($m['pct'] !== null): ?><progress class="share-track" max="100" value="<?= (int) $m['pct'] ?>" aria-label="aanwezigheid"></progress><?php endif; ?></td>
      <td><?= $m['last'] ? e(fmt_date($m['last'])) : '—' ?></td>
      <td><?php if ($m['flag']): ?><span class="chip warn"><?= e($m['flag']) ?></span><?php endif; ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php endif; ?>
