<div class="row-between">
  <h1>Website</h1>
  <a class="btn btn-ghost" href="<?= e(url('home')) ?>" target="_blank" rel="noopener">Website bekijken ↗</a>
</div>
<div class="tabs" role="tablist">
  <a href="<?= e(url('content', ['tab' => 'news'])) ?>" <?= $tab === 'news' ? 'aria-current="page"' : '' ?>>Nieuws (<?= count($posts) ?>)</a>
  <a href="<?= e(url('content', ['tab' => 'pages'])) ?>" <?= $tab === 'pages' ? 'aria-current="page"' : '' ?>>Pagina's (<?= count($pages) ?>)</a>
  <a href="<?= e(url('content', ['tab' => 'media'])) ?>" <?= $tab === 'media' ? 'aria-current="page"' : '' ?>>Afbeeldingen (<?= count($media) ?>)</a>
</div>

<?php if ($tab === 'news'): ?>
  <p><a class="btn" href="<?= e(url('post_form')) ?>">+ Nieuw bericht</a></p>
  <?php if (!$posts): ?><p class="card muted">Nog geen berichten.</p><?php else: ?>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Titel</th><th>Status</th><th>Gepubliceerd</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($posts as $p): ?>
      <tr>
        <td><a href="<?= e(url('post_form', ['id' => $p['id']])) ?>"><?= e($p['title']) ?></a></td>
        <td><span class="chip <?= $p['status'] === 'PUBLISHED' ? 'ok' : 'off' ?>"><?= $p['status'] === 'PUBLISHED' ? 'Gepubliceerd' : 'Concept' ?></span></td>
        <td><?= $p['published_at'] ? e(fmt_date(substr($p['published_at'], 0, 10))) : '—' ?></td>
        <td class="right">
          <a class="btn btn-small btn-ghost" href="<?= e(url('post', ['slug' => $p['slug']])) ?>">Bekijken</a>
          <form method="post" action="<?= e(url('post_delete')) ?>" data-confirm="Dit bericht definitief verwijderen?">
            <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
            <button class="btn btn-small btn-ghost" type="submit">Verwijderen</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>

<?php elseif ($tab === 'pages'): ?>
  <div class="actions-inline">
    <a class="btn" href="<?= e(url('page_form')) ?>">+ Nieuwe pagina</a>
  </div>
  <?php $nIssues = count($check['issues']); ?>
  <div class="card">
    <strong>Controle:</strong>
    <?php if (!$nIssues): ?>alle pagina's zijn in orde (geen lege pagina's, kapotte links of onbereikbare pagina's).
    <?php else: ?><?= $nIssues ?> pagina's met opmerkingen (zie kolom <em>Controle</em>).
      <?php if ($check['removable']): ?>
      <form class="inline-form" method="post" action="<?= e(url('page_cleanup')) ?>" data-confirm="<?= count($check['removable']) ?> verouderde of lege pagina's definitief verwijderen?">
        <?= csrf_field() ?><button class="btn btn-small" type="submit">Overbodige pagina's opruimen (<?= count($check['removable']) ?>)</button>
      </form>
      <?php endif; ?>
    <?php endif; ?>
  </div>
  <p class="muted small">Pagina's met het menu aangevinkt staan in de bovenste balk van de website; subpagina's verschijnen onder hun hoofdpagina. Een pagina met adres <code>home</code> wordt als introtekst op de startpagina getoond.</p>
  <?php if (!$pages): ?><p class="card muted">Nog geen pagina's.</p><?php else: ?>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Titel</th><th>Onder</th><th>Menu</th><th>Status</th><th>Controle</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($pages as $p): ?>
      <tr>
        <td><?= $p['parent_id'] ? '↳ ' : '' ?><a href="<?= e(url('page_form', ['id' => $p['id']])) ?>"><?= e($p['title']) ?></a> <span class="muted small">(<?= e($p['slug']) ?>)</span><?php if (isset(ContentController::DYNAMIC[$p['slug']])): ?> <span class="chip">Automatisch</span><?php endif; ?></td>
        <td><?= e($p['parent_title'] ?? '') ?: '—' ?></td>
        <td><?= (int) $p['in_menu'] ? 'Ja' : 'Nee' ?></td>
        <td><span class="chip <?= $p['status'] === 'PUBLISHED' ? 'ok' : 'off' ?>"><?= $p['status'] === 'PUBLISHED' ? 'Gepubliceerd' : 'Concept' ?></span></td>
        <td class="small"><?php foreach ($check['issues'][(int) $p['id']] ?? [] as [$lvl, $txt]): ?><div class="<?= $lvl === 'warn' ? '' : 'muted' ?>"><?= $lvl === 'warn' ? '⚠ ' : '' ?><?= e($txt) ?></div><?php endforeach; ?></td>
        <td class="right">
          <a class="btn btn-small btn-ghost" href="<?= e(url('page', ['slug' => $p['slug']])) ?>">Bekijken</a>
          <?php if (!isset(ContentController::DYNAMIC[$p['slug']])): ?><form method="post" action="<?= e(url('page_delete')) ?>" data-confirm="Deze pagina definitief verwijderen?">
            <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
            <button class="btn btn-small btn-ghost" type="submit">Verwijderen</button>
          </form><?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>

<?php else: ?>
  <form class="card form" method="post" action="<?= e(url('media_upload')) ?>" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <label>Afbeelding uploaden (jpg, png, gif of webp, max. 5 MB)
      <input type="file" name="file" accept="image/jpeg,image/png,image/gif,image/webp" required>
    </label>
    <button class="btn" type="submit">Uploaden</button>
  </form>
  <?php if (!$media): ?><p class="card muted">Nog geen afbeeldingen.</p><?php endif; ?>
  <div class="thumb-grid">
  <?php foreach ($media as $m): ?>
    <div class="card">
      <img src="<?= e($m['path']) ?>" alt="">
      <code>![<?= e(pathinfo($m['original_name'], PATHINFO_FILENAME)) ?>](<?= e($m['path']) ?>)</code>
      <form method="post" action="<?= e(url('media_delete')) ?>" data-confirm="Deze afbeelding verwijderen? Teksten die ze gebruiken tonen ze dan niet meer.">
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
        <button class="btn btn-small btn-ghost" type="submit">Verwijderen</button>
      </form>
    </div>
  <?php endforeach; ?>
  </div>
<?php endif; ?>
