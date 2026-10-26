<h1>Bericht sturen</h1>
<p class="muted">Stuur een mail naar de leden en ouders van een groep, een trainingsblok of een activiteit. Elke ontvanger krijgt het bericht apart.</p>

<?php if ($errors): ?>
  <div class="alert alert-error"><ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<form class="card form" method="post" action="<?= e(url('mail_send')) ?>">
  <?= csrf_field() ?>
  <input type="hidden" name="step" value="preview">
  <label>Aan
    <select name="to" required>
      <option value="">— kies een publiek —</option>
      <?php if ($extra !== null): ?><option value="<?= e($key) ?>" selected><?= e($extra) ?></option><?php endif; ?>
      <?php $lastGroup = null; foreach ($options as $k => [$grp, $label]):
          if ($lastGroup !== $grp) { if ($lastGroup !== null) { echo '</optgroup>'; } $lastGroup = $grp; echo '<optgroup label="' . e($grp) . '">'; } ?>
        <option value="<?= e($k) ?>" <?= $k === $key ? 'selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; if ($lastGroup !== null) { echo '</optgroup>'; } ?>
    </select>
  </label>
  <label>Onderwerp
    <input name="subject" value="<?= e($subject) ?>" maxlength="150" required>
  </label>
  <label>Bericht
    <textarea name="body" rows="8" maxlength="5000" required><?= e($body) ?></textarea>
  </label>
  <p class="muted small">Je ziet op de volgende stap precies naar hoeveel adressen het gaat, voor het verstuurd wordt.</p>
  <div class="actions"><button class="btn" type="submit">Controleren</button></div>
</form>

<?php if ($recent): ?>
<section class="card">
  <h2>Laatst verstuurd</h2>
  <div class="table-wrap">
  <table class="table">
    <thead><tr><th>Wanneer</th><th>Aan</th><th>Onderwerp</th><th>Ontvangers</th><?php if (Scope::clubWide()): ?><th>Door</th><?php endif; ?></tr></thead>
    <tbody>
    <?php foreach ($recent as $b): ?>
      <tr><td><?= e(fmt_dt($b['created_at'])) ?></td><td><?= e($b['audience']) ?></td><td><?= e($b['subject']) ?></td><td><?= (int) $b['recipient_count'] ?></td><?php if (Scope::clubWide()): ?><td><?= e($b['display_name'] ?? '—') ?></td><?php endif; ?></tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</section>
<?php endif; ?>
