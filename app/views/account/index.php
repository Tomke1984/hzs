<h1>Mijn account</h1>
<?php if ($forced): ?>
  <div class="alert alert-warning"><strong>Tweestapsverificatie is verplicht voor beheerders.</strong> Zet ze hieronder aan om verder te kunnen.</div>
<?php endif; ?>

<section class="card">
  <dl class="facts">
    <dt>Naam</dt><dd><?= e($u['display_name']) ?></dd>
    <dt>E-mail</dt><dd><?= e($u['email']) ?></dd>
    <dt>Rol</dt><dd><?= e(implode(', ', $roles)) ?></dd>
  </dl>
</section>

<section class="card" id="agenda">
  <h2>Mijn agenda in je telefoon</h2>
  <p class="muted">Met een persoonlijke link zie je de trainingen, wedstrijden en activiteiten van jezelf of je kinderen in Google Agenda, Apple Agenda of Outlook. Geschrapte trainingen verdwijnen of krijgen de melding “geschrapt”. Deel deze link met niemand: wie ze kent, ziet jouw agenda.</p>
  <?php if ($ical): ?>
    <label>Agenda-link
      <input type="text" readonly value="<?= e($ical) ?>" aria-label="Agenda-link">
    </label>
    <p class="small"><a href="<?= e(preg_replace('#^https?://#', 'webcal://', $ical)) ?>">Direct toevoegen aan je agenda-app</a> · Google Agenda: “Andere agenda’s” → “Via URL” en plak de link.</p>
    <div class="actions">
      <form method="post" action="<?= e(url('account_ical')) ?>" data-confirm="Een nieuwe link maken? De oude link werkt dan niet meer.">
        <?= csrf_field() ?><button class="btn btn-ghost btn-small" type="submit">Nieuwe link maken</button>
      </form>
      <form method="post" action="<?= e(url('account_ical')) ?>">
        <?= csrf_field() ?><input type="hidden" name="do" value="remove"><button class="btn btn-ghost btn-small" type="submit">Link uitschakelen</button>
      </form>
    </div>
  <?php else: ?>
    <form method="post" action="<?= e(url('account_ical')) ?>">
      <?= csrf_field() ?><button class="btn" type="submit">Persoonlijke agenda-link maken</button>
    </form>
  <?php endif; ?>
</section>

<section class="card" id="2fa">
  <h2>Tweestapsverificatie</h2>
  <?php if ($setupSecret): ?>
    <p>1. Open een authenticator-app (Google Authenticator, Microsoft Authenticator, 1Password, Aegis, …) en kies “Account toevoegen” → “Sleutel handmatig invoeren”.</p>
    <p>2. Vul deze sleutel in (tijdgebonden, 6 cijfers):</p>
    <p><code class="big"><?= e(trim(chunk_split($setupSecret, 4, ' '))) ?></code></p>
    <p class="small muted">Of gebruik deze koppeling op je telefoon: <a href="<?= e($otpUri) ?>">sleutel toevoegen in je app</a>.</p>
    <p>3. Vul de code in die de app nu toont.</p>
    <form class="inline-form" method="post" action="<?= e(url('account_2fa_enable')) ?>" autocomplete="off">
      <?= csrf_field() ?>
      <label>Code <input name="code" inputmode="numeric" maxlength="7" required autocomplete="one-time-code"></label>
      <button class="btn" type="submit">Aanzetten</button>
    </form>
  <?php elseif ((int) $u['totp_enabled'] === 1): ?>
    <p><span class="chip ok">Aan</span> Bij het aanmelden vragen we naast je wachtwoord een code uit je authenticator-app.</p>
    <form class="inline-form" method="post" action="<?= e(url('account_2fa_disable')) ?>" data-confirm="Tweestapsverificatie uitzetten?">
      <?= csrf_field() ?>
      <label>Je wachtwoord <input type="password" name="current_password" required autocomplete="current-password"></label>
      <button class="btn btn-ghost" type="submit">Uitzetten</button>
    </form>
  <?php else: ?>
    <p class="muted">Een extra beveiliging bovenop je wachtwoord. Aanbevolen voor beheerders en bestuur, omdat zij persoonsgegevens van minderjarigen zien.</p>
    <form method="post" action="<?= e(url('account_2fa_start')) ?>">
      <?= csrf_field() ?><button class="btn" type="submit">Instellen</button>
    </form>
  <?php endif; ?>
</section>

<section class="card">
  <h2>Wachtwoord wijzigen</h2>
  <form class="form" method="post" action="<?= e(url('account_password')) ?>" autocomplete="off">
    <?= csrf_field() ?>
    <label>Huidig wachtwoord <input type="password" name="current_password" required autocomplete="current-password"></label>
    <div class="grid2">
      <label>Nieuw wachtwoord (min. 10 tekens) <input type="password" name="new_password" minlength="10" required autocomplete="new-password"></label>
      <label>Herhaal nieuw wachtwoord <input type="password" name="new_password2" minlength="10" required autocomplete="new-password"></label>
    </div>
    <div class="actions"><button class="btn" type="submit">Wijzigen</button></div>
  </form>
</section>
