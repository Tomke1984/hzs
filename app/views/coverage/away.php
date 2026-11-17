<div class="row-between page-head">
  <h1>Ik ben een tijd weg</h1>
</div>
<p class="muted">Geef op wanneer je er niet bent. Je ziet meteen welke trainingen en wedstrijden dat raakt.</p>
<form class="card away-form" method="get" action="<?= e(url('coverage_away')) ?>">
  <div class="away-dates">
    <label>Van <input type="date" name="from" value="<?= e($from) ?>" required></label>
    <label>Tot en met <input type="date" name="to" value="<?= e($to) ?>" required></label>
  </div>
  <label>Reden <span class="muted">(optioneel, je collega's zien dit)</span>
    <input type="text" name="reason" maxlength="200" value="<?= e($reason) ?>" placeholder="bv. Op reis"></label>
  <button class="btn<?= $shown ? ' btn-ghost' : '' ?>" type="submit"><?= $shown ? 'Opnieuw bekijken' : 'Toon wat dit raakt' ?></button>
</form>

<?php if ($shown): ?>
  <?php if (!$rows): ?>
    <p class="muted">Je staat in die periode nergens ingepland. Er is niets te doen.</p>
  <?php else: ?>
  <form method="post" action="<?= e(url('coverage_away_save')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="reason" value="<?= e($reason) ?>">
    <h2>Dit raakt <?= count($rows) ?> training<?= count($rows) === 1 ? '' : 'en' ?> of wedstrijd<?= count($rows) === 1 ? '' : 'en' ?></h2>
    <div class="mine-list">
    <?php foreach ($rows as $a): ?>
      <article class="mine-card">
        <input type="hidden" name="ids[]" value="<?= (int) $a['id'] ?>">
        <div class="mine-top"><strong><?= e(fmt_day_month($a['starts_at'])) ?></strong><span class="mine-hours"><?= e(fmt_time($a['starts_at'])) ?>–<?= e(fmt_time($a['ends_at'])) ?></span></div>
        <div class="mine-what"><?= $a['type_code'] === 'COMPETITION' ? '🏆 ' : '' ?><?= e($a['title']) ?><?= $a['gnames'] !== '' ? ' <span class="muted">· ' . e($a['gnames']) . '</span>' : '' ?></div>
        <?php if (!$a['short']): ?>
          <p class="small ok-text">Er blijven genoeg trainers: geen vervanger nodig.</p>
        <?php else: ?>
          <fieldset class="pills"><legend class="small">Wie neemt over?</legend>
            <?php foreach ($a['free'] as $pid => $pname): ?>
              <label class="pill"><input type="radio" name="rep[<?= (int) $a['id'] ?>]" value="<?= (int) $pid ?>"> <?= e($pname) ?></label>
            <?php endforeach; ?>
            <label class="pill"><input type="radio" name="rep[<?= (int) $a['id'] ?>]" value="ask" checked> Vraag de groep</label>
          </fieldset>
        <?php endif; ?>
      </article>
    <?php endforeach; ?>
    </div>
    <div class="actions"><button class="btn" type="submit">Afmelden voor <?= count($rows) ?> training<?= count($rows) === 1 ? '' : 'en' ?></button>
      <a class="cancel-link" href="<?= e(url('coverage', ['view' => 'mine'])) ?>">Annuleren</a></div>
    <p class="small muted">Bij <em>Vraag de groep</em> krijgen de collega's die vrij zijn een mail met een knop <em>Ik neem over</em>.</p>
  </form>
  <?php endif; ?>
<?php endif; ?>
