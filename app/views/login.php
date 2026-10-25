<div class="card narrow">
  <h1>Aanmelden</h1>
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
</div>
