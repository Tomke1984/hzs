<div class="card narrow">
  <img class="login-logo" src="assets/logo.png" alt="Hasseltse Zwemvereniging Spartacus" width="109" height="96">
  <h1 class="center">Aanmelden</h1>
  <form method="post" action="<?= e(url('login')) ?>" autocomplete="on">
    <?= csrf_field() ?>
    <label>E-mailadres
      <input type="email" name="email" value="<?= e($email) ?>" required autofocus autocomplete="username">
    </label>
    <label>Wachtwoord
      <input type="password" name="password" required autocomplete="current-password">
    </label>
    <button class="btn" type="submit">Aanmelden</button>
  </form>
  <p class="center"><a href="<?= e(url('forgot')) ?>">Wachtwoord vergeten?</a></p>
  <p class="center">Nog geen lid? <a href="<?= e(url('register')) ?>">Schrijf je in</a></p>
</div>
