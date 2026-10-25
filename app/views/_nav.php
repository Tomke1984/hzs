<?php
/** Menu van de beheer-app: gegroepeerd, met pictogrammen, tellers en actieve markering. */
$icons = [
    'home' => '<path d="M3 11.5 12 4l9 7.5"/><path d="M5.5 10v9.5h13V10"/>',
    'users' => '<circle cx="9" cy="8" r="3.2"/><path d="M3 19c0-3.3 2.7-5.5 6-5.5s6 2.2 6 5.5"/><path d="M16 5.2a3 3 0 0 1 0 5.6M18 14c2 .6 3 2.3 3 5"/>',
    'groups' => '<rect x="3" y="4" width="7.5" height="7.5" rx="1.5"/><rect x="13.5" y="4" width="7.5" height="7.5" rx="1.5"/><rect x="3" y="14" width="7.5" height="6" rx="1.5"/><rect x="13.5" y="14" width="7.5" height="6" rx="1.5"/>',
    'key' => '<circle cx="8" cy="12" r="3.5"/><path d="M11.5 12H21M17 12v3M20 12v2"/>',
    'calendar' => '<rect x="3.5" y="5" width="17" height="15" rx="2"/><path d="M3.5 10h17M8 3v4M16 3v4"/>',
    'repeat' => '<path d="M17 3l3 3-3 3"/><path d="M4 11V9.5A3.5 3.5 0 0 1 7.5 6H20"/><path d="M7 21l-3-3 3-3"/><path d="M20 13v1.5a3.5 3.5 0 0 1-3.5 3.5H4"/>',
    'inbox' => '<path d="M3 13l3-8h12l3 8v6H3z"/><path d="M3 13h5l1.5 2.5h5L16 13h5"/>',
    'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2"/>',
    'mail' => '<rect x="3" y="5.5" width="18" height="13" rx="2"/><path d="m3.5 7 8.5 6.5L20.5 7"/>',
    'chart' => '<path d="M4 20h17"/><path d="M6.5 20v-6M11.5 20V6M16.5 20v-9"/>',
    'globe' => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c3 3 3 15 0 18M12 3c-3 3-3 15 0 18"/>',
];
$nReg = Auth::can('registrations.manage') ? (int) Database::val("SELECT COUNT(*) FROM registrations WHERE status = 'PENDING'") : 0;
$nWait = Auth::can('registrations.manage') ? (int) Database::val("SELECT COUNT(*) FROM swimtest_requests WHERE status = 'WAITING'") : 0;
$nMsg = Auth::can('registrations.manage') ? (int) Database::val('SELECT COUNT(*) FROM contact_messages WHERE handled_at IS NULL') : 0;
$cur = (string) ($_GET['r'] ?? 'home');
$menu = [
    [null, [
        ['Overzicht', 'dashboard', 'home', null, ['dashboard'], 0],
    ]],
    ['Club', [
        [members_label(), 'members', 'users', 'members.read', ['members', 'member', 'member_form', 'member_save', 'membership_add', 'membership_move', 'membership_end', 'member_group', 'invite_member', 'invite_parent', 'invite_bulk', 'invite_resend', 'user_child_add', 'user_child_remove', 'user_child_relation'], 0],
        ['Groepen', 'groups', 'groups', 'groups.read', ['groups', 'group', 'group_form', 'group_save', 'group_delete'], 0],
        ['Gebruikers', 'users', 'key', 'users.manage', ['users', 'user_form', 'user_save', 'user_trainer_add', 'user_trainer_remove'], 0],
        ['Statistieken', 'stats', 'chart', 'stats.read', ['stats'], 0],
    ]],
    ['Planning', [
        ['Kalender', 'calendar', 'calendar', 'activities.read', ['calendar', 'activity', 'activity_form', 'activity_save', 'attendance_save'], 0],
        ['Trainingsreeksen', 'series', 'repeat', 'activities.write', ['series', 'series_show', 'series_form', 'series_save', 'series_bulk', 'series_delete', 'series_public', 'occurrence'], 0],
    ]],
    ['Aanvragen', [
        ['Inschrijvingen', 'registrations', 'inbox', 'registrations.manage', ['registrations', 'registration', 'registration_approve', 'registration_reject', 'registration_resend'], $nReg],
        ['Zwemtest', 'swimtests', 'clock', 'registrations.manage', ['swimtests', 'swimtest_show', 'swimtest_status', 'swimtest_note', 'swimtest_enroll'], $nWait],
        ['Berichten', 'messages', 'mail', 'registrations.manage', ['messages', 'message_handle', 'message_delete'], $nMsg],
    ]],
    ['Website', [
        ['Beheren', 'content', 'globe', 'content.manage', ['content', 'post_form', 'post_save', 'post_delete', 'page_form', 'page_save', 'page_delete', 'page_cleanup', 'media_upload', 'media_delete'], 0],
    ]],
];
?>
<nav class="side-nav" id="app-nav" aria-label="Hoofdmenu">
  <?php foreach ($menu as [$label, $items]):
      $items = array_values(array_filter($items, fn($i) => $i[3] === null || Auth::can($i[3])));
      if (!$items) { continue; } ?>
    <div class="side-section">
      <?php if ($label): ?><div class="side-label"><?= e($label) ?></div><?php endif; ?>
      <?php foreach ($items as [$text, $route, $icon, , $routes, $count]): $active = in_array($cur, $routes, true); ?>
        <a class="side-link<?= $active ? ' is-active' : '' ?>" href="<?= e(url($route)) ?>"<?= $active ? ' aria-current="page"' : '' ?>>
          <svg class="side-icon" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?= $icons[$icon] ?></svg>
          <span><?= e($text) ?></span>
          <?php if ($count): ?><span class="badge" aria-label="<?= (int) $count ?> openstaand"><?= (int) $count ?></span><?php endif; ?>
        </a>
      <?php endforeach; ?>
      <?php if ($label === 'Website' && Auth::can('content.manage')): ?>
        <a class="side-link side-link-ext" href="<?= e(url('home')) ?>" target="_blank" rel="noopener">
          <svg class="side-icon" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 4h6v6M20 4l-9 9M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5"/></svg>
          <span>Website bekijken</span>
        </a>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</nav>
