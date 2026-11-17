<?php
/**
 * Kop van de trainersplanning: titel, menu ⋯ en de keuze van de weergave.
 * Verwacht: $mode ('mine', 'grid', 'week', 'list'), $canManage, $hasMine, $remindDays.
 */
?>
<div class="row-between page-head">
  <h1><?= $mode === 'mine' ? 'Mijn trainingen' : 'Trainersplanning' ?></h1>
  <details class="more-menu">
    <summary class="btn btn-ghost" aria-label="Meer acties">⋯</summary>
    <div class="more-list">
      <?php if ($hasMine): ?><a href="<?= e(url('coverage_away')) ?>">Ik ben een tijd weg…</a><?php endif; ?>
      <a href="<?= e(url('coverage_bulk_form')) ?>">Meerdere trainingen tegelijk…</a>
      <?php if ($canManage): ?>
        <a href="<?= e(url('coverage_settings')) ?>">Vaste trainers en instellingen</a>
        <form method="post" action="<?= e(url('coverage_remind')) ?>" data-confirm="Nu een mail sturen naar de coördinator en alle competitietrainers voor elke training of wedstrijd in de komende <?= (int) $remindDays ?> dagen waar nog een trainer ontbreekt?">
          <?= csrf_field() ?><button type="submit">Nu mail sturen</button>
        </form>
      <?php endif; ?>
    </div>
  </details>
</div>
<div class="segmented view-switch" role="group" aria-label="Weergave">
  <?php foreach (array_filter(['mine' => $hasMine ? 'Mijn trainingen' : null, 'grid' => 'Raster', 'week' => 'Week', 'list' => 'Lijst']) as $k => $label): ?>
    <a data-keep-scroll href="<?= e(url('coverage', ['view' => $k])) ?>"<?= $mode === $k ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
  <?php endforeach; ?>
</div>
