<h1>Leden uitnodigen</h1>

<?php if ($driver === 'log'): ?>
  <div class="alert alert-warning">
    <strong>Testmodus voor e-mail.</strong> De mailinstelling staat op <code>log</code>: er wordt niets echt verstuurd, de mails komen in <code>storage/mail/</code>.
    Zet <code>mail.driver</code> op <code>smtp</code> of <code>mail</code> in <code>config.php</code> om echt te versturen.
  </div>
<?php endif; ?>

<?php
$nParents = count($plan['parents']);
$nKids = array_sum(array_map('count', $plan['parents']));
$nSelf = count($plan['self']);
?>

<section class="card">
  <p>Iedereen met een e-mailadres krijgt een persoonlijke link om zelf een wachtwoord te kiezen. Wie al een account heeft of al een ouder gekoppeld heeft, wordt overgeslagen. Bij minderjarige leden gebruiken we het e-mailadres van het lid als adres van de ouder.</p>
  <ul>
    <li><strong><?= $nParents ?></strong> <?= $nParents === 1 ? 'ouder-account' : 'ouder-accounts' ?> voor <strong><?= $nKids ?></strong> <?= $nKids === 1 ? 'minderjarig lid' : 'minderjarige leden' ?> (broers en zussen op hetzelfde e-mailadres delen één account)</li>
    <li><strong><?= $nSelf ?></strong> eigen <?= $nSelf === 1 ? 'account' : 'accounts' ?> voor leden van 18+</li>
    <li><strong><?= (int) $plan['skipped'] ?></strong> overgeslagen (hebben al een account of ouder)</li>
    <li><strong><?= count($plan['noEmail']) ?></strong> zonder bruikbaar e-mailadres: kunnen niet uitgenodigd worden</li>
  </ul>
  <?php if ($nParents + $nSelf === 0): ?>
    <p class="muted">Er is niemand om uit te nodigen.</p>
  <?php else: ?>
    <form method="post" action="<?= e(url('invite_bulk')) ?>" data-confirm="Uitnodigingen versturen?">
      <?= csrf_field() ?>
      <?php $n = (int) ($nParents + $nSelf); ?>
      <button class="btn" type="submit"><?= $n ?> <?= $n === 1 ? 'uitnodiging' : 'uitnodigingen' ?> versturen</button>
    </form>
  <?php endif; ?>
</section>

<?php if ($plan['parents']): ?>
<section class="card">
  <h2>Ouders die uitgenodigd worden</h2>
  <div class="table-wrap">
  <table class="table">
    <thead><tr><th>E-mail</th><th>Kinderen</th></tr></thead>
    <tbody>
    <?php foreach ($plan['parents'] as $mail => $kids): ?>
      <tr><td><?= e($mail) ?></td><td><?= e(implode(', ', array_map(fn($k) => $k['first_name'] . ' ' . $k['last_name'], $kids))) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</section>
<?php endif; ?>

<?php if ($plan['noEmail']): ?>
<section class="card">
  <h2>Zonder e-mailadres</h2>
  <p class="muted small">Vul het e-mailadres in bij het lid (Wijzigen) of nodig de ouder uit via de ledenpagina.</p>
  <ul class="list">
    <?php foreach ($plan['noEmail'] as $m): ?>
      <li><a href="<?= e(url('member', ['id' => $m['id']])) ?>"><?= e($m['last_name']) ?> <?= e($m['first_name']) ?></a><span class="muted"><?= e(member_hint($m)) ?></span></li>
    <?php endforeach; ?>
  </ul>
</section>
<?php endif; ?>

<p><a href="<?= e(url('users', ['tab' => 'members'])) ?>">← Terug naar gebruikers</a></p>
