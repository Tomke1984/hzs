<div class="card narrow take-card">
  <img class="login-logo" src="<?= e(asset('logo.png')) ?>" alt="">
  <?php if ($a): ?>
    <h1><?= $done ? 'Bedankt' . ($offer ? ', ' . e(explode(' ', trim((string) $offer['display_name']))[0]) : '') . '!' : ($a['type_code'] === 'COMPETITION' ? 'Kan jij mee?' : 'Kan jij overnemen?') ?></h1>
    <div class="take-what">
      <strong><?= $a['type_code'] === 'COMPETITION' ? '🏆 ' : '' ?><?= e($a['title']) ?></strong>
      <span><?= e(fmt_day_month($a['starts_at'])) ?> · <?= e(fmt_time($a['starts_at'])) ?>–<?= e(fmt_time($a['ends_at'])) ?></span>
      <?php if ($a['location']): ?><span><?= e($a['location']) ?></span><?php endif; ?>
      <?php if ($a['gnames'] !== ''): ?><span class="muted"><?= e($a['gnames']) ?></span><?php endif; ?>
    </div>
  <?php else: ?>
    <h1>Kan jij overnemen?</h1>
  <?php endif; ?>

  <?php if ($done): ?>
    <p class="alert alert-success">Je staat ingepland. Wie het vroeg, kreeg een bericht. De training staat ook in je kalender in de app.</p>
  <?php elseif ($problem !== null): ?>
    <p class="alert alert-info"><?= e($problem) ?></p>
  <?php else: ?>
    <p>Er <?= $a['missing'] === 1 ? 'is nog 1 trainer' : 'zijn nog ' . (int) $a['missing'] . ' trainers' ?> nodig.</p>
    <form method="post" action="<?= e(url('coverage_take')) ?>">
      <?= csrf_field() ?><input type="hidden" name="t" value="<?= e($token) ?>">
      <button class="btn take-btn" type="submit"><?= $a['type_code'] === 'COMPETITION' ? 'Ik ga mee' : 'Ik neem over' ?></button>
    </form>
    <p class="small muted">Kan je niet? Dan hoef je niets te doen.</p>
  <?php endif; ?>
  <p class="small"><a href="<?= e(url('coverage')) ?>">Naar de trainersplanning</a></p>
</div>
