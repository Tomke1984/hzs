<?php
  // Lege velden: wie mag aanpassen, krijgt een uitnodiging om aan te vullen in plaats van een streepje
  $fillHref = !empty($canEditOwn) ? url('child_form', ['id' => $member['id']]) : (Auth::can('members.write') ? url('member_form', ['id' => $member['id']]) : null);
  if (!empty($minorSelf) && !$fillHref) {
      $fillHref = null; // een minderjarige vult enkel e-mail en telefoon aan via de eigen knop
  }
?>
<div class="row-between">
  <h1><?= e($member['first_name']) ?> <?= e($member['last_name']) ?></h1>
  <?php if (!empty($canEditOwn)): ?>
    <a class="btn btn-ghost" href="<?= e(url('child_form', ['id' => $member['id']])) ?>">Gegevens wijzigen</a>
  <?php endif; ?>
  <?php if (!empty($minorSelf)): ?>
    <a class="btn btn-ghost" href="<?= e(url('child_form', ['id' => $member['id']])) ?>">Mijn e-mail en telefoon wijzigen</a>
  <?php endif; ?>
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
    <?php if ($member['status'] !== 'ACTIVE'): /* enkel afwijkingen tonen */ ?>
    <dt>Status</dt><dd><span class="chip off">Inactief</span>
      <?php if ($member['status_reason'] || $member['status_date']): ?> <span class="muted"><?= e(trim((string) $member['status_reason'] . ($member['status_date'] ? ' sinds ' . fmt_date($member['status_date']) : ''))) ?></span><?php endif; ?></dd>
    <?php endif; ?>
    <?php if ($member['birth_date']): ?><dt>Geboortedatum</dt><dd><?= e(fmt_date($member['birth_date'])) ?></dd><?php endif; ?>
    <?php if ($showOwnContact): ?>
      <dt>E-mail</dt><dd><?= e($member['email']) ?: missing_field($fillHref, 'toevoegen') ?></dd>
      <dt>Telefoon</dt><dd><?= e($member['phone']) ?: missing_field($fillHref, 'toevoegen') ?></dd>
      <dt>Adres</dt><dd><?= e(trim(($member['street'] ?? '') . ', ' . trim(($member['postal_code'] ?? '') . ' ' . ($member['city'] ?? '')), ', ')) ?: missing_field($fillHref, 'toevoegen') ?></dd>
    <?php endif; ?>
    <?php if ($showMedical && ($canEditOwn || $member['emergency_name'] || $member['emergency_phone'] || $member['medical_note'])): ?>
      <dt>Noodcontact</dt><dd><?= e(trim((string) $member['emergency_name'] . ' ' . (string) $member['emergency_phone'])) ?: missing_field($fillHref, 'toevoegen', '#nood', true) ?></dd>
      <dt>Medisch</dt><dd><?= e((string) $member['medical_note']) ?: '<span class="muted">Niets gemeld</span>' ?></dd>
    <?php endif; ?>
  </dl>
</section>

<?php if ($showContact): ?>
<?php $collapseParents = !$isMinor && !$parents; ?>
<?php if ($collapseParents): ?>
<details class="card collapsible parents-collapsed">
  <summary class="add-link">+ Ouder of voogd toevoegen</summary>
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
        <td><?php if (Auth::can('users.manage')): ?>
          <div class="rel-edit">
          <span class="rel-text"><?= e($p['relationship']) ?: '<span class="muted">—</span>' ?></span>
          <button type="button" class="btn btn-small btn-ghost rel-edit-btn" hidden>Bewerken</button>
          <form class="inline-form rel-form" method="post" action="<?= e(url('user_child_relation')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="link_id" value="<?= (int) $p['link_id'] ?>">
            <input type="hidden" name="return" value="member">
            <input name="relationship" value="<?= e($p['relationship']) ?>" maxlength="40" placeholder="bv. moeder, vader, voogd" aria-label="Relatie">
            <button class="btn btn-small" type="submit">Opslaan</button>
            <button type="button" class="btn btn-small btn-ghost rel-cancel" hidden>Annuleren</button>
          </form>
          </div>
          <?php else: ?><?= e($p['relationship']) ?: '<span class="muted">—</span>' ?><?php endif; ?></td>
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
        <td><?php if (Auth::can('users.manage')): ?>
          <div class="rel-edit">
          <span class="rel-text"><?= e($p['relationship']) ?: '<span class="muted">—</span>' ?></span>
          <button type="button" class="btn btn-small btn-ghost rel-edit-btn" hidden>Bewerken</button>
          <form class="inline-form rel-form" method="post" action="<?= e(url('user_child_relation')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="link_id" value="<?= (int) $p['link_id'] ?>">
            <input type="hidden" name="return" value="member">
            <input name="relationship" value="<?= e($p['relationship']) ?>" maxlength="40" placeholder="bv. moeder, vader, voogd" aria-label="Relatie">
            <button class="btn btn-small" type="submit">Opslaan</button>
            <button type="button" class="btn btn-small btn-ghost rel-cancel" hidden>Annuleren</button>
          </form>
          </div>
          <?php else: ?><?= e($p['relationship']) ?: '<span class="muted">—</span>' ?><?php endif; ?></td>
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

<?php if (!empty($childAccountManage)): $ca = $childAccount; ?>
<section class="card" id="eigen-account">
  <h2>Eigen account voor <?= e($member['first_name']) ?></h2>
  <?php if ($ca): ?>
    <p><?= e($member['first_name']) ?> meldt aan met <strong><?= e($ca['email']) ?></strong>
      <?php if (Invites::isPending($ca)): ?><span class="chip warn">Uitnodiging nog niet aanvaard</span><?php elseif ((int) $ca['is_active']): ?><span class="chip ok">Actief</span><?php else: ?><span class="chip off">Inactief</span><?php endif; ?></p>
    <p class="muted small"><?= e($member['first_name']) ?> ziet de eigen kalender en groep en kan zelf het e-mailadres en telefoonnummer aanpassen. Alle andere gegevens beheer jij als ouder.</p>
    <details class="danger-zone">
      <summary>Account van <?= e($member['first_name']) ?> verwijderen…</summary>
      <form method="post" action="<?= e(url('child_account_delete')) ?>" data-confirm="Het eigen account van <?= e($member['first_name']) ?> verwijderen? <?= e($member['first_name']) ?> kan dan niet meer aanmelden; de gegevens van het lid blijven bestaan.">
        <?= csrf_field() ?><input type="hidden" name="member_id" value="<?= (int) $member['id'] ?>">
        <button class="link-danger" type="submit">Account verwijderen</button>
      </form>
    </details>
  <?php else: ?>
    <p class="muted"><?= e($member['first_name']) ?> is jonger dan 18. Met een eigen account kan <?= e($member['first_name']) ?> zelf aanmelden om de eigen kalender te zien en het eigen e-mailadres en telefoonnummer aan te passen. Alle andere gegevens blijven bij jou als ouder.</p>
    <form class="inline-form" method="post" action="<?= e(url('child_account')) ?>">
      <?= csrf_field() ?><input type="hidden" name="member_id" value="<?= (int) $member['id'] ?>">
      <label>E-mailadres van <?= e($member['first_name']) ?> zelf
        <input type="email" name="email" value="<?= e((string) $member['email']) ?>" required maxlength="190" autocomplete="off">
      </label>
      <button class="btn" type="submit">Eigen account aanvragen</button>
    </form>
    <p class="muted small"><?= e($member['first_name']) ?> krijgt een mail om een wachtwoord te kiezen. Gebruik een eigen e-mailadres, niet dat van een ouder.</p>
  <?php endif; ?>
</section>
<?php endif; ?>

<?php if (Auth::can('users.manage') && !Guardianship::isMinor($member['birth_date'] ?? null)): ?>
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
<?php endif; ?>

<section class="card">
  <h2>Groep</h2>
  <?php $cur = $memberships[0] ?? null; ?>
  <?php if (!$cur): ?>
    <p class="muted">Nog niet in een groep.</p>
  <?php else: ?>
    <?php if (!($availableGroups && $member['status'] === 'ACTIVE')): ?><p><?= e($cur['group_name']) ?></p><?php endif; ?>
  <?php endif; ?>

  <?php if ($availableGroups && $member['status'] === 'ACTIVE'): ?>
  <form class="inline-form" method="post" action="<?= e(url('member_group')) ?>" data-autosubmit>
    <?= csrf_field() ?>
    <input type="hidden" name="member_id" value="<?= (int) $member['id'] ?>">
    <label><span<?= $cur ? ' class="sr-only"' : '' ?>><?= $cur ? 'Groep' : 'In groep plaatsen' ?></span>
      <select name="group_id">
        <option value="0">— geen groep —</option>
        <?php foreach ($availableGroups as $g): ?>
          <option value="<?= (int) $g['id'] ?>" <?= $cur && (int) $cur['group_id'] === (int) $g['id'] ? 'selected' : '' ?>><?= e($g['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <button class="btn" type="submit">Opslaan</button>
  </form>
  <?php endif; ?>
  <?php if ($cur && Auth::can('groups.read') && Scope::canSeeGroup((int) $cur['group_id'])): ?>
    <p class="small"><a href="<?= e(url('group', ['id' => $cur['group_id']])) ?>">Naar de groep <?= e($cur['group_name']) ?> →</a></p>
  <?php endif; ?>
</section>

<?php if (Auth::can('members.write')): ?>
<details class="danger-zone">
  <summary><?= e($member['first_name']) ?> definitief verwijderen…</summary>
  <p class="small">Ook de groep, de koppeling met ouders, aanwezigheden en afmeldingen van dit lid verdwijnen. Tijdelijk weg? Zet het lid dan op <em>Inactief</em> via <a href="<?= e(url('member_form', ['id' => $member['id']])) ?>">Wijzigen</a>.</p>
  <form method="post" action="<?= e(url('member_delete')) ?>" data-confirm="<?= e($member['first_name'] . ' ' . $member['last_name']) ?> definitief verwijderen? Dit kan je niet ongedaan maken.">
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $member['id'] ?>">
    <button class="link-danger" type="submit">Verwijderen</button>
  </form>
</details>
<?php endif; ?>
