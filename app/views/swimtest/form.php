<div class="banner on-deep"><div class="wrap"><h1>Zwemtest aanvragen</h1></div></div>
<div class="wrap page-grid solo">
<div class="form-page">
  <p class="lede-dark">Meld je kind aan voor de zwemtest van de zwemschool. Je bevestigt daarna je e-mailadres en je kind komt op de wachtlijst. We nodigen je uit zodra de club een zwemtest organiseert, meestal tijdens de vakantieperiodes. <a href="<?= e(url('page', ['slug' => 'inschrijven-zwemtest'])) ?>">Lees eerst de uitleg</a>.</p>

  <?php if ($errors): ?>
    <div class="alert alert-error" role="alert"><ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
  <?php endif; ?>

  <form class="form" method="post" action="<?= e(url('swimtest')) ?>" autocomplete="on">
    <?= csrf_field() ?>
    <div class="hp" aria-hidden="true"><label>Laat dit leeg <input name="website" tabindex="-1" autocomplete="off"></label></div>

    <fieldset>
      <legend>Je kind</legend>
      <div class="grid2">
        <label>Voornaam *
          <input name="child_first_name" value="<?= e($old['child_first_name']) ?>" maxlength="80" required>
        </label>
        <label>Achternaam *
          <input name="child_last_name" value="<?= e($old['child_last_name']) ?>" maxlength="80" required>
        </label>
      </div>
      <label>Geboortedatum *
        <input type="date" name="child_birth_date" value="<?= e($old['child_birth_date']) ?>" max="<?= e(today()) ?>" required>
      </label>
    </fieldset>

    <fieldset>
      <legend>Diploma en ervaring</legend>
      <label>Diploma
        <select name="diploma">
          <?php foreach (SwimTestController::DIPLOMAS as $code => $label): ?>
            <option value="<?= e($code) ?>" <?= $old['diploma'] === $code ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Andere zwemervaring (optioneel)
        <input name="experience" value="<?= e($old['experience']) ?>" maxlength="300" placeholder="bv. zwemlessen bij een andere club">
      </label>
    </fieldset>

    <fieldset>
      <legend>Wanneer kan je kind zwemmen?</legend>
      <p class="muted small">Vink alle momenten aan die passen. Zo kunnen we je kind sneller een plaats geven.</p>
      <?php foreach (SwimTestController::SLOTS as $code => $label): ?>
        <label class="check"><input type="checkbox" name="slots[]" value="<?= e($code) ?>" <?= in_array($code, $old['slots'], true) ? 'checked' : '' ?>> <?= e($label) ?></label>
      <?php endforeach; ?>
    </fieldset>

    <fieldset>
      <legend>Jouw gegevens (ouder of voogd)</legend>
      <div class="grid2">
        <label>Naam *
          <input name="contact_name" value="<?= e($old['contact_name']) ?>" maxlength="120" required autocomplete="name">
        </label>
        <label>Relatie tot het kind
          <input name="relationship" value="<?= e($old['relationship']) ?>" maxlength="40" placeholder="bv. moeder, vader, voogd">
        </label>
      </div>
      <div class="grid2">
        <label>E-mailadres *
          <input type="email" name="contact_email" value="<?= e($old['contact_email']) ?>" maxlength="190" required autocomplete="email">
        </label>
        <label>Telefoon *
          <input type="tel" name="contact_phone" value="<?= e($old['contact_phone']) ?>" maxlength="40" required autocomplete="tel">
        </label>
      </div>
      <label>Straat en nummer *
        <input name="street" value="<?= e($old['street']) ?>" maxlength="120" required autocomplete="street-address">
      </label>
      <div class="grid2">
        <label>Postcode *
          <input name="postal_code" value="<?= e($old['postal_code']) ?>" maxlength="10" required autocomplete="postal-code">
        </label>
        <label>Gemeente *
          <input name="city" value="<?= e($old['city']) ?>" maxlength="80" required autocomplete="address-level2">
        </label>
      </div>
    </fieldset>

    <label>Opmerking (optioneel)
      <textarea name="remarks" rows="3" maxlength="500"><?= e($old['remarks']) ?></textarea>
    </label>

    <label class="check">
      <input type="checkbox" name="consent" value="1" required>
      <span>Ik ga akkoord met de verwerking van deze gegevens door HZS voor de wachtlijst en de inschrijving.<?php $pu = privacy_href(); if ($pu !== ''): ?> <a href="<?= e($pu) ?>" target="_blank" rel="noopener">Privacyverklaring</a><?php endif; ?></span>
    </label>

    <p><button class="btn" type="submit">Zwemtest aanvragen</button></p>
  </form>
</div>
</div>
