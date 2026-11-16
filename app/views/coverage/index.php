<div class="row-between page-head">
  <h1>Trainersplanning</h1>
  <details class="more-menu">
    <summary class="btn btn-ghost" aria-label="Meer acties">⋯</summary>
    <div class="more-list">
      <a href="<?= e(url('coverage_bulk_form')) ?>">Meerdere trainingen tegelijk…</a>
      <?php if ($canManage): ?>
        <a href="<?= e(url('coverage_settings')) ?>">Vaste trainers en instellingen</a>
        <form method="post" action="<?= e(url('coverage_remind')) ?>" data-confirm="Nu een mail sturen naar de coördinator en alle competitietrainers voor elke training of wedstrijd in de komende <?= (int) $remindDays ?> dagen waar nog een trainer ontbreekt?">
          <?= csrf_field() ?><button type="submit">Nu mail sturen</button>
        </form>
      <?php endif; ?>
    </div>
  </details>
</div>
<details class="howto">
  <summary>ⓘ Hoe werkt dit?</summary>
  <p>De trainers van een groep staan automatisch op elke training van die groep. Kan je niet? Meld je af en kies wie overneemt; anders zien de andere trainers meteen dat er iemand nodig is.
    Staat er binnen <?= (int) $remindDays ?> dagen nog een training of wedstrijd zonder genoeg trainers, dan krijgen de coördinator en alle competitietrainers automatisch een mail.
    Wie op een wedstrijd staat, staat op trainingen op hetzelfde moment automatisch <em>op wedstrijd</em>.</p>
  <p>Via <strong>⋯</strong> rechtsboven plan je één trainer in één keer in of af voor meerdere trainingen (bv. alle dinsdagen overnemen of een vakantie doorgeven).</p>
</details>

<?php if ($noCompGroups): ?>
  <div class="alert alert-info">Er is nog geen actieve competitiegroep. Zet bij een groep de afdeling op <em>Competitie</em> om ze hier te zien.</div>
<?php endif; ?>

<?php if ($shortages): ?>
<section class="card cover-problems">
  <h2><span class="short-badge">!</span> Trainer gezocht <span class="badge"><?= count($shortages) ?></span> <span class="muted small">komende <?= Coverage::SIGNAL_DAYS ?> dagen</span></h2>
  <ul class="list">
    <?php foreach ($shortages as $p): ?>
      <li><a href="<?= e(url('activity', ['id' => $p['id']])) ?>#trainers"><strong><?= e(fmt_date($p['starts_at'])) ?> <?= e(fmt_time($p['starts_at'])) ?></strong> · <?= e($p['title']) ?></a>
        <span class="muted"><?= e($p['gnames']) ?> · <?= (int) $p['present'] ?> van <?= (int) $p['required'] ?> trainer(s)</span></li>
    <?php endforeach; ?>
  </ul>
</section>
<?php elseif (!$noCompGroups): ?>
  <p class="ok-line">✓ Geen tekorten in de komende <?= Coverage::SIGNAL_DAYS ?> dagen.<?php if ($nextShort): ?>
    Volgend tekort: <a href="<?= e(url('activity', ['id' => $nextShort['id']])) ?>#trainers"><?= e(fmt_date($nextShort['starts_at'])) ?> – <?= e($nextShort['title']) ?></a>.<?php endif; ?></p>
<?php endif; ?>

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
  <div class="segmented" role="group" aria-label="Weergave">
    <a data-keep-scroll href="<?= e(url('coverage', $q(['view' => 'week', 'weeks' => '']))) ?>"<?= $mode === 'week' ? ' aria-current="page"' : '' ?>>Week</a>
    <a data-keep-scroll href="<?= e(url('coverage', $q(['view' => 'list', 'w' => '']))) ?>"<?= $mode === 'list' ? ' aria-current="page"' : '' ?>>Lijst</a>
  </div>
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
