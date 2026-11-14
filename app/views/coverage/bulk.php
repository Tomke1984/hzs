<h1>Meerdere trainingen tegelijk</h1>
<p class="muted">Plan één trainer in één keer in, of meld hem af, voor een reeks trainingen en wedstrijden. Bijvoorbeeld: <em>Lander neemt alle dinsdagen van Kern over tot de kerstvakantie</em>, of <em>Kobe is met vakantie van 20 tot 31 december</em>.</p>

<form class="card form" method="get" action="<?= e(url('coverage_bulk_form')) ?>">
  <input type="hidden" name="show" value="1">
  <h2>1. Wie en wat?</h2>
  <div class="grid2">
    <label>Trainer
      <select name="user_id" required>
        <option value="">Kies een trainer…</option>
        <?php foreach ($pool as $pid => $pname): ?><option value="<?= (int) $pid ?>"<?= (int) ($f['user_id'] ?? 0) === $pid ? ' selected' : '' ?>><?= e($pname) ?></option><?php endforeach; ?>
      </select>
    </label>
    <fieldset class="bulk-do">
      <legend>Wat moet er gebeuren?</legend>
      <label class="check"><input type="radio" name="do" value="assign"<?= $f['do'] === 'assign' ? ' checked' : '' ?>> <strong>Inplannen</strong> – de trainer geeft (mee) training</label>
      <label class="check"><input type="radio" name="do" value="absent"<?= $f['do'] === 'absent' ? ' checked' : '' ?>> <strong>Afmelden</strong> – de trainer kan niet</label>
    </fieldset>
  </div>
  <h2>2. Welke trainingen en wedstrijden?</h2>
  <div class="grid2">
    <label>Groep
      <select name="g">
        <option value="0">Alle groepen</option>
        <?php foreach ($groups as $g): ?><option value="<?= (int) $g['id'] ?>"<?= $f['g'] === (int) $g['id'] ? ' selected' : '' ?>><?= e($g['name']) ?></option><?php endforeach; ?>
      </select>
    </label>
    <label>Dag
      <select name="d">
        <option value="0">Alle dagen</option>
        <?php foreach (SeriesService::DAYS as $n => $day): ?><option value="<?= $n ?>"<?= $f['d'] === $n ? ' selected' : '' ?>><?= e(ucfirst($day)) ?></option><?php endforeach; ?>
      </select>
    </label>
    <label>Van <input type="date" name="from" value="<?= e($f['from']) ?>" min="<?= e(today()) ?>" required></label>
    <label>Tot en met <input type="date" name="to" value="<?= e($f['to']) ?>" min="<?= e(today()) ?>" required></label>
  </div>
  <div class="actions"><button class="btn" type="submit">Trainingen tonen</button></div>
</form>

<?php if ($shown): $who = $pool[(int) $f['user_id']]; $todo = array_filter($rows, fn($r) => !$r['skip']); ?>
<form id="cover-bulk" class="card" method="post" action="<?= e(url('coverage_bulk')) ?>">
  <?= csrf_field() ?>
  <?php foreach (['user_id', 'do', 'g', 'd', 'from', 'to'] as $k): ?><input type="hidden" name="<?= $k ?>" value="<?= e((string) $f[$k]) ?>"><?php endforeach; ?>
  <h2>3. Controleren en bevestigen</h2>
  <?php if (!$rows): ?>
    <p class="muted">Geen trainingen of wedstrijden gevonden voor deze keuze. Pas groep, dag of periode aan.</p>
  <?php else: ?>
    <p><?= $f['do'] === 'assign' ? 'Inplannen' : 'Afmelden' ?>: <strong><?= e($who) ?></strong> · <?= count($rows) ?> gevonden, waarvan <strong><?= count($todo) ?></strong> te wijzigen.
      <?php if (count($todo) < count($rows)): ?><span class="muted small">De grijze regels blijven zoals ze zijn.</span><?php endif; ?></p>
    <div class="table-wrap"><table class="table bulk-table">
      <thead><tr><th class="check-col"><?php if ($todo): ?><input type="checkbox" id="cover-all" checked aria-label="Alles aan- of uitvinken"><?php endif; ?></th><th>Wanneer</th><th>Training</th><th>Nu ingepland</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $a): ?>
        <tr class="<?= $a['skip'] ? 'is-off' : '' ?>">
          <td class="check-col"><?php if (!$a['skip']): ?><input type="checkbox" class="cover-pick" name="ids[]" value="<?= (int) $a['id'] ?>" checked aria-label="<?= e($a['title'] . ' ' . fmt_dt($a['starts_at'])) ?>"><?php endif; ?></td>
          <td><span><strong><?= e(fmt_date($a['starts_at'])) ?></strong> <?= e(fmt_time($a['starts_at'])) ?>–<?= e(fmt_time($a['ends_at'])) ?></span></td>
          <td><span><?= e($a['title']) ?> <span class="muted small">· <?= e($a['gnames']) ?></span></span></td>
          <td><span>
            <?php $names = array_map(fn($t) => $t['name'] . ($t['absent'] ? ' (afgemeld)' : ''), $a['trainers']); ?>
            <?= $names ? e(implode(', ', $names)) : '<span class="muted">niemand</span>' ?>
            <?php if ($a['skip']): ?><br><span class="chip off"><?= e(ucfirst($a['skip'])) ?></span><?php endif; ?>
          </span></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <?php if ($todo): ?>
      <?php if ($f['do'] === 'absent'): ?>
        <div class="grid2">
          <label>Wie neemt over? *
            <select name="replacement" required>
              <option value="">Kies…</option>
              <option value="none">Nog geen vervanger – trainer gezocht (mail naar de collega's)</option>
              <?php foreach ($pool as $pid => $pname): if ($pid === (int) $f['user_id']) { continue; } ?><option value="<?= (int) $pid ?>"><?= e($pname) ?></option><?php endforeach; ?>
            </select>
          </label>
          <label>Reden (optioneel, zichtbaar voor de andere trainers) <input name="note" maxlength="200" placeholder="bv. vakantie"></label>
        </div>
      <?php endif; ?>
      <div class="actions">
        <button class="btn btn-wrap" type="submit"><?= e($who) ?> <?= $f['do'] === 'assign' ? 'inplannen' : 'afmelden' ?> voor <span data-bulk-count><?= count($todo) ?></span> training(en)</button>
        <a href="<?= e(url('coverage')) ?>">Annuleren</a>
      </div>
    <?php else: ?>
      <p class="muted">Er is niets te wijzigen: <?= e($who) ?> <?= $f['do'] === 'assign' ? 'staat al overal ingepland' : 'staat nergens ingepland of is al afgemeld' ?>.</p>
    <?php endif; ?>
  <?php endif; ?>
</form>
<?php elseif (!$pool): ?>
  <div class="alert alert-warning">Er zijn nog geen competitietrainers: koppel trainers aan een groep.</div>
<?php endif; ?>
