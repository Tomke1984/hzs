<div class="banner on-deep"><div class="wrap"><h1>Weekplanning <?= e($group['name']) ?></h1></div></div>
<div class="wrap section">
  <p class="muted">Trainingen en wedstrijden van de komende 3 weken. Een geschrapte training staat doorstreept. Bijgewerkt op <?= e(fmt_dt(now())) ?>.</p>
  <?php if (!$byWeek): ?><p class="empty">Er staat niets gepland in de komende 3 weken.</p><?php endif; ?>
  <?php foreach ($byWeek as $monday => $events): ?>
    <h2 class="week-head">Week van <?= e(fmt_date($monday)) ?></h2>
    <div class="tiles">
    <?php foreach ($events as $ev): $c = $ev['status'] === 'CANCELLED'; $dt = new DateTimeImmutable(substr($ev['starts_at'], 0, 10)); ?>
      <div class="tile <?= $ev['type_code'] === 'COMPETITION' ? 'competition' : '' ?> <?= $c ? 'is-off' : '' ?>">
        <div class="date"><b><?= e($dt->format('j')) ?></b><i><?= e(mb_substr(MONTHS_NL[(int) $dt->format('n')], 0, 3)) ?></i></div>
        <div>
          <h3 class="<?= $c ? 'cancelled-text' : '' ?>"><?= e($ev['title']) ?> <span class="chip"><?= e($ev['type_name']) ?></span>
            <?php if ($c): ?><span class="chip off">Geschrapt<?= $ev['status_note'] ? ': ' . e($ev['status_note']) : '' ?></span><?php endif; ?></h3>
          <p><?= e(SeriesService::DAYS[(int) $dt->format('N')]) ?> · <?= e(fmt_time($ev['starts_at'])) ?>–<?= e(fmt_time($ev['ends_at'])) ?><?= $ev['location'] ? ' · ' . e($ev['location']) : '' ?></p>
        </div>
      </div>
    <?php endforeach; ?>
    </div>
  <?php endforeach; ?>
</div>
