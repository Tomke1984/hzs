<article class="card article">
  <?php if ($p['status'] !== 'PUBLISHED'): ?><p class="alert alert-warning">Concept: deze pagina is nog niet publiek.</p><?php endif; ?>
  <?php if ($parent): ?><p class="muted small"><a href="<?= e(url('page', ['slug' => $parent['slug']])) ?>"><?= e($parent['title']) ?></a> ›</p><?php endif; ?>
  <h1><?= e($p['title']) ?></h1>
  <div class="prose"><?= Markup::render((string) $p['body']) ?></div>
  <?php if ($children): ?>
    <h2>Meer in deze rubriek</h2>
    <ul><?php foreach ($children as $c): ?><li><a href="<?= e(url('page', ['slug' => $c['slug']])) ?>"><?= e($c['title']) ?></a></li><?php endforeach; ?></ul>
  <?php endif; ?>
</article>
