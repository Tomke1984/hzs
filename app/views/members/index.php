<div class="row-between">
  <h1><?= e(members_label()) ?></h1>
  <?php if (Auth::can('members.write')): ?>
    <a class="btn" href="<?= e(url('member_form')) ?>">+ Nieuw lid</a>
  <?php endif; ?>
</div>

<form class="filters" method="get" action="">
  <input type="hidden" name="r" value="members">
  <input type="search" name="q" value="<?= e($q) ?>" placeholder="Zoek op naam of lidnummer">
  <select name="status" aria-label="Status">
    <option value="ACTIVE" <?= $status === 'ACTIVE' ? 'selected' : '' ?>>Actieve leden</option>
    <option value="INACTIVE" <?= $status === 'INACTIVE' ? 'selected' : '' ?>>Inactieve leden</option>
    <option value="ALL" <?= $status === 'ALL' ? 'selected' : '' ?>>Alle leden</option>
  </select>
  <button class="btn btn-small" type="submit">Zoeken</button>
</form>

<?php if (!$members): ?>
  <p class="card muted">Geen leden gevonden.</p>
<?php else: ?>
<div class="table-wrap">
<table class="table">
  <thead>
    <tr>
      <th>Nr.</th><th>Naam</th><th>Groepen</th>
      <?php if ($showContact): ?><th>E-mail</th><th>Telefoon</th><?php endif; ?>
      <th>Status</th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($members as $m): ?>
    <tr>
      <td><?= e($m['member_no']) ?></td>
      <td><a href="<?= e(url('member', ['id' => $m['id']])) ?>"><?= e($m['last_name']) ?> <?= e($m['first_name']) ?></a></td>
      <td><?= e(implode(', ', $groupsByMember[$m['id']] ?? [])) ?: '<span class="muted">—</span>' ?></td>
      <?php if ($showContact): ?>
        <td><?= e($m['email']) ?></td><td><?= e($m['phone']) ?></td>
      <?php endif; ?>
      <td><span class="chip <?= $m['status'] === 'ACTIVE' ? 'ok' : 'off' ?>"><?= $m['status'] === 'ACTIVE' ? 'Actief' : 'Inactief' ?></span></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php endif; ?>
