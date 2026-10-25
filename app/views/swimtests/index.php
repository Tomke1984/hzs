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

<?php if (!$rows): ?>
  <p class="card muted">Geen aanvragen met status “<?= e(mb_strtolower(SwimTestController::STATUSES[$s])) ?>”.</p>
<?php else: ?>
<div class="table-wrap">
<table class="table">
  <thead><tr><?php if ($s === 'WAITING'): ?><th>#</th><?php endif; ?><th>Kind</th><th>Diploma</th><th>Contact</th><th>Aangemeld</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($rows as $i => $r): $age = (new DateTimeImmutable($r['child_birth_date']))->diff(new DateTimeImmutable(today()))->y; ?>
    <tr>
      <?php if ($s === 'WAITING'): ?><td><?= $i + 1 ?></td><?php endif; ?>
      <td><a href="<?= e(url('swimtest_show', ['id' => $r['id']])) ?>"><?= e($r['child_last_name']) ?> <?= e($r['child_first_name']) ?></a> <span class="muted small">(<?= (int) $age ?> jaar)</span></td>
      <td><?= e(SwimTestController::DIPLOMAS[$r['diploma']] ?? $r['diploma']) ?></td>
      <td><?= e($r['contact_name']) ?><br><span class="muted small"><?= e($r['contact_email']) ?></span></td>
      <td><?= e(fmt_dt($r['confirmed_at'])) ?></td>
      <td class="right"><a class="btn btn-small btn-ghost" href="<?= e(url('swimtest_show', ['id' => $r['id']])) ?>">Openen</a></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php endif; ?>
