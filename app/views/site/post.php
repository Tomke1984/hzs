<article class="card article">
  <?php if ($p['status'] !== 'PUBLISHED'): ?><p class="alert alert-warning">Concept: dit bericht is nog niet publiek.</p><?php endif; ?>
  <h1><?= e($p['title']) ?></h1>
  <p class="muted small"><?= e(fmt_date(substr((string) ($p['published_at'] ?: $p['created_at']), 0, 10))) ?></p>
  <?php if ($p['image']): ?><img class="post-hero" src="<?= e($p['image']) ?>" alt=""><?php endif; ?>
  <div class="prose"><?= Markup::render((string) $p['body']) ?></div>
</article>
<p><a href="<?= e(url('news')) ?>">← Alle nieuws</a></p>
