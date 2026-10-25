<div class="row-between">
  <h1>Trainingsreeksen</h1>
  <span class="actions-inline">
    <a class="btn btn-ghost" href="<?= e(url('series_bulk')) ?>">Schrappen / verplaatsen</a>
    <a class="btn" href="<?= e(url('series_form')) ?>">+ Nieuwe reeks</a>
  </span>
</div>
<?php if (!empty($importPending) && $cat === '' && !$gid): ?>
<div class="card">
  <p><strong>Trainingsuren van de competitie overnemen.</strong> Maakt de competitiegroepen (Competitie 3, Competitie 2, Beloften, Competitie 1 Provinciaal en Nationaal, Kern) aan als ze nog niet bestaan, met per groep een reeks <em>Zwemmen</em> en <em>Droogtraining</em> volgens de uren van khzs.be, zichtbaar op de website tot eind juni. Wat al bestaat, blijft ongemoeid (nu nog <?= (int) $importPending ?> reeksen te maken).</p>
  <form method="post" action="<?= e(url('series_import_competition')) ?>" data-confirm="De trainingsuren van de competitie nu aanmaken?">
    <?= csrf_field() ?>
    <button class="btn" type="submit">Competitie-uren overnemen</button>
  </form>
</div>
<?php endif; ?>
<form class="filters" method="get" action="">
  <input type="hidden" name="r" value="series">
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
