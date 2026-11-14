<h1>Reeksen importeren</h1>
<p class="muted">Eén regel per trainingsmoment: <code>Groep;Dag;Begin;Einde;Titel;Locatie;Vaste trainers</code>. Plakken uit Excel (tabs) of met <code>|</code> kan ook.
  Titel, locatie en trainers zijn optioneel. Regels met dezelfde groep en titel worden samen één reeks (bv. <em>Zwemtraining</em> op ma, wo en za); een droogtraining krijgt best een eigen titel.
  Trainers zoeken we op naam of voornaam bij de competitietrainers en worden de vaste trainers van dat moment in de trainersplanning.</p>

<form method="post" action="<?= e(url('series_import')) ?>" class="card form">
  <?= csrf_field() ?>
  <div class="row-between">
    <h2>1. Uurrooster</h2>
    <a class="btn btn-small btn-ghost" href="<?= e(url('series_import', ['voorbeeld' => 'khzs'])) ?>">Uurrooster van khzs.be invullen</a>
  </div>
  <textarea name="text" rows="14" class="mono" spellcheck="false" placeholder="Kern;maandag;18:00;20:00;Zwemtraining;;Lander, Antoinette&#10;Kern;dinsdag;17:30-18:30;Droogtraining"><?= e($text) ?></textarea>
  <p class="muted small">Groepen in de app: <?= $groups ? e(implode(', ', $groups)) : 'nog geen' ?>. Een naam als “Competitie 3 (Jorien)” vindt ook de groep “Competitie 3”.</p>

  <h2>2. Periode</h2>
  <div class="grid2">
    <label>Vanaf <input type="date" name="start_date" value="<?= e($start) ?>" required></label>
    <label>Tot en met <input type="date" name="end_date" value="<?= e($end) ?>" required></label>
  </div>

  <div class="actions">
    <button class="btn btn-ghost" type="submit" name="do" value="preview">Voorbeeld bekijken</button>
    <?php if ($parsed && !$parsed['errors'] && $parsed['series']): ?>
      <button class="btn" type="submit" name="do" value="create">Reeksen aanmaken (<?= count(array_filter($parsed['series'], fn($s) => !$s['exists'])) ?>)</button>
    <?php endif; ?>
  </div>
</form>

<?php if ($parsed): ?>
<section class="card" id="voorbeeld">
  <h2>Voorbeeld</h2>
  <?php if ($parsed['errors']): ?>
    <div class="alert alert-error"><strong>Los dit eerst op:</strong><ul><?php foreach ($parsed['errors'] as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
  <?php endif; ?>
  <?php foreach ($parsed['warnings'] as $w): ?><div class="alert alert-warning"><?= e($w) ?></div><?php endforeach; ?>
  <?php if (!$parsed['series']): ?>
    <p class="muted">Nog niets om aan te maken.</p>
  <?php else: ?>
    <p class="muted small"><?= (int) $parsed['lines'] ?> trainingsmomenten → <?= count($parsed['series']) ?> reeksen, van <?= e(fmt_date($start)) ?> tot <?= e(fmt_date($end)) ?>.</p>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>Groep</th><th>Reeks</th><th>Momenten</th><th>Vaste trainers</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($parsed['series'] as $s): ?>
        <tr class="<?= $s['exists'] ? 'is-off' : '' ?>">
          <td><?= group_chip($s['group']) ?></td>
          <td><strong><?= e($s['title']) ?></strong></td>
          <td><?php foreach ($s['slots'] as $sl): ?><div><?= e(ucfirst(SeriesService::DAYS[$sl['weekday']])) ?> <?= e($sl['start_time']) ?>–<?= e($sl['end_time']) ?><?= $sl['location'] !== '' ? ' · ' . e($sl['location']) : '' ?></div><?php endforeach; ?></td>
          <td><?php foreach ($s['slots'] as $sl): ?><div class="small"><?= $sl['trainers'] ? e(implode(', ', $sl['trainers'])) : '<span class="muted">—</span>' ?></div><?php endforeach; ?></td>
          <td><?= $s['exists'] ? '<span class="chip off">Bestaat al: overslaan</span>' : '<span class="chip ok">Nieuw</span>' ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</section>
<?php endif; ?>
