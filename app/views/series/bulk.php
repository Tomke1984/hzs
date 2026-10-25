<h1>Trainingen schrappen of verplaatsen</h1>
<p class="muted">Voor vakantie, wedstrijden of een tijdelijk andere locatie. Geldt voor trainingen die uit een reeks komen.</p>
<?php if ($errors): ?>
  <div class="alert alert-error"><ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>
<form class="card form" method="post" action="<?= e(url('series_bulk')) ?>">
  <?= csrf_field() ?>
  <div class="grid2">
    <label>Van *<input type="date" name="from" value="<?= e($v['from']) ?>" required></label>
    <label>Tot en met *<input type="date" name="to" value="<?= e($v['to']) ?>" required></label>
  </div>
  <fieldset>
    <legend>Actie *</legend>
    <label class="check"><input type="radio" name="do" value="cancel" <?= $v['do'] === 'cancel' ? 'checked' : '' ?>> Schrappen</label>
    <label class="check"><input type="radio" name="do" value="restore" <?= $v['do'] === 'restore' ? 'checked' : '' ?>> Geschrapte trainingen herstellen</label>
    <label class="check"><input type="radio" name="do" value="location" <?= $v['do'] === 'location' ? 'checked' : '' ?>> Locatie wijzigen</label>
  </fieldset>
  <label>Reden (bij schrappen)<input name="reason" value="<?= e($v['reason']) ?>" maxlength="200" placeholder="bv. Krokusvakantie"></label>
  <label>Nieuwe locatie (bij locatie wijzigen)<input name="location" value="<?= e($v['location']) ?>" maxlength="150"></label>
  <fieldset>
    <legend>Voor *</legend>
    <label class="check"><input type="radio" name="scope" value="all" <?= $v['scope'] !== 'groups' ? 'checked' : '' ?>> Alle groepen</label>
    <label class="check"><input type="radio" name="scope" value="groups" <?= $v['scope'] === 'groups' ? 'checked' : '' ?>> Enkel geselecteerde groepen</label>
    <div class="group-picks">
      <?php foreach ($groups as $g): ?>
        <label class="check"><input type="checkbox" name="group_ids[]" value="<?= (int) $g['id'] ?>" <?= in_array((int) $g['id'], $sel, true) ? 'checked' : '' ?>> <?= e($g['name']) ?> <span class="muted small">(<?= e(group_category_label($g['category'])) ?>)</span></label>
      <?php endforeach; ?>
    </div>
  </fieldset>
  <div class="actions">
    <button class="btn" type="submit">Toepassen</button>
    <a class="btn btn-ghost" href="<?= e(url('series')) ?>">Annuleren</a>
  </div>
</form>
