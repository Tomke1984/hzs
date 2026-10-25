<h1><?= $s['id'] ? 'Reeks wijzigen' : 'Nieuwe reeks' ?></h1>
<?php if ($errors): ?>
  <div class="alert alert-error"><ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>
<form class="card form" method="post" action="<?= e(url('series_save')) ?>">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
  <div class="grid2">
    <label>Type *
      <select name="type_id" required>
        <?php foreach ($types as $t): ?>
          <option value="<?= (int) $t['id'] ?>" <?= (int) $s['type_id'] === (int) $t['id'] ? 'selected' : '' ?>><?= e($t['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>Standaardlocatie
      <input name="location" value="<?= e($s['location']) ?>" maxlength="150" placeholder="bv. Zwembad Hasselt">
    </label>
  </div>
  <label>Titel *
    <input name="title" value="<?= e($s['title']) ?>" maxlength="150" required placeholder="bv. Zwemtraining">
  </label>
  <div class="grid2">
    <label>Van *<input type="date" name="start_date" value="<?= e($s['start_date']) ?>" required></label>
    <label>Tot en met *<input type="date" name="end_date" value="<?= e($s['end_date']) ?>" required></label>
  </div>
  <label>Omschrijving
    <textarea name="description" rows="2"><?= e($s['description']) ?></textarea>
  </label>

  <label class="check"><input type="checkbox" name="is_public" value="1" <?= !empty($s['is_public']) ? 'checked' : '' ?>> Tonen op de website (pagina Trainingsuren; trainingen staan nooit in de publieke kalender)</label>

  <fieldset>
    <legend>Trainingsdagen * <span class="muted small">(vink de dagen aan; een tweede training op dezelfde dag = een aparte reeks, bv. droogtraining)</span></legend>
    <?php foreach (SeriesService::DAYS as $n => $label): $sl = $slots[$n] ?? null; ?>
      <div class="slot-row">
        <label class="check slot-day"><input type="checkbox" name="slot_on[<?= $n ?>]" value="1" <?= $sl ? 'checked' : '' ?>> <?= e(ucfirst($label)) ?></label>
        <input type="time" name="slot_start[<?= $n ?>]" value="<?= e($sl['start_time'] ?? '18:00') ?>" aria-label="Begin <?= e($label) ?>">
        <input type="time" name="slot_end[<?= $n ?>]" value="<?= e($sl['end_time'] ?? '19:00') ?>" aria-label="Einde <?= e($label) ?>">
        <input class="slot-loc" name="slot_location[<?= $n ?>]" value="<?= e($sl['location'] ?? '') ?>" maxlength="150" placeholder="Andere locatie (optioneel)" aria-label="Locatie <?= e($label) ?>">
      </div>
    <?php endforeach; ?>
  </fieldset>

  <fieldset>
    <legend>Groepen *</legend>
    <div class="group-picks">
      <?php $lastCat = null; foreach ($groups as $g): ?>
        <?php if ($lastCat !== $g['category']): $lastCat = $g['category']; ?>
          <p class="muted small cat-head"><?= e(group_category_label($g['category'])) ?></p>
        <?php endif; ?>
        <label class="check"><input type="checkbox" name="group_ids[]" value="<?= (int) $g['id'] ?>" <?= in_array((int) $g['id'], $selected, true) ? 'checked' : '' ?>> <?= e($g['name']) ?></label>
      <?php endforeach; ?>
    </div>
  </fieldset>

  <?php if ($s['id']): ?>
  <label class="check"><input type="checkbox" name="is_active" value="1" <?= (int) $s['is_active'] ? 'checked' : '' ?>> Reeks actief (uitvinken stopt de reeks: komende trainingen zonder aanwezigheden verdwijnen)</label>
  <p class="muted small">Wijzigingen gelden voor komende trainingen. Trainingen die je individueel hebt aangepast of waarvan de aanwezigheid al is ingevuld blijven ongemoeid; verleden blijft bewaard.</p>
  <?php endif; ?>

  <div class="actions">
    <button class="btn" type="submit">Opslaan</button>
    <a class="btn btn-ghost" href="<?= e($s['id'] ? url('series_show', ['id' => $s['id']]) : url('series')) ?>">Annuleren</a>
  </div>
</form>
