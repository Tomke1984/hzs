<div class="row-between">
  <h1><?= e(members_label()) ?></h1>
  <?php if (Auth::can('members.write')): ?>
    <a class="btn" href="<?= e(url('member_form')) ?>">+ Nieuw lid</a>
  <?php endif; ?>
</div>

<?php if ($missingOnly): ?>
  <div class="alert alert-warning">
    Je ziet enkel actieve leden jonger dan 18 zonder ouder of voogd.
    <a href="<?= e(url('members')) ?>">Toon alle leden</a>
  </div>
<?php endif; ?>

<form class="filters" method="get" action="">
  <input type="search" name="q" value="<?= e($q) ?>" placeholder="Zoek op naam">
  <select name="status" aria-label="Status">
    <option value="ACTIVE" <?= $status === 'ACTIVE' ? 'selected' : '' ?>>Actieve leden</option>
    <option value="INACTIVE" <?= $status === 'INACTIVE' ? 'selected' : '' ?>>Inactieve leden</option>
    <option value="ALL" <?= $status === 'ALL' ? 'selected' : '' ?>>Alle leden</option>
  </select>
  <?= group_filter_fields($cat, $gid, $groupOptions) ?>
  <button class="btn btn-small" type="submit">Zoeken</button>
  <?php if ($cat !== '' || $gid || $q !== '' || $status !== 'ACTIVE'): ?><a href="<?= e(url('members')) ?>">Wis filters</a><?php endif; ?>
</form>

<?php if (!$members): ?>
  <p class="card muted">Geen leden gevonden.</p>
<?php else: ?>
<div class="table-wrap">
<table class="table">
  <thead>
    <tr>
      <th>Naam</th><th>Groepen</th>
      <?php if ($showContact): ?><th>Ouders</th><th>E-mail</th><th>Telefoon</th><?php endif; ?>
      <th>Status</th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($members as $m): ?>
    <tr>
      <td><a href="<?= e(url('member', ['id' => $m['id']])) ?>"><?= e($m['last_name']) ?> <?= e($m['first_name']) ?></a>
        <?php if (in_array((int) $m['id'], $missingIds, true)): ?><span class="chip warn">Ouder ontbreekt</span><?php endif; ?></td>
      <td><?= e(implode(', ', $groupsByMember[$m['id']] ?? [])) ?: '<span class="muted">—</span>' ?></td>
      <?php if ($showContact): ?>
        <td>
          <?php foreach ($parentsByMember[$m['id']] ?? [] as $i => $p): ?>
            <?= $i ? ', ' : '' ?><a href="<?= e(url('user_form', ['id' => $p['user_id']])) ?>"><?= e($p['display_name']) ?></a><?php if ($p['relationship']): ?> <span class="muted small">(<?= e($p['relationship']) ?>)</span><?php endif; ?>
          <?php endforeach; ?>
          <?php if (empty($parentsByMember[$m['id']])): ?><span class="muted">—</span><?php endif; ?>
        </td>
        <td><?= e($m['email']) ?></td><td><?= e($m['phone']) ?></td>
      <?php endif; ?>
      <td><span class="chip <?= $m['status'] === 'ACTIVE' ? 'ok' : 'off' ?>"><?= $m['status'] === 'ACTIVE' ? 'Actief' : 'Inactief' ?></span></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php endif; ?>
