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
</header>
<div class="app-shell">
  <aside class="sidebar" id="sidebar">
    <a class="brand side-brand" href="<?= e(url('dashboard')) ?>"><img class="logo" src="assets/logo.png" alt="" width="52" height="46"><span>Clubapp</span></a>
    <form class="side-search" method="get" action="<?= e(url('search')) ?>" role="search">
      <input type="search" name="q" placeholder="Zoeken…" aria-label="Zoeken in de app" maxlength="60" value="<?= e((string) ($_GET['r'] ?? '') === 'search' ? (string) ($_GET['q'] ?? '') : '') ?>">
    </form>
    <?php require __DIR__ . '/_nav.php'; ?>
    <?php if (Auth::can('users.manage') && ($build = app_build()) !== ''): ?><p class="side-build" title="Welke code draait er?"><?= e($build) ?></p><?php endif; ?>
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
    <a class="brand" href="<?= e(url('login')) ?>"><img class="logo" src="assets/logo.png" alt="HZS" width="52" height="46"><span>Clubapp</span></a>
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
<footer class="wrap foot">Hasseltse Zwemvereniging Spartacus · Clubapp<?php if (($privacy = privacy_href()) !== ''): ?> · <a href="<?= e($privacy) ?>">Privacybeleid</a><?php endif; ?></footer>
<?php if ($user): $tabCur = (string) ($_GET['r'] ?? 'dashboard'); ?>
  </div>
</div>
<?php
  // Mobiel: vaste tabbalk onderaan (duimbereik) met de meest gebruikte schermen + het volledige menu
  $tabs = [['Overzicht', 'dashboard', ['dashboard'], '<path d="M3 11.5 12 4l9 7.5"/><path d="M5.5 10v9.5h13V10"/>']];
  if (Auth::can('activities.read')) {
      $tabs[] = ['Kalender', 'calendar', ['calendar', 'activity', 'activity_form'], '<rect x="3.5" y="5" width="17" height="15" rx="2"/><path d="M3.5 10h17M8 3v4M16 3v4"/>'];
  }
  if (Coverage::canView()) {
      $tabs[] = ['Planning', 'coverage', ['coverage', 'coverage_bulk_form', 'coverage_settings'], '<rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 4V3h6v1"/><circle cx="12" cy="10" r="2.2"/><path d="M8.5 17c.4-2 1.8-3.2 3.5-3.2s3.1 1.2 3.5 3.2"/>'];
  } elseif (Auth::can('members.read')) {
      $tabs[] = [members_label(), ($single = family_single_member()) ? ['member', ['id' => $single]] : 'members', ['members', 'member', 'child_form'], '<circle cx="9" cy="8" r="3.2"/><path d="M3 19c0-3.3 2.7-5.5 6-5.5s6 2.2 6 5.5"/>'];
  }
  $tabBadge = Coverage::canView() ? count(Coverage::shortages()) : 0;
?>
<nav class="tabbar" aria-label="Snelmenu">
  <?php foreach ($tabs as [$tl, $tr, $troutes, $ticon]): $on = in_array($tabCur, $troutes, true); ?>
    <a class="tab<?= $on ? ' is-active' : '' ?>" href="<?= e(is_array($tr) ? url($tr[0], $tr[1]) : url($tr)) ?>"<?= $on ? ' aria-current="page"' : '' ?>>
      <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?= $ticon ?></svg>
      <span><?= e($tl) ?></span>
      <?php if ($tr === 'coverage' && $tabBadge): ?><span class="tab-badge" aria-label="<?= $tabBadge ?> tekorten"><?= $tabBadge ?></span><?php endif; ?>
    </a>
  <?php endforeach; ?>
  <button class="tab app-menu-btn" type="button" aria-expanded="false" aria-controls="app-nav">
    <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
    <span>Menu</span>
  </button>
</nav>
<?php endif; ?>
</body>
</html>
