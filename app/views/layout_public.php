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
?><!doctype html>
<html lang="nl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#064ca2">
<title><?= e($title && $title !== 'Hasseltse Zwemvereniging Spartacus' ? $title . ' · ' : '') ?>Hasseltse Zwemvereniging Spartacus</title>
<link rel="icon" type="image/png" href="assets/logo.png">
<link rel="stylesheet" href="assets/style.css">
<script src="assets/app.js" defer></script>
</head>
<body class="public-site">
<header class="topbar">
  <div class="wrap bar">
    <a class="brand" href="<?= e(url('home')) ?>"><img class="logo" src="assets/logo.png" alt="HZS" width="52" height="46"><span>HZS</span></a>
    <button class="btn btn-small btn-ghost menu-btn" type="button" aria-expanded="false" aria-controls="site-nav" hidden>Menu</button>
    <nav class="nav site-nav" id="site-nav">
        <a href="<?= e(url('home')) ?>" <?= $cur === 'home' ? 'aria-current="page"' : '' ?>>Home</a>
        <?php foreach ($top as $t): ?>
          <span class="nav-group">
            <a href="<?= e(url('page', ['slug' => $t['slug']])) ?>"><?= e($t['title']) ?></a>
            <?php if ($t['children']): ?>
              <span class="sub">
              <?php foreach ($t['children'] as $c): ?><a href="<?= e(url('page', ['slug' => $c['slug']])) ?>"><?= e($c['title']) ?></a><?php endforeach; ?>
              </span>
            <?php endif; ?>
          </span>
        <?php endforeach; ?>
        <a href="<?= e(url('trainingstimes')) ?>" <?= $cur === 'trainingstimes' ? 'aria-current="page"' : '' ?>>Trainingsuren</a>
        <a href="<?= e(url('agenda')) ?>" <?= $cur === 'agenda' ? 'aria-current="page"' : '' ?>>Kalender</a>
        <a href="<?= e(url('register')) ?>">Inschrijven</a>
        <a class="nav-login" href="<?= e(url($user ? 'dashboard' : 'login')) ?>"><?= $user ? 'Mijn Clubapp' : 'Aanmelden' ?></a>
    </nav>
  </div>
</header>
<main class="wrap">
  <?php foreach (pull_flash() as [$type, $msg]): ?>
    <div class="alert alert-<?= e($type) ?>" role="status"><?= e($msg) ?></div>
  <?php endforeach; ?>
  <?= $content ?>
</main>
<footer class="wrap foot">Hasseltse Zwemvereniging Spartacus<?php $pu = trim((string) config('privacy_url', '')); if ($pu !== ''): ?> · <a href="<?= e($pu) ?>">Privacy</a><?php endif; ?></footer>
</body>
</html>
