<div class="row-between">
  <h1>Trainersplanning competitie</h1>
  <?php if ($canManage): ?>
    <span class="actions-inline">
      <form method="post" action="<?= e(url('coverage_remind')) ?>" data-confirm="Nu een mail sturen naar de coördinator en alle competitietrainers voor elke training of wedstrijd in de komende <?= (int) $remindDays ?> dagen waar nog een trainer ontbreekt?">
        <?= csrf_field() ?><button class="btn btn-ghost" type="submit">Nu mail sturen</button>
      </form>
      <a class="btn" href="<?= e(url('coverage_settings')) ?>">Vaste trainers en instellingen</a>
    </span>
  <?php endif; ?>
</div>
<p><a class="btn btn-ghost" href="<?= e(url('coverage_bulk_form')) ?>">Meerdere trainingen tegelijk…</a>
  <span class="muted small">Eén trainer in één keer inplannen of afmelden, bv. alle dinsdagen overnemen of een vakantie doorgeven.</span></p>
<p class="muted">Wie geeft welke training en wie begeleidt welke wedstrijd. Kan je niet? Meld je af, dan zien de andere trainers meteen dat er iemand nodig is.
  Staat er binnen <?= (int) $remindDays ?> dagen nog een training of wedstrijd zonder genoeg trainers, dan krijgen de coördinator en alle competitietrainers automatisch een mail.</p>

<?php if ($noCompGroups): ?>
  <div class="alert alert-info">Er is nog geen actieve competitiegroep. Zet bij een groep de afdeling op <em>Competitie</em> om ze hier te zien.</div>
<?php endif; ?>

<?php if ($problems): ?>
<section class="card cover-problems">
  <h2>Trainer gezocht <span class="badge"><?= count($problems) ?></span></h2>
  <ul class="list">
    <?php foreach ($problems as $p): ?>
      <li><a href="<?= e(self_url()) ?>#a<?= (int) $p['id'] ?>"><strong><?= e(fmt_date($p['starts_at'])) ?> <?= e(fmt_time($p['starts_at'])) ?></strong> · <?= e($p['title']) ?></a>
        <span class="muted"><?= e($p['gnames']) ?> · <?= (int) $p['present'] ?> van <?= (int) $p['required'] ?> trainer(s)</span></li>
    <?php endforeach; ?>
  </ul>
</section>
<?php elseif (!$noCompGroups): ?>
  <div class="alert alert-success">Alle trainingen en wedstrijden in deze periode hebben genoeg trainers.</div>
<?php endif; ?>

<nav class="tabs" aria-label="Weergave">
  <?php foreach (['' => 'Alles', 'problems' => 'Enkel tekorten', 'mine' => 'Mijn planning'] as $k => $label): ?>
    <a href="<?= e(url('coverage', array_filter(['show' => $k, 'weeks' => $weeks === 4 ? '' : $weeks]))) ?>"<?= $show === $k ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
  <?php endforeach; ?>
</nav>
<form class="filters" method="get" action="<?= e(url('coverage')) ?>">
  <?php if ($show !== ''): ?><input type="hidden" name="show" value="<?= e($show) ?>"><?php endif; ?>
  <label>Periode
    <select name="weeks">
      <?php foreach ([1, 2, 4, 8] as $w): ?><option value="<?= $w ?>"<?= $weeks === $w ? ' selected' : '' ?>>komende <?= $w === 1 ? 'week' : "$w weken" ?></option><?php endforeach; ?>
    </select>
  </label>
  <button class="btn btn-small" type="submit">Tonen</button>
</form>


<?php if (!$byWeek): ?>
  <p class="muted"><?= $show === 'mine' ? 'Je staat in deze periode nergens ingepland.' : ($show === 'problems' ? 'Geen tekorten in deze periode.' : 'Geen trainingen of wedstrijden van de competitie in deze periode.') ?></p>
<?php endif; ?>

<?php $keep = array_filter(['weeks' => $weeks === 4 ? '' : $weeks, 'show' => $show]); $back = ''; ?>
<?php foreach ($byWeek as $monday => $items): ?>
  <h2 class="cover-week">Week van <?= e(fmt_date($monday)) ?></h2>
  <?php foreach ($items as $a): ?>
    <?php require __DIR__ . '/_card.php'; ?>
  <?php endforeach; ?>
<?php endforeach; ?>
