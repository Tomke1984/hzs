<div class="banner on-deep"><div class="wrap"><h1><?= $state === 'confirmed' ? 'Bedankt!' : ($state === 'invalid' ? 'Link niet geldig' : ($state === 'mailfail' ? 'Mail niet verstuurd' : 'Controleer je mailbox')) ?></h1></div></div>
<div class="wrap page-grid solo">
<div class="form-page">
  <?php if ($state === 'sent'): ?>
    <p>We hebben een mail gestuurd<?= $email !== '' ? ' naar <strong>' . e($email) . '</strong>' : '' ?>. Klik op de link in die mail om je aanvraag te bevestigen. Zonder bevestiging komt je kind niet op de wachtlijst.</p>
    <p class="muted">Niets ontvangen? Kijk ook in je ongewenste mail.</p>
    <?php if (!empty($link)): ?>
      <div class="alert alert-warning"><strong>Testmodus:</strong> er is geen echte mail verstuurd (zie <code>storage/mail</code>). Bevestigingslink: <a href="<?= e($link) ?>"><?= e($link) ?></a></div>
    <?php endif; ?>
  <?php elseif ($state === 'mailfail'): ?>
    <p>Je aanvraag is ontvangen, maar de bevestigingsmail kon niet verstuurd worden. Controleer je e-mailadres en probeer opnieuw, of neem contact op met de club.</p>
    <p><a class="btn" href="<?= e(url('swimtest')) ?>">Opnieuw proberen</a></p>
  <?php elseif ($state === 'confirmed'): ?>
    <p>Je e-mailadres is bevestigd en je kind staat op de wachtlijst voor de zwemtest. We nemen contact met je op zodra de club een zwemtest organiseert.</p>
    <p>Heb je nog een kind? Je kan elk kind apart aanmelden.</p>
    <p><a class="btn btn-ghost" href="<?= e(url('swimtest')) ?>">Nog een kind aanmelden</a> <a class="btn btn-ghost" href="<?= e(url('home')) ?>">Naar de startpagina</a></p>
  <?php else: ?>
    <p>Deze bevestigingslink is verlopen of werd al gebruikt. Is je e-mailadres al bevestigd? Dan hoef je niets meer te doen. Zo niet, vraag de zwemtest dan opnieuw aan.</p>
    <p><a class="btn" href="<?= e(url('swimtest')) ?>">Zwemtest aanvragen</a></p>
  <?php endif; ?>
</div>
</div>
