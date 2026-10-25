<div class="banner on-deep">
  <div class="wrap">
    <?php if ($parent): ?><p class="crumbs"><a href="<?= e(url('page', ['slug' => $parent['slug']])) ?>"><?= e($parent['title']) ?></a></p><?php endif; ?>
    <h1><?= e($p['title']) ?></h1>
  </div>
</div>
<div class="wrap page-grid <?= $side ? '' : 'solo' ?>">
  <?php if ($side): ?>
  <nav class="side" aria-label="In deze rubriek">
    <b><a href="<?= e(url('page', ['slug' => $side['slug']])) ?>" <?= $side['slug'] === $p['slug'] ? 'aria-current="page"' : '' ?>><?= e($side['title']) ?></a></b>
    <?php foreach ($side['items'] as $c): ?><a href="<?= e(url('page', ['slug' => $c['slug']])) ?>" <?= $c['slug'] === $p['slug'] ? 'aria-current="page"' : '' ?>><?= e($c['title']) ?></a><?php endforeach; ?>
  </nav>
  <?php endif; ?>
  <article>
    <?php if ($p['status'] !== 'PUBLISHED'): ?><p class="alert alert-warning">Concept: deze pagina is nog niet publiek.</p><?php endif; ?>
    <div class="prose"><?= Markup::render((string) $p['body']) ?></div>
    <?php if ($goalGroups): ?>
      <nav class="goal-jump" aria-label="Groepen">
        <?php foreach ($goalGroups as $g): ?><a href="#groep-<?= (int) $g['id'] ?>"><?= e($g['name']) ?></a><?php endforeach; ?>
      </nav>
      <?php foreach ($goalGroups as $g): ?>
        <section class="goal" id="groep-<?= (int) $g['id'] ?>">
          <h2><?= e($g['name']) ?></h2>
          <div class="prose"><?= Markup::render((string) $g['goals']) ?></div>
        </section>
      <?php endforeach; ?>
    <?php endif; ?>
  </article>
</div>
