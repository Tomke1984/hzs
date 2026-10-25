<section class="hero card">
  <img src="assets/logo.png" alt="Hasseltse Zwemvereniging Spartacus" width="109" height="96">
  <div>
    <h1>Hasseltse Zwemvereniging Spartacus</h1>
    <?php if ($intro): ?><div class="prose"><?= Markup::render((string) $intro['body']) ?></div><?php endif; ?>
    <p class="actions"><a class="btn" href="<?= e(url('register')) ?>">Inschrijven</a> <a class="btn btn-ghost" href="<?= e(url('trainingstimes')) ?>">Trainingsuren</a></p>
  </div>
</section>

<div class="home-cols">
  <section>
    <h2 class="section-title" id="nieuws">Nieuws</h2>
    <?php if (!$posts): ?><p class="card muted">Nog geen nieuws.</p><?php endif; ?>
    <?php foreach ($posts as $p): ?>
      <article class="card article" id="bericht-<?= e($p['slug']) ?>">
        <?php if ($p['status'] !== 'PUBLISHED'): ?><p class="alert alert-warning">Concept: dit bericht is nog niet publiek (enkel zichtbaar voor het bestuur).</p><?php endif; ?>
        <h3><?= e($p['title']) ?></h3>
        <p class="muted small"><?= e(fmt_date(substr((string) ($p['published_at'] ?: $p['created_at']), 0, 10))) ?></p>
        <?php if ($p['image']): ?><img class="post-hero" src="<?= e($p['image']) ?>" alt=""><?php endif; ?>
        <div class="prose"><?= Markup::render((string) $p['body']) ?></div>
      </article>
    <?php endforeach; ?>
    <?php if ($pages > 1): ?>
    <nav class="cal-nav">
      <?php if ($page > 1): ?><a class="btn btn-small btn-ghost" href="<?= e(url('home', ['p' => $page - 1])) ?>#nieuws">← Nieuwer</a><?php else: ?><span></span><?php endif; ?>
      <span class="muted">Pagina <?= (int) $page ?> van <?= (int) $pages ?></span>
      <?php if ($page < $pages): ?><a class="btn btn-small btn-ghost" href="<?= e(url('home', ['p' => $page + 1])) ?>#nieuws">Ouder →</a><?php else: ?><span></span><?php endif; ?>
    </nav>
    <?php endif; ?>
  </section>
  <section>
    <h2 class="section-title">Komende activiteiten</h2>
    <?php if (!$events): ?><p class="card muted">Geen geplande activiteiten.</p><?php endif; ?>
    <?php foreach ($events as $ev): ?>
      <div class="card event <?= $ev['status'] === 'CANCELLED' ? 'is-off' : '' ?>">
        <strong><?= e(fmt_date(substr($ev['starts_at'], 0, 10))) ?></strong> · <?= e(fmt_time($ev['starts_at'])) ?>
        <div class="<?= $ev['status'] === 'CANCELLED' ? 'cancelled-text' : '' ?>"><?= e($ev['title']) ?></div>
        <?php if ($ev['location']): ?><div class="muted small"><?= e($ev['location']) ?></div><?php endif; ?>
        <?php if ($ev['status'] === 'CANCELLED'): ?><span class="chip off">Geschrapt</span><?php endif; ?>
      </div>
    <?php endforeach; ?>
    <p><a href="<?= e(url('agenda')) ?>">Volledige kalender →</a></p>
  </section>
</div>
