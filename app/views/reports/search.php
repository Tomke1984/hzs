<h1>Zoeken</h1>
<form class="filters" method="get" action="<?= e(url('search')) ?>">
  <input type="search" name="q" value="<?= e($q) ?>" placeholder="Zoek leden, groepen, activiteiten…" autofocus>
  <button class="btn btn-small" type="submit">Zoeken</button>
</form>
<?php if (mb_strlen($q) < 2): ?>
  <p class="muted">Typ minstens 2 tekens.</p>
<?php elseif (!$total): ?>
  <p class="card muted">Niets gevonden voor “<?= e($q) ?>”.</p>
<?php else: ?>
  <?php if ($res['members']): ?><section class="card"><h2>Leden</h2><ul class="list">
    <?php foreach ($res['members'] as $m): ?><li><a href="<?= e(url('member', ['id' => $m['id']])) ?>"><?= e($m['last_name']) ?> <?= e($m['first_name']) ?></a> <?php if ($m['status'] !== 'ACTIVE'): ?><span class="chip off">Inactief</span><?php endif; ?></li><?php endforeach; ?></ul></section><?php endif; ?>
  <?php if ($res['groups']): ?><section class="card"><h2>Groepen</h2><ul class="list">
    <?php foreach ($res['groups'] as $g): ?><li><a href="<?= e(url('group', ['id' => $g['id']])) ?>"><?= e($g['name']) ?></a> <span class="muted small"><?= e(group_category_label($g['category'])) ?></span></li><?php endforeach; ?></ul></section><?php endif; ?>
  <?php if ($res['activities']): ?><section class="card"><h2>Activiteiten</h2><ul class="list">
    <?php foreach ($res['activities'] as $a): ?><li><a href="<?= e(url('activity', ['id' => $a['id']])) ?>"><?= e($a['title']) ?></a> <span class="muted"><?= e(fmt_dt($a['starts_at'])) ?></span><?= $a['status'] === 'CANCELLED' ? ' <span class="chip off">Geschrapt</span>' : '' ?></li><?php endforeach; ?></ul></section><?php endif; ?>
  <?php if ($res['users']): ?><section class="card"><h2>Gebruikers</h2><ul class="list">
    <?php foreach ($res['users'] as $u): ?><li><a href="<?= e(url('user_form', ['id' => $u['id']])) ?>"><?= e($u['display_name']) ?></a> <span class="muted"><?= e($u['email']) ?></span></li><?php endforeach; ?></ul></section><?php endif; ?>
  <?php if ($res['pages'] || $res['posts']): ?><section class="card"><h2>Website</h2><ul class="list">
    <?php foreach ($res['pages'] as $p): ?><li><a href="<?= e(url('page_form', ['id' => $p['id']])) ?>"><?= e($p['title']) ?></a> <span class="muted small">pagina</span></li><?php endforeach; ?>
    <?php foreach ($res['posts'] as $p): ?><li><a href="<?= e(url('post_form', ['id' => $p['id']])) ?>"><?= e($p['title']) ?></a> <span class="muted small">nieuwsbericht</span></li><?php endforeach; ?></ul></section><?php endif; ?>
<?php endif; ?>
