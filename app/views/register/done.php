<div class="card narrow">
  <img class="login-logo" src="assets/logo.png" alt="Hasseltse Zwemvereniging Spartacus" width="109" height="96">
  <?php if ($state === 'sent'): ?>
    <h1 class="center">Controleer je mailbox</h1>
    <p>We hebben een mail gestuurd<?= $email !== '' ? ' naar <strong>' . e($email) . '</strong>' : '' ?>. Klik op de link in die mail om je inschrijving te bevestigen. Zonder bevestiging wordt ze niet behandeld.</p>
    <p class="muted small">Niets ontvangen? Kijk ook in je ongewenste mail.</p>
    <?php if (!empty($link)): ?>
      <div class="alert alert-warning"><strong>Testmodus:</strong> er is geen echte mail verstuurd (zie <code>storage/mail</code>). Bevestigingslink: <a href="<?= e($link) ?>"><?= e($link) ?></a></div>
    <?php endif; ?>
  <?php elseif ($state === 'mailfail'): ?>
    <h1 class="center">Mail niet verstuurd</h1>
    <p>Je inschrijving is ontvangen, maar de bevestigingsmail kon niet verstuurd worden. Controleer je e-mailadres en schrijf je opnieuw in, of neem contact op met de club.</p>
    <p class="center"><a class="btn" href="<?= e(url('register')) ?>">Opnieuw proberen</a></p>
  <?php elseif ($state === 'confirmed'): ?>
    <h1 class="center">Bedankt!</h1>
    <p>Je e-mailadres is bevestigd. Het bestuur bekijkt je inschrijving. Zodra die is goedgekeurd, krijg je een mail om je account te activeren.</p>
  <?php else: ?>
    <h1 class="center">Link niet geldig</h1>
    <p>Deze bevestigingslink is verlopen of werd al gebruikt. Is je e-mailadres al bevestigd? Dan hoef je niets meer te doen. Zo niet, schrijf je dan opnieuw in.</p>
    <p class="center"><a class="btn" href="<?= e(url('register')) ?>">Opnieuw inschrijven</a></p>
  <?php endif; ?>
  <p class="center"><a href="<?= e(url('login')) ?>">Naar aanmelden</a></p>
</div>
