<?php
/** Uitleg en het tekortsignaal (komende Coverage::SIGNAL_DAYS dagen). Verwacht: $remindDays, $noCompGroups, $shortages, $nextShort. */
?>
<details class="howto">
  <summary>ⓘ Hoe werkt dit?</summary>
  <p>De trainers van een groep staan automatisch op elke training van die groep. Kan je niet? Meld je af en kies wie overneemt; anders zien de andere trainers meteen dat er iemand nodig is.
    Staat er binnen <?= (int) $remindDays ?> dagen nog een training of wedstrijd zonder genoeg trainers, dan krijgen de coördinator en alle competitietrainers automatisch een mail.
    Wie op een wedstrijd staat, staat op trainingen op hetzelfde moment automatisch <em>op wedstrijd</em>.</p>
  <p>Kies <strong>Mijn trainingen</strong> om je snel af te melden, <strong>Raster</strong> om per groep en week te zien waar nog een trainer nodig is. Via <strong>⋯</strong> rechtsboven plan je één trainer in één keer in of af voor meerdere trainingen (bv. alle dinsdagen overnemen of een vakantie doorgeven).</p>
</details>

<?php if ($noCompGroups): ?>
  <div class="alert alert-info">Er is nog geen actieve competitiegroep. Zet bij een groep de afdeling op <em>Competitie</em> om ze hier te zien.</div>
<?php endif; ?>

<?php if ($shortages): ?>
<section class="card cover-problems">
  <h2><span class="short-badge">!</span> Trainer gezocht <span class="badge"><?= count($shortages) ?></span> <span class="muted small">komende <?= Coverage::SIGNAL_DAYS ?> dagen</span></h2>
  <ul class="list">
    <?php foreach ($shortages as $p): ?>
      <li><a href="<?= e(url('activity', ['id' => $p['id']])) ?>#trainers"><strong><?= e(fmt_date($p['starts_at'])) ?> <?= e(fmt_time($p['starts_at'])) ?></strong> · <?= e($p['title']) ?></a>
        <span class="muted"><?= e($p['gnames']) ?> · <?= (int) $p['present'] ?> van <?= (int) $p['required'] ?> trainer(s)</span></li>
    <?php endforeach; ?>
  </ul>
</section>
<?php elseif (!$noCompGroups): ?>
  <p class="ok-line">✓ Geen tekorten in de komende <?= Coverage::SIGNAL_DAYS ?> dagen.<?php if ($nextShort): ?>
    Volgend tekort: <a href="<?= e(url('activity', ['id' => $nextShort['id']])) ?>#trainers"><?= e(fmt_date($nextShort['starts_at'])) ?> – <?= e($nextShort['title']) ?></a>.<?php endif; ?></p>
<?php endif; ?>

