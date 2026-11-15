<?php
/**
 * Compacte weeknavigatie: ‹ 9–15 nov › (+ "Naar nu" als je weggebladerd bent).
 * Verwacht: $monday (Y-m-d), $offset (int), $weekUrl (fn(int $offset): string).
 */
?>
<div class="week-nav">
  <a class="week-arrow" href="<?= e($weekUrl($offset - 1)) ?>" aria-label="Vorige week" title="Vorige week">‹</a>
  <strong class="week-range"><?= e(fmt_week_range($monday)) ?></strong>
  <a class="week-arrow" href="<?= e($weekUrl($offset + 1)) ?>" aria-label="Volgende week" title="Volgende week">›</a>
  <?php if ($offset !== 0): ?><a class="week-now" href="<?= e($weekUrl(0)) ?>">Naar nu</a><?php endif; ?>
</div>
