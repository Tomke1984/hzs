<h1>Trainersplanning: vaste trainers en instellingen</h1>
<p class="muted">De vaste trainers van een blok staan automatisch op elke training van dat blok. Meldt een vaste trainer zich af, dan toont de trainersplanning een tekort
  en krijgen de coördinator en de competitietrainers <?= (int) $remindDays ?> dagen vooraf een mail als er nog niemand overgenomen heeft.</p>

<?php if (!$pool): ?>
  <div class="alert alert-warning">Er zijn nog geen competitietrainers. Koppel trainers aan een competitiegroep (Gebruikers → trainer → groepen) of geef iemand de rol <em>Coördinator competitie</em>.</div>
<?php endif; ?>

<form method="post" action="<?= e(url('coverage_settings_save')) ?>" class="form">
  <?= csrf_field() ?>
  <section class="card">
    <h2>Minimum aantal trainers per groep</h2>
    <?php if (!$groups): ?>
      <p class="muted">Geen actieve competitiegroepen.</p>
    <?php else: ?>
    <div class="cover-grid">
      <?php foreach ($groups as $g): ?>
        <label><?= group_chip($g['name']) ?>
          <input type="number" name="min[<?= (int) $g['id'] ?>]" min="1" max="9" value="<?= e((string) ($g['min_trainers'] ?? '')) ?>" placeholder="1" inputmode="numeric">
        </label>
      <?php endforeach; ?>
    </div>
    <p class="muted small">Leeg = 1. Bij een wedstrijd kan je per wedstrijd een ander aantal ingeven (Activiteit wijzigen).</p>
    <?php endif; ?>
  </section>

  <section class="card">
    <h2>Vaste trainers per blok</h2>
    <?php if (!$series): ?>
      <p class="muted">Er zijn geen lopende trainingsreeksen voor de competitie. Maak ze aan via <a href="<?= e(url('series')) ?>">Trainingsreeksen</a>.</p>
    <?php endif; ?>
    <?php foreach ($series as $s): ?>
      <fieldset class="cover-block">
        <legend><?= e($s['title']) ?> <span class="muted small"><?= e($s['groups']) ?> · tot <?= e(fmt_date($s['end_date'])) ?></span></legend>
        <?php foreach ($s['slots'] as $sl): $wd = (int) $sl['weekday']; $chosen = $s['trainers'][$wd] ?? []; ?>
          <div class="cover-slot">
            <strong><?= e(ucfirst(SeriesService::DAYS[$wd])) ?> <?= e($sl['start_time']) ?>–<?= e($sl['end_time']) ?></strong>
            <input type="hidden" name="slot[<?= (int) $s['id'] ?>][<?= $wd ?>][]" value="">
            <span class="cover-checks">
              <?php foreach ($pool as $pid => $pname): ?>
                <label class="check"><input type="checkbox" name="slot[<?= (int) $s['id'] ?>][<?= $wd ?>][]" value="<?= (int) $pid ?>"<?= in_array($pid, $chosen, true) ? ' checked' : '' ?>> <?= e($pname) ?></label>
              <?php endforeach; ?>
              <?php if (!$chosen): ?><span class="chip bad">Geen vaste trainer</span><?php endif; ?>
            </span>
          </div>
        <?php endforeach; ?>
      </fieldset>
    <?php endforeach; ?>
  </section>
  <div class="actions"><button class="btn" type="submit">Opslaan</button></div>
</form>

<section class="card" id="delen">
  <h2>Weekplanning delen met ouders</h2>
  <p class="muted">Een geheime link per groep met de trainingen en wedstrijden van de komende 3 weken (ook geschrapte trainingen), zonder namen van trainers of leden.
    Handig om in de oudergroep te delen zolang niet iedereen een account heeft. Iedereen met de link kan de planning zien; maak een nieuwe link als hij ongewenst rondgaat.</p>
  <?php foreach ($groups as $g): ?>
    <div class="cover-share">
      <?= group_chip($g['name']) ?>
      <?php if ($g['share_token']): $link = $base . '/' . url('weekplanning', ['t' => $g['share_token']]); ?>
        <input type="text" readonly value="<?= e($link) ?>" aria-label="Deellink voor <?= e($g['name']) ?>" class="cover-link">
        <a class="btn btn-small btn-ghost" href="<?= e($link) ?>" target="_blank" rel="noopener">Bekijken</a>
        <form method="post" action="<?= e(url('coverage_share')) ?>" data-confirm="Nieuwe link maken? De huidige link van <?= e($g['name']) ?> werkt dan niet meer."><?= csrf_field() ?><input type="hidden" name="group_id" value="<?= (int) $g['id'] ?>"><button class="btn btn-small btn-ghost" type="submit">Nieuwe link</button></form>
        <form method="post" action="<?= e(url('coverage_share')) ?>"><?= csrf_field() ?><input type="hidden" name="group_id" value="<?= (int) $g['id'] ?>"><input type="hidden" name="do" value="remove"><button class="btn btn-small btn-ghost" type="submit">Uitzetten</button></form>
      <?php else: ?>
        <form method="post" action="<?= e(url('coverage_share')) ?>"><?= csrf_field() ?><input type="hidden" name="group_id" value="<?= (int) $g['id'] ?>"><button class="btn btn-small" type="submit">Deellink maken</button></form>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</section>
