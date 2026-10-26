<div class="banner on-deep">
  <div class="wrap">
    <p class="crumbs"><a href="<?= e(url('home')) ?>#nieuws">Nieuws</a></p>
    <h1><?= e($p['title']) ?></h1>
  </div>
</div>
<div class="wrap section">
  <article class="news-item">
    <?php if ($p['status'] !== 'PUBLISHED'): ?><p class="alert alert-warning">Concept: dit bericht is nog niet publiek (enkel zichtbaar voor het bestuur).</p><?php endif; ?>
    <time datetime="<?= e(substr((string) ($p['published_at'] ?: $p['created_at']), 0, 10)) ?>"><?= e(fmt_date(substr((string) ($p['published_at'] ?: $p['created_at']), 0, 10))) ?></time>
    <?php if ($p['image']): ?><img class="post-hero" src="<?= e($p['image']) ?>" alt=""><?php endif; ?>
    <?php if (Auth::can('content.manage') && ($mdProblems = Markup::problems((string) $p['body']))): ?><div class="alert alert-warning" role="alert"><strong>Let op (enkel zichtbaar voor beheerders):</strong> deze regel(s) lijken een kop maar worden niet herkend: <?php foreach ($mdProblems as $mp): ?><code><?= e($mp) ?></code> <?php endforeach; ?>. Zet de kop op een eigen regel, begin met <code>## </code> (twee hekjes en een spatie) en laat een lege regel erboven en eronder.</div><?php endif; ?>
    <div class="prose"><?= Markup::render((string) $p['body']) ?></div>
  </article>
  <p><a href="<?= e(url('home')) ?>#nieuws">← Alle nieuws</a></p>
</div>
