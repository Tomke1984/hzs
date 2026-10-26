<h1>Seizoensovergang</h1>
<p class="muted">Sluit het seizoen af en bereid het volgende voor in één keer: kies per groep of de leden blijven, doorstromen naar de volgende groep of stoppen. Je kan per lid afwijken. Er gebeurt niets tot je onderaan bevestigt.</p>
<?php if ($cur): ?><p class="small">Huidig seizoen: <strong><?= e($cur['name']) ?></strong> (tot <?= e(fmt_date($cur['end_date'])) ?>).</p><?php endif; ?>

<form method="post" action="<?= e(url('season_rollover_apply')) ?>" data-confirm="De seizoensovergang nu uitvoeren? Leden worden verplaatst of op inactief gezet.">
  <?= csrf_field() ?>
  <section class="card form">
    <h2>Nieuw seizoen</h2>
    <div class="grid2">
      <label>Naam <input name="name" value="<?= e($season['name']) ?>" maxlength="40" required></label>
      <span></span>
      <label>Begin <input type="date" name="start_date" value="<?= e($season['start']) ?>" required></label>
      <label>Einde <input type="date" name="end_date" value="<?= e($season['end']) ?>" required></label>
    </div>
    <label class="check"><input type="checkbox" name="generate" value="1" checked> Rekeningen aanmaken voor alle actieve leden (volgens het bedrag van hun nieuwe groep)</label>
    <label class="small">Vervaldatum rekeningen <input type="date" name="due_date"></label>
  </section>

  <?php if (!$groups): ?><p class="card muted">Er zijn geen actieve leden in groepen.</p><?php endif; ?>
  <?php foreach ($groups as $g): ?>
  <section class="card">
    <div class="row-between">
      <h2><?= group_chip($g['name']) ?> <span class="muted small">(<?= count($g['members']) ?> leden)</span></h2>
      <label class="small">Standaard voor deze groep
        <select name="bulk[<?= (int) $g['id'] ?>]">
          <option value="stay">Iedereen blijft</option>
          <?php if ($g['next_group_id']): $ng = array_values(array_filter($allGroups, fn($x) => (int) $x['id'] === (int) $g['next_group_id']))[0] ?? null; if ($ng): ?>
            <option value="next">Iedereen → <?= e($ng['name']) ?></option>
          <?php endif; endif; ?>
          <option value="stop">Iedereen stopt</option>
        </select>
      </label>
    </div>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>Lid</th><th>Keuze</th></tr></thead>
      <tbody>
      <?php foreach ($g['members'] as $m): ?>
        <tr>
          <td><?= e($m['last_name'] . ' ' . $m['first_name']) ?><?= e(member_hint($m)) ?></td>
          <td>
            <select name="choice[<?= (int) $m['id'] ?>]" aria-label="Keuze voor <?= e($m['first_name'] . ' ' . $m['last_name']) ?>">
              <option value="default">Volgt de standaard van de groep</option>
              <option value="stay">Blijft in <?= e($g['name']) ?></option>
              <?php foreach ($allGroups as $og): if ((int) $og['id'] === (int) $g['id']) { continue; } ?>
                <option value="g:<?= (int) $og['id'] ?>">→ <?= e($og['name']) ?></option>
              <?php endforeach; ?>
              <option value="stop">Stopt (inactief)</option>
            </select>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  </section>
  <?php endforeach; ?>

  <div class="actions"><button class="btn" type="submit">Seizoensovergang uitvoeren</button> <a class="btn btn-ghost" href="<?= e(url('fees')) ?>">Annuleren</a></div>
</form>
