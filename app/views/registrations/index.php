<div class="row-between">
  <h1>Inschrijvingen</h1>
</div>
<p class="muted">Aanvragen via de inschrijfpagina. Pas bij goedkeuring worden leden en een account aangemaakt. Deelnemers delen de link naar <code><?= e(Invites::baseUrl()) ?>/inschrijven</code>.</p>

<nav class="tabs" aria-label="Soort inschrijvingen">
  <a href="<?= e(url('registrations', ['tab' => 'new'])) ?>" <?= $tab === 'new' ? 'aria-current="page"' : '' ?>>Te beoordelen <span class="chip <?= $counts['new'] ? 'warn' : '' ?>"><?= (int) $counts['new'] ?></span></a>
  <a href="<?= e(url('registrations', ['tab' => 'done'])) ?>" <?= $tab === 'done' ? 'aria-current="page"' : '' ?>>Afgehandeld <span class="chip"><?= (int) $counts['done'] ?></span></a>
</nav>
<?php if ($counts['unconfirmed']): ?>
  <p class="muted small"><?= (int) $counts['unconfirmed'] ?> aanvraag<?= $counts['unconfirmed'] === 1 ? '' : 'en' ?> wacht<?= $counts['unconfirmed'] === 1 ? '' : 'en' ?> nog op bevestiging van het e-mailadres door de inschrijver.</p>
<?php endif; ?>

<?php if (!$rows): ?>
  <p class="card muted"><?= $tab === 'new' ? 'Geen inschrijvingen om te beoordelen.' : 'Nog niets afgehandeld.' ?></p>
<?php else: ?>
<div class="table-wrap">
<table class="table">
  <thead><tr><th>Wie</th><th>Contact</th><th><?= $tab === 'new' ? 'Bevestigd op' : 'Beslissing' ?></th><th></th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <tr>
      <td><a href="<?= e(url('registration', ['id' => $r['id']])) ?>"><?= e(implode(', ', $names[$r['id']] ?? [])) ?></a>
        <?php if ($r['applicant_type'] === 'SELF'): ?><span class="chip">zelf</span><?php endif; ?></td>
      <td><?= e($r['contact_name']) ?><br><span class="muted small"><?= e($r['contact_email']) ?></span></td>
      <?php if ($tab === 'new'): ?>
        <td><?= e(fmt_dt($r['confirmed_at'])) ?></td>
      <?php else: ?>
        <td><span class="chip <?= $r['status'] === 'APPROVED' ? 'ok' : 'off' ?>"><?= $r['status'] === 'APPROVED' ? 'Goedgekeurd' : 'Afgewezen' ?></span> <span class="muted small"><?= e(fmt_dt($r['decided_at'])) ?></span></td>
      <?php endif; ?>
      <td class="right"><a class="btn btn-small btn-ghost" href="<?= e(url('registration', ['id' => $r['id']])) ?>"><?= $tab === 'new' ? 'Beoordelen' : 'Bekijken' ?></a></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php endif; ?>
