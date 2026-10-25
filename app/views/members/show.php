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
    <dt>Lidnummer</dt><dd><?= e($member['member_no']) ?></dd>
    <dt>Status</dt><dd><span class="chip <?= $member['status'] === 'ACTIVE' ? 'ok' : 'off' ?>"><?= $member['status'] === 'ACTIVE' ? 'Actief' : 'Inactief' ?></span></dd>
    <?php if ($member['birth_date']): ?><dt>Geboortedatum</dt><dd><?= e(fmt_date($member['birth_date'])) ?></dd><?php endif; ?>
    <?php if ($showContact): ?>
      <dt>E-mail</dt><dd><?= e($member['email']) ?: '—' ?></dd>
      <dt>Telefoon</dt><dd><?= e($member['phone']) ?: '—' ?></dd>
    <?php endif; ?>
  </dl>
</section>

<?php if ($showContact): ?>
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
    <p class="muted small">Bestaat de ouder nog niet? <a href="<?= e(url('user_form')) ?>">Maak eerst een ouderaccount aan</a> (rol Ouder) en koppel het daarna aan dit lid.</p>
  <?php endif; ?>
</section>
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
