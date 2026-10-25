<div class="card narrow">
  <img class="login-logo" src="assets/logo.png" alt="Hasseltse Zwemvereniging Spartacus" width="109" height="96">
  <h1 class="center">Wachtwoord vergeten</h1>
  <?php if ($sent): ?>
    <p class="alert alert-success">Bestaat er een account met dit e-mailadres, dan krijg je zo meteen een mail met een link om een nieuw wachtwoord te kiezen. Controleer ook je ongewenste mail.</p>
    <p class="center"><a href="<?= e(url('login')) ?>">Terug naar aanmelden</a></p>
  <?php else: ?>
  <form method="post" action="<?= e(url('forgot')) ?>">
    <?= csrf_field() ?>
    <label>E-mailadres
      <input type="email" name="email" value="<?= e($email) ?>" required autofocus autocomplete="username">
    </label>
    <button class="btn" type="submit">Stuur me een link</button>
  </form>
  <p class="center"><a href="<?= e(url('login')) ?>">Terug naar aanmelden</a></p>
  <?php endif; ?>
</div>
