<div class="banner on-deep"><div class="wrap"><h1>Trainingsuren</h1></div></div>
<div class="wrap section">
<?php if (!$byCat): ?><p class="empty">De trainingsuren worden binnenkort bekendgemaakt.</p><?php endif; ?>
<?php foreach (group_categories() as $code => $label): if (empty($byCat[$code])) { continue; } ?>
  <section class="cat-block">
  <h2 class="cat-title"><?= e($label) ?></h2>
  <div class="timetable">
  <?php foreach ($byCat[$code] as $groupName => $list): ?>
    <div class="slotcard <?= $code === 'COMPETITION' ? 'competition' : '' ?>">
      <h3><?= e($groupName) ?></h3>
      <?php foreach ($list as $s): ?>
        <div class="series"><?= e($s['title']) ?></div>
        <dl>
        <?php foreach ($s['slots'] as $sl): ?>
          <dt><?= e(mb_substr(SeriesService::DAYS[(int) $sl['weekday']], 0, 2)) ?></dt>
          <dd><?= e($sl['start_time']) ?>–<?= e($sl['end_time']) ?><?php $loc = $sl['location'] ?: $s['location']; echo $loc ? ' <span class="muted">' . e($loc) . '</span>' : ''; ?></dd>
        <?php endforeach; ?>
        </dl>
      <?php endforeach; ?>
    </div>
  <?php endforeach; ?>
  </div>
  </section>
<?php endforeach; ?>
</div>
