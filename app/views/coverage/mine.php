<?php
$mode = 'mine';
$hasMine = true;
require __DIR__ . '/_head.php';
$when = fn(array $a) => fmt_day_month($a['starts_at']);
$hours = fn(array $a) => fmt_time($a['starts_at']) . '–' . fmt_time($a['ends_at']);
$meetIcon = '<span class="meet-icon" aria-label="Wedstrijd" title="Wedstrijd">🏆</span> ';
?>
<p class="mine-intro muted">Je trainingen en wedstrijden van de komende <?= (int) $weeksAhead ?> weken. Kan je niet? Tik op <strong>Ik kan niet</strong>.
  <a class="mine-away" href="<?= e(url('coverage_away')) ?>">Ik ben een tijd weg…</a></p>

<?php if (!$mine): ?>
  <p class="muted">Je staat de komende <?= (int) $weeksAhead ?> weken nergens ingepland.</p>
<?php endif; ?>

<div class="mine-list">
<?php foreach ($mine as $a): $me = $a['me']; ?>
  <article class="mine-card<?= $me['absent'] ? ' is-off' : '' ?>" id="a<?= (int) $a['id'] ?>">
    <div class="mine-top"><strong><?= e($when($a)) ?></strong><span class="mine-hours"><?= e($hours($a)) ?></span></div>
    <div class="mine-what"><?= $a['type_code'] === 'COMPETITION' ? $meetIcon : '' ?><a href="<?= e(url('activity', ['id' => $a['id']])) ?>"><?= e($a['title']) ?></a><?php if ($a['location']): ?> <span class="muted">· <?= e($a['location']) ?></span><?php endif; ?></div>
    <div class="mine-with small muted"><?= $a['others'] ? 'Met ' . e(implode(', ', $a['others'])) : ($a['type_code'] === 'COMPETITION' ? 'Nog niemand anders mee' : 'Geen andere trainer') ?></div>

    <?php if (!$me['absent']): ?>
      <div class="mine-status">
        <span class="mine-ok">✓ Jij <?= $a['type_code'] === 'COMPETITION' ? 'gaat mee' : 'komt' ?></span>
        <?php if ($a['ends_at'] > now()): ?>
          <a class="btn btn-ghost mine-cannot" data-sheet="sheet-<?= (int) $a['id'] ?>" href="<?= e(url('coverage', ['view' => 'mine', 'cannot' => $a['id']])) ?>#sheet-<?= (int) $a['id'] ?>">Ik kan niet</a>
        <?php endif; ?>
      </div>
      <dialog class="sheet" id="sheet-<?= (int) $a['id'] ?>" aria-labelledby="sheet-t<?= (int) $a['id'] ?>"<?= $cannot === (int) $a['id'] ? ' open' : '' ?>>
        <form method="post" action="<?= e(url('coverage_action')) ?>" class="sheet-body">
          <?= csrf_field() ?>
          <input type="hidden" name="activity_id" value="<?= (int) $a['id'] ?>">
          <input type="hidden" name="do" value="absent">
          <input type="hidden" name="user_id" value="<?= (int) $uid ?>">
          <input type="hidden" name="back" value="mine">
          <div class="sheet-head">
            <div><span class="small muted"><?= e($when($a)) ?> · <?= e($a['title']) ?></span>
              <h2 id="sheet-t<?= (int) $a['id'] ?>">Ik kan niet</h2></div>
            <a class="sheet-close" data-sheet-close href="<?= e(url('coverage', ['view' => 'mine'])) ?>" aria-label="Sluiten">✕</a>
          </div>
          <fieldset class="sheet-reasons">
            <legend>Waarom? <span class="muted">(je collega's zien dit, mag leeg)</span></legend>
            <?php foreach (['Ziek', 'Werk', 'Op reis', 'Familie'] as $r): ?>
              <label class="pill"><input type="radio" name="reason" value="<?= e($r) ?>"> <?= e($r) ?></label>
            <?php endforeach; ?>
            <input type="text" name="note" maxlength="200" placeholder="Of typ zelf een reden" aria-label="Andere reden">
          </fieldset>
          <?php if ($a['shortIfOff']): ?>
            <p class="sheet-label">Wie neemt over?</p>
            <?php foreach ($a['free'] as $pid => $pname): ?>
              <button class="sheet-person" type="submit" name="replacement" value="<?= (int) $pid ?>"><span class="avatar" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($pname, 0, 1))) ?></span><span><?= e($pname) ?><span class="small ok-text">Vrij op dat moment</span></span></button>
            <?php endforeach; ?>
            <button class="btn sheet-main" type="submit" name="replacement" value="ask">Vraag het aan de groep</button>
            <p class="small muted"><?= $a['free'] ? e(nl_list(array_values($a['free']))) . ' krijg' . (count($a['free']) === 1 ? 't' : 'en') . ' een mail met een knop <em>Ik neem over</em>. Wie eerst klikt, staat ingepland.' : 'Niemand is op dat moment vrij. De coördinator krijgt bericht.' ?></p>
          <?php else: ?>
            <p class="small ok-text">Er blijven genoeg trainers: je hoeft geen vervanger te zoeken.</p>
            <button class="btn sheet-main" type="submit" name="replacement" value="">Afmelden</button>
          <?php endif; ?>
        </form>
      </dialog>
    <?php elseif ($me['auto']): ?>
      <div class="mine-status"><span class="chip warn">Op wedstrijd</span> <span class="small muted"><?= e(substr((string) $me['note'], 14)) ?></span></div>
    <?php else: ?>
      <div class="mine-status">
        <span class="small"><strong>Je bent afgemeld</strong><?= $me['note'] ? ' · ' . e($me['note']) : '' ?><br>
          <span class="<?= $a['missing'] > 0 ? 'text-bad' : 'ok-text' ?>"><?= $a['missing'] > 0 ? 'Er wordt nog een vervanger gezocht.' : 'Er zijn genoeg trainers.' ?></span></span>
        <?php if ($a['ends_at'] > now()): ?>
          <form method="post" action="<?= e(url('coverage_action')) ?>"><?= csrf_field() ?><input type="hidden" name="activity_id" value="<?= (int) $a['id'] ?>"><input type="hidden" name="do" value="back"><input type="hidden" name="user_id" value="<?= (int) $uid ?>"><input type="hidden" name="back" value="mine"><button class="link-btn" type="submit">Toch wel</button></form>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </article>
<?php endforeach; ?>
</div>

<h2 class="mine-help-title">Hier kan je helpen</h2>
<?php if (!$help): ?>
  <p class="muted">Nergens een trainer nodig op een moment dat jij vrij bent.</p>
<?php else: ?>
<div class="mine-list">
  <?php foreach ($help as $a): ?>
    <article class="mine-card is-help" id="a<?= (int) $a['id'] ?>">
      <div class="mine-top"><strong><?= e($when($a)) ?></strong><span class="mine-hours"><?= e($hours($a)) ?></span></div>
      <div class="mine-what"><?= $a['type_code'] === 'COMPETITION' ? $meetIcon : '' ?><a href="<?= e(url('activity', ['id' => $a['id']])) ?>"><?= e($a['title']) ?></a><?php if ($a['gnames'] !== ''): ?> <span class="muted">· <?= e($a['gnames']) ?></span><?php endif; ?></div>
      <div class="mine-status">
        <span class="text-bad"><strong>Nog <?= (int) $a['missing'] ?> trainer<?= $a['missing'] > 1 ? 's' : '' ?> nodig</strong></span>
        <form method="post" action="<?= e(url('coverage_action')) ?>"><?= csrf_field() ?><input type="hidden" name="activity_id" value="<?= (int) $a['id'] ?>"><input type="hidden" name="do" value="claim"><input type="hidden" name="back" value="mine">
          <button class="btn" type="submit"><?= $a['type_code'] === 'COMPETITION' ? 'Ik ga mee' : 'Ik neem over' ?></button></form>
      </div>
    </article>
  <?php endforeach; ?>
</div>
<?php endif; ?>
<p class="small muted">Enkel trainingen en wedstrijden op momenten dat jij vrij bent. Alles zien? Kies <a href="<?= e(url('coverage', ['view' => 'week'])) ?>">Week</a> of <a href="<?= e(url('coverage', ['view' => 'grid'])) ?>">Raster</a>.</p>
