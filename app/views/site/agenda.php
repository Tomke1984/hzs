<div class="banner on-deep"><div class="wrap"><h1>Kalender</h1></div></div>
<div class="wrap section">
  <div class="list-head">
    <a class="btn btn-small btn-ghost" href="<?= e(url('agenda', ['m' => $prev])) ?>">Vorige maand</a>
    <strong><?= e($monthLabel) ?></strong>
    <a class="btn btn-small btn-ghost" href="<?= e(url('agenda', ['m' => $next])) ?>">Volgende maand</a>
  </div>
  <?php if (!$byDay): ?><p class="empty">Er zijn geen activiteiten in deze maand.</p><?php endif; ?>
  <?php foreach ($byDay as $day => $events): $dt = new DateTimeImmutable($day); ?>
    <section class="day">
      <div class="tiles">
      <?php foreach ($events as $ev): $c = $ev['status'] === 'CANCELLED'; ?>
        <div class="tile <?= $ev['type_code'] === 'COMPETITION' ? 'competition' : '' ?> <?= $c ? 'is-off' : '' ?>">
          <div class="date"><b><?= e($dt->format('j')) ?></b><i><?= e(mb_substr(MONTHS_NL[(int) $dt->format('n')], 0, 3)) ?></i></div>
          <div>
            <h3 class="<?= $c ? 'cancelled-text' : '' ?>"><?= e($ev['title']) ?> <span class="chip"><?= e($ev['type_name']) ?></span>
              <?php if ($c): ?><span class="chip off">Geschrapt<?= $ev['status_note'] ? ': ' . e($ev['status_note']) : '' ?></span><?php endif; ?></h3>
            <p><?= e(SeriesService::DAYS[(int) $dt->format('N')]) ?> · <?= e(fmt_time($ev['starts_at'])) ?>–<?= e(fmt_time($ev['ends_at'])) ?><?= $ev['location'] ? ' · ' . e($ev['location']) : '' ?></p>
            <?php if ($ev['description']): ?><p><?= nl2br(e($ev['description'])) ?></p><?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
      </div>
    </section>
  <?php endforeach; ?>
</div>
