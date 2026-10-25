<h1>Kalender</h1>
<div class="cal-nav">
  <a class="btn btn-small btn-ghost" href="<?= e(url('agenda', ['m' => $prev])) ?>">← Vorige</a>
  <strong class="cal-title"><?= e($monthLabel) ?></strong>
  <a class="btn btn-small btn-ghost" href="<?= e(url('agenda', ['m' => $next])) ?>">Volgende →</a>
</div>
<?php if (!$byDay): ?><p class="card muted">Geen activiteiten in deze maand.</p><?php endif; ?>
<?php foreach ($byDay as $day => $events): $dt = new DateTimeImmutable($day); ?>
  <h2 class="section-title"><?= e(DAYS_NL_SHORT[(int) $dt->format('N')]) ?> <?= e(fmt_date($day)) ?></h2>
  <?php foreach ($events as $ev): $c = $ev['status'] === 'CANCELLED'; ?>
    <div class="card event <?= $c ? 'is-off' : '' ?>">
      <span class="chip type-<?= e(strtolower($ev['type_code'])) ?>"><?= e($ev['type_name']) ?></span>
      <strong class="<?= $c ? 'cancelled-text' : '' ?>"><?= e($ev['title']) ?></strong>
      <?php if ($c): ?><span class="chip off">Geschrapt<?= $ev['status_note'] ? ': ' . e($ev['status_note']) : '' ?></span><?php endif; ?>
      <div class="muted"><?= e(fmt_time($ev['starts_at'])) ?>–<?= e(fmt_time($ev['ends_at'])) ?><?= $ev['location'] ? ' · ' . e($ev['location']) : '' ?></div>
      <?php if ($ev['description']): ?><p><?= nl2br(e($ev['description'])) ?></p><?php endif; ?>
    </div>
  <?php endforeach; ?>
<?php endforeach; ?>
