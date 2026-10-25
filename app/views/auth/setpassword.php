<div class="card narrow">
  <img class="login-logo" src="assets/logo.png" alt="Hasseltse Zwemvereniging Spartacus" width="109" height="96">
  <?php if (!$found): ?>
    <h1 class="center">Link niet meer geldig</h1>
    <p>Deze link is verlopen of al gebruikt. Vraag een nieuwe aan via <a href="<?= e(url('forgot')) ?>">Wachtwoord vergeten</a>.</p>
  <?php else: ?>
    <h1 class="center"><?= $found['purpose'] === 'INVITE' ? 'Account activeren' : 'Nieuw wachtwoord' ?></h1>
    <p class="muted center"><?= e($found['email']) ?></p>
    <?php if ($errors): ?>
      <div class="alert alert-error"><ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>
    <form method="post" action="<?= e(url('activate')) ?>" autocomplete="off">
      <?= csrf_field() ?>
      <input type="hidden" name="token" value="<?= e($token) ?>">
      <?php if ($found['purpose'] === 'INVITE'): ?>
      <label>Je naam
        <input name="display_name" value="<?= e($name) ?>" maxlength="120" required autocomplete="name">
      </label>
      <?php endif; ?>
      <label>Wachtwoord (min. 10 tekens)
        <input type="password" name="password" minlength="10" required autocomplete="new-password" autofocus>
      </label>
      <label>Herhaal wachtwoord
        <input type="password" name="password2" minlength="10" required autocomplete="new-password">
      </label>
      <button class="btn" type="submit">Opslaan</button>
    </form>
  <?php endif; ?>
</div>
