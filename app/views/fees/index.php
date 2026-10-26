<div class="row-between">
  <h1>Lidgeld</h1>
  <span class="actions-inline">
    <a class="btn" href="<?= e(url('season_rollover')) ?>">Seizoensovergang</a>
    <a class="btn btn-ghost" href="<?= e(url('export')) ?>">Exporteren</a>
  </span>
</div>
<p class="muted">Het bedrag per seizoen stel je in bij de groep. Elke rekening krijgt een gestructureerde mededeling, zodat betalingen makkelijk te herkennen zijn.</p>
<?php if (Fees::payInfo() === ''): ?>
  <div class="alert alert-warning">Stel in <code>config.php</code> het rekeningnummer in (<code>'fees' =&gt; ['iban' =&gt; 'BE00 0000 0000 0000']</code>), zodat het in de herinneringsmails en op de ledenpagina staat.</div>
<?php endif; ?>

<?php if (!$season): ?>
<section class="card">
  <h2>Eerste seizoen aanmaken</h2>
  <form class="form" method="post" action="<?= e(url('season_save')) ?>">
    <?= csrf_field() ?><input type="hidden" name="is_current" value="1">
    <div class="grid2">
      <label>Naam <input name="name" value="<?= e($defaultSeason[0]) ?>" maxlength="40" required></label>
      <span></span>
      <label>Begin <input type="date" name="start_date" value="<?= e($defaultSeason[1]) ?>" required></label>
      <label>Einde <input type="date" name="end_date" value="<?= e($defaultSeason[2]) ?>" required></label>
    </div>
    <div class="actions"><button class="btn" type="submit">Seizoen aanmaken</button></div>
  </form>
</section>
<?php else: ?>

<nav class="tabs" aria-label="Seizoen">
  <?php foreach ($seasons as $s): ?>
    <a href="<?= e(url('fees', ['season' => $s['id']])) ?>" <?= (int) $s['id'] === (int) $season['id'] ? 'aria-current="page"' : '' ?>><?= e($s['name']) ?><?= (int) $s['is_current'] ? ' ★' : '' ?></a>
  <?php endforeach; ?>
</nav>

<div class="stats">
  <div class="stat"><strong><?= Fees::money($sum['all'][1]) ?></strong><span>totaal (<?= (int) $sum['all'][0] ?> rekeningen)</span></div>
  <div class="stat"><strong><?= Fees::money($sum['PAID'][1]) ?></strong><span>betaald (<?= (int) $sum['PAID'][0] ?>)</span></div>
  <div class="stat"><strong><?= Fees::money($sum['OPEN'][1]) ?></strong><span>nog open (<?= (int) $sum['OPEN'][0] ?>)</span></div>
  <div class="stat"><strong><?= (int) $sum['WAIVED'][0] ?></strong><span>kwijtgescholden</span></div>
</div>

<?php if ($sum['all'][1] > 0): ?><p><progress class="share-track wide" max="<?= (int) $sum['all'][1] ?>" value="<?= (int) $sum['PAID'][1] ?>" aria-label="Aandeel betaald"></progress></p><?php endif; ?>

<?php if ($noFee || $noAmount): ?>
<div class="alert alert-warning">
  <?php if ($noFee): ?><strong><?= (int) $noFee ?> actieve leden</strong> hebben nog geen rekening voor dit seizoen.<?php endif; ?>
  <?php if ($noAmount): ?> Groepen zonder lidgeldbedrag (worden overgeslagen): <?= e(implode(', ', array_column($noAmount, 'name'))) ?>.<?php endif; ?>
</div>
<?php endif; ?>

<section class="card">
  <h2>Acties voor <?= e($season['name']) ?></h2>
  <div class="actions-inline">
    <form class="inline-form" method="post" action="<?= e(url('fee_generate')) ?>" data-confirm="Rekeningen aanmaken voor alle actieve leden zonder rekening?">
      <?= csrf_field() ?><input type="hidden" name="season_id" value="<?= (int) $season['id'] ?>">
      <label class="small">Vervaldatum <input type="date" name="due_date"></label>
      <button class="btn" type="submit">Rekeningen aanmaken</button>
    </form>
    <form class="inline-form" method="post" action="<?= e(url('fee_remind')) ?>" data-confirm="Een herinnering sturen voor alle open rekeningen (behalve wie de laatste 14 dagen al een herinnering kreeg)?">
      <?= csrf_field() ?><input type="hidden" name="season_id" value="<?= (int) $season['id'] ?>">
      <button class="btn btn-ghost" type="submit">Herinnering naar alle openstaande</button>
    </form>
  </div>
</section>

<form class="filters" method="get" action="">
  <input type="hidden" name="season" value="<?= (int) $season['id'] ?>">
  <input type="search" name="q" value="<?= e($q) ?>" placeholder="Zoek op naam">
  <select name="status" aria-label="Status">
    <option value="">Alle statussen</option>
    <?php foreach (['OPEN', 'PAID', 'WAIVED'] as $st): ?><option value="<?= $st ?>" <?= $status === $st ? 'selected' : '' ?>><?= e(Fees::statusLabel($st)) ?></option><?php endforeach; ?>
  </select>
  <select name="g" aria-label="Groep"><option value="0">Alle groepen</option>
    <?php foreach ($groups as $g): ?><option value="<?= (int) $g['id'] ?>" <?= $gid === (int) $g['id'] ? 'selected' : '' ?>><?= e($g['name']) ?></option><?php endforeach; ?></select>
  <button class="btn btn-small" type="submit">Filteren</button>
</form>

<?php if (!$rows): ?>
  <p class="card muted">Geen rekeningen gevonden.</p>
<?php else: ?>
<div class="table-wrap">
<table class="table">
  <thead><tr><th>Lid</th><th>Groep</th><th>Bedrag</th><th>Status</th><th>Mededeling</th><th>Herinnering</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($rows as $d): ?>
    <tr>
      <td><a href="<?= e(url('member', ['id' => $d['member_id']])) ?>"><?= e($d['last_name'] . ' ' . $d['first_name']) ?></a></td>
      <td><?= $d['group_name'] ? group_chip($d['group_name']) : '—' ?></td>
      <td><?= Fees::money((int) $d['amount_cents']) ?></td>
      <td><span class="chip <?= $d['status'] === 'PAID' ? 'ok' : ($d['status'] === 'OPEN' ? 'warn' : 'off') ?>"><?= e(Fees::statusLabel($d['status'])) ?></span>
        <?php if ($d['paid_at']): ?><span class="muted small"><?= e(fmt_date($d['paid_at'])) ?></span><?php endif; ?></td>
      <td><code><?= e(Fees::reference((int) $d['id'])) ?></code></td>
      <td class="small muted"><?= $d['reminder_count'] ? (int) $d['reminder_count'] . '× · ' . e(fmt_date((string) $d['reminder_at'])) : '—' ?></td>
      <td class="right">
        <form class="inline-form" method="post" action="<?= e(url('fee_status')) ?>">
          <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $d['id'] ?>">
          <input type="hidden" name="filter_status" value="<?= e($status) ?>"><input type="hidden" name="filter_g" value="<?= $gid ?: '' ?>"><input type="hidden" name="filter_q" value="<?= e($q) ?>">
          <?php if ($d['status'] === 'OPEN'): ?>
            <button class="btn btn-small" type="submit" name="do" value="paid">Betaald</button>
            <button class="btn btn-small btn-ghost" type="submit" name="do" value="waived">Kwijtschelden</button>
          <?php else: ?>
            <button class="btn btn-small btn-ghost" type="submit" name="do" value="open">Heropenen</button>
          <?php endif; ?>
        </form>
        <?php if ($d['status'] === 'OPEN'): ?>
        <form class="inline-form" method="post" action="<?= e(url('fee_remind')) ?>">
          <?= csrf_field() ?><input type="hidden" name="season_id" value="<?= (int) $season['id'] ?>"><input type="hidden" name="id" value="<?= (int) $d['id'] ?>">
          <button class="btn btn-small btn-ghost" type="submit">Herinnering</button>
        </form>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php endif; ?>

<details class="card collapsible">
  <summary class="btn btn-ghost btn-small">+ Seizoen toevoegen of wijzigen</summary>
  <form class="form" method="post" action="<?= e(url('season_save')) ?>">
    <?= csrf_field() ?>
    <div class="grid2">
      <label>Naam <input name="name" value="<?= e($defaultSeason[0]) ?>" maxlength="40" required></label>
      <label class="check"><input type="checkbox" name="is_current" value="1"> Dit is het huidige seizoen</label>
      <label>Begin <input type="date" name="start_date" value="<?= e($defaultSeason[1]) ?>" required></label>
      <label>Einde <input type="date" name="end_date" value="<?= e($defaultSeason[2]) ?>" required></label>
    </div>
    <div class="actions"><button class="btn" type="submit">Bewaren</button></div>
  </form>
</details>
<?php endif; ?>
