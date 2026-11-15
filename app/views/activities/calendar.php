<?php
$daysInMonth = (int) $first->format('t');
$offset = (int) $first->format('N') - 1; // maandag = 0
$todayStr = today();
?>
<div class="row-between page-head">
  <h1>Kalender</h1>
  <?php if (Auth::can('activities.write')): ?>
    <span class="head-actions">
      <a class="btn" href="<?= e(url('activity_form')) ?>">+ Nieuw</a>
      <details class="more-menu">
        <summary class="btn btn-ghost" aria-label="Meer acties">⋯</summary>
        <div class="more-list">
          <a href="<?= e(url('series_bulk')) ?>">Afgelasten / verplaatsen…</a>
          <a href="<?= e(url('series')) ?>">Trainingsreeksen</a>
        </div>
      </details>
    </span>
  <?php endif; ?>
</div>

<?php $keep = array_filter(['g' => $gid ?: '', 't' => $type]); ?>
<form class="filters" data-auto method="get" action="">
  <input type="hidden" name="m" value="<?= e($first->format('Y-m')) ?>">
  <?= group_filter_fields($cat, $gid, $groupOptions) ?>
  <select name="t" aria-label="Soort activiteit">
    <option value="">Alle soorten</option>
    <?php foreach ($types as $ty): ?><option value="<?= e($ty['code']) ?>"<?= $type === $ty['code'] ? ' selected' : '' ?>><?= e($ty['name']) ?></option><?php endforeach; ?>
  </select>
  <button class="btn btn-small" type="submit">Filteren</button>
  <?php if ($gid || $type !== ''): ?><a href="<?= e(url('calendar', ['m' => $first->format('Y-m')])) ?>">Wis filter</a><?php endif; ?>
</form>
<?php if ($gid): ?>
  <p class="muted small">Gefilterd op groep. Activiteiten voor de hele club blijven altijd zichtbaar.</p>
<?php endif; ?>

<div class="week-nav month-nav">
  <a class="week-arrow" href="<?= e(url('calendar', ['m' => $prev] + $keep)) ?>" aria-label="Vorige maand" title="Vorige maand">‹</a>
  <strong class="week-range"><?= e(ucfirst($monthLabel)) ?></strong>
  <a class="week-arrow" href="<?= e(url('calendar', ['m' => $next] + $keep)) ?>" aria-label="Volgende maand" title="Volgende maand">›</a>
  <?php if ($first->format('Y-m') !== date('Y-m')): ?><a class="week-now" href="<?= e(url('calendar', $keep)) ?>">Naar nu</a><?php endif; ?>
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
        <?php endif; ?>
      </div>
      <?php foreach ($events as $ev): ?>
        <<?= $ev['open'] ? 'a' : 'span' ?> class="cal-event type-<?= e(strtolower($ev['type_code'])) ?> g-<?= e($ev['gcolor']) ?> <?= $ev['type_code'] === 'COMPETITION' ? 'is-comp' : '' ?> <?= $ev['mine'] ? '' : 'other' ?> <?= $ev['status'] === 'CANCELLED' ? 'cancelled' : '' ?> <?= !empty($ev['short']) ? 'short' : '' ?>"
           title="<?= e((!empty($ev['short']) ? 'Trainer gezocht (' . $ev['short'] . ') · ' : '') . $ev['type_name'] . ' · ' . $ev['gnames'] . ' · ' . ($ev['mine'] ? '' : 'Niet voor jouw groep. ') . ($ev['status'] === 'CANCELLED' ? 'Afgelast' . ($ev['status_note'] ? ': ' . $ev['status_note'] : '') : ($ev['location'] ?? ''))) ?>"
           <?= $ev['open'] ? 'href="' . e(url('activity', ['id' => $ev['id']])) . '"' : '' ?>>
          <?php if (!empty($ev['short'])): ?><span class="short-flag" aria-label="Trainer gezocht">!</span><?php endif; ?><span class="time"><?= e(fmt_time($ev['starts_at'])) ?></span> <?= $ev['type_code'] === 'COMPETITION' ? '<span class="meet-icon" aria-hidden="true">🏆</span>' : '' ?><?= e($ev['title']) ?><?php if ($ev['gnames'] !== ''): ?><span class="grp"><?= e($ev['gnames']) ?></span><?php endif; ?>
        </<?= $ev['open'] ? 'a' : 'span' ?>>
      <?php endforeach; ?>
    </div>
  <?php endfor; ?>
</div>

<?php if (!empty($legend)): ?>
<ul class="cal-legend" aria-label="Legende">
  <?php foreach ($legend as [$lname, $lcol]): ?><li><span class="swatch g-<?= e($lcol) ?>"></span><?= e($lname) ?></li><?php endforeach; ?>
  <?php if (!empty($hasShort)): ?><li><span class="short-flag short-flag-legend" aria-hidden="true">!</span>Trainer gezocht · <a href="<?= e(url('coverage', ['show' => 'problems', 'view' => 'list'])) ?>">trainersplanning</a></li><?php endif; ?>
</ul>
<?php endif; ?>

<?php if (!$byDay): ?>
  <p class="muted">Geen activiteiten in deze maand.</p>
<?php endif; ?>
