<div class="banner on-deep"><div class="wrap"><h1>Trainingsuren</h1></div></div>
<div class="wrap section">
<?php if (!$byCat): ?><p class="empty">De trainingsuren worden binnenkort bekendgemaakt.</p><?php endif; ?>
<?php foreach (group_categories() as $code => $label): if (empty($byCat[$code])) { continue; } ?>
  <section class="cat-block">
  <h2 class="cat-title"><?= e($label) ?></h2>
  <?php $groups = $byCat[$code]; require __DIR__ . '/_slotcards.php'; ?>
  </section>
<?php endforeach; ?>
</div>
