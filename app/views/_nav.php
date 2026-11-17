<?php
/** Menu van de beheer-app: gegroepeerd, met pictogrammen, tellers en actieve markering. */
$icons = [
    'home' => '<path d="M3 11.5 12 4l9 7.5"/><path d="M5.5 10v9.5h13V10"/>',
    'users' => '<circle cx="9" cy="8" r="3.2"/><path d="M3 19c0-3.3 2.7-5.5 6-5.5s6 2.2 6 5.5"/><path d="M16 5.2a3 3 0 0 1 0 5.6M18 14c2 .6 3 2.3 3 5"/>',
    'groups' => '<rect x="3" y="4" width="7.5" height="7.5" rx="1.5"/><rect x="13.5" y="4" width="7.5" height="7.5" rx="1.5"/><rect x="3" y="14" width="7.5" height="6" rx="1.5"/><rect x="13.5" y="14" width="7.5" height="6" rx="1.5"/>',
    'key' => '<circle cx="8" cy="12" r="3.5"/><path d="M11.5 12H21M17 12v3M20 12v2"/>',
    'calendar' => '<rect x="3.5" y="5" width="17" height="15" rx="2"/><path d="M3.5 10h17M8 3v4M16 3v4"/>',
    'repeat' => '<path d="M17 3l3 3-3 3"/><path d="M4 11V9.5A3.5 3.5 0 0 1 7.5 6H20"/><path d="M7 21l-3-3 3-3"/><path d="M20 13v1.5a3.5 3.5 0 0 1-3.5 3.5H4"/>',
    'shield' => '<path d="M12 3 5 6v5.5c0 4.2 2.9 7.4 7 9 4.1-1.6 7-4.8 7-9V6z"/><path d="m9 12 2.2 2.2L15 10"/>',
    'send' => '<path d="M21 4 3 11l6.5 2.5L12 20z"/><path d="m9.5 13.5 11-9.5"/>',
    'mail' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m4 7 8 6 8-6"/>',
    'report' => '<path d="M6 3h9l4 4v14H6z"/><path d="M14 3v5h5M9 13h6M9 17h6"/>',
    'roster' => '<rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 4V3h6v1"/><circle cx="12" cy="10" r="2.2"/><path d="M8.5 17c.4-2 1.8-3.2 3.5-3.2s3.1 1.2 3.5 3.2"/>',
];
$coverView = Coverage::canView();
$nShort = $coverView ? count(Coverage::shortages()) : 0;
$cur = (string) ($_GET['r'] ?? 'dashboard');
$menu = [
    [null, [
        ['Overzicht', 'dashboard', 'home', null, ['dashboard'], 0],
    ]],
    ['Club', [
        [members_label(), 'members', 'users', 'members.read', ['members', 'member', 'child_form', 'member_form', 'member_save', 'membership_add', 'membership_move', 'membership_end', 'member_group', 'invite_member', 'invite_parent', 'invite_bulk', 'invite_resend', 'user_child_add', 'user_child_remove', 'user_child_relation'], 0],
        ['Groepen', 'groups', 'groups', 'groups.read', ['groups', 'group', 'group_form', 'group_save', 'group_delete'], 0],
        ['Gebruikers', 'users', 'key', 'users.manage', ['users', 'user_form', 'user_save', 'user_trainer_add', 'user_trainer_remove'], 0],
        ['Auditlog', 'audit', 'shield', 'audit.read', ['audit'], 0],
        ['Mailinstellingen', 'mail_settings', 'mail', 'users.manage', ['mail_settings', 'mail_settings_save', 'mail_test'], 0],
    ]],
    ['Planning', [
        ['Kalender', 'calendar', 'calendar', 'activities.read', ['calendar', 'activity', 'activity_form', 'activity_save', 'attendance_save'], 0],
        ['Trainingsreeksen', 'series', 'repeat', 'activities.write', ['series', 'series_show', 'series_form', 'series_save', 'series_bulk', 'series_delete', 'occurrence'], 0],
        ['Trainersplanning', 'coverage', 'roster', 'coverage.view', ['coverage', 'coverage_settings', 'coverage_action', 'coverage_bulk', 'coverage_bulk_form', 'coverage_settings_save', 'coverage_share', 'coverage_remind'], $nShort],
        ['Aanwezigheden', 'attendance_report', 'report', feature('attendance') ? 'attendance.read' : 'feature.off', ['attendance_report'], 0],
        ['Bericht sturen', 'mail', 'send', 'mail.send', ['mail', 'mail_send', 'mail_edit'], 0],
    ]],
];
?>
<nav class="side-nav" id="app-nav" aria-label="Hoofdmenu">
  <?php foreach ($menu as [$label, $items]):
      $items = array_values(array_filter($items, fn($i) => $i[3] === null || ($i[3] === 'coverage.view' ? $coverView : ($i[3] !== 'feature.off' && Auth::can($i[3])))));
      if (!$items) { continue; } ?>
    <div class="side-section">
      <?php if ($label): ?><div class="side-label"><?= e($label) ?></div><?php endif; ?>
      <?php foreach ($items as [$text, $route, $icon, , $routes, $count]): $active = in_array($cur, $routes, true); ?>
        <a class="side-link<?= $active ? ' is-active' : '' ?>" href="<?= e($route === 'members' && ($single = family_single_member()) ? url('member', ['id' => $single]) : url($route)) ?>"<?= $active ? ' aria-current="page"' : '' ?>>
          <svg class="side-icon" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?= $icons[$icon] ?></svg>
          <span><?= e($text) ?></span>
          <?php if ($count): ?><span class="badge" aria-label="<?= (int) $count ?> openstaand"><?= (int) $count ?></span><?php endif; ?>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endforeach; ?>
</nav>
