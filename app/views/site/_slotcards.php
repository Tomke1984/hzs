<?php /** @var array $groups  naam => ['series' => [...], 'trainers' => [...]]; @var string $code */ ?>
<div class="timetable">
<?php foreach ($groups as $groupName => $g): ?>
  <div class="slotcard <?= $code === 'COMPETITION' ? 'competition' : '' ?>">
    <h3><?= e($groupName) ?></h3>
    <?php if (!empty($g['trainers'])): ?><p class="trainers"><?= count($g['trainers']) > 1 ? 'Trainers' : 'Trainer' ?>: <?= e(implode(', ', $g['trainers'])) ?></p><?php endif; ?>
    <?php foreach ($g['series'] as $s): ?>
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
