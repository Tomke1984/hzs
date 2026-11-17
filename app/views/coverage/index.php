<?php require __DIR__ . '/_head.php'; ?>
<?php require __DIR__ . '/_signal.php'; ?>

<?php
  // Querystring om binnen dezelfde weergave te blijven
  $q = fn(array $over = []) => array_filter(array_merge(['view' => $mode, 'show' => $show, 'weeks' => $weeks === 4 ? '' : $weeks, 'w' => $offset ?: ''], $over), fn($v) => $v !== '' && $v !== null);
?>
<div class="cover-toolbar">
  <nav class="tabs" aria-label="Filter">
    <?php foreach (['' => 'Alles', 'problems' => 'Enkel tekorten', 'mine' => 'Mijn planning'] as $k => $label): ?>
      <a href="<?= e(url('coverage', $q(['show' => $k]))) ?>"<?= $show === $k ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
    <?php endforeach; ?>
  </nav>
</div>

<?php if ($mode === 'week'): ?>
  <?php $weekUrl = fn(int $o) => url('coverage', $q(['w' => $o ?: ''])); require APP_ROOT . '/app/views/_weeknav.php'; ?>
  <div class="week-grid">
    <?php for ($i = 0; $i < 7; $i++): $day = date('Y-m-d', strtotime("$monday +$i days")); $items = $byDay[$day] ?? []; ?>
      <section class="week-day<?= $day === today() ? ' is-today' : '' ?><?= $items ? ' has-events' : '' ?>">
        <h3 class="week-head"><?= e(fmt_day($day)) ?></h3>
        <?php if (!$items): ?><p class="muted small week-empty">—</p><?php endif; ?>
        <?php foreach ($items as $a): $past = $a['ends_at'] <= now(); $isMine = (bool) array_filter($a['trainers'], fn($t) => $t['id'] === $uid && !$t['absent']); ?>
          <a class="week-item type-<?= e(strtolower($a['type_code'])) ?><?= $a['missing'] > 0 && !$past ? ' is-short' : '' ?><?= $past ? ' is-past' : '' ?><?= $isMine ? ' is-mine' : '' ?>" href="<?= e(url('activity', ['id' => $a['id']])) ?>#trainers">
            <?php if ($isMine): ?><span class="chip mine-chip">Jij</span><?php endif; ?>
            <span class="week-time"><?= e(fmt_time($a['starts_at'])) ?>–<?= e(fmt_time($a['ends_at'])) ?></span>
            <span class="week-title"><?= $a['type_code'] === 'COMPETITION' ? '<span class="meet-icon" aria-label="Wedstrijd" title="Wedstrijd">🏆</span> ' : '' ?><?= e($a['title']) ?></span>
            <?php if ($a['missing'] > 0): ?>
              <span class="short-badge">! <?= $a['present'] === 0 ? 'Geen trainer' : 'Trainer gezocht' ?> · <?= (int) $a['present'] ?>/<?= (int) $a['required'] ?></span>
            <?php else: ?>
              <span class="chip ok">In orde · <?= (int) $a['present'] ?>/<?= (int) $a['required'] ?></span>
            <?php endif; ?>
            <?php if ($a['trainers']): ?>
              <span class="week-trainers">
                <?php foreach ($a['trainers'] as $t): ?>
                  <span class="<?= $t['absent'] ? 'is-absent' : '' ?><?= $t['id'] === $uid ? ' is-me' : '' ?>" title="<?= e($t['absent'] ? ($t['auto'] ? 'Op wedstrijd' : 'Afgemeld' . ($t['note'] ? ': ' . $t['note'] : '')) : 'Ingepland') ?>"><?= e($t['name']) ?><?= $t['absent'] ? ($t['auto'] ? ' (op wedstrijd)' : ' (afgemeld)') : '' ?></span>
                <?php endforeach; ?>
              </span>
            <?php endif; ?>
          </a>
        <?php endforeach; ?>
      </section>
    <?php endfor; ?>
  </div>
  <p class="muted small">Klik op een training of wedstrijd om je af te melden, over te nemen of iemand in te plannen.</p>
  <?php if (!$byDay): ?>
    <p class="muted"><?= $show === 'mine' ? 'Je staat deze week nergens ingepland.' : ($show === 'problems' ? 'Geen tekorten deze week.' : 'Geen trainingen of wedstrijden van de competitie deze week.') ?></p>
  <?php endif; ?>
<?php else: ?>
<form class="filters" data-auto method="get" action="<?= e(url('coverage')) ?>">
  <input type="hidden" name="view" value="list">
  <?php if ($show !== ''): ?><input type="hidden" name="show" value="<?= e($show) ?>"><?php endif; ?>
  <label>Periode
    <select name="weeks">
      <?php foreach ([1, 2, 4, 8] as $w): ?><option value="<?= $w ?>"<?= $weeks === $w ? ' selected' : '' ?>>komende <?= $w === 1 ? 'week' : "$w weken" ?></option><?php endforeach; ?>
    </select>
  </label>
  <button class="btn btn-small" type="submit">Tonen</button>
</form>

<?php if (!$byWeek): ?>
  <p class="muted"><?= $show === 'mine' ? 'Je staat in deze periode nergens ingepland.' : ($show === 'problems' ? 'Geen tekorten in deze periode.' : 'Geen trainingen of wedstrijden van de competitie in deze periode.') ?></p>
<?php endif; ?>

<?php $keep = array_filter(['weeks' => $weeks === 4 ? '' : $weeks, 'show' => $show]); $back = ''; ?>
<?php foreach ($byWeek as $mon => $items): ?>
  <h2 class="cover-week">Week van <?= e(fmt_date($mon)) ?></h2>
  <?php foreach ($items as $a): ?>
    <?php require __DIR__ . '/_card.php'; ?>
  <?php endforeach; ?>
<?php endforeach; ?>
<?php endif; ?>
