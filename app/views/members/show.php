<div class="row-between">
  <h1><?= e($member['first_name']) ?> <?= e($member['last_name']) ?></h1>
  <?php if (Auth::can('members.write')): ?>
    <a class="btn btn-ghost" href="<?= e(url('member_form', ['id' => $member['id']])) ?>">Wijzigen</a>
  <?php endif; ?>
</div>

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
