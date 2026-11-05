<?php

/*
 * Managed Instances (Admin > Managed Instances) - adding and changing them, their connection
 * codes, asking them to update, read-only and suspended, signing in to them as support, and
 * removing them. The workings are in functions/managed.php.
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

/* The instance a request is about, not removed - or back with why */
$managed_instance_for_request = function ($id, bool $connected = false) {
    $row = managedInstanceRow(intval($id));
    if (!$row) {
        flashAlert("That instance is gone - it may have been deleted", 'error');
        redirect('managed_instances.php');
    }
    if (!empty($row['managed_instance_archived_at'])) {
        flashAlert(escapeHtml($row['managed_instance_name']) . " has been removed", 'error');
        redirect("managed_instance.php?id=" . intval($row['managed_instance_id']));
    }
    if ($connected && (empty($row['managed_instance_secret_hash']) || trim((string) $row['managed_instance_url']) === '')) {
        flashAlert(escapeHtml($row['managed_instance_name']) . " isn't connected yet", 'error');
        redirect("managed_instance.php?id=" . intval($row['managed_instance_id']));
    }
    return $row;
};

if (isset($_POST['add_managed_instance'])) {

    validateCSRFToken();

    [$fields, $error] = managedInstanceFromInput($_POST);
    if ($error !== '') {
        flashAlert(escapeHtml($error), 'error');
        redirect();
    }

    // The signing key is made now, so a server that can't make one says so before anything connects
    try {
        managerKeys();
    } catch (Throwable $e) {
        flashAlert(escapeHtml($e->getMessage()), 'error');
        redirect();
    }

    mysqli_query($mysqli, "INSERT INTO managed_instances SET managed_instance_uuid = '" . managedUuid() . "', " . managedInstanceSetSql($fields));
    $instance_id = intval(mysqli_insert_id($mysqli));

    $_SESSION['managed_instance_code'][$instance_id] = managedInstanceNewCode($instance_id);

    logAudit("Managed Instance", "Create", "$session_name added managed instance " . escapeSql($fields['name']), $fields['client_id'], $instance_id);

    flashAlert("<strong>" . escapeHtml($fields['name']) . "</strong> added - now connect it with the code below");

    redirect("managed_instance.php?id=$instance_id");

}

if (isset($_POST['edit_managed_instance'])) {

    validateCSRFToken();

    $row = $managed_instance_for_request($_POST['managed_instance_id'] ?? 0);
    $instance_id = intval($row['managed_instance_id']);

    [$fields, $error] = managedInstanceFromInput($_POST);
    if ($error !== '') {
        flashAlert(escapeHtml($error), 'error');
        redirect();
    }

    mysqli_query($mysqli, "UPDATE managed_instances SET " . managedInstanceSetSql($fields) . " WHERE managed_instance_id = $instance_id");

    // What changed, for the audit log - what it's set to, in words
    $changes = [];
    if ($row['managed_instance_name'] !== $fields['name']) {
        $changes[] = 'renamed from ' . $row['managed_instance_name'];
    }
    if (intval($row['managed_instance_seat_limit']) !== $fields['seat_limit']) {
        $changes[] = 'seat limit ' . ($fields['seat_limit'] ?: 'none') . ' (was ' . (intval($row['managed_instance_seat_limit']) ?: 'none') . ')';
    }
    if ($row['managed_instance_update_policy'] !== $fields['update_policy'] || intval($row['managed_instance_update_delay_days']) !== $fields['update_delay_days']) {
        $changes[] = 'updates ' . ($fields['update_policy'] === 'auto' ? 'automatic after ' . $fields['update_delay_days'] . ' days' : 'by hand');
    }
    if ((string) $row['managed_instance_white_label_key'] !== $fields['white_label_key']) {
        $changes[] = $fields['white_label_key'] === '' ? 'white-label key taken off' : 'white-label key set';
    }
    if ($row['managed_instance_mode'] !== $fields['mode']) {
        $changes[] = strtolower(MANAGED_MODES[$fields['mode']]) . ' (was ' . strtolower(MANAGED_MODES[$row['managed_instance_mode']] ?? $row['managed_instance_mode']) . ')';
    }
    if (intval($row['managed_instance_client_id']) !== $fields['client_id']) {
        $changes[] = 'client changed';
    }
    if ($changes) {
        logAudit("Managed Instance", "Edit", "$session_name edited managed instance " . escapeSql($fields['name']) . " - " . escapeSql(implode(', ', $changes)), $fields['client_id'], $instance_id);
    }

    flashAlert("<strong>" . escapeHtml($fields['name']) . "</strong> " . ($changes ? 'saved' . (!empty($row['managed_instance_secret_hash']) ? ' - it takes the change at its next check-in' : '') : 'unchanged'));

    redirect();

}

if (isset($_GET['managed_instance_new_code'])) {

    validateCSRFToken();

    $row = $managed_instance_for_request($_GET['managed_instance_new_code']);
    $instance_id = intval($row['managed_instance_id']);

    $_SESSION['managed_instance_code'][$instance_id] = managedInstanceNewCode($instance_id);

    logAudit("Managed Instance", "Code", "$session_name made a new connection code for " . escapeSql($row['managed_instance_name']), intval($row['managed_instance_client_id']), $instance_id);

    redirect("managed_instance.php?id=$instance_id");

}

if (isset($_GET['managed_command'])) {

    validateCSRFToken();

    $command = (string) $_GET['managed_command'];
    if (!in_array($command, ['update_now', 'check_updates'], true)) {
        flashAlert("That isn't something an instance can be asked", 'error');
        redirect();
    }

    $row = $managed_instance_for_request($_GET['id'] ?? 0, true);
    $instance_id = intval($row['managed_instance_id']);
    $by = (string) (mysqli_fetch_row(mysqli_query($mysqli, "SELECT user_name FROM users WHERE user_id = " . intval($session_user_id)))[0] ?? 'An admin');

    managedQueueCommand($instance_id, $command, $by);

    logAudit("Managed Instance", $command === 'update_now' ? "Update" : "Check", "$session_name asked " . escapeSql($row['managed_instance_name']) . " to " . strtolower(managedCommandLabel($command)), intval($row['managed_instance_client_id']), $instance_id);

    flashAlert("Asked - <strong>" . escapeHtml($row['managed_instance_name']) . "</strong> picks it up at its next check-in, within a minute");

    redirect();

}

if (isset($_GET['managed_instance_mode'])) {

    validateCSRFToken();

    $mode = (string) $_GET['managed_instance_mode'];
    if (!isset(MANAGED_MODES[$mode])) {
        flashAlert("That isn't a mode", 'error');
        redirect();
    }

    $row = $managed_instance_for_request($_GET['id'] ?? 0);
    $instance_id = intval($row['managed_instance_id']);

    mysqli_query($mysqli, "UPDATE managed_instances SET managed_instance_mode = '$mode' WHERE managed_instance_id = $instance_id");

    logAudit("Managed Instance", "Mode", "$session_name made " . escapeSql($row['managed_instance_name']) . " " . strtolower(MANAGED_MODES[$mode]) . " (was " . strtolower(MANAGED_MODES[$row['managed_instance_mode']] ?? $row['managed_instance_mode']) . ")", intval($row['managed_instance_client_id']), $instance_id);

    flashAlert("<strong>" . escapeHtml($row['managed_instance_name']) . "</strong> is " . strtolower(MANAGED_MODES[$mode]) . (!empty($row['managed_instance_secret_hash']) ? ' from its next check-in' : ' - it takes it when it connects'));

    redirect();

}

if (isset($_GET['managed_support_sign_in'])) {

    validateCSRFToken();

    $row = $managed_instance_for_request($_GET['managed_support_sign_in'], true);
    $instance_id = intval($row['managed_instance_id']);
    $me = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT user_name, user_email FROM users WHERE user_id = " . intval($session_user_id))) ?: [];

    try {
        $link = managedSupportLink($row, (string) ($me['user_name'] ?? ''), (string) ($me['user_email'] ?? ''));
    } catch (Throwable $e) {
        flashAlert(escapeHtml($e->getMessage()), 'error');
        redirect("managed_instance.php?id=$instance_id");
    }

    logAudit("Managed Instance", "Support", "$session_name signed in to " . escapeSql($row['managed_instance_name']) . " as support", intval($row['managed_instance_client_id']), $instance_id);

    redirect($link);

}

if (isset($_GET['remove_managed_instance'])) {

    validateCSRFToken();

    $row = $managed_instance_for_request($_GET['remove_managed_instance']);
    $instance_id = intval($row['managed_instance_id']);

    mysqli_query($mysqli, "UPDATE managed_instances SET managed_instance_archived_at = NOW(), managed_instance_enroll_hash = NULL, managed_instance_enroll_expires_at = NULL
        WHERE managed_instance_id = $instance_id");
    // What it hasn't picked up, it won't now
    mysqli_query($mysqli, "UPDATE managed_instance_commands SET managed_command_status = 'cancelled', managed_command_result = 'The instance was removed', managed_command_done_at = NOW()
        WHERE managed_command_instance_id = $instance_id AND managed_command_status = 'queued'");

    logAudit("Managed Instance", "Remove", "$session_name removed managed instance " . escapeSql($row['managed_instance_name']), intval($row['managed_instance_client_id']), $instance_id);

    flashAlert("<strong>" . escapeHtml($row['managed_instance_name']) . "</strong> removed" . (!empty($row['managed_instance_secret_hash']) ? ' - it lets go at its next check-in' : ''), 'error');

    redirect('managed_instances.php');

}

if (isset($_GET['delete_managed_instance'])) {

    validateCSRFToken();

    $row = managedInstanceRow(intval($_GET['delete_managed_instance']));
    if (!$row || empty($row['managed_instance_archived_at'])) {
        flashAlert("Only a removed instance can be deleted", 'error');
        redirect('managed_instances.php');
    }
    $instance_id = intval($row['managed_instance_id']);

    mysqli_query($mysqli, "DELETE FROM managed_instance_commands WHERE managed_command_instance_id = $instance_id");
    mysqli_query($mysqli, "DELETE FROM managed_instances WHERE managed_instance_id = $instance_id");

    logAudit("Managed Instance", "Delete", "$session_name deleted managed instance " . escapeSql($row['managed_instance_name']), intval($row['managed_instance_client_id']), $instance_id);

    flashAlert("<strong>" . escapeHtml($row['managed_instance_name']) . "</strong> deleted", 'error');

    redirect('managed_instances.php?show=removed');

}
