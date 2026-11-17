<h1><?= $activity['id'] ? 'Activiteit wijzigen' : 'Nieuwe activiteit' ?></h1>

<?php if ($errors): ?>
  <div class="alert alert-error"><ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<form class="card form" method="post" action="<?= e(url('activity_save')) ?>">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int) $activity['id'] ?>">

  <div class="grid2">
    <label>Type *
      <select name="type_id" required data-type-select>
        <?php foreach ($types as $t): ?>
          <option value="<?= (int) $t['id'] ?>" data-code="<?= e($t['code']) ?>" <?= (int) $activity['type_id'] === (int) $t['id'] ? 'selected' : '' ?>><?= e($t['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>Locatie
      <input name="location" value="<?= e($activity['location']) ?>" maxlength="150">
    </label>
  </div>

  <label>Titel *
    <input name="title" value="<?= e($activity['title']) ?>" maxlength="150" required>
  </label>

  <div class="grid2">
    <label>Begin *
      <input type="datetime-local" name="starts_at" value="<?= e($activity['starts_local']) ?>" required>
    </label>
    <label>Einde *
      <input type="datetime-local" name="ends_at" value="<?= e($activity['ends_local']) ?>" required>
    </label>
  </div>

  <label>Omschrijving
    <textarea name="description" rows="3"><?= e($activity['description']) ?></textarea>
  </label>

  <fieldset>
    <legend>Doelgroep *</legend>
    <label class="check"><input type="radio" name="audience" value="CLUB" <?= $activity['audience'] === 'CLUB' ? 'checked' : '' ?>> Hele club</label>
    <label class="check"><input type="radio" name="audience" value="GROUPS" <?= $activity['audience'] === 'GROUPS' ? 'checked' : '' ?>> Specifieke groepen</label>
    <div class="group-picks" id="group-picks">
      <?php if (!$groups): ?>
        <p class="muted">Er zijn nog geen actieve groepen.</p>
      <?php endif; ?>
      <?php foreach ($groups as $g): ?>
        <label class="check">
          <input type="checkbox" name="group_ids[]" value="<?= (int) $g['id'] ?>" <?= in_array((int) $g['id'], $selected, true) ? 'checked' : '' ?>>
          <?= e($g['name']) ?>
        </label>
      <?php endforeach; ?>
    </div>
  </fieldset>


  <?php $typeCode = ''; foreach ($types as $t) { if ((int) $t['id'] === (int) $activity['type_id']) { $typeCode = $t['code']; } } ?>
  <fieldset data-for-type="COMPETITION"<?= $typeCode === 'COMPETITION' ? '' : ' hidden' ?>>
    <legend>Trainers die meegaan <span class="muted small">(wedstrijd)</span></legend>
    <?php if (!$pool): ?><p class="muted small">Er zijn nog geen trainers van de competitie.</p><?php endif; ?>
    <div class="check-cols">
      <?php foreach ($pool as $pid => $pname): ?>
        <label class="check"><input type="checkbox" name="trainer_ids[]" value="<?= (int) $pid ?>"<?= in_array((int) $pid, $trainerIds, true) ? ' checked' : '' ?>> <?= e($pname) ?></label>
      <?php endforeach; ?>
    </div>
    <p class="muted small">Niemand aangevinkt? Dan staat de wedstrijd als <em>trainer gezocht</em> in de trainersplanning.</p>
  </fieldset>

  <label data-for-type="TRAINING"<?= $typeCode === 'TRAINING' ? '' : ' hidden' ?>>Aantal trainers nodig (trainersplanning competitie)
    <input name="min_trainers" inputmode="numeric" maxlength="1" value="<?= e((string) ($activity['min_trainers'] ?? '')) ?>" placeholder="volgens de groep">
    <span class="muted small">Leeg = het minimum van de groep (standaard 1).</span>
  </label>

  <?php if ($activity['id']): ?>
  <label>Status
    <select name="status">
      <option value="PLANNED" <?= $activity['status'] === 'PLANNED' ? 'selected' : '' ?>>Gepland</option>
      <option value="CANCELLED" <?= $activity['status'] === 'CANCELLED' ? 'selected' : '' ?>>Geannuleerd</option>
    </select>
  </label>
  <?php endif; ?>

  <div class="actions">
    <button class="btn" type="submit">Opslaan</button>
    <a class="cancel-link" href="<?= e($activity['id'] ? url('activity', ['id' => $activity['id']]) : url('calendar')) ?>">Annuleren</a>
  </div>
</form>
