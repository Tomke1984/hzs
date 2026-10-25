<div class="row-between">
  <h1>Gebruikers</h1>
  <a class="btn" href="<?= e(url('user_form')) ?>">+ Nieuwe gebruiker</a>
</div>
<p class="muted">Beheer hier wie kan aanmelden, welke rol iemand heeft en welke kinderen of groepen bij een account horen.</p>

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
      <td><span class="chip <?= (int) $u['is_active'] ? 'ok' : 'off' ?>"><?= (int) $u['is_active'] ? 'Actief' : 'Inactief' ?></span></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
