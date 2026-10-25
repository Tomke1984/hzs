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
  <label class="check">
    <input type="checkbox" name="is_active" value="1" <?= (int) $group['is_active'] ? 'checked' : '' ?>>
    Groep is actief
  </label>
  <div class="actions">
    <button class="btn" type="submit">Opslaan</button>
    <a class="btn btn-ghost" href="<?= e($group['id'] ? url('group', ['id' => $group['id']]) : url('groups')) ?>">Annuleren</a>
  </div>
</form>
