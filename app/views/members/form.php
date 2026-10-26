<h1><?= $member['id'] ? 'Lid wijzigen' : 'Nieuw lid' ?></h1>

<?php if ($errors): ?>
  <div class="alert alert-error"><ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<?php if (!empty($duplicates)): ?>
  <div class="alert alert-warning">
    <strong>Mogelijk dubbel:</strong>
    <?php foreach ($duplicates as $dup): ?>
      <a href="<?= e(url('member', ['id' => $dup['id']])) ?>"><?= e($dup['first_name'] . ' ' . $dup['last_name']) ?></a> (<?= e(fmt_date($dup['birth_date'])) ?><?= $dup['status'] === 'ACTIVE' ? '' : ', inactief' ?>)
    <?php endforeach; ?>
    <label class="check"><input type="checkbox" name="confirm_duplicate" value="1" form="member-form"> Het is een ander lid: toch aanmaken</label>
  </div>
<?php endif; ?>

<form class="card form" id="member-form" method="post" action="<?= e(url('member_save')) ?>">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int) $member['id'] ?>">

  <div class="grid2">
    <label>Voornaam *
      <input name="first_name" value="<?= e($member['first_name']) ?>" maxlength="80" required>
    </label>
    <label>Achternaam *
      <input name="last_name" value="<?= e($member['last_name']) ?>" maxlength="80" required>
    </label>
  </div>
  <label>Geboortedatum
    <input type="date" name="birth_date" value="<?= e($member['birth_date']) ?>" max="<?= e(today()) ?>">
  </label>
  <div class="grid2">
    <label>E-mail
      <input type="email" name="email" value="<?= e($member['email']) ?>" maxlength="190">
    </label>
    <label>Telefoon
      <input name="phone" value="<?= e($member['phone']) ?>" maxlength="40">
    </label>
  </div>
  <label>Straat en nummer
    <input name="street" value="<?= e($member['street'] ?? '') ?>" maxlength="120" autocomplete="street-address">
  </label>
  <div class="grid2">
    <label>Postcode
      <input name="postal_code" value="<?= e($member['postal_code'] ?? '') ?>" maxlength="10" autocomplete="postal-code">
    </label>
    <label>Gemeente
      <input name="city" value="<?= e($member['city'] ?? '') ?>" maxlength="80" autocomplete="address-level2">
    </label>
  </div>
  <div class="grid2">
    <label>Status
      <select name="status">
        <option value="ACTIVE" <?= $member['status'] === 'ACTIVE' ? 'selected' : '' ?>>Actief</option>
        <option value="INACTIVE" <?= $member['status'] === 'INACTIVE' ? 'selected' : '' ?>>Inactief</option>
      </select>
    </label>
    <label>Reden (bij inactief)
      <select name="status_reason">
        <option value="">— kies —</option>
        <?php foreach (MemberController::REASONS as $r): ?><option value="<?= e($r) ?>" <?= ($member['status_reason'] ?? '') === $r ? 'selected' : '' ?>><?= e($r) ?></option><?php endforeach; ?>
      </select>
    </label>
  </div>
  <label class="check"><input type="checkbox" name="photo_consent" value="1" <?= !empty($member['photo_consent']) ? 'checked' : '' ?>> Toestemming om foto’s van dit lid op de website te tonen</label>
  <?php if (Auth::can('members.medical')): ?>
  <fieldset>
    <legend>Noodgegevens <span class="muted small">(enkel zichtbaar voor trainers en bestuur, nooit in een export)</span></legend>
    <div class="grid2">
      <label>Noodcontact (naam) <input name="emergency_name" value="<?= e((string) ($member['emergency_name'] ?? '')) ?>" maxlength="120"></label>
      <label>Noodcontact (telefoon) <input name="emergency_phone" value="<?= e((string) ($member['emergency_phone'] ?? '')) ?>" maxlength="40"></label>
    </div>
    <label>Medische opmerking <span class="muted small">(enkel wat een trainer moet weten bij een incident)</span>
      <textarea name="medical_note" rows="2" maxlength="500"><?= e((string) ($member['medical_note'] ?? '')) ?></textarea>
    </label>
  </fieldset>
  <?php endif; ?>
  <p class="muted small">Leden worden nooit verwijderd. Zet een lid op inactief om het uit nieuwe presentielijsten te halen; de historiek blijft bewaard.</p>

  <div class="actions">
    <button class="btn" type="submit">Opslaan</button>
    <a class="btn btn-ghost" href="<?= e($member['id'] ? url('member', ['id' => $member['id']]) : url('members')) ?>">Annuleren</a>
  </div>
</form>
