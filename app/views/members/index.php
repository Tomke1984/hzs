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

<?php if (!is_family_view()): /* ouders en zwemmers: enkel de eigen kinderen, geen zoeken of filters */ ?>
<form class="filters" data-auto method="get" action="">
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
<?php endif; ?>

<?php if (!$members): ?>
  <p class="card muted">Geen leden gevonden.</p>
<?php else: ?>
<div class="table-wrap">
<table class="table member-table">
  <thead>
    <tr>
      <th>Naam</th><th>Groepen</th>
      <?php if ($showContact): ?><th>Ouders</th><th class="col-contact">E-mail</th><th class="col-contact">Telefoon</th><?php endif; ?>
      <th class="col-status">Status</th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($members as $m): ?>
    <tr>
      <td class="col-name"><a href="<?= e(url('member', ['id' => $m['id']])) ?>"><?= e($m['first_name']) ?> <?= e($m['last_name']) ?></a>
        <?php if (in_array((int) $m['id'], $missingIds, true)): ?><span class="chip warn">Ouder ontbreekt</span><?php endif; ?></td>
      <td class="col-groups"><?= implode(', ', array_map('group_chip', $groupsByMember[$m['id']] ?? [])) ?: '<span class="muted">—</span>' ?></td>
      <?php if ($showContact): ?>
        <td class="col-parents">
          <?php foreach ($parentsByMember[$m['id']] ?? [] as $i => $p): ?>
            <?= $i ? ', ' : '' ?><a href="<?= e(url('user_form', ['id' => $p['user_id']])) ?>"><?= e($p['display_name']) ?></a><?php if ($p['relationship']): ?> <span class="muted small">(<?= e($p['relationship']) ?>)</span><?php endif; ?>
          <?php endforeach; ?>
          <?php if (empty($parentsByMember[$m['id']])): ?><span class="muted">—</span><?php endif; ?>
        </td>
        <td class="col-contact"><?= e($m['email']) ?></td><td class="col-contact"><?= e($m['phone']) ?></td>
      <?php endif; ?>
      <td class="col-status"><?php if ($m['status'] !== 'ACTIVE'): /* enkel afwijkingen tonen */ ?><span class="chip off"><?= e($m['status_reason'] ?: 'Inactief') ?></span><?php if ($m['status_date']): ?> <span class="muted small"><?= e(fmt_date($m['status_date'])) ?></span><?php endif; ?><?php endif; ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php endif; ?>
