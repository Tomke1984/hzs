<h1>Gegevens van <?= e($member['first_name']) ?> wijzigen</h1>
<p class="muted">Pas aan wat niet (meer) klopt. De trainers en het bestuur zien meteen de nieuwe gegevens. Groep en status past het bestuur aan.</p>

<?php if ($errors): ?>
  <div class="alert alert-error"><ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<form class="card form" method="post" action="<?= e(url('child_save')) ?>">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int) $member['id'] ?>">

  <div class="grid2">
    <label>Voornaam *
      <input name="first_name" value="<?= e($member['first_name']) ?>" maxlength="80" required autocomplete="given-name">
    </label>
    <label>Achternaam *
      <input name="last_name" value="<?= e($member['last_name']) ?>" maxlength="80" required autocomplete="family-name">
    </label>
  </div>
  <label>Geboortedatum
    <input type="date" name="birth_date" value="<?= e((string) $member['birth_date']) ?>" max="<?= e(today()) ?>">
  </label>
  <div class="grid2">
    <label>E-mail
      <input type="email" name="email" value="<?= e((string) $member['email']) ?>" maxlength="190" autocomplete="email">
    </label>
    <label>Telefoon
      <input type="tel" name="phone" value="<?= e((string) $member['phone']) ?>" maxlength="40" autocomplete="tel">
    </label>
  </div>
  <label>Straat en nummer
    <input name="street" value="<?= e((string) ($member['street'] ?? '')) ?>" maxlength="120" autocomplete="street-address">
  </label>
  <div class="grid2">
    <label>Postcode
      <input name="postal_code" value="<?= e((string) ($member['postal_code'] ?? '')) ?>" maxlength="10" autocomplete="postal-code" inputmode="numeric">
    </label>
    <label>Gemeente
      <input name="city" value="<?= e((string) ($member['city'] ?? '')) ?>" maxlength="80" autocomplete="address-level2">
    </label>
  </div>
  <fieldset>
    <legend>Noodgegevens <span class="muted small">(enkel zichtbaar voor de trainers en het bestuur)</span></legend>
    <div class="grid2">
      <label>Noodcontact (naam) <input name="emergency_name" value="<?= e((string) ($member['emergency_name'] ?? '')) ?>" maxlength="120"></label>
      <label>Noodcontact (telefoon) <input type="tel" name="emergency_phone" value="<?= e((string) ($member['emergency_phone'] ?? '')) ?>" maxlength="40"></label>
    </div>
    <label>Medische opmerking <span class="muted small">(enkel wat een trainer moet weten bij een incident, bv. astma of epilepsie)</span>
      <textarea name="medical_note" rows="2" maxlength="500"><?= e((string) ($member['medical_note'] ?? '')) ?></textarea>
    </label>
  </fieldset>

  <div class="actions">
    <button class="btn" type="submit">Opslaan</button>
    <a class="btn btn-ghost" href="<?= e(url('member', ['id' => $member['id']])) ?>">Annuleren</a>
  </div>
</form>
