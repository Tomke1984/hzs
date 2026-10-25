<div class="row-between">
  <h1><?= e($group['name']) ?> <?php if (!(int) $group['is_active']): ?><span class="chip off">Inactief</span><?php endif; ?></h1>
  <?php if (Auth::can('groups.write')): ?>
    <a class="btn btn-ghost" href="<?= e(url('group_form', ['id' => $group['id']])) ?>">Wijzigen</a>
  <?php endif; ?>
</div>
<p><span class="chip cat-<?= e(strtolower($group['category'] ?? 'SWIM_SCHOOL')) ?>"><?= e(group_category_label($group['category'] ?? null)) ?></span></p>
<?php if ($group['description']): ?><p class="muted"><?= e($group['description']) ?></p><?php endif; ?>

<section class="card">
  <h2>Trainers</h2>
  <?php if (!$trainers): ?>
    <p class="muted">Geen actieve trainer toegewezen.</p>
  <?php else: ?>
    <p><?= e(implode(', ', array_column($trainers, 'display_name'))) ?></p>
  <?php endif; ?>
</section>

<section class="card">
  <h2>Leden</h2>
  <?php if (!$memberships): ?>
    <p class="muted">Nog geen leden in deze groep.</p>
  <?php else: ?>
  <div class="table-wrap">
  <table class="table">
    <thead><tr><?php if (Auth::can('groups.write') && $targets): ?><th class="check-col"><input type="checkbox" id="select-all-members" title="Alles selecteren" aria-label="Alle leden selecteren"></th><?php endif; ?><th>Naam</th><th>Van</th><th>Tot</th><?php if (Auth::can('groups.write')): ?><th></th><?php endif; ?></tr></thead>
    <tbody>
    <?php foreach ($memberships as $ms): ?>
      <tr class="<?= $ms['end_date'] !== null ? 'is-off' : '' ?>">
        <?php if (Auth::can('groups.write') && $targets): ?>
        <td class="check-col"><?php if ($ms['end_date'] === null && $ms['status'] === 'ACTIVE'): ?><input type="checkbox" class="member-pick" form="move-form" name="membership_ids[]" value="<?= (int) $ms['membership_id'] ?>" aria-label="Selecteer <?= e($ms['first_name'] . ' ' . $ms['last_name']) ?>"><?php endif; ?></td>
        <?php endif; ?>
        <td><a href="<?= e(url('member', ['id' => $ms['id']])) ?>"><?= e($ms['last_name']) ?> <?= e($ms['first_name']) ?></a>
          <?php if ($ms['status'] !== 'ACTIVE'): ?><span class="chip off">Inactief</span><?php endif; ?></td>
        <td><?= e(fmt_date($ms['start_date'])) ?></td>
        <td><?= $ms['end_date'] ? e(fmt_date($ms['end_date'])) : '<span class="chip ok">lopend</span>' ?></td>
        <?php if (Auth::can('groups.write')): ?>
        <td class="right">
          <?php if ($ms['end_date'] === null): ?>
          <form method="post" action="<?= e(url('membership_end')) ?>" data-confirm="Koppeling van dit lid met de groep beëindigen per vandaag?">
            <?= csrf_field() ?>
            <input type="hidden" name="membership_id" value="<?= (int) $ms['membership_id'] ?>">
            <input type="hidden" name="return" value="group">
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

  <?php if (Auth::can('groups.write') && $targets && $memberships): ?>
  <form class="inline-form move-form" id="move-form" data-confirm="De geselecteerde leden verplaatsen? Hun huidige koppeling eindigt de dag vóór de gekozen datum." method="post" action="<?= e(url('membership_move')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="from_group_id" value="<?= (int) $group['id'] ?>">
    <label>Geselecteerde leden verplaatsen naar
      <select name="to_group_id" required>
        <option value="">— kies een groep —</option>
        <?php $lastCat = null; foreach ($targets as $t): ?>
          <?php if ($lastCat !== $t['category']): if ($lastCat !== null) { echo '</optgroup>'; } $lastCat = $t['category']; echo '<optgroup label="' . e(group_category_label($t['category'])) . '">'; endif; ?>
          <option value="<?= (int) $t['id'] ?>"><?= e($t['name']) ?></option>
        <?php endforeach; ?>
        <?php if ($lastCat !== null): ?></optgroup><?php endif; ?>
      </select>
    </label>
    <label>Per
      <input type="date" name="start_date" value="<?= e(today()) ?>" required>
    </label>
    <button class="btn" type="submit">Verplaatsen</button>
  </form>
  <?php endif; ?>

  <?php if ($candidates && (int) $group['is_active']): ?>
  <form class="inline-form" method="post" action="<?= e(url('membership_add')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="group_id" value="<?= (int) $group['id'] ?>">
    <input type="hidden" name="return" value="group">
    <label>Lid toevoegen <span class="muted small">(leden zonder groep)</span>
      <select name="member_id" required>
        <option value="">— kies een lid —</option>
        <?php foreach ($candidates as $c): ?>
          <?php $blocked = in_array((int) $c['id'], $blockedIds, true); ?>
          <option value="<?= (int) $c['id'] ?>" <?= $blocked ? 'disabled' : '' ?>><?= e($c['last_name']) ?> <?= e($c['first_name']) ?><?= e(member_hint($c)) ?><?= $blocked ? ' — ouder of voogd ontbreekt' : '' ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>Vanaf
      <input type="date" name="start_date" value="<?= e(today()) ?>" required>
    </label>
    <button class="btn" type="submit">Toevoegen</button>
  </form>
  <?php endif; ?>
</section>

<p><a href="<?= e(url('groups')) ?>">← Terug naar de groepen</a></p>
