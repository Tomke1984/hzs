<h1><?= $user['id'] ? 'Gebruiker wijzigen' : 'Nieuwe gebruiker' ?></h1>

<?php if ($errors): ?>
  <div class="alert alert-error"><ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<form class="card form" method="post" action="<?= e(url('user_save')) ?>" autocomplete="off">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int) $user['id'] ?>">
  <h2>Account</h2>

  <div class="grid2">
    <label>Naam *
      <input name="display_name" value="<?= e($user['display_name']) ?>" maxlength="120" required>
    </label>
    <label>E-mailadres (login) *
      <input type="email" name="email" value="<?= e($user['email']) ?>" maxlength="190" required autocomplete="off">
    </label>
  </div>

  <label><?= $user['id'] ? 'Nieuw wachtwoord (leeg laten om te behouden)' : 'Wachtwoord * (min. 10 tekens)' ?>
    <input type="password" name="password" minlength="10" autocomplete="new-password" <?= $user['id'] ? '' : 'required' ?>>
  </label>

  <fieldset>
    <legend>Rollen *</legend>
    <?php foreach (['PARENT', 'SWIMMER', 'TRAINER', 'ADMIN'] as $code): ?>
      <label class="check">
        <input type="checkbox" name="roles[]" value="<?= $code ?>" <?= in_array($code, $selectedRoles, true) ? 'checked' : '' ?>>
        <?= e(role_label($code)) ?>
        <span class="muted small">
          <?= ['PARENT' => '— ziet de kalender en aanwezigheid van de gekoppelde kinderen',
               'SWIMMER' => '— is zelf lid en ziet de eigen gegevens en groepen (ook voor trainers die zelf lid zijn)',
               'TRAINER' => '— ziet de leden en activiteiten van de toegewezen groepen en registreert aanwezigheid. Is de trainer ook lid? Vink dan ook Zwemmer aan.',
               'ADMIN' => '— heeft clubbrede toegang, inclusief gebruikersbeheer'][$code] ?>
        </span>
      </label>
    <?php endforeach; ?>
  </fieldset>

  <label id="swimmer-member" hidden>Verbonden met lid * (het eigen lidprofiel van deze gebruiker)
    <select name="member_id">
      <option value="">— kies een lid —</option>
      <?php foreach ($members as $m): ?>
        <option value="<?= (int) $m['id'] ?>" <?= (int) $user['member_id'] === (int) $m['id'] ? 'selected' : '' ?>>
          <?= e($m['last_name']) ?> <?= e($m['first_name']) ?><?= e(member_hint($m)) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </label>

  <label class="check">
    <input type="checkbox" name="is_active" value="1" <?= (int) $user['is_active'] ? 'checked' : '' ?> <?= $isSelf ? 'disabled' : '' ?>>
    Account is actief (inactieve gebruikers kunnen niet aanmelden)
  </label>
  <?php if ($isSelf): ?>
    <input type="hidden" name="is_active" value="1">
    <p class="muted small">Dit is je eigen account: je kunt het niet deactiveren of je beheerdersrol intrekken.</p>
  <?php endif; ?>

  <div class="actions">
    <button class="btn" type="submit">Opslaan</button>
    <a class="btn btn-ghost" href="<?= e(url('users')) ?>">Terug naar de lijst</a>
  </div>
</form>

<?php if ($user['id']): ?>

<section class="card">
  <h2>Gekoppelde kinderen</h2>
  <?php if (!in_array('PARENT', $storedRoles, true)): ?>
    <p class="muted">Geef deze gebruiker de rol <strong>Ouder</strong> en sla op om kinderen te kunnen koppelen.</p>
  <?php else: ?>
    <?php if (!$children): ?>
      <p class="muted">Nog geen kind gekoppeld. Zonder koppeling ziet deze ouder niets.</p>
    <?php else: ?>
    <div class="table-wrap">
    <table class="table">
      <thead><tr><th>Naam</th><th>Relatie</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($children as $c): ?>
        <tr>
          <td><a href="<?= e(url('member', ['id' => $c['member_id']])) ?>"><?= e($c['last_name']) ?> <?= e($c['first_name']) ?></a>
            <?php if ($c['status'] !== 'ACTIVE'): ?><span class="chip off">Inactief</span><?php endif; ?></td>
          <td><?= e($c['relationship']) ?: '<span class="muted">—</span>' ?></td>
          <td class="right">
            <form method="post" action="<?= e(url('user_child_remove')) ?>" data-confirm="Koppeling verwijderen? Deze ouder ziet dit kind dan niet meer.">
              <?= csrf_field() ?>
              <input type="hidden" name="link_id" value="<?= (int) $c['id'] ?>">
              <button class="btn btn-small btn-ghost" type="submit">Ontkoppelen</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
    <?php endif; ?>

    <?php if ($linkableMembers): ?>
    <form class="inline-form" method="post" action="<?= e(url('user_child_add')) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="user_id" value="<?= (int) $user['id'] ?>">
      <label>Kind koppelen
        <select name="member_id" required>
          <option value="">— kies een lid —</option>
          <?php foreach ($linkableMembers as $m): ?>
            <option value="<?= (int) $m['id'] ?>"><?= e($m['last_name']) ?> <?= e($m['first_name']) ?><?= e(member_hint($m)) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Relatie (optioneel)
        <input name="relationship" maxlength="40" placeholder="bv. moeder, vader, voogd">
      </label>
      <button class="btn" type="submit">Koppelen</button>
    </form>
    <?php endif; ?>
  <?php endif; ?>
</section>

<section class="card">
  <h2>Groepen als trainer</h2>
  <?php if (!in_array('TRAINER', $storedRoles, true)): ?>
    <p class="muted">Geef deze gebruiker de rol <strong>Trainer</strong> en sla op om groepen toe te wijzen.</p>
  <?php else: ?>
    <?php if (!$trainerGroups): ?>
      <p class="muted">Nog geen groep toegewezen. Zonder groep ziet deze trainer niets.</p>
    <?php else: ?>
    <div class="table-wrap">
    <table class="table">
      <thead><tr><th>Groep</th><th>Van</th><th>Tot</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($trainerGroups as $tg): ?>
        <tr class="<?= $tg['end_date'] !== null ? 'is-off' : '' ?>">
          <td><a href="<?= e(url('group', ['id' => $tg['group_id']])) ?>"><?= e($tg['name']) ?></a></td>
          <td><?= e(fmt_date($tg['start_date'])) ?></td>
          <td><?= $tg['end_date'] ? e(fmt_date($tg['end_date'])) : '<span class="chip ok">lopend</span>' ?></td>
          <td class="right">
            <?php if ($tg['end_date'] === null): ?>
            <form method="post" action="<?= e(url('user_trainer_end')) ?>" data-confirm="Toewijzing beëindigen per vandaag?">
              <?= csrf_field() ?>
              <input type="hidden" name="trainer_group_id" value="<?= (int) $tg['id'] ?>">
              <button class="btn btn-small btn-ghost" type="submit">Beëindigen</button>
            </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
    <?php endif; ?>

    <?php if ($groups): ?>
    <form class="inline-form" method="post" action="<?= e(url('user_trainer_add')) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="user_id" value="<?= (int) $user['id'] ?>">
      <label>Groep toewijzen
        <select name="group_id" required>
          <option value="">— kies een groep —</option>
          <?php foreach ($groups as $g): ?>
            <option value="<?= (int) $g['id'] ?>"><?= e($g['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Vanaf
        <input type="date" name="start_date" value="<?= e(today()) ?>" required>
      </label>
      <button class="btn" type="submit">Toewijzen</button>
    </form>
    <?php endif; ?>
  <?php endif; ?>
</section>

<?php endif; ?>
