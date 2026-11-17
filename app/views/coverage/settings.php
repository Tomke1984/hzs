<h1>Trainersplanning: instellingen</h1>
<p class="muted">De <strong>vaste trainers</strong> zijn de trainers van de groep: ze staan automatisch op elke training van hun groep. Je stelt ze in bij <em>Groepen → groep → Wijzigen</em>.
  Meldt een vaste trainer zich af, dan kiest hij wie overneemt; is er nog niemand, dan krijgen de coördinator en de trainers <?= (int) $remindDays ?> dagen vooraf een mail.
  Wie op een wedstrijd ingepland is, staat automatisch afgemeld voor trainingen op hetzelfde moment.</p>

<form method="post" action="<?= e(url('coverage_settings_save')) ?>" class="card form">
  <?= csrf_field() ?>
  <h2>Vaste trainers en minimum per groep</h2>
  <?php if (!$groups): ?>
    <p class="muted">Geen actieve groepen.</p>
  <?php else: ?>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Groep</th><th>Vaste trainers</th><th>Minimum aantal trainers</th></tr></thead>
    <tbody>
    <?php foreach ($groups as $g): ?>
      <tr>
        <td><?= group_chip($g['name']) ?></td>
        <td><?= $g['trainers'] ? e(implode(', ', $g['trainers'])) : '<span class="chip bad">Geen trainer</span>' ?>
          <?php if (Auth::can('groups.write')): ?> <a class="small" href="<?= e(url('group_form', ['id' => $g['id']])) ?>">wijzigen</a><?php endif; ?></td>
        <td><input type="number" name="min[<?= (int) $g['id'] ?>]" min="1" max="9" value="<?= e((string) ($g['min_trainers'] ?? '')) ?>" placeholder="1" inputmode="numeric" aria-label="Minimum aantal trainers voor <?= e($g['name']) ?>" class="input-narrow"></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <p class="muted small">Minimum leeg = 1. Bij een wedstrijd kan je per wedstrijd een ander aantal ingeven (Activiteit wijzigen).</p>
  <div class="actions"><button class="btn" type="submit">Opslaan</button></div>
  <?php endif; ?>
</form>

<section class="card" id="delen">
  <h2>Weekplanning delen met ouders</h2>
  <p class="muted">Een geheime link per groep met de trainingen en wedstrijden van de komende 3 weken (ook geschrapte trainingen), zonder namen van trainers of leden.
    Handig om in de oudergroep te delen zolang niet iedereen een account heeft. Iedereen met de link kan de planning zien; maak een nieuwe link als hij ongewenst rondgaat.</p>
  <?php foreach ($groups as $g): ?>
    <div class="cover-share">
      <?= group_chip($g['name']) ?>
      <?php if ($g['share_token']): $link = $base . '/' . url('weekplanning', ['t' => $g['share_token']]); ?>
        <input type="text" readonly value="<?= e($link) ?>" aria-label="Deellink voor <?= e($g['name']) ?>" class="cover-link">
        <a class="btn btn-small btn-ghost" href="<?= e($link) ?>" target="_blank" rel="noopener">Bekijken</a>
        <form method="post" action="<?= e(url('coverage_share')) ?>" data-confirm="Nieuwe link maken? De huidige link van <?= e($g['name']) ?> werkt dan niet meer."><?= csrf_field() ?><input type="hidden" name="group_id" value="<?= (int) $g['id'] ?>"><button class="btn btn-small btn-ghost" type="submit">Nieuwe link</button></form>
        <form method="post" action="<?= e(url('coverage_share')) ?>"><?= csrf_field() ?><input type="hidden" name="group_id" value="<?= (int) $g['id'] ?>"><input type="hidden" name="do" value="remove"><button class="btn btn-small btn-ghost" type="submit">Uitzetten</button></form>
        <?php if ($g['share_expires']): ?><span class="small <?= $g['share_expires'] < today() ? 'text-bad' : 'muted' ?>"><?= $g['share_expires'] < today() ? 'Verlopen op ' : 'Geldig tot ' ?><?= e(fmt_date($g['share_expires'])) ?></span><?php endif; ?>
      <?php else: ?>
        <form method="post" action="<?= e(url('coverage_share')) ?>"><?= csrf_field() ?><input type="hidden" name="group_id" value="<?= (int) $g['id'] ?>"><button class="btn btn-small" type="submit">Deellink maken</button></form>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</section>
