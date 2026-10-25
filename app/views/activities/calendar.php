<?php
$daysInMonth = (int) $first->format('t');
$offset = (int) $first->format('N') - 1; // maandag = 0
$todayStr = today();
?>
<div class="row-between">
  <h1>Kalender</h1>
  <?php if (Auth::can('activities.write')): ?>
    <a class="btn" href="<?= e(url('activity_form')) ?>">+ Nieuwe activiteit</a>
  <?php endif; ?>
</div>

<?php $keep = array_filter(['cat' => $cat, 'g' => $gid ?: '']); ?>
<form class="filters" method="get" action="">
  <input type="hidden" name="r" value="calendar">
  <input type="hidden" name="m" value="<?= e($first->format('Y-m')) ?>">
  <?= group_filter_fields($cat, $gid, $groupOptions) ?>
  <button class="btn btn-small" type="submit">Filteren</button>
  <?php if ($cat !== '' || $gid): ?><a href="<?= e(url('calendar', ['m' => $first->format('Y-m')])) ?>">Wis filter</a><?php endif; ?>
</form>
<?php if ($cat !== '' || $gid): ?>
  <p class="muted small">Gefilterd op groep. Activiteiten voor de hele club blijven altijd zichtbaar.</p>
<?php endif; ?>

<div class="cal-nav">
  <a class="btn btn-small btn-ghost" href="<?= e(url('calendar', ['m' => $prev] + $keep)) ?>">← Vorige</a>
  <strong class="cal-title"><?= e(ucfirst($monthLabel)) ?></strong>
  <a class="btn btn-small btn-ghost" href="<?= e(url('calendar', ['m' => $next] + $keep)) ?>">Volgende →</a>
</div>

<div class="legend">
  <span class="chip type-training">Training</span>
  <span class="chip type-competition">Wedstrijd</span>
  <span class="chip type-club">Clubactiviteit</span>
</div>

<div class="cal-grid" role="grid">
  <?php foreach (['Ma', 'Di', 'Wo', 'Do', 'Vr', 'Za', 'Zo'] as $d): ?>
    <div class="cal-head"><?= $d ?></div>
  <?php endforeach; ?>

  <?php for ($i = 0; $i < $offset; $i++): ?><div class="cal-day empty"></div><?php endfor; ?>

  <?php for ($day = 1; $day <= $daysInMonth; $day++):
      $date = $first->format('Y-m-') . sprintf('%02d', $day);
      $events = $byDay[$date] ?? [];
      $dt = new DateTime($date);
      $classes = 'cal-day' . ($events ? ' has-events' : '') . ($date === $todayStr ? ' is-today' : ''); ?>
    <div class="<?= $classes ?>">
      <div class="cal-num">
        <span class="cal-dayname"><?= DAYS_NL_SHORT[(int) $dt->format('N')] ?></span> <?= $day ?>
        <?php if (Auth::can('activities.write')): ?>
          <a class="cal-add" href="<?= e(url('activity_form', ['date' => $date])) ?>" title="Activiteit toevoegen op deze dag" aria-label="Activiteit toevoegen op <?= e(fmt_date($date)) ?>">+</a>
        <?php endif; ?>
      </div>
      <?php foreach ($events as $ev): ?>
        <a class="cal-event type-<?= e(strtolower($ev['type_code'])) ?> <?= $ev['status'] === 'CANCELLED' ? 'cancelled' : '' ?>"
           href="<?= e(url('activity', ['id' => $ev['id']])) ?>">
          <span class="time"><?= e(fmt_time($ev['starts_at'])) ?></span> <?= e($ev['title']) ?>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endfor; ?>
</div>

<?php if (!$byDay): ?>
  <p class="muted">Geen activiteiten in deze maand.</p>
<?php endif; ?>
