<div class="row-between">
  <h1>Inschrijvingen: <?= e($a['title']) ?></h1>
  <div>
    <a class="btn btn-ghost" href="<?= e(url('activity', ['id' => $a['id']])) ?>">Naar de activiteit</a>
    <?php if ($rows): ?><a class="btn btn-ghost" href="<?= e(url('event_signups', ['id' => $a['id'], 'csv' => 1])) ?>">Exporteer CSV</a><?php endif; ?>
  </div>
</div>
<p class="muted"><?= e(fmt_dt($a['starts_at'])) ?> · <?= (int) $taken ?> persoon/personen<?= (int) $a['signup_capacity'] > 0 ? ' van ' . (int) $a['signup_capacity'] : '' ?>
  <?php if ($a['signup_capacity'] === null): ?> · <span class="chip off">Online inschrijven staat uit</span><?php else: ?> · <a href="<?= e(url('event', ['id' => $a['id']])) ?>" target="_blank" rel="noopener">publiek formulier ↗</a><?php endif; ?></p>
<?php if (!$rows): ?><p class="card muted">Nog geen inschrijvingen.</p><?php else: ?>
<div class="table-wrap"><table class="table">
  <thead><tr><th>Naam</th><th>E-mail</th><th>Telefoon</th><th>Pers.</th><th>Opmerking</th><th>Op</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <tr>
      <td><?= e($r['name']) ?></td><td><a href="mailto:<?= e($r['email']) ?>"><?= e($r['email']) ?></a></td><td><?= e((string) $r['phone']) ?></td>
      <td><?= (int) $r['persons'] ?></td><td><?= e((string) $r['note']) ?></td><td><?= e(fmt_dt($r['created_at'])) ?></td>
      <td><form method="post" action="<?= e(url('event_signup_delete')) ?>" data-confirm="Inschrijving van <?= e($r['name']) ?> verwijderen?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><button class="btn btn-small btn-ghost" type="submit">Verwijder</button></form></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<?php endif; ?>
