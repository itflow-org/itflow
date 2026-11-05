<?php

/*
 * Managed By (Admin > Managed By) - connecting this ITFlow to a manager with its code,
 * checking in straight away, and disconnecting. The workings are in functions/managed.php.
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['managed_connect'])) {

    validateCSRFToken();

    if (!settingsColumnExists($mysqli, 'config_managed')) {
        flashAlert("Apply the database update first", 'error');
        redirect();
    }
    if (managedIsManaged()) {
        flashAlert("This ITFlow is already managed by <strong>" . escapeHtml(managedManagerName()) . "</strong> - disconnect it first", 'error');
        redirect();
    }

    $url = rtrim(trim((string) ($_POST['url'] ?? '')), '/');
    if (!preg_match('#^https?://[^\s/]+(/[^\s]*)?$#', $url)) {
        flashAlert("That isn't an address - it starts https://", 'error');
        redirect();
    }

    $error = '';
    if (!managedEnroll((string) ($_POST['code'] ?? ''), $url, $error)) {
        flashAlert("Not connected - " . escapeHtml($error), 'error');
        redirect();
    }

    $manager = managedManagerName();
    logAudit("Management", "Connect", "$session_name connected this ITFlow to " . escapeSql($manager) . " to be managed");

    flashAlert("Connected - <strong>" . escapeHtml($manager) . "</strong> manages this ITFlow now");

    redirect();

}

if (isset($_POST['managed_checkin_now'])) {

    validateCSRFToken();

    if (!managedIsManaged()) {
        flashAlert("This ITFlow isn't managed", 'error');
        redirect();
    }

    $result = managedCheckin();
    if (!$result['ok']) {
        flashAlert("The check-in didn't get through - " . escapeHtml($result['error']), 'error');
    } elseif (!managedIsManaged()) {
        flashAlert("Checked in - the manager has removed this ITFlow, so it is no longer managed", 'info');
    } else {
        flashAlert("Checked in with <strong>" . escapeHtml(managedManagerName()) . "</strong>" . ($result['commands'] ? ' - it had ' . $result['commands'] . ' request' . ($result['commands'] === 1 ? '' : 's') : ''));
    }

    redirect();

}

if (isset($_POST['managed_disconnect'])) {

    validateCSRFToken();

    if (!managedIsManaged()) {
        flashAlert("This ITFlow isn't managed", 'error');
        redirect();
    }
    if (managedRequired()) {
        flashAlert("This install's config.php keeps it managed, so it can't be disconnected here", 'error');
        redirect();
    }

    $manager = managedManagerName();
    managedDisconnect('disconnected');

    logAudit("Management", "Disconnect", "$session_name disconnected this ITFlow from " . escapeSql($manager));

    flashAlert("Disconnected - <strong>" . escapeHtml($manager) . "</strong> no longer manages this ITFlow", 'error');

    redirect();

}
