<?php
$daysInMonth = (int) $first->format('t');
$offset = (int) $first->format('N') - 1; // maandag = 0
$todayStr = today();
?>
<div class="row-between">
  <h1>Kalender</h1>
  <?php if (Auth::can('activities.write')): ?>
    <span class="actions-inline">
      <a class="btn btn-ghost" href="<?= e(url('series_week')) ?>">Weekoverzicht zwemschool</a>
      <a class="btn btn-ghost" href="<?= e(url('series')) ?>">Trainingsreeksen</a>
      <a class="btn" href="<?= e(url('activity_form')) ?>">+ Nieuwe activiteit</a>
    </span>
  <?php endif; ?>
</div>

<?php $keep = array_filter(['cat' => $cat, 'g' => $gid ?: '']); ?>
<form class="filters" method="get" action="">
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

<div class="legend"><span class="chip off">Grijs: wedstrijd voor een andere groep</span></div>

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
          <?php if ($events): ?><a class="cal-add cal-skip" href="<?= e(url('series_bulk', ['date' => $date])) ?>" title="Alle activiteiten van deze dag schrappen" aria-label="Alle activiteiten van <?= e(fmt_date($date)) ?> schrappen">&times;</a><?php endif; ?>
        <?php endif; ?>
      </div>
      <?php foreach ($events as $ev): ?>
        <<?= $ev['open'] ? 'a' : 'span' ?> class="cal-event type-<?= e(strtolower($ev['type_code'])) ?> g-<?= e($ev['gcolor']) ?> <?= $ev['type_code'] === 'COMPETITION' ? 'is-comp' : '' ?> <?= $ev['mine'] ? '' : 'other' ?> <?= $ev['status'] === 'CANCELLED' ? 'cancelled' : '' ?>"
           title="<?= e($ev['type_name'] . ' · ' . $ev['gnames'] . ' · ' . ($ev['mine'] ? '' : 'Niet voor jouw groep. ') . ($ev['status'] === 'CANCELLED' ? 'Geschrapt' . ($ev['status_note'] ? ': ' . $ev['status_note'] : '') : ($ev['location'] ?? ''))) ?>"
           <?= $ev['open'] ? 'href="' . e(url('activity', ['id' => $ev['id']])) . '"' : '' ?>>
          <span class="time"><?= e(fmt_time($ev['starts_at'])) ?></span> <?= e($ev['title']) ?><?php if ($ev['gnames'] !== ''): ?><span class="grp"><?= e($ev['gnames']) ?></span><?php endif; ?>
        </<?= $ev['open'] ? 'a' : 'span' ?>>
      <?php endforeach; ?>
    </div>
  <?php endfor; ?>
</div>

<?php if (!empty($legend)): ?>
<ul class="cal-legend" aria-label="Legende">
  <?php foreach ($legend as [$lname, $lcol]): ?><li><span class="swatch g-<?= e($lcol) ?>"></span><?= e($lname) ?></li><?php endforeach; ?>
  <li><span class="swatch is-comp-sw"></span>Wedstrijd (gele rand)</li>
</ul>
<?php endif; ?>

<?php if (!$byDay): ?>
  <p class="muted">Geen activiteiten in deze maand.</p>
<?php endif; ?>
