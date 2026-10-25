<div class="row-between">
  <h1>Groepen</h1>
  <?php if (Auth::can('groups.write')): ?>
    <a class="btn" href="<?= e(url('group_form')) ?>">+ Nieuwe groep</a>
  <?php endif; ?>
</div>

<?php if (!$groups): ?>
  <p class="card muted">Geen groepen gevonden.</p>
<?php else: ?>
<div class="cards">
  <?php foreach ($groups as $g): ?>
    <a class="card card-link <?= (int) $g['is_active'] ? '' : 'is-off' ?>" href="<?= e(url('group', ['id' => $g['id']])) ?>">
      <h2><?= e($g['name']) ?> <?php if (!(int) $g['is_active']): ?><span class="chip off">Inactief</span><?php endif; ?></h2>
      <p class="muted"><?= e($g['description']) ?: '&nbsp;' ?></p>
      <p><strong><?= (int) $g['member_count'] ?></strong> <?= (int) $g['member_count'] === 1 ? 'lid' : 'leden' ?></p>
    </a>
  <?php endforeach; ?>
</div>
<?php endif; ?>
