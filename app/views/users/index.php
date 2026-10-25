<div class="row-between">
  <h1>Gebruikers</h1>
  <div class="actions" style="margin:0">
    <a class="btn btn-ghost" href="<?= e(url('invite_bulk')) ?>">Leden uitnodigen</a>
    <a class="btn" href="<?= e(url('user_form')) ?>">+ Nieuwe gebruiker</a>
  </div>
</div>
<p class="muted">Een account is enkel nodig om aan te melden. Leden zijn niet automatisch gebruikers: kinderen loggen meestal niet zelf in, hun ouder doet dat. Maak een account voor een lid aan via de ledenpagina.</p>

<nav class="tabs" aria-label="Soort gebruikers">
  <a href="<?= e(url('users', ['tab' => 'staff'])) ?>" <?= $tab === 'staff' ? 'aria-current="page"' : '' ?>>Beheer en trainers <span class="chip"><?= (int) $counts['staff'] ?></span></a>
  <a href="<?= e(url('users', ['tab' => 'members'])) ?>" <?= $tab === 'members' ? 'aria-current="page"' : '' ?>>Ouders en zwemmers <span class="chip"><?= (int) $counts['members'] ?></span></a>
</nav>

<form class="filters" method="get" action="">
  <input type="hidden" name="r" value="users">
  <input type="hidden" name="tab" value="<?= e($tab) ?>">
  <input type="search" name="q" value="<?= e($q) ?>" placeholder="Zoek op naam of e-mail">
  <button class="btn btn-small" type="submit">Zoeken</button>
  <?php if ($q !== ''): ?><a href="<?= e(url('users', ['tab' => $tab])) ?>">Wis zoekopdracht</a><?php endif; ?>
</form>

<?php if (!$users): ?>
  <p class="card muted">Geen gebruikers gevonden.</p>
<?php else: ?>
<div class="table-wrap">
<table class="table">
  <thead><tr><th>Naam</th><th>E-mail</th><th>Rollen</th><th>Gekoppeld</th><th>Status</th></tr></thead>
  <tbody>
  <?php foreach ($users as $u):
      $roles = $rolesByUser[$u['id']] ?? []; ?>
    <tr class="<?= (int) $u['is_active'] ? '' : 'is-off' ?>">
      <td><a href="<?= e(url('user_form', ['id' => $u['id']])) ?>"><?= e($u['display_name']) ?></a></td>
      <td><?= e($u['email']) ?></td>
      <td><?php foreach ($roles as $r): ?><span class="chip"><?= e(role_label($r)) ?></span> <?php endforeach; ?></td>
      <td>
        <?php if (in_array('PARENT', $roles, true)): ?>
          <?= (int) ($childCount[$u['id']] ?? 0) ?> <?= (int) ($childCount[$u['id']] ?? 0) === 1 ? 'kind' : 'kinderen' ?>
          <?php if (!(int) ($childCount[$u['id']] ?? 0)): ?><span class="chip off">nog geen kind</span><?php endif; ?>
        <?php elseif ($u['m_last']): ?>
          <?= e($u['m_first'] . ' ' . $u['m_last']) ?>
        <?php else: ?><span class="muted">—</span><?php endif; ?>
      </td>
      <td><span class="chip <?= (int) $u['is_active'] ? 'ok' : 'off' ?>"><?= (int) $u['is_active'] ? 'Actief' : 'Inactief' ?></span>
        <?php if (Invites::isPending($u)): ?><span class="chip warn">Nog niet geactiveerd</span><?php endif; ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php endif; ?>
