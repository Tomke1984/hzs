<h1>Exporteren</h1>
<p class="muted">Download gegevens als CSV, om te openen in Excel. Noodcontacten en medische opmerkingen zitten nooit in een export. Elke export komt in de auditlog.</p>
<?php $gopts = function () use ($groups) { $h = '<option value="0">Alle groepen</option>'; foreach ($groups as $g) { $h .= '<option value="' . (int) $g['id'] . '">' . e($g['name']) . '</option>'; } return $h; }; ?>

<section class="card">
  <h2>Ledenlijst</h2>
  <form class="filters" method="get" action="<?= e(url('export_csv')) ?>">
    <input type="hidden" name="what" value="members">
    <select name="status" aria-label="Status"><option value="ACTIVE">Actieve leden</option><option value="INACTIVE">Inactieve leden</option><option value="ALL">Alle leden</option></select>
    <select name="g" aria-label="Groep"><?= $gopts() ?></select>
    <button class="btn btn-small" type="submit">Downloaden</button>
  </form>
  <p class="muted small">Naam, geboortedatum, contactgegevens, groep en ouders/voogden.</p>
</section>

<section class="card">
  <h2>Aanwezigheden</h2>
  <form class="filters" method="get" action="<?= e(url('export_csv')) ?>">
    <input type="hidden" name="what" value="attendance">
    <label class="small">Van <input type="date" name="from" value="<?= e($from) ?>"></label>
    <label class="small">Tot <input type="date" name="to" value="<?= e($to) ?>"></label>
    <select name="g" aria-label="Groep"><?= $gopts() ?></select>
    <button class="btn btn-small" type="submit">Downloaden</button>
  </form>
</section>

<section class="card">
  <h2>Aanmeldingen</h2>
  <p>
    <a class="btn btn-small btn-ghost" href="<?= e(url('export_csv', ['what' => 'registrations'])) ?>">Inschrijvingen</a>
    <a class="btn btn-small btn-ghost" href="<?= e(url('export_csv', ['what' => 'swimtests'])) ?>">Zwemtest-aanvragen</a>
    <a class="btn btn-small btn-ghost" href="<?= e(url('export_csv', ['what' => 'subscribers'])) ?>">Nieuwsbriefabonnees</a>
  </p>
</section>

<?php if (Auth::can('fees.manage')): ?>
<section class="card">
  <h2>Lidgeld</h2>
  <?php if ($seasons): ?>
  <form class="filters" method="get" action="<?= e(url('export_csv')) ?>">
    <input type="hidden" name="what" value="dues">
    <select name="season" aria-label="Seizoen"><?php foreach ($seasons as $s): ?><option value="<?= (int) $s['id'] ?>"><?= e($s['name']) ?></option><?php endforeach; ?></select>
    <button class="btn btn-small" type="submit">Downloaden</button>
  </form>
  <?php else: ?><p class="muted">Nog geen seizoenen.</p><?php endif; ?>
</section>
<?php endif; ?>
