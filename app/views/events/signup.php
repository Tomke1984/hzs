<div class="banner on-deep">
  <div class="wrap">
    <p class="crumbs"><a href="<?= e(url('agenda')) ?>">Kalender</a></p>
    <h1><?= e($a['title']) ?></h1>
  </div>
</div>
<div class="wrap section">
  <p><strong><?= e(fmt_dt($a['starts_at'])) ?></strong> – <?= e(fmt_time($a['ends_at'])) ?><?= $a['location'] ? ' · ' . e($a['location']) : '' ?></p>
  <?php if ($a['description']): ?><p><?= nl2br(e($a['description'])) ?></p><?php endif; ?>
  <?php if ($done): ?>
    <div class="alert alert-success" role="status">Bedankt, <?= e($old['name']) ?>! Je inschrijving is genoteerd. Je krijgt een bevestiging op <?= e($old['email']) ?>.</div>
    <p><a href="<?= e(url('agenda')) ?>">← Terug naar de kalender</a></p>
  <?php elseif ($a['status'] !== 'PLANNED' || $a['ends_at'] < now()): ?>
    <p class="alert alert-warning">Inschrijven is niet meer mogelijk voor deze activiteit.</p>
  <?php elseif ($free === 0): ?>
    <p class="alert alert-warning">Deze activiteit is volzet.</p>
  <?php else: ?>
    <?php if ($free !== null): ?><p class="muted"><?= (int) $free ?> plaats(en) vrij.</p><?php endif; ?>
    <?php if ($errors): ?><div class="alert alert-error" role="alert"><ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <form class="form" method="post" action="<?= e(url('event_signup')) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
      <div class="hp" aria-hidden="true"><label>Laat dit leeg <input name="website" tabindex="-1" autocomplete="off"></label></div>
      <label>Naam *<input name="name" value="<?= e($old['name']) ?>" maxlength="120" required autocomplete="name"></label>
      <div class="grid2">
        <label>E-mail *<input type="email" name="email" value="<?= e($old['email']) ?>" maxlength="190" required autocomplete="email"></label>
        <label>Telefoon<input name="phone" value="<?= e($old['phone']) ?>" maxlength="40" autocomplete="tel"></label>
      </div>
      <label>Aantal personen<input type="number" name="persons" min="1" max="10" value="<?= (int) $old['persons'] ?>"></label>
      <label>Opmerking<input name="note" value="<?= e($old['note']) ?>" maxlength="300"></label>
      <p class="muted small">We gebruiken je gegevens enkel om deze activiteit te organiseren<?php $pu = privacy_href(); if ($pu !== ''): ?> (<a href="<?= e($pu) ?>">privacy</a>)<?php endif; ?>.</p>
      <div class="actions"><button class="btn" type="submit">Inschrijven</button></div>
    </form>
  <?php endif; ?>
</div>
