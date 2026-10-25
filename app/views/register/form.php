<div class="card narrow wide">
  <img class="login-logo" src="assets/logo.png" alt="Hasseltse Zwemvereniging Spartacus" width="109" height="96">
  <h1 class="center">Inschrijven bij HZS</h1>
  <p class="muted center">Vul dit formulier in. Je bevestigt daarna je e-mailadres en het bestuur beoordeelt je inschrijving. Is die goedgekeurd, dan ontvang je een mail om je account te activeren.</p>

  <?php if ($errors): ?>
    <div class="alert alert-error"><ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
  <?php endif; ?>

  <form class="form" method="post" action="<?= e(url('register')) ?>" autocomplete="on">
    <?= csrf_field() ?>
    <div class="hp" aria-hidden="true"><label>Laat dit leeg <input name="website" tabindex="-1" autocomplete="off"></label></div>

    <fieldset>
      <legend>Wie schrijf je in?</legend>
      <label class="check"><input type="radio" name="applicant_type" value="PARENT" <?= $old['applicant_type'] === 'PARENT' ? 'checked' : '' ?>> Mijn kind(eren), ik ben de ouder of voogd</label>
      <label class="check"><input type="radio" name="applicant_type" value="SELF" <?= $old['applicant_type'] === 'SELF' ? 'checked' : '' ?>> Mezelf (18 jaar of ouder)</label>
    </fieldset>

    <?php for ($i = 0; $i < $max; $i++): $p = $old['persons'][$i]; ?>
      <fieldset <?= $i > 0 ? 'data-show-for="PARENT"' : '' ?>>
        <legend><span data-show-for="PARENT">Kind <?= $i + 1 ?></span><span data-show-for="SELF" hidden>Jouw gegevens</span><?= $i > 0 ? ' <span class="muted small">(optioneel)</span>' : '' ?></legend>
        <div class="grid2">
          <label>Voornaam<?= $i === 0 ? ' *' : '' ?>
            <input name="persons[<?= $i ?>][first_name]" value="<?= e($p['first_name']) ?>" maxlength="80" <?= $i === 0 ? 'required' : '' ?> autocomplete="<?= $i === 0 ? 'given-name' : 'off' ?>">
          </label>
          <label>Achternaam<?= $i === 0 ? ' *' : '' ?>
            <input name="persons[<?= $i ?>][last_name]" value="<?= e($p['last_name']) ?>" maxlength="80" <?= $i === 0 ? 'required' : '' ?> autocomplete="<?= $i === 0 ? 'family-name' : 'off' ?>">
          </label>
        </div>
        <div class="grid2">
          <label>Geboortedatum<?= $i === 0 ? ' *' : '' ?>
            <input type="date" name="persons[<?= $i ?>][birth_date]" value="<?= e($p['birth_date']) ?>" max="<?= e(today()) ?>" <?= $i === 0 ? 'required' : '' ?>>
          </label>
          <label>Voorkeur
            <select name="persons[<?= $i ?>][preferred_category]">
              <option value="">Weet ik nog niet</option>
              <?php foreach (group_categories() as $code => $label): ?>
                <option value="<?= e($code) ?>" <?= $p['preferred_category'] === $code ? 'selected' : '' ?>><?= e($label) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
        </div>
      </fieldset>
    <?php endfor; ?>

    <fieldset>
      <legend>Contactgegevens</legend>
      <div data-show-for="PARENT">
        <div class="grid2">
          <label>Jouw naam (ouder of voogd) *
            <input name="contact_name" value="<?= e($old['contact_name']) ?>" maxlength="120" autocomplete="name">
          </label>
          <label>Relatie tot het kind
            <input name="relationship" value="<?= e($old['relationship']) ?>" maxlength="40" placeholder="bv. moeder, vader, voogd">
          </label>
        </div>
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
      <span>Ik ga akkoord met het reglement van de club en met de verwerking van deze gegevens door HZS voor de ledenadministratie.<?php $pu = trim((string) config('privacy_url', '')); if ($pu !== ''): ?> <a href="<?= e($pu) ?>" target="_blank" rel="noopener">Privacyverklaring</a><?php endif; ?></span>
    </label>

    <div class="actions">
      <button class="btn" type="submit">Inschrijving versturen</button>
      <a class="btn btn-ghost" href="<?= e(url('login')) ?>">Annuleren</a>
    </div>
  </form>
</div>
