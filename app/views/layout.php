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
<body>
<?php $user = Auth::user(); ?>
<header class="topbar">
  <div class="wrap bar">
    <a class="brand" href="<?= e(url('dashboard')) ?>"><img class="logo" src="assets/logo.png" alt="HZS" width="52" height="46"><span>Clubapp</span></a>
    <?php if ($user): ?>
    <nav class="nav">
      <a href="<?= e(url('dashboard')) ?>">Overzicht</a>
      <?php if (Auth::can('activities.read')): ?><a href="<?= e(url('calendar')) ?>">Kalender</a><?php endif; ?>
      <?php if (Auth::can('members.read')): ?><a href="<?= e(url('members')) ?>"><?= e(members_label()) ?></a><?php endif; ?>
      <?php if (Auth::can('groups.read')): ?><a href="<?= e(url('groups')) ?>">Groepen</a><?php endif; ?>
      <?php if (Auth::can('registrations.manage')): $nReg = (int) Database::val("SELECT COUNT(*) FROM registrations WHERE status = 'PENDING'"); ?><a href="<?= e(url('registrations')) ?>">Inschrijvingen<?= $nReg ? ' <span class="badge">' . $nReg . '</span>' : '' ?></a><?php endif; ?>
      <?php if (Auth::can('registrations.manage')): $nWait = (int) Database::val("SELECT COUNT(*) FROM swimtest_requests WHERE status = 'WAITING'"); ?><a href="<?= e(url('swimtests')) ?>">Zwemtest<?= $nWait ? ' <span class="badge">' . $nWait . '</span>' : '' ?></a><?php endif; ?>
      <?php if (Auth::can('registrations.manage')): $nMsg = (int) Database::val('SELECT COUNT(*) FROM contact_messages WHERE handled_at IS NULL'); ?><a href="<?= e(url('messages')) ?>">Berichten<?= $nMsg ? ' <span class="badge">' . $nMsg . '</span>' : '' ?></a><?php endif; ?>
      <?php if (Auth::can('content.manage')): ?><a href="<?= e(url('content')) ?>">Website</a><?php endif; ?>
      <?php if (Auth::can('users.manage')): ?><a href="<?= e(url('users')) ?>">Gebruikers</a><?php endif; ?>
    </nav>
    <form class="userbox" method="post" action="<?= e(url('logout')) ?>">
      <?= csrf_field() ?>
      <span class="who"><?= e($user['display_name']) ?></span>
      <button class="btn btn-small btn-ghost" type="submit">Afmelden</button>
    </form>
    <?php endif; ?>
  </div>
</header>
<main class="wrap">
  <?php if ($user && (string) ($_GET['r'] ?? 'dashboard') !== 'dashboard'):
      $backHref = back_url((string) ($_GET['r'] ?? ''), $_GET); ?>
    <p class="backlink"><a href="<?= e($backHref) ?>">← Terug</a></p>
  <?php endif; ?>
  <?php foreach (pull_flash() as [$type, $msg]): ?>
    <div class="alert alert-<?= e($type) ?>" role="status"><?= e($msg) ?></div>
  <?php endforeach; ?>
  <?= $content ?>
</main>
<footer class="wrap foot">Hasseltse Zwemvereniging Spartacus · Clubapp</footer>
</body>
</html>
