<?php
require dirname(__DIR__) . '/app/bootstrap.php';

if (config('db') === null) {
    redirect('install.php');
}

/*
 * Routetabel: route => [methode => [controller, actie, vereiste permissie]]
 * 'public' = zonder login. null = enkel ingelogd. Alles anders = login + permissie.
 */
$routes = [
    'login'            => ['GET' => ['AuthController', 'showLogin', 'public'], 'POST' => ['AuthController', 'login', 'public']],
    'login_2fa'        => ['GET' => ['AuthController', 'showTwoFactor', 'public'], 'POST' => ['AuthController', 'twoFactor', 'public']],
    'forgot'           => ['GET' => ['AuthController', 'showForgot', 'public'], 'POST' => ['AuthController', 'forgot', 'public']],
    'activate'         => ['GET' => ['AuthController', 'showActivate', 'public'], 'POST' => ['AuthController', 'activate', 'public']],
    'logout'           => ['POST' => ['AuthController', 'logout', null]],
    'dashboard'        => ['GET' => ['DashboardController', 'index', null]],

    'members'          => ['GET' => ['MemberController', 'index', 'members.read']],
    'member'           => ['GET' => ['MemberController', 'show', 'members.read']],
    'member_form'      => ['GET' => ['MemberController', 'form', 'members.write']],
    'member_save'      => ['POST' => ['MemberController', 'save', 'members.write']],

    'groups'           => ['GET' => ['GroupController', 'index', 'groups.read']],
    'group'            => ['GET' => ['GroupController', 'show', 'groups.read']],
    'group_form'       => ['GET' => ['GroupController', 'form', 'groups.write']],
    'group_delete'     => ['POST' => ['GroupController', 'delete', 'groups.write']],
    'group_save'       => ['POST' => ['GroupController', 'save', 'groups.write']],
    'member_days'      => ['POST' => ['GroupController', 'saveDays', 'activities.write']],
    'membership_add'   => ['POST' => ['GroupController', 'addMember', 'groups.write']],
    'member_group'     => ['POST' => ['GroupController', 'setMemberGroup', 'groups.write']],
    'membership_move'  => ['POST' => ['GroupController', 'moveMembers', 'groups.write']],
    'membership_end'   => ['POST' => ['GroupController', 'removeMember', 'groups.write']],

    'users'            => ['GET' => ['UserController', 'index', 'users.manage']],
    'user_form'        => ['GET' => ['UserController', 'form', 'users.manage']],
    'user_save'        => ['POST' => ['UserController', 'save', 'users.manage']],
    'user_2fa_reset'   => ['POST' => ['UserController', 'resetTwoFactor', 'users.manage']],
    'user_child_add'   => ['POST' => ['UserController', 'addChild', 'users.manage']],
    'user_child_relation' => ['POST' => ['UserController', 'updateRelation', 'users.manage']],
    'user_child_remove' => ['POST' => ['UserController', 'removeChild', 'users.manage']],
    'user_trainer_add' => ['POST' => ['UserController', 'addTrainerGroup', 'users.manage']],
    'user_trainer_remove' => ['POST' => ['UserController', 'removeTrainerGroup', 'users.manage']],

    'invite_parent'    => ['POST' => ['InviteController', 'parent', 'users.manage']],
    'invite_member'    => ['POST' => ['InviteController', 'member', 'users.manage']],
    'invite_resend'    => ['POST' => ['InviteController', 'resend', 'users.manage']],
    'invite_bulk'      => ['GET' => ['InviteController', 'bulk', 'users.manage'], 'POST' => ['InviteController', 'bulkSend', 'users.manage']],

    'calendar'         =>['GET' => ['ActivityController', 'calendar', 'activities.read']],
    'activity'         => ['GET' => ['ActivityController', 'show', 'activities.read']],
    'activity_form'    => ['GET' => ['ActivityController', 'form', 'activities.write']],
    'activity_save'    => ['POST' => ['ActivityController', 'save', 'activities.write']],
    'series'           => ['GET' => ['SeriesController', 'index', 'activities.write']],
    'series_week'      => ['GET' => ['SeriesController', 'week', 'activities.write']],
    'series_member_add'    => ['POST' => ['SeriesController', 'memberAdd', 'activities.write']],
    'series_member_remove' => ['POST' => ['SeriesController', 'memberRemove', 'activities.write']],
    'series_extend'    => ['POST' => ['SeriesController', 'extend', 'activities.write']],
    'series_show'      => ['GET' => ['SeriesController', 'show', 'activities.write']],
    'series_form'      => ['GET' => ['SeriesController', 'form', 'activities.write']],
    'series_delete'    => ['POST' => ['SeriesController', 'delete', 'activities.write']],
    'series_save'      => ['POST' => ['SeriesController', 'save', 'activities.write']],
    'series_bulk'      => ['GET' => ['SeriesController', 'bulkForm', 'activities.write'], 'POST' => ['SeriesController', 'bulk', 'activities.write']],
    'occurrence'       => ['POST' => ['SeriesController', 'occurrence', 'activities.write']],
    'coverage'         => ['GET' => ['CoverageController', 'index', null]],
    'coverage_action'  => ['POST' => ['CoverageController', 'action', null]],
    'coverage_settings' => ['GET' => ['CoverageController', 'settings', 'coverage.manage']],
    'coverage_settings_save' => ['POST' => ['CoverageController', 'settingsSave', 'coverage.manage']],
    'coverage_share'   => ['POST' => ['CoverageController', 'share', 'coverage.manage']],
    'coverage_remind'  => ['POST' => ['CoverageController', 'remindNow', 'coverage.manage']],
    'weekplanning'     => ['GET' => ['CoverageController', 'week', 'public']],
    'attendance_save'  => ['POST' => ['ActivityController', 'saveAttendance', 'attendance.write']],
    'absence'          => ['POST' => ['ActivityController', 'absence', 'activities.read']],
    'attendance_report' => ['GET' => ['ReportController', 'attendance', 'attendance.read']],
    'audit'            => ['GET' => ['ReportController', 'audit', 'audit.read']],
    'search'           => ['GET' => ['ReportController', 'search', null]],
    'mail'             => ['GET' => ['MailController', 'compose', 'mail.send']],
    'mail_send'        => ['POST' => ['MailController', 'send', 'mail.send']],
    'mail_edit'        => ['POST' => ['MailController', 'edit', 'mail.send']],
    'account'          => ['GET' => ['AccountController', 'index', null]],
    'account_password' => ['POST' => ['AccountController', 'password', null]],
    'account_ical'     => ['POST' => ['AccountController', 'icalReset', null]],
    'account_2fa_start' => ['POST' => ['AccountController', 'twoFactorStart', null]],
    'account_2fa_enable' => ['POST' => ['AccountController', 'twoFactorEnable', null]],
    'account_2fa_disable' => ['POST' => ['AccountController', 'twoFactorDisable', null]],
    'ical'             => ['GET' => ['AccountController', 'ical', 'public']],
];

// Leesbare adressen: /aanmelden, /app/kalender, ... (zonder ?r= in de URL). De startpagina is de app.
if (!isset($_GET['r'])) {
    $path = trim(rawurldecode((string) parse_url((string) $_SERVER['REQUEST_URI'], PHP_URL_PATH)), '/');
    $b = trim(base_path(), '/');
    if ($b !== '' && str_starts_with($path . '/', $b . '/')) {
        $path = trim(substr($path, strlen($b)), '/');
    }
    if ($path === 'index.php') {
        $path = '';
    }
    if ($path !== '') {
        $byPath = array_flip(PRETTY_ROUTES);
        if (isset($byPath[$path])) {
            $_GET['r'] = $byPath[$path];
        } elseif ($path === 'app' || str_starts_with($path, 'app/')) {
            $hit = app_route_from_path($path, $routes);
            if (!$hit) {
                abort(404, 'Deze pagina bestaat niet.');
            }
            $_GET['r'] = $hit[0];
            $_GET += $hit[1];
        } else {
            abort(404, 'Deze pagina bestaat niet.');
        }
    }
}

$route  = (string) ($_GET['r'] ?? 'dashboard');
$method = $_SERVER['REQUEST_METHOD'] === 'POST' ? 'POST' : ($_SERVER['REQUEST_METHOD'] === 'GET' || $_SERVER['REQUEST_METHOD'] === 'HEAD' ? 'GET' : '');

if (!isset($routes[$route])) {
    abort(404, 'Deze pagina bestaat niet.');
}
if (!isset($routes[$route][$method])) {
    abort(405, 'Deze actie is niet toegestaan via deze methode.');
}

[$class, $action, $permission] = $routes[$route][$method];

if ($method === 'POST') {
    verify_csrf();
}
if ($permission !== 'public') {
    header('Cache-Control: no-store');
    header('X-Robots-Tag: noindex, nofollow');
    Auth::requireLogin();
    if (Auth::mustEnableTwoFactor() && !in_array($route, ['account', 'account_2fa_start', 'account_2fa_enable', 'logout'], true)) {
        flash('warning', 'Beheerders moeten eerst tweestapsverificatie aanzetten.');
        redirect(url('account') . '#2fa');
    }
    if ($permission !== null) {
        Auth::require($permission);
    }
}

Coverage::autoRemind(); // mail als er binnenkort een trainer ontbreekt (hooguit één keer per uur, na het antwoord)
$class::$action();
