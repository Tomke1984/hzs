<section class="hero on-deep">
  <div class="wrap hero-inner">
    <h1>Hasseltse Zwemvereniging <span>Spartacus</span></h1>
    <div>
      <?php if ($intro): ?><div class="lede prose"><?= Markup::render((string) $intro['body']) ?></div><?php endif; ?>
      <div class="hero-actions">
        <a class="btn btn-float" href="<?= e(url('swimtest')) ?>">Zwemtest aanvragen</a>
        <a class="btn btn-ghost" href="<?= e(url('trainingstimes')) ?>">Trainingsuren</a>
      </div>
    </div>
  </div>
  <?php if ($lanes): ?>
  <div class="lanes">
    <?php foreach ($lanes as $l): ?>
      <a class="lane" href="<?= e(url('page', ['slug' => $l['slug']])) ?>"><b><?= e($l['title']) ?></b><span><?= e($l['blurb']) ?></span></a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</section>

<div class="wrap section home-cols">
  <section>
    <h2 id="nieuws">Nieuws</h2>
    <?php if (!$posts): ?><p class="empty">Er is nog geen nieuws.</p><?php endif; ?>
    <?php foreach ($posts as $p): ?>
      <article class="news-item" id="bericht-<?= e($p['slug']) ?>">
        <?php if ($p['status'] !== 'PUBLISHED'): ?><p class="alert alert-warning">Concept: dit bericht is nog niet publiek (enkel zichtbaar voor het bestuur).</p><?php endif; ?>
        <h3><a href="<?= e(url('post', ['slug' => $p['slug']])) ?>"><?= e($p['title']) ?></a></h3>
        <time datetime="<?= e(substr((string) ($p['published_at'] ?: $p['created_at']), 0, 10)) ?>"><?= e(fmt_date(substr((string) ($p['published_at'] ?: $p['created_at']), 0, 10))) ?></time>
        <?php if ($p['image']): ?><img class="post-hero" src="<?= e($p['image']) ?>" alt=""><?php endif; ?>
        <div class="prose"><?= Markup::render((string) $p['body']) ?></div>
      </article>
    <?php endforeach; ?>
    <?php if ($pages > 1): ?>
    <nav class="pager" aria-label="Nieuwspagina's">
      <?php if ($page > 1): ?><a class="btn btn-small btn-ghost" href="<?= e(url('home', ['p' => $page - 1])) ?>#nieuws">Nieuwer</a><?php else: ?><span></span><?php endif; ?>
      <span class="muted">Pagina <?= (int) $page ?> van <?= (int) $pages ?></span>
      <?php if ($page < $pages): ?><a class="btn btn-small btn-ghost" href="<?= e(url('home', ['p' => $page + 1])) ?>#nieuws">Ouder</a><?php else: ?><span></span><?php endif; ?>
    </nav>
    <?php endif; ?>
  </section>
  <section>
    <h2>Komende activiteiten</h2>
    <?php if (!$events): ?><p class="empty">Er zijn geen activiteiten gepland.</p><?php endif; ?>
    <div class="tiles">
    <?php foreach ($events as $ev): $off = $ev['status'] === 'CANCELLED'; $d = new DateTimeImmutable(substr($ev['starts_at'], 0, 10)); ?>
      <div class="tile <?= $ev['type_code'] === 'COMPETITION' ? 'competition' : '' ?> <?= $off ? 'is-off' : '' ?>">
        <div class="date"><b><?= e($d->format('j')) ?></b><i><?= e(mb_substr(MONTHS_NL[(int) $d->format('n')], 0, 3)) ?></i></div>
        <div>
          <h3 class="<?= $off ? 'cancelled-text' : '' ?>"><?= e($ev['title']) ?></h3>
          <p><?= e(fmt_time($ev['starts_at'])) ?><?= $ev['location'] ? ' · ' . e($ev['location']) : '' ?></p>
          <?php if ($off): ?><span class="chip off">Geschrapt</span><?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
    </div>
    <p><a href="<?= e(url('agenda')) ?>">Volledige kalender</a></p>
  </section>
</div>
