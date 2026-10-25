<div class="row-between">
  <h1>Groepen</h1>
  <?php if (Auth::can('groups.write')): ?>
    <a class="btn" href="<?= e(url('group_form')) ?>">+ Nieuwe groep</a>
  <?php endif; ?>
</div>

<?php if (!$groups): ?>
  <p class="card muted">Geen groepen gevonden.</p>
<?php else: ?>
  <?php foreach (group_categories() as $code => $label):
      $list = array_values(array_filter($groups, fn($g) => ($g['category'] ?? 'SWIM_SCHOOL') === $code));
      if (!$list) { continue; } ?>
  <h2 class="section-title"><?= e($label) ?> <span class="muted small">(<?= count($list) ?>)</span></h2>
  <div class="cards cards-compact">
    <?php foreach ($list as $g): ?>
      <a class="card card-link cat-border-<?= e(strtolower($code)) ?> <?= (int) $g['is_active'] ? '' : 'is-off' ?>" href="<?= e(url('group', ['id' => $g['id']])) ?>">
        <h2><?= group_chip($g['name']) ?> <?php if (!(int) $g['is_active']): ?><span class="chip off">Inactief</span><?php endif; ?></h2>
        <p class="muted small"><?= e($g['description']) ?></p>
        <p class="small"><strong><?= (int) $g['member_count'] ?></strong> <?= (int) $g['member_count'] === 1 ? 'lid' : 'leden' ?></p>
      </a>
    <?php endforeach; ?>
  </div>
  <?php endforeach; ?>
<?php endif; ?>
