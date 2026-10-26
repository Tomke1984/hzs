<?php
$user = Auth::user();
$menuPages = Database::all("SELECT id, title, slug, parent_id FROM pages WHERE status = 'PUBLISHED' AND in_menu = 1 ORDER BY sort_order, title");
$top = [];
foreach ($menuPages as $mp) {
    if (!$mp['parent_id']) {
        $top[$mp['id']] = $mp + ['children' => []];
    }
}
foreach ($menuPages as $mp) {
    if ($mp['parent_id'] && isset($top[$mp['parent_id']])) {
        $top[$mp['parent_id']]['children'][] = $mp;
    }
}
$cur = (string) ($_GET['r'] ?? 'home');
$curSlug = (string) ($_GET['slug'] ?? '');
$siteName = 'Hasseltse Zwemvereniging Spartacus';
$desc = trim((string) ($metaDesc ?? ''));
if ($desc === '') {
    $desc = 'Zwemschool en competitie in Hasselt: leren zwemmen op je eigen niveau, trainen en wedstrijden zwemmen met Hasseltse Zwemvereniging Spartacus.';
}
$desc = mb_substr(preg_replace('/\s+/', ' ', $desc), 0, 160);
$canonical = Invites::baseUrl() . '/' . ltrim((string) strtok(substr(self_url(), strlen(rtrim(base_path(), '/'))), '?'), '/');
$ogImage = !empty($metaImage) ? Invites::baseUrl() . '/' . ltrim((string) $metaImage, '/') : Invites::baseUrl() . '/assets/logo.png';
$showGallery = GalleryController::hasPublic();
?><!doctype html>
<html lang="nl">
<head>
<meta charset="utf-8">
<base href="<?= e(base_path() . '/') ?>">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#05264d">
<title><?= e($title && $title !== 'Hasseltse Zwemvereniging Spartacus' ? $title . ' · ' : '') ?>Hasseltse Zwemvereniging Spartacus</title>
<meta name="description" content="<?= e($desc) ?>">
<link rel="canonical" href="<?= e($canonical) ?>">
<meta property="og:site_name" content="<?= e($siteName) ?>">
<meta property="og:type" content="<?= $cur === 'post' ? 'article' : 'website' ?>">
<meta property="og:locale" content="nl_BE">
<meta property="og:title" content="<?= e($title ?: $siteName) ?>">
<meta property="og:description" content="<?= e($desc) ?>">
<meta property="og:url" content="<?= e($canonical) ?>">
<meta property="og:image" content="<?= e($ogImage) ?>">
<meta name="twitter:card" content="summary">
<?php if ($cur === 'home'): ?><script type="application/ld+json"><?= json_encode(['@context' => 'https://schema.org', '@type' => 'SportsClub', 'name' => $siteName, 'sport' => 'Swimming', 'url' => Invites::baseUrl() . '/', 'logo' => Invites::baseUrl() . '/assets/logo.png', 'address' => ['@type' => 'PostalAddress', 'addressLocality' => 'Hasselt', 'addressCountry' => 'BE']], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?></script><?php endif; ?>
<link rel="icon" type="image/png" href="assets/logo.png">
<link rel="preload" href="assets/fonts/barlow-condensed-latin-800-italic.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="assets/site.css">
<script src="assets/app.js" defer></script>
</head>
<body class="site">
<a class="skip" href="<?= e(self_url()) ?>#inhoud">Naar de inhoud</a>
<header class="site-head">
  <div class="wrap bar">
    <a class="brand" href="<?= e(url('home')) ?>" aria-label="Hasseltse Zwemvereniging Spartacus"><img src="assets/logo.png" alt="" width="58" height="51"><span><small>Hasseltse Zwemvereniging</small><b>Spartacus</b></span></a>
    <button class="menu-btn" type="button" aria-expanded="false" aria-controls="site-nav" hidden>Menu</button>
    <nav class="site-nav" id="site-nav" aria-label="Hoofdmenu">
      <?php foreach ($top as $t): ?>
        <span class="nav-group">
          <a href="<?= e(url('page', ['slug' => $t['slug']])) ?>" <?= ($cur === 'page' && in_array($curSlug, array_merge([$t['slug']], array_column($t['children'], 'slug')), true)) ? 'aria-current="page"' : '' ?>><?= e($t['title']) ?></a>
          <?php if ($t['children']): ?>
            <span class="sub">
            <?php foreach ($t['children'] as $c): ?><a href="<?= e(url('page', ['slug' => $c['slug']])) ?>"><?= e($c['title']) ?></a><?php endforeach; ?>
            </span>
          <?php endif; ?>
        </span>
      <?php endforeach; ?>
      <a href="<?= e(url('agenda')) ?>" <?= $cur === 'agenda' ? 'aria-current="page"' : '' ?>>Kalender</a>
      <?php if ($showGallery): ?><a href="<?= e(url('gallery')) ?>" <?= $cur === 'gallery' ? 'aria-current="page"' : '' ?>>Foto's</a><?php endif; ?>
      <a class="nav-login" href="<?= e(url($user ? 'dashboard' : 'login')) ?>"><?= $user ? 'Mijn Clubapp' : 'Aanmelden' ?></a>
      <a class="nav-cta" href="<?= e(url('swimtest')) ?>">Zwemtest aanvragen</a>
    </nav>
  </div>
  <div class="rope" aria-hidden="true"></div>
</header>
<main id="inhoud">
  <?php if ($flashes = pull_flash()): ?>
  <div class="wrap">
  <?php foreach ($flashes as [$type, $msg]): ?>
    <div class="alert alert-<?= e($type) ?>" role="status"><?= e($msg) ?></div>
  <?php endforeach; ?>
  </div>
  <?php endif; ?>
  <?= $content ?>
</main>
<footer class="site-foot">
  <div class="rope thin" aria-hidden="true"></div>
  <div class="wrap inner">
    <div><b>Hasseltse Zwemvereniging Spartacus</b>Zwemschool en competitie in Hasselt.</div>
    <div><b>De club</b>
      <?php foreach ($top as $t): ?><a href="<?= e(url('page', ['slug' => $t['slug']])) ?>"><?= e($t['title']) ?></a><?php endforeach; ?>
    </div>
    <div><b>Snel naar</b>
      <a href="<?= e(url('swimtest')) ?>">Zwemtest aanvragen</a>
      <a href="<?= e(url('register')) ?>">Lid worden</a>
      <a href="<?= e(url('trainingstimes')) ?>">Trainingsuren</a>
      <a href="<?= e(url('agenda')) ?>">Kalender</a>
      <?php if ($showGallery): ?><a href="<?= e(url('gallery')) ?>">Foto's</a><?php endif; ?>
      <a href="<?= e(url($user ? 'dashboard' : 'login')) ?>"><?= $user ? 'Mijn Clubapp' : 'Aanmelden' ?></a>
    </div>
  </div>
  <div class="wrap inner" id="nieuwsbrief">
    <div><b>Nieuwsbrief</b>Het laatste nieuws van de club in je mailbox.
      <form class="newsletter" method="post" action="<?= e(url('newsletter_subscribe')) ?>">
        <?= csrf_field() ?>
        <div class="hp" aria-hidden="true"><label>Laat dit leeg <input name="website" tabindex="-1" autocomplete="off"></label></div>
        <label class="sr-only" for="nl-email">E-mailadres</label>
        <input id="nl-email" type="email" name="email" placeholder="je@e-mailadres.be" maxlength="190" required autocomplete="email">
        <button class="btn btn-small" type="submit">Aanmelden</button>
      </form>
    </div>
  </div>
  <div class="wrap legal"><?php $pu = privacy_href(); if ($pu !== ''): ?><a href="<?= e($pu) ?>">Privacy</a><?php endif; ?></div>
</footer>
</body>
</html>
