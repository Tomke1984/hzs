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
    <?php if ($timetable): ?>
      <?php $intro = trim((string) preg_split('/^##\s/m', Markup::clean((string) $p['body']), 2)[0]); if ($intro !== ''): ?><div class="prose"><?= Markup::render($intro) ?></div><?php endif; ?>
      <?php $groups = $timetable; $code = 'COMPETITION'; require __DIR__ . '/_slotcards.php'; ?>
      <p class="muted small">Wijzigingen of geschrapte trainingen staan in de <a href="<?= e(url('agenda')) ?>">kalender</a>.</p>
    <?php else: ?>
    <div class="prose"><?= Markup::render((string) $p['body']) ?></div>
    <?php endif; ?>
    <?php if ($contact): $old = $contact['old']; ?>
      <section class="contact-form" id="bericht">
        <h2>Stuur een bericht</h2>
        <?php if ($contact['errors']): ?>
          <div class="alert alert-error" role="alert"><ul><?php foreach ($contact['errors'] as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>
        <form class="form" method="post" action="<?= e(url('contact_send')) ?>#bericht">
          <?= csrf_field() ?>
          <div class="hp" aria-hidden="true"><label>Laat dit leeg <input name="website" tabindex="-1" autocomplete="off"></label></div>
          <label>Waarover gaat je vraag?
            <select name="topic">
              <?php foreach (SiteController::TOPICS as $code => $label): ?>
                <option value="<?= e($code) ?>" <?= $old['topic'] === $code ? 'selected' : '' ?>><?= e($label) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <div class="grid2">
            <label>Voornaam *
              <input name="first_name" value="<?= e($old['first_name']) ?>" maxlength="80" required autocomplete="given-name">
            </label>
            <label>Achternaam *
              <input name="last_name" value="<?= e($old['last_name']) ?>" maxlength="80" required autocomplete="family-name">
            </label>
          </div>
          <label>E-mailadres *
            <input type="email" name="email" value="<?= e($old['email']) ?>" maxlength="190" required autocomplete="email">
          </label>
          <label>Bericht *
            <textarea name="message" rows="6" maxlength="3000" required><?= e($old['message']) ?></textarea>
          </label>
          <p class="muted small">We gebruiken je gegevens enkel om op je bericht te antwoorden.<?php $pu = privacy_href(); if ($pu !== ''): ?> <a href="<?= e($pu) ?>" target="_blank" rel="noopener">Privacyverklaring</a><?php endif; ?></p>
          <p><button class="btn" type="submit">Bericht versturen</button></p>
        </form>
      </section>
    <?php endif; ?>
    <?php if ($goalGroups): ?>
      <nav class="goal-jump" aria-label="Groepen">
        <?php foreach ($goalGroups as $g): ?><a href="<?= e(self_url()) ?>#groep-<?= (int) $g['id'] ?>"><?= e($g['name']) ?></a><?php endforeach; ?>
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
