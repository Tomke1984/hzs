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
    'forgot'           => ['GET' => ['AuthController', 'showForgot', 'public'], 'POST' => ['AuthController', 'forgot', 'public']],
    'activate'         => ['GET' => ['AuthController', 'showActivate', 'public'], 'POST' => ['AuthController', 'activate', 'public']],
    'register'         => ['GET' => ['RegistrationController', 'showForm', 'public'], 'POST' => ['RegistrationController', 'submit', 'public']],
    'register_confirm' => ['GET' => ['RegistrationController', 'confirm', 'public']],
    'logout'           => ['POST' => ['AuthController', 'logout', null]],
    'dashboard'        => ['GET' => ['DashboardController', 'index', null]],

    'home'             => ['GET' => ['SiteController', 'home', 'public']],
    'news'             => ['GET' => ['SiteController', 'news', 'public']],
    'post'             => ['GET' => ['SiteController', 'news', 'public']],
    'page'             => ['GET' => ['SiteController', 'page', 'public']],
    'agenda'           => ['GET' => ['SiteController', 'agenda', 'public']],
    'trainingstimes'   => ['GET' => ['SiteController', 'trainingTimes', 'public']],

    'content'          => ['GET' => ['ContentController', 'index', 'content.manage']],
    'post_form'        => ['GET' => ['ContentController', 'postForm', 'content.manage']],
    'post_save'        => ['POST' => ['ContentController', 'postSave', 'content.manage']],
    'post_delete'      => ['POST' => ['ContentController', 'postDelete', 'content.manage']],
    'page_form'        => ['GET' => ['ContentController', 'pageForm', 'content.manage']],
    'page_save'        => ['POST' => ['ContentController', 'pageSave', 'content.manage']],
    'content_seed'     => ['POST' => ['ContentController', 'seedPages', 'content.manage']],
    'page_delete'      => ['POST' => ['ContentController', 'pageDelete', 'content.manage']],
    'media_upload'     => ['POST' => ['ContentController', 'mediaUpload', 'content.manage']],
    'media_delete'     => ['POST' => ['ContentController', 'mediaDelete', 'content.manage']],

    'members'          => ['GET' => ['MemberController', 'index', 'members.read']],
    'member'           => ['GET' => ['MemberController', 'show', 'members.read']],
    'member_form'      => ['GET' => ['MemberController', 'form', 'members.write']],
    'member_save'      => ['POST' => ['MemberController', 'save', 'members.write']],

    'groups'           => ['GET' => ['GroupController', 'index', 'groups.read']],
    'group'            => ['GET' => ['GroupController', 'show', 'groups.read']],
    'group_form'       => ['GET' => ['GroupController', 'form', 'groups.write']],
    'group_delete'     => ['POST' => ['GroupController', 'delete', 'groups.write']],
    'group_save'       => ['POST' => ['GroupController', 'save', 'groups.write']],
    'membership_add'   => ['POST' => ['GroupController', 'addMember', 'groups.write']],
    'member_group'     => ['POST' => ['GroupController', 'setMemberGroup', 'groups.write']],
    'membership_move'  => ['POST' => ['GroupController', 'moveMembers', 'groups.write']],
    'membership_end'   => ['POST' => ['GroupController', 'removeMember', 'groups.write']],

    'users'            => ['GET' => ['UserController', 'index', 'users.manage']],
    'user_form'        => ['GET' => ['UserController', 'form', 'users.manage']],
    'user_save'        => ['POST' => ['UserController', 'save', 'users.manage']],
    'user_child_add'   => ['POST' => ['UserController', 'addChild', 'users.manage']],
    'user_child_relation' => ['POST' => ['UserController', 'updateRelation', 'users.manage']],
    'user_child_remove' => ['POST' => ['UserController', 'removeChild', 'users.manage']],
    'user_trainer_add' => ['POST' => ['UserController', 'addTrainerGroup', 'users.manage']],
    'user_trainer_remove' => ['POST' => ['UserController', 'removeTrainerGroup', 'users.manage']],

    'invite_parent'    => ['POST' => ['InviteController', 'parent', 'users.manage']],
    'invite_member'    => ['POST' => ['InviteController', 'member', 'users.manage']],
    'invite_resend'    => ['POST' => ['InviteController', 'resend', 'users.manage']],
    'invite_bulk'      => ['GET' => ['InviteController', 'bulk', 'users.manage'], 'POST' => ['InviteController', 'bulkSend', 'users.manage']],

    'registrations'    => ['GET' => ['RegistrationController', 'index', 'registrations.manage']],
    'registration'     => ['GET' => ['RegistrationController', 'show', 'registrations.manage']],
    'registration_approve' => ['POST' => ['RegistrationController', 'approve', 'registrations.manage']],
    'registration_reject'  => ['POST' => ['RegistrationController', 'reject', 'registrations.manage']],
    'registration_resend'  => ['POST' => ['RegistrationController', 'resend', 'registrations.manage']],

    'calendar'         =>['GET' => ['ActivityController', 'calendar', 'activities.read']],
    'activity'         => ['GET' => ['ActivityController', 'show', 'activities.read']],
    'activity_form'    => ['GET' => ['ActivityController', 'form', 'activities.write']],
    'activity_save'    => ['POST' => ['ActivityController', 'save', 'activities.write']],
    'series'           => ['GET' => ['SeriesController', 'index', 'activities.write']],
    'series_show'      => ['GET' => ['SeriesController', 'show', 'activities.write']],
    'series_form'      => ['GET' => ['SeriesController', 'form', 'activities.write']],
    'series_save'      => ['POST' => ['SeriesController', 'save', 'activities.write']],
    'series_bulk'      => ['GET' => ['SeriesController', 'bulkForm', 'activities.write'], 'POST' => ['SeriesController', 'bulk', 'activities.write']],
    'occurrence'       => ['POST' => ['SeriesController', 'occurrence', 'activities.write']],
    'attendance_save'  => ['POST' => ['ActivityController', 'saveAttendance', 'attendance.write']],
];

$route  = (string) ($_GET['r'] ?? (Auth::user() ? 'dashboard' : 'home'));
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
