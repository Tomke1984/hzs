<div class="row-between">
  <h1>Zwemtest</h1>
</div>
<p class="muted">Aanvragen voor de zwemtest van de zwemschool, via <code><?= e(Invites::baseUrl()) ?>/zwemtest</code>. Op de wachtlijst staan de aanvragen in volgorde van bevestiging. Na de test zet je een kind door naar een gewone inschrijving.</p>

<nav class="tabs" aria-label="Status">
  <?php foreach (SwimTestController::STATUSES as $code => $label): ?>
    <a href="<?= e(url('swimtests', ['s' => $code])) ?>" <?= $s === $code ? 'aria-current="page"' : '' ?>><?= e($label) ?> <span class="chip <?= $code === 'WAITING' && $counts[$code] ? 'warn' : '' ?>"><?= (int) $counts[$code] ?></span></a>
  <?php endforeach; ?>
</nav>
<?php if ($unconfirmed): ?>
  <p class="muted small"><?= (int) $unconfirmed ?> aanvra<?= $unconfirmed === 1 ? 'ag wacht' : 'gen wachten' ?> nog op bevestiging van het e-mailadres door de ouder.</p>
<?php endif; ?>

<?php if ($followUp): ?>
<section class="card">
  <h2>Opvolging nodig <span class="chip warn"><?= count($followUp) ?></span></h2>
  <p class="muted small">Uitgenodigd of getest, maar al een tijd zonder vervolg.</p>
  <ul class="list">
    <?php foreach (array_slice($followUp, 0, 8) as $f): $d = (int) floor((time() - strtotime($f['status_changed_at'] ?: $f['confirmed_at'] ?: $f['created_at'])) / 86400); ?>
      <li><a href="<?= e(url('swimtest_show', ['id' => $f['id']])) ?>"><strong><?= e($f['child_last_name'] . ' ' . $f['child_first_name']) ?></strong></a>
        <span class="muted"><?= e(SwimTestController::STATUSES[$f['status']]) ?> sinds <?= $d ?> dagen<?= $f['reminder_count'] ? ' · ' . (int) $f['reminder_count'] . ' herinnering(en)' : '' ?></span></li>
    <?php endforeach; ?>
  </ul>
</section>
<?php endif; ?>

<?php if (!$rows): ?>
  <p class="card muted">Geen aanvragen met status “<?= e(mb_strtolower(SwimTestController::STATUSES[$s])) ?>”.</p>
<?php else: ?>
<div class="table-wrap">
<table class="table">
  <thead><tr><?php if ($s === 'WAITING'): ?><th>#</th><?php endif; ?><th>Kind</th><th>Diploma</th><th>Contact</th><th>Aangemeld</th><th>In deze status</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($rows as $i => $r): $age = (new DateTimeImmutable($r['child_birth_date']))->diff(new DateTimeImmutable(today()))->y; ?>
    <tr>
      <?php if ($s === 'WAITING'): ?><td><?= $i + 1 ?></td><?php endif; ?>
      <td><a href="<?= e(url('swimtest_show', ['id' => $r['id']])) ?>"><?= e($r['child_last_name']) ?> <?= e($r['child_first_name']) ?></a> <span class="muted small">(<?= (int) $age ?> jaar)</span></td>
      <td><?= e(SwimTestController::DIPLOMAS[$r['diploma']] ?? $r['diploma']) ?></td>
      <td><?= e($r['contact_name']) ?><br><span class="muted small"><?= e($r['contact_email']) ?></span></td>
      <td><?= e(fmt_dt($r['confirmed_at'])) ?></td>
      <?php $since = (int) floor((time() - strtotime($r['status_changed_at'] ?: $r['confirmed_at'])) / 86400); $over = isset(SwimTestController::FOLLOW_UP_DAYS[$r['status']]) && $since > SwimTestController::FOLLOW_UP_DAYS[$r['status']]; ?>
      <td><span class="<?= $over ? 'chip warn' : 'muted' ?>"><?= $since ?> d</span><?= $r['reminder_count'] ? ' <span class="muted small">' . (int) $r['reminder_count'] . '× herinnerd</span>' : '' ?></td>
      <td class="right">
        <?php if (SwimTestController::canRemind($r)): ?>
        <form class="inline-form" method="post" action="<?= e(url('swimtest_remind')) ?>" data-confirm="Een herinnering mailen naar <?= e($r['contact_email']) ?>?">
          <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><input type="hidden" name="return" value="list">
          <button class="btn btn-small btn-ghost" type="submit">Herinnering</button>
        </form>
        <?php endif; ?>
        <a class="btn btn-small btn-ghost" href="<?= e(url('swimtest_show', ['id' => $r['id']])) ?>">Openen</a></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php endif; ?>
