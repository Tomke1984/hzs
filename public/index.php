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
    'logout'           => ['POST' => ['AuthController', 'logout', null]],
    'dashboard'        => ['GET' => ['DashboardController', 'index', null]],

    'members'          => ['GET' => ['MemberController', 'index', 'members.read']],
    'member'           => ['GET' => ['MemberController', 'show', 'members.read']],
    'member_form'      => ['GET' => ['MemberController', 'form', 'members.write']],
    'member_save'      => ['POST' => ['MemberController', 'save', 'members.write']],

    'groups'           => ['GET' => ['GroupController', 'index', 'groups.read']],
    'group'            => ['GET' => ['GroupController', 'show', 'groups.read']],
    'group_form'       => ['GET' => ['GroupController', 'form', 'groups.write']],
    'group_save'       => ['POST' => ['GroupController', 'save', 'groups.write']],
    'membership_add'   => ['POST' => ['GroupController', 'addMember', 'groups.write']],
    'membership_end'   => ['POST' => ['GroupController', 'endMembership', 'groups.write']],

    'calendar'         => ['GET' => ['ActivityController', 'calendar', 'activities.read']],
    'activity'         => ['GET' => ['ActivityController', 'show', 'activities.read']],
    'activity_form'    => ['GET' => ['ActivityController', 'form', 'activities.write']],
    'activity_save'    => ['POST' => ['ActivityController', 'save', 'activities.write']],
    'attendance_save'  => ['POST' => ['ActivityController', 'saveAttendance', 'attendance.write']],
];

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
    Auth::requireLogin();
    if ($permission !== null) {
        Auth::require($permission);
    }
}

$class::$action();
