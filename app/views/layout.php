<!doctype html>
<html lang="nl">
<head>
<meta charset="utf-8">
<base href="<?= e(base_path() . '/') ?>">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#064ca2">
<title><?= e($title ? $title . ' · ' : '') ?><?= e(config('app_name', 'HZS Clubapp')) ?></title>
<link rel="icon" type="image/png" href="assets/logo.png">
<link rel="stylesheet" href="assets/style.css">
<script src="assets/app.js" defer></script>
</head>
<body class="<?= Auth::user() ? 'has-sidebar' : 'guest' ?>">
<?php $user = Auth::user(); ?>
<noscript><link rel="stylesheet" href="assets/nojs.css"></noscript>
<?php if ($user): ?>
<header class="app-topbar">
  <a class="brand" href="<?= e(url('dashboard')) ?>"><img class="logo" src="assets/logo.png" alt="" width="52" height="46"><span>Clubapp</span></a>
  <button class="app-menu-btn" type="button" aria-expanded="false" aria-controls="app-nav">Menu</button>
</header>
<div class="app-shell">
  <aside class="sidebar" id="sidebar">
    <a class="brand side-brand" href="<?= e(url('dashboard')) ?>"><img class="logo" src="assets/logo.png" alt="" width="52" height="46"><span>Clubapp</span></a>
    <form class="side-search" method="get" action="<?= e(url('search')) ?>" role="search">
      <input type="search" name="q" placeholder="Zoeken…" aria-label="Zoeken in de app" maxlength="60" value="<?= e((string) ($_GET['r'] ?? '') === 'search' ? (string) ($_GET['q'] ?? '') : '') ?>">
    </form>
    <?php require __DIR__ . '/_nav.php'; ?>
    <?php if (Auth::can('stats.read') && ($build = app_build()) !== ''): ?><p class="side-build" title="Welke code draait er?"><?= e($build) ?></p><?php endif; ?>
    <form class="side-user" method="post" action="<?= e(url('logout')) ?>">
      <?= csrf_field() ?>
      <div class="side-who"><span class="avatar" aria-hidden="true"><?= e(mb_strtoupper(mb_substr((string) $user['display_name'], 0, 1))) ?></span><a class="side-account" href="<?= e(url('account')) ?>" title="Mijn account"><?= e($user['display_name']) ?></a></div>
      <button class="btn btn-small btn-ghost" type="submit">Afmelden</button>
    </form>
  </aside>
  <div class="app-overlay" hidden></div>
  <div class="app-content">
<?php else: ?>
<header class="topbar">
  <div class="wrap bar">
    <a class="brand" href="<?= e(url('home')) ?>"><img class="logo" src="assets/logo.png" alt="HZS" width="52" height="46"><span>Clubapp</span></a>
  </div>
</header>
<?php endif; ?>
<main class="wrap">
  <?php $curRoute = (string) ($_GET['r'] ?? 'dashboard');
      if ($user) { remember_list_context($curRoute, $_GET); } ?>
  <?php if ($user && !in_array($curRoute, BACK_TOP_ROUTES, true)):
      $backHref = back_url($curRoute, $_GET); ?>
    <p class="backlink"><a href="<?= e($backHref) ?>">← Terug</a></p>
  <?php endif; ?>
  <?php foreach (pull_flash() as [$type, $msg]): ?>
    <div class="alert alert-<?= e($type) ?>" role="status"><?= e($msg) ?></div>
  <?php endforeach; ?>
  <?= $content ?>
</main>
<footer class="wrap foot">Hasseltse Zwemvereniging Spartacus · Clubapp</footer>
<?php if ($user): ?>
  </div>
</div>
<?php endif; ?>
</body>
</html>
