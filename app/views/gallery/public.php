<div class="banner on-deep">
  <div class="wrap">
    <?php if ($album): ?><p class="crumbs"><a href="<?= e(url('gallery')) ?>">Foto's</a></p><?php endif; ?>
    <h1><?= $album ? e($album['title']) : 'Foto\'s' ?></h1>
  </div>
</div>
<div class="wrap section">
<?php if ($album): ?>
  <?php if ($album['description']): ?><p><?= e($album['description']) ?></p><?php endif; ?>
  <div class="photo-grid">
    <?php foreach ($photos as $p): ?>
      <figure><a href="<?= e($p['path']) ?>" target="_blank" rel="noopener"><img src="<?= e($p['path']) ?>" alt="<?= e((string) ($p['caption'] ?? '')) ?>" loading="lazy"></a>
        <?php if ($p['caption']): ?><figcaption><?= e($p['caption']) ?></figcaption><?php endif; ?></figure>
    <?php endforeach; ?>
  </div>
  <p><a href="<?= e(url('gallery')) ?>">← Alle albums</a></p>
<?php elseif (!$albums): ?>
  <p class="empty">Er zijn nog geen foto's.</p>
<?php else: ?>
  <div class="photo-grid albums">
    <?php foreach ($albums as $a): ?>
      <figure><a href="<?= e(url('gallery', ['album' => $a['id']])) ?>"><img src="<?= e((string) $a['cover']) ?>" alt="" loading="lazy"></a>
        <figcaption><a href="<?= e(url('gallery', ['album' => $a['id']])) ?>"><strong><?= e($a['title']) ?></strong></a> <span class="muted">· <?= (int) $a['n'] ?> foto's</span></figcaption></figure>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
</div>
