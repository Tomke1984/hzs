<h1>Nieuws</h1>
<?php if (!$posts): ?><p class="card muted">Nog geen nieuws.</p><?php endif; ?>
<?php foreach ($posts as $p): ?><?php require __DIR__ . '/_post_card.php'; ?><?php endforeach; ?>
<?php if ($pages > 1): ?>
<nav class="cal-nav">
  <?php if ($page > 1): ?><a class="btn btn-small btn-ghost" href="<?= e(url('news', ['p' => $page - 1])) ?>">← Nieuwere</a><?php else: ?><span></span><?php endif; ?>
  <span class="muted">Pagina <?= (int) $page ?> van <?= (int) $pages ?></span>
  <?php if ($page < $pages): ?><a class="btn btn-small btn-ghost" href="<?= e(url('news', ['p' => $page + 1])) ?>">Oudere →</a><?php else: ?><span></span><?php endif; ?>
</nav>
<?php endif; ?>
