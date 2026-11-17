<?php $d = $m['driver'] ?? 'log'; $s = $m['smtp'] ?? []; ?>
<h1>Mailinstellingen</h1>
<p class="muted">Zo verstuurt de app mails: uitnodigingen, wachtwoord vergeten, herinneringen van de trainersplanning en berichten.
  <?php if (($m['source'] ?? '') === 'config'): ?>Nu komen de instellingen uit <code>config.php</code>; wat je hier opslaat, gaat daarvoor.<?php endif; ?></p>

<?php if ($d === 'log'): ?>
  <div class="alert alert-warning"><strong>Testmodus:</strong> er worden geen echte mails verstuurd. Uitnodigingen komen dus niet aan. Kies hieronder SMTP (aanbevolen) of PHP mail().</div>
<?php endif; ?>
<?php if ($errors): ?>
  <div class="alert alert-error"><ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<form class="card form" method="post" action="<?= e(url('mail_settings_save')) ?>" autocomplete="off">
  <?= csrf_field() ?>
  <fieldset>
    <legend>Manier van versturen</legend>
    <label class="check"><input type="radio" name="driver" value="smtp" <?= $d === 'smtp' ? 'checked' : '' ?>> SMTP via je mailserver <span class="muted small">(aanbevolen: komt het best aan)</span></label>
    <label class="check"><input type="radio" name="driver" value="mail" <?= $d === 'mail' ? 'checked' : '' ?>> PHP mail() van de server <span class="muted small">(<?= APP_ROOT === '/app' ? 'werkt <strong>niet</strong> in Coolify/Docker: kies SMTP' : 'eenvoudig op shared hosting; belandt vaker in ongewenste mail' ?>)</span></label>
    <label class="check"><input type="radio" name="driver" value="log" <?= $d === 'log' ? 'checked' : '' ?>> Testmodus <span class="muted small">(niets versturen; enkel lokaal testen)</span></label>
  </fieldset>
  <div class="grid2">
    <label>Afzender (e-mailadres) * <input type="email" name="from_email" value="<?= e((string) ($m['from_email'] ?? '')) ?>" required maxlength="190" placeholder="noreply@khzs.be"></label>
    <label>Afzender (naam) <input name="from_name" value="<?= e((string) ($m['from_name'] ?? 'HZS Clubapp')) ?>" maxlength="80"></label>
  </div>
  <fieldset>
    <legend>SMTP <span class="muted small">(enkel bij SMTP; gegevens van je mailprovider)</span></legend>
    <div class="grid2">
      <label>Server <input name="smtp_host" value="<?= e((string) ($s['host'] ?? '')) ?>" maxlength="120" placeholder="smtp.jouwprovider.be"></label>
      <label>Poort <input name="smtp_port" inputmode="numeric" value="<?= (int) ($s['port'] ?? 587) ?>" maxlength="5"></label>
    </div>
    <label>Beveiliging
      <select name="smtp_encryption">
        <?php foreach (['tls' => 'TLS (STARTTLS), meestal poort 587', 'ssl' => 'SSL, meestal poort 465', 'none' => 'Geen (niet aanbevolen)'] as $k => $l): ?>
          <option value="<?= $k ?>"<?= ($s['encryption'] ?? 'tls') === $k ? ' selected' : '' ?>><?= e($l) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <div class="grid2">
      <label>Gebruiker <input name="smtp_user" value="<?= e((string) ($s['user'] ?? '')) ?>" maxlength="190" autocomplete="off"></label>
      <label>Wachtwoord <input type="password" name="smtp_pass" value="" autocomplete="new-password" placeholder="<?= ($s['pass'] ?? '') !== '' ? '•••••••• (ongewijzigd laten)' : '' ?>"></label>
    </div>
    <p class="muted small">Het wachtwoord wordt versleuteld bewaard. Laat het veld leeg om het bestaande wachtwoord te behouden.</p>
  </fieldset>
  <div class="actions">
    <button class="btn" type="submit">Opslaan</button>
  </div>
</form>

<section class="card">
  <h2>Testmail</h2>
  <p class="muted">Stuurt een testmail naar je eigen adres (<?= e(Auth::user()['email']) ?>) met de opgeslagen instellingen.</p>
  <form method="post" action="<?= e(url('mail_test')) ?>">
    <?= csrf_field() ?><button class="btn btn-ghost" type="submit">Testmail sturen</button>
  </form>
</section>
