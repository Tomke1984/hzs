<h1>Trainingsuren</h1>
<?php if (!$byCat): ?><p class="card muted">De trainingsuren worden binnenkort bekendgemaakt.</p><?php endif; ?>
<?php foreach (group_categories() as $code => $label): if (empty($byCat[$code])) { continue; } ?>
  <h2 class="section-title"><?= e($label) ?></h2>
  <?php foreach ($byCat[$code] as $groupName => $list): ?>
    <div class="card cat-border-<?= e(strtolower($code)) ?>">
      <h3><?= e($groupName) ?></h3>
      <ul class="plain">
      <?php foreach ($list as $s): ?>
        <li><strong><?= e($s['title']) ?></strong>:
          <?php foreach ($s['slots'] as $sl): ?>
            <br><?= e(ucfirst(SeriesService::DAYS[(int) $sl['weekday']])) ?> <?= e($sl['start_time']) ?>–<?= e($sl['end_time']) ?><?php $loc = $sl['location'] ?: $s['location']; echo $loc ? ' <span class="muted">(' . e($loc) . ')</span>' : ''; ?>
          <?php endforeach; ?>
        </li>
      <?php endforeach; ?>
      </ul>
    </div>
  <?php endforeach; ?>
<?php endforeach; ?>
