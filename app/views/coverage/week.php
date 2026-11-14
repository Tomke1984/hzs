<h1>Weekplanning <?= e($group['name']) ?></h1>
<p class="muted">Trainingen en wedstrijden van de komende 3 weken. Een geschrapte training staat doorstreept. Bijgewerkt op <?= e(fmt_dt(now())) ?>.</p>
<?php if (!$byWeek): ?><p class="muted">Er staat niets gepland in de komende 3 weken.</p><?php endif; ?>
<?php foreach ($byWeek as $monday => $events): ?>
  <h2 class="cover-week">Week van <?= e(fmt_date($monday)) ?></h2>
  <ul class="list card">
  <?php foreach ($events as $ev): $c = $ev['status'] === 'CANCELLED'; ?>
    <li class="<?= $c ? 'cancelled-text' : '' ?>">
      <strong><?= e(fmt_date($ev['starts_at'])) ?> · <?= e(fmt_time($ev['starts_at'])) ?>–<?= e(fmt_time($ev['ends_at'])) ?></strong>
      · <?= e($ev['title']) ?> <span class="chip type-<?= e(strtolower($ev['type_code'])) ?>"><?= e($ev['type_name']) ?></span>
      <?php if ($c): ?><span class="chip off">Geschrapt<?= $ev['status_note'] ? ': ' . e($ev['status_note']) : '' ?></span><?php endif; ?>
      <?php if ($ev['location']): ?><span class="muted small"><?= e($ev['location']) ?></span><?php endif; ?>
    </li>
  <?php endforeach; ?>
  </ul>
<?php endforeach; ?>
