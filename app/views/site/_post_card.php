<article class="card post-card">
  <?php if (!empty($p['image'])): ?><a href="<?= e(url('post', ['slug' => $p['slug']])) ?>"><img class="post-thumb" src="<?= e($p['image']) ?>" alt="" loading="lazy"></a><?php endif; ?>
  <div>
    <h3><a href="<?= e(url('post', ['slug' => $p['slug']])) ?>"><?= e($p['title']) ?></a></h3>
    <p class="muted small"><?= e(fmt_date(substr((string) $p['published_at'], 0, 10))) ?></p>
    <?php if (!empty($p['summary'])): ?><p><?= e($p['summary']) ?></p><?php endif; ?>
  </div>
</article>
