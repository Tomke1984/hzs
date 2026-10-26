<div class="card narrow">
  <h1 class="center">Verificatiecode</h1>
  <p class="muted center">Open je authenticator-app en vul de code van 6 cijfers in.</p>
  <form method="post" action="<?= e(url('login_2fa')) ?>" autocomplete="off">
    <?= csrf_field() ?>
    <label>Code
      <input name="code" inputmode="numeric" pattern="[0-9 ]{6,7}" maxlength="7" required autofocus autocomplete="one-time-code">
    </label>
    <button class="btn" type="submit">Bevestigen</button>
  </form>
  <p class="center small"><a href="<?= e(url('login')) ?>">Opnieuw beginnen</a></p>
  <p class="muted small center">Telefoon kwijt? Vraag een beheerder om de tweestapsverificatie van je account uit te zetten.</p>
</div>
