<div class="row-between">
  <h1><?= e($member['first_name']) ?> <?= e($member['last_name']) ?></h1>
  <?php if (Auth::can('members.write')): ?>
    <a class="btn btn-ghost" href="<?= e(url('member_form', ['id' => $member['id']])) ?>">Wijzigen</a>
  <?php endif; ?>
</div>

<?php if ($guardianMissing): ?>
  <div class="alert alert-warning">
    <strong>Ouder of voogd ontbreekt.</strong> Dit lid is jonger dan 18 en moet minstens één ouder of voogd hebben.
    Zolang die ontbreekt, kan het lid niet aan een groep gekoppeld worden.
  </div>
<?php endif; ?>

<section class="card">
  <dl class="facts">
    <dt>Status</dt><dd><span class="chip <?= $member['status'] === 'ACTIVE' ? 'ok' : 'off' ?>"><?= $member['status'] === 'ACTIVE' ? 'Actief' : 'Inactief' ?></span></dd>
    <?php if ($member['birth_date']): ?><dt>Geboortedatum</dt><dd><?= e(fmt_date($member['birth_date'])) ?></dd><?php endif; ?>
    <?php if ($showContact): ?>
      <dt>E-mail</dt><dd><?= e($member['email']) ?: '—' ?></dd>
      <dt>Telefoon</dt><dd><?= e($member['phone']) ?: '—' ?></dd>
    <?php endif; ?>
  </dl>
</section>

<?php if ($showContact): ?>
<?php $collapseParents = !$isMinor && !$parents; ?>
<?php if ($collapseParents): ?>
<details class="card collapsible">
  <summary class="btn btn-ghost btn-small">+ Ouder of voogd toevoegen</summary>
  <p class="muted small">Dit lid is 18 of ouder. Een ouder of voogd is niet verplicht.</p>
  <div class="row-between">
    <h2>Ouders / voogden</h2>
    <?php if (Auth::can('users.manage')): ?>
      <a class="btn btn-small btn-ghost" href="<?= e(url('users')) ?>">Gebruikers beheren</a>
    <?php endif; ?>
  </div>
  <?php if (!$parents): ?>
    <p class="muted">Nog geen ouder of voogd gekoppeld aan dit lid.</p>
  <?php else: ?>
  <div class="table-wrap">
  <table class="table">
    <thead><tr><th>Naam</th><th>Relatie</th><th>E-mail</th><th>Account</th></tr></thead>
    <tbody>
    <?php foreach ($parents as $p): ?>
      <tr>
        <td><?= Auth::can('users.manage')
              ? '<a href="' . e(url('user_form', ['id' => $p['user_id']])) . '">' . e($p['display_name']) . '</a>'
              : e($p['display_name']) ?></td>
        <td><?= e($p['relationship']) ?: '<span class="muted">—</span>' ?></td>
        <td><a href="mailto:<?= e($p['email']) ?>"><?= e($p['email']) ?></a></td>
        <td><span class="chip <?= (int) $p['is_active'] ? 'ok' : 'off' ?>"><?= (int) $p['is_active'] ? 'Actief' : 'Inactief' ?></span></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php endif; ?>

  <?php if (Auth::can('users.manage')): ?>
    <?php if ($parentAccounts): ?>
    <form class="inline-form" method="post" action="<?= e(url('user_child_add')) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="member_id" value="<?= (int) $member['id'] ?>">
      <input type="hidden" name="return" value="member">
      <label>Ouder of voogd koppelen
        <select name="user_id" required>
          <option value="">— kies een ouderaccount —</option>
          <?php foreach ($parentAccounts as $pa): ?>
            <option value="<?= (int) $pa['id'] ?>"><?= e($pa['display_name']) ?> (<?= e($pa['email']) ?>)</option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Relatie (optioneel)
        <input name="relationship" maxlength="40" placeholder="bv. moeder, vader, voogd">
      </label>
      <button class="btn" type="submit">Koppelen</button>
    </form>
    <?php endif; ?>
    <form class="inline-form" method="post" action="<?= e(url('invite_parent')) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="member_id" value="<?= (int) $member['id'] ?>">
      <label>Of nodig een ouder of voogd uit per e-mail
        <input type="email" name="email" required maxlength="190" placeholder="e-mailadres van de ouder">
      </label>
      <label>Naam (optioneel)
        <input name="display_name" maxlength="120" placeholder="de ouder kan dit zelf aanpassen">
      </label>
      <label>Relatie (optioneel)
        <input name="relationship" maxlength="40" placeholder="bv. moeder, vader, voogd">
      </label>
      <button class="btn" type="submit">Uitnodigen</button>
    </form>
    <p class="muted small">De ouder krijgt een mail met een persoonlijke link om zelf een wachtwoord te kiezen en is meteen gekoppeld aan dit lid.</p>
  <?php endif; ?>
</details>
<?php else: ?>
<section class="card">
  <div class="row-between">
    <h2>Ouders / voogden</h2>
    <?php if (Auth::can('users.manage')): ?>
      <a class="btn btn-small btn-ghost" href="<?= e(url('users')) ?>">Gebruikers beheren</a>
    <?php endif; ?>
  </div>
  <?php if (!$parents): ?>
    <p class="muted">Nog geen ouder of voogd gekoppeld aan dit lid.</p>
  <?php else: ?>
  <div class="table-wrap">
  <table class="table">
    <thead><tr><th>Naam</th><th>Relatie</th><th>E-mail</th><th>Account</th></tr></thead>
    <tbody>
    <?php foreach ($parents as $p): ?>
      <tr>
        <td><?= Auth::can('users.manage')
              ? '<a href="' . e(url('user_form', ['id' => $p['user_id']])) . '">' . e($p['display_name']) . '</a>'
              : e($p['display_name']) ?></td>
        <td><?= e($p['relationship']) ?: '<span class="muted">—</span>' ?></td>
        <td><a href="mailto:<?= e($p['email']) ?>"><?= e($p['email']) ?></a></td>
        <td><span class="chip <?= (int) $p['is_active'] ? 'ok' : 'off' ?>"><?= (int) $p['is_active'] ? 'Actief' : 'Inactief' ?></span></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php endif; ?>

  <?php if (Auth::can('users.manage')): ?>
    <?php if ($parentAccounts): ?>
    <form class="inline-form" method="post" action="<?= e(url('user_child_add')) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="member_id" value="<?= (int) $member['id'] ?>">
      <input type="hidden" name="return" value="member">
      <label>Ouder of voogd koppelen
        <select name="user_id" required>
          <option value="">— kies een ouderaccount —</option>
          <?php foreach ($parentAccounts as $pa): ?>
            <option value="<?= (int) $pa['id'] ?>"><?= e($pa['display_name']) ?> (<?= e($pa['email']) ?>)</option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Relatie (optioneel)
        <input name="relationship" maxlength="40" placeholder="bv. moeder, vader, voogd">
      </label>
      <button class="btn" type="submit">Koppelen</button>
    </form>
    <?php endif; ?>
    <form class="inline-form" method="post" action="<?= e(url('invite_parent')) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="member_id" value="<?= (int) $member['id'] ?>">
      <label>Of nodig een ouder of voogd uit per e-mail
        <input type="email" name="email" required maxlength="190" placeholder="e-mailadres van de ouder">
      </label>
      <label>Naam (optioneel)
        <input name="display_name" maxlength="120" placeholder="de ouder kan dit zelf aanpassen">
      </label>
      <label>Relatie (optioneel)
        <input name="relationship" maxlength="40" placeholder="bv. moeder, vader, voogd">
      </label>
      <button class="btn" type="submit">Uitnodigen</button>
    </form>
    <p class="muted small">De ouder krijgt een mail met een persoonlijke link om zelf een wachtwoord te kiezen en is meteen gekoppeld aan dit lid.</p>
  <?php endif; ?>
</section>
<?php endif; ?>
<?php endif; ?>

<?php if (Auth::can('users.manage') && !Guardianship::isMinor($member['birth_date'] ?? null) || (Auth::can('users.manage') && $account)): ?>
<section class="card">
  <h2>Eigen account</h2>
  <?php if ($account): ?>
    <p>Dit lid kan aanmelden met <strong><?= e($account['email']) ?></strong>
      <span class="chip <?= (int) $account['is_active'] ? 'ok' : 'off' ?>"><?= (int) $account['is_active'] ? 'Actief' : 'Inactief' ?></span></p>
    <p><a class="btn btn-small btn-ghost" href="<?= e(url('user_form', ['id' => $account['id']])) ?>">Account beheren</a></p>
  <?php else: ?>
    <p class="muted">Dit lid heeft geen eigen login. Maak een account aan als het lid zelf wil aanmelden om de eigen kalender en groepen te zien.</p>
    <div class="actions">
      <?php if (filter_var((string) $member['email'], FILTER_VALIDATE_EMAIL)): ?>
      <form method="post" action="<?= e(url('invite_member')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="member_id" value="<?= (int) $member['id'] ?>">
        <button class="btn btn-small" type="submit">Uitnodigen per e-mail (<?= e($member['email']) ?>)</button>
      </form>
      <?php endif; ?>
      <a class="btn btn-small btn-ghost" href="<?= e(url('user_form', ['member_id' => $member['id']])) ?>">Account zelf aanmaken</a>
    </div>
  <?php endif; ?>
</section>
<?php elseif (Auth::can('users.manage')): ?>
<p class="muted small">Dit lid is jonger dan 18: de ouder of voogd meldt aan. Een eigen account kan later, via de ouder.</p>
<?php endif; ?>

<section class="card">
  <h2>Groepen</h2>
  <?php if (!$memberships): ?>
    <p class="muted">Nog niet gekoppeld aan een groep.</p>
  <?php else: ?>
  <div class="table-wrap">
  <table class="table">
    <thead><tr><th>Groep</th><th>Van</th><th>Tot</th><?php if ($availableGroups || Auth::can('groups.write')): ?><th></th><?php endif; ?></tr></thead>
    <tbody>
    <?php foreach ($memberships as $ms):
        $open = $ms['end_date'] === null || $ms['end_date'] >= today(); ?>
      <tr>
        <td><?= Auth::can('groups.read') && Scope::canSeeGroup((int) $ms['group_id'])
              ? '<a href="' . e(url('group', ['id' => $ms['group_id']])) . '">' . e($ms['group_name']) . '</a>'
              : e($ms['group_name']) ?></td>
        <td><?= e(fmt_date($ms['start_date'])) ?></td>
        <td><?= $ms['end_date'] ? e(fmt_date($ms['end_date'])) : '<span class="chip ok">lopend</span>' ?></td>
        <?php if (Auth::can('groups.write')): ?>
        <td class="right">
          <?php if ($ms['end_date'] === null): ?>
          <form method="post" action="<?= e(url('membership_end')) ?>" data-confirm="Koppeling met deze groep beëindigen per vandaag?">
            <?= csrf_field() ?>
            <input type="hidden" name="membership_id" value="<?= (int) $ms['id'] ?>">
            <button class="btn btn-small btn-ghost" type="submit">Beëindigen</button>
          </form>
          <?php endif; ?>
        </td>
        <?php endif; ?>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php endif; ?>

  <?php if ($availableGroups && $member['status'] === 'ACTIVE'): ?>
  <form class="inline-form" method="post" action="<?= e(url('membership_add')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="member_id" value="<?= (int) $member['id'] ?>">
    <label>Aan groep koppelen
      <select name="group_id" required>
        <option value="">— kies een groep —</option>
        <?php foreach ($availableGroups as $g): ?>
          <option value="<?= (int) $g['id'] ?>"><?= e($g['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>Vanaf
      <input type="date" name="start_date" value="<?= e(today()) ?>" required>
    </label>
    <button class="btn" type="submit">Koppelen</button>
  </form>
  <?php endif; ?>
</section>

<p><a href="<?= e(url('members')) ?>">← Terug naar de lijst</a></p>
