<h1><?= $group['id'] ? 'Groep wijzigen' : 'Nieuwe groep' ?></h1>

<?php if ($errors): ?>
  <div class="alert alert-error"><ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<form class="card form" method="post" action="<?= e(url('group_save')) ?>">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int) $group['id'] ?>">
  <label>Naam *
    <input name="name" value="<?= e($group['name']) ?>" maxlength="80" required>
  </label>
  <label>Soort groep *
    <select name="category" required>
      <?php foreach (group_categories() as $code => $label): ?>
        <option value="<?= e($code) ?>" <?= ($group['category'] ?? 'SWIM_SCHOOL') === $code ? 'selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>Omschrijving
    <input name="description" value="<?= e($group['description']) ?>" maxlength="255">
  </label>
  <label>Doelstellingen (voor de website)
    <textarea class="editor" name="goals" rows="9" maxlength="8000"><?= e((string) ($group['goals'] ?? '')) ?></textarea>
    <span class="muted small">Verschijnen op de pagina Doelstellingen van de zwemschool (enkel voor actieve groepen van de zwemschool). Een lijst maak je met <code>- </code> voor elke regel; <code>**vet**</code> kan ook.</span>
  </label>
  <div class="grid2">
    <label>Capaciteit (max. aantal leden)
      <input name="capacity" inputmode="numeric" value="<?= e((string) ($group['capacity'] ?? '')) ?>" maxlength="3" placeholder="onbeperkt">
    </label>
    <label>Lidgeld per seizoen (€)
      <input name="fee" inputmode="decimal" value="<?= isset($group['fee']) ? e((string) $group['fee']) : (($group['fee_cents'] ?? null) !== null ? e(number_format($group['fee_cents'] / 100, 2, ',', '')) : '') ?>" maxlength="9" placeholder="bv. 125,00">
    </label>
  </div>
  <label>Doorstroomgroep
    <select name="next_group_id">
      <option value="0">— geen —</option>
      <?php foreach ($otherGroups as $og): ?><option value="<?= (int) $og['id'] ?>" <?= (int) ($group['next_group_id'] ?? 0) === (int) $og['id'] ? 'selected' : '' ?>><?= e($og['name']) ?></option><?php endforeach; ?>
    </select>
    <span class="muted small">De groep waar leden normaal naartoe doorstromen bij een nieuw seizoen.</span>
  </label>
  <label class="check">
    <input type="checkbox" name="is_active" value="1" <?= (int) $group['is_active'] ? 'checked' : '' ?>>
    Groep is actief
  </label>
  <fieldset>
    <legend>Trainers</legend>
    <?php if (!$trainerOptions): ?>
      <p class="muted">Er zijn nog geen gebruikers met de rol Trainer.</p>
    <?php else: foreach ($trainerOptions as $t): ?>
      <label class="check">
        <input type="checkbox" name="trainers[]" value="<?= (int) $t['id'] ?>" <?= in_array((int) $t['id'], $selectedTrainers, true) ? 'checked' : '' ?>>
        <?= e($t['display_name']) ?><?= (int) $t['is_active'] ? '' : ' (inactief)' ?>
      </label>
    <?php endforeach; endif; ?>
  </fieldset>
  <div class="actions">
    <button class="btn" type="submit">Opslaan</button>
    <a class="btn btn-ghost" href="<?= e($group['id'] ? url('group', ['id' => $group['id']]) : url('groups')) ?>">Annuleren</a>
  </div>
</form>
