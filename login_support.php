<?php

/*
 * ITFlow - Support sign-in from this ITFlow's manager (functions/managed.php)
 *
 * The manager's "Sign in as support" lands here with a link it signed with its own key - for
 * this instance, good for two minutes and once. The person is signed in as the instance's
 * support user: an admin with no password anyone knows, no MFA (their sign-in to the manager
 * stands for it) and no seat, for two hours. It can't open the credential vault, which takes
 * a user's own password. Who it was, as the manager knows them, goes in the audit log and to
 * the instance's agents as a notification.
 *
 * It works whether the instance is active, read-only or suspended - support has to be able to
 * look at it either way.
 */

require_once "config.php";
require_once "functions.php";

// Session cookies are HTTPS-only by default (includes/session_init.php) - over plain HTTP the
// session would never stick, so say why rather than loop back to the sign-in page
if ((!isset($config_https_only) || $config_https_only)
    && (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on')
    && (!isset($_SERVER['HTTP_X_FORWARDED_PROTO']) || $_SERVER['HTTP_X_FORWARDED_PROTO'] !== 'https')) {
    exit("Support sign-in needs HTTPS, as sign-in does - this ITFlow is set to HTTPS only.");
}

require_once __DIR__ . "/includes/session_init.php";
require_once "includes/inc_set_timezone.php";

header('Referrer-Policy: no-referrer');

$session_ip = escapeSql(getIP());
$session_user_agent = escapeSql($_SERVER['HTTP_USER_AGENT'] ?? '');
$session_user_id = 0;

$support_error = '';
$support_who = settingsColumnExists($mysqli, 'config_managed')
    ? managedVerifySupportToken((string) ($_GET['token'] ?? ''), $support_error)
    : null;
if (!$support_who && $support_error === '') {
    $support_error = 'This ITFlow is not managed, so it takes no support sign-ins.';
}

$support_user_id = $support_who ? managedSupportUser() : 0;
if ($support_who && !$support_user_id) {
    $support_error = "This ITFlow has no admin role to sign support in with.";
}

if (!$support_user_id) {
    logAudit("Login", "Failed", escapeSql("Support sign-in refused - $support_error"));
    managedBlockedPage(403, 'Support sign-in', $support_error, '', '', '/login.php');
}

$support_manager = managedManagerName();
$support_label = trim($support_who['name'] . ($support_who['email'] !== '' ? " ({$support_who['email']})" : ''));
$support_password = (string) (mysqli_fetch_row(mysqli_query($mysqli, "SELECT user_password FROM users WHERE user_id = $support_user_id"))[0] ?? '');

// Whatever this browser was signed in as before, it isn't now
session_unset();
session_regenerate_id(true);

$_SESSION['user_id'] = $support_user_id;
$_SESSION['csrf_token'] = randomString(32);
$_SESSION['logged'] = true;
$_SESSION['user_session_stamp'] = userSessionStamp($support_password);
$_SESSION['managed_support'] = [
    'manager' => $support_manager,
    'name' => $support_who['name'],
    'email' => $support_who['email'],
    'started' => time(),
];

$session_user_id = $support_user_id;
logAudit("Login", "Support", escapeSql("Support sign-in from $support_manager" . ($support_label !== '' ? " - $support_label" : '')), 0, $support_user_id);
appNotify("Management", escapeSql(($support_label !== '' ? $support_label : 'Support') . " from $support_manager signed in for support"), "/admin/audit_logs.php", 0, $support_user_id);

$support_start_page = (string) (mysqli_fetch_row(mysqli_query($mysqli, "SELECT config_start_page FROM settings WHERE company_id = 1"))[0] ?? '');
redirect("/agent/" . (preg_match('/^[a-z0-9_\/.?=&-]+$/i', $support_start_page) ? $support_start_page : 'clients.php'));
