<?php

/*
 * ITFlow - GET/POST request handler for tasks
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

/** The ticket task a request names and its ticket, after the ticket's checks (ticketForRequest()) - [task, ticket]. */
function taskForRequest($task_id, $level) {
    global $mysqli;
    $task_id = intval($task_id);
    $task = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT task_id, task_name, task_ticket_id, task_completed_at FROM tasks WHERE task_id = $task_id LIMIT 1"));
    if (!$task || !intval($task['task_ticket_id'])) {
        flashAlert('That task no longer exists.', 'error');
        redirect();
    }
    return [$task, ticketForRequest($task['task_ticket_id'], $level)];
}

if (isset($_POST['add_task'])) {

    validateCSRFToken();

    $row = ticketForRequest($_POST['ticket_id'] ?? 0, 2);
    $ticket_id = intval($row['ticket_id']);
    $task_name = escapeSql(mb_substr(trim((string) ($_POST['name'] ?? '')), 0, 255));
    if ($task_name === '') {
        flashAlert('A task needs a name.', 'error');
        redirect();
    }

    // At the end of the list
    $order = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COALESCE(MAX(task_order), 0) + 1 FROM tasks WHERE task_ticket_id = $ticket_id"))[0]);
    mysqli_query($mysqli, "INSERT INTO tasks SET task_name = '$task_name', task_order = $order, task_ticket_id = $ticket_id");

    $task_id = mysqli_insert_id($mysqli);

    logAudit("Task", "Create", "$session_name created task $task_name", $client_id, $task_id);

    flashAlert("You created Task <strong>" . escapeHtml(stripslashes($task_name)) . "</strong>");

    redirect();

}

if (isset($_POST['edit_ticket_task'])) {

    validateCSRFToken();

    [$task, $row] = taskForRequest($_POST['task_id'] ?? 0, 2);
    $task_id = intval($task['task_id']);
    $task_name = escapeSql(mb_substr(trim((string) ($_POST['name'] ?? '')), 0, 255));
    $task_order = intval($_POST['order'] ?? 0);
    $task_completion_estimate = max(0, intval($_POST['completion_estimate'] ?? 0));
    if ($task_name === '') {
        flashAlert('A task needs a name.', 'error');
        redirect();
    }

    mysqli_query($mysqli, "UPDATE tasks SET task_name = '$task_name', task_order = $task_order, task_completion_estimate = $task_completion_estimate WHERE task_id = $task_id");

    logAudit("Task", "Edit", "$session_name edited task $task_name", $client_id, $task_id);

    flashAlert("Task <strong>" . escapeHtml(stripslashes($task_name)) . "</strong> edited");

    redirect();

}

if (isset($_POST['edit_ticket_template_task'])) {

    validateCSRFToken();

    enforceUserPermission('module_support', 2);

    $task_template_id = intval($_POST['task_template_id']);
    $task_name = escapeSql($_POST['name']);
    $task_order = intval($_POST['order']);
    $task_completion_estimate = intval($_POST['completion_estimate']);

    mysqli_query($mysqli, "UPDATE task_templates SET task_template_name = '$task_name', task_template_order = $task_order, task_template_completion_estimate = $task_completion_estimate WHERE task_template_id = $task_template_id");

    logAudit("Task", "Edit", "$session_name edited task $task_name", 0, $task_template_id);

    flashAlert("Task <strong>$task_name</strong> edited");

    redirect();

}

if (isset($_GET['delete_task'])) {

    validateCSRFToken();

    [$task, $row] = taskForRequest($_GET['delete_task'], 3);
    $task_id = intval($task['task_id']);
    $task_name = escapeSql($task['task_name']);

    mysqli_query($mysqli, "DELETE FROM task_approvals WHERE approval_task_id = $task_id");
    mysqli_query($mysqli, "DELETE FROM tasks WHERE task_id = $task_id");

    logAudit("Task", "Delete", "$session_name deleted task $task_name", $client_id, $task_id);

    flashAlert("Task <strong>" . escapeHtml($task['task_name']) . "</strong> deleted", 'error');

    redirect();

}

if (isset($_GET['complete_task']) || isset($_GET['undo_complete_task'])) {

    validateCSRFToken();

    $complete = isset($_GET['complete_task']);
    [$task, $row] = taskForRequest($complete ? $_GET['complete_task'] : $_GET['undo_complete_task'], 2);
    $task_id = intval($task['task_id']);
    $ticket_id = intval($row['ticket_id']);
    $task_name = escapeSql($task['task_name']);

    if ($complete) {
        mysqli_query($mysqli, "UPDATE tasks SET task_completed_at = NOW(), task_completed_by = $session_user_id WHERE task_id = $task_id AND task_completed_at IS NULL");
    } else {
        mysqli_query($mysqli, "UPDATE tasks SET task_completed_at = NULL, task_completed_by = NULL WHERE task_id = $task_id");
    }

    // Audit trail only - task_completion_estimate is planning information, not labour.
    // Booking it as time worked double-counted against whatever the tech actually logged.
    mysqli_query($mysqli, "INSERT INTO ticket_replies SET ticket_reply = '" . ($complete ? 'Completed Task' : 'Undo Completed Task') . " - $task_name', ticket_reply_time_worked = '00:00:00', ticket_reply_type = 'Internal', ticket_reply_by = $session_user_id, ticket_reply_ticket_id = $ticket_id");

    logAudit("Task", "Edit", "$session_name " . ($complete ? "completed task $task_name" : "marked task $task_name as incomplete"), $client_id, $task_id);

    flashAlert("Task <strong>" . escapeHtml($task['task_name']) . "</strong> " . ($complete ? 'Completed' : 'marked as incomplete'), $complete ? 'success' : 'error');

    redirect();

}

if (isset($_POST['add_ticket_task_approver'])) {

    validateCSRFToken();

    [$task, $row] = taskForRequest($_POST['task_id'] ?? 0, 2);
    $task_id = intval($task['task_id']);

    $scope = ($_POST['approval_scope'] ?? '') === 'internal' ? 'internal' : 'client';
    $type = in_array($_POST['approval_type'] ?? '', ['any', 'technical', 'billing', 'specific'], true) ? $_POST['approval_type'] : 'any';
    $approval_url_key = randomString(32);

    $required_user_id = "NULL";
    if ($type == 'specific') {
        $required_user_id = ticketAgentId($_POST['approval_required_user_id'] ?? 0);
        if (!$required_user_id) {
            flashAlert('Pick the agent who has to approve it.', 'error');
            redirect();
        }
    }

    mysqli_query($mysqli, "INSERT INTO task_approvals SET approval_scope = '$scope', approval_type = '$type', approval_required_user_id = $required_user_id, approval_status = 'pending', approval_created_by = $session_user_id, approval_url_key = '$approval_url_key', approval_task_id = $task_id");

    $approval_id = mysqli_insert_id($mysqli);

    // Task/Ticket Info
    $task_name = escapeSql($task['task_name']);
    $ticket_id = intval($row['ticket_id']);
    $ticket_prefix = escapeSql($row['ticket_prefix']);
    $ticket_number = intval($row['ticket_number']);
    $ticket_subject = escapeSql($row['ticket_subject']);
    $ticket_status = escapeSql($row['ticket_status_name']);
    $ticket_url_key = escapeSql($row['ticket_url_key']);
    $ticket_contact_id = intval($row['ticket_contact_id']);
    $data = [];

    // --Notifications--

    // Sanitize Config vars from get_settings.php
    $config_ticket_from_name = escapeSql($config_ticket_from_name);
    $config_ticket_from_email = escapeSql($config_ticket_from_email);
    $config_base_url = escapeSql($config_base_url);

    // Get Company Info
    $crow = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT company_name, company_phone, company_phone_country_code FROM companies WHERE company_id = 1"));
    $company_name = escapeSql($crow['company_name']);
    $company_phone = escapeSql(formatPhoneNumber($crow['company_phone'], $crow['company_phone_country_code']));

    // Email contents
    $subject = "Ticket task approval required - [$ticket_prefix$ticket_number] - $ticket_subject";
    $body = "<i style=\'color: #808080\'>##- Please type your reply above this line -##</i><br><br>Hello,<br><br>A ticket regarding $ticket_subject has a task requiring your approval:- <br>Task name: $task_name<br>Scope/Type: $scope - $type <br><br>To approve this task, please click <a href=\'https://$config_base_url/guest/guest_approve_ticket_task.php?task_approval_id=$approval_id&url_key=$approval_url_key\'>here</a>.<br>If you require further information, please reply to this e-mail.<br><br>Ticket: $ticket_prefix$ticket_number<br>Subject: $ticket_subject<br>Status: $ticket_status<br>Portal: <a href=\'https://$config_base_url/guest/guest_view_ticket.php?ticket_id=$ticket_id&url_key=$ticket_url_key\'>View ticket</a><br><br>--<br>$company_name - Support<br>$config_ticket_from_email<br>$company_phone";

    if ($scope == 'internal' && $type == 'specific' && $session_user_id !== $required_user_id) {
        mysqli_query($mysqli, "INSERT INTO notifications SET notification_type = 'Ticket', notification = '$session_name needs your approval for ticket $ticket_prefix$ticket_number task $task_name', notification_action = 'ticket.php?ticket_id=$ticket_id', notification_client_id = 0, notification_user_id = $required_user_id");

        if (!empty($config_smtp_provider)) {
            $agent_contact = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT user_name, user_email FROM users WHERE user_id = $required_user_id AND user_archived_at IS NULL"));
            $name = escapeSql($agent_contact['user_name']);
            $email = escapeSql($agent_contact['user_email']);

            // Only add contact to email queue if email is valid
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $data[] = [
                    'from' => $config_ticket_from_email,
                    'from_name' => $config_ticket_from_name,
                    'recipient' => $email,
                    'recipient_name' => $name,
                    'subject' => $subject,
                    'body' => $body
                ];

                addToMailQueue($data);
            }

        }
    }

    if (!empty($config_smtp_provider) && $scope == 'client' && $type == 'any') {

        $contact_row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT contact_name, contact_email FROM contacts WHERE contact_id = $ticket_contact_id LIMIT 1"));
        $contact_name = escapeSql($contact_row['contact_name']);
        $contact_email = escapeSql($contact_row['contact_email']);

        $data = [];

        if (filter_var($contact_email, FILTER_VALIDATE_EMAIL)) {
            $data[] = [
                'from' => $config_ticket_from_email,
                'from_name' => $config_ticket_from_name,
                'recipient' => $contact_email,
                'recipient_name' => $contact_name,
                'subject' => $subject,
                'body' => $body
            ];
            addToMailQueue($data);
        }

    }

    if (!empty($config_smtp_provider) && $scope == 'client' && $type == 'technical') {

        $sql_technical_contacts = mysqli_query(
            $mysqli,
            "SELECT contact_name, contact_email FROM contacts
            WHERE contact_technical = 1
            AND contact_email != ''
            AND contact_client_id = $client_id"
        );

        $data = [];

        while ($technical_contact = mysqli_fetch_assoc($sql_technical_contacts)) {
            $technical_contact_name = escapeSql($technical_contact['contact_name']);
            $technical_contact_email = escapeSql($technical_contact['contact_email']);

            if (filter_var($technical_contact_email, FILTER_VALIDATE_EMAIL)) {
                $data[] = [
                    'from' => $config_ticket_from_email,
                    'from_name' => $config_ticket_from_name,
                    'recipient' => $technical_contact_email,
                    'recipient_name' => $technical_contact_name,
                    'subject' => $subject,
                    'body' => $body
                ];
            }

        }

        addToMailQueue($data);

    }

    if (!empty($config_smtp_provider) && $scope == 'client' && $type == 'billing') {

        $sql_billing_contacts = mysqli_query(
            $mysqli,
            "SELECT contact_name, contact_email FROM contacts
            WHERE contact_billing = 1
            AND contact_email != ''
            AND contact_client_id = $client_id"
        );

        $data = [];

        while ($billing_contact = mysqli_fetch_assoc($sql_billing_contacts)) {
            $billing_contact_name = escapeSql($billing_contact['contact_name']);
            $billing_contact_email = escapeSql($billing_contact['contact_email']);

            if (filter_var($billing_contact_email, FILTER_VALIDATE_EMAIL)) {
                $data[] = [
                        'from' => $config_ticket_from_email,
                        'from_name' => $config_ticket_from_name,
                        'recipient' => $billing_contact_email,
                        'recipient_name' => $billing_contact_name,
                        'subject' => $subject,
                        'body' => $body
                ];
            }

        }

        addToMailQueue($data);

    }

    // Logging
    logAudit("Task", "Edit", "$session_name added task approver for $task_name", $client_id, $task_id);

    flashAlert("Added approver");
    redirect();
}

if (isset($_GET['approve_ticket_task'])) {

    validateCSRFToken();

    [$task, $row] = taskForRequest($_GET['approve_ticket_task'], 2);
    $task_id = intval($task['task_id']);
    $approval_id = intval($_GET['approval_id'] ?? 0);

    $approval_row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT approval_created_by, approval_required_user_id, approval_scope, approval_type
        FROM task_approvals WHERE approval_id = $approval_id AND approval_task_id = $task_id AND approval_scope = 'internal'"));
    if (!$approval_row) {
        flashAlert("Cannot find/approve that task", 'error');
        redirect();
    }

    $task_name = escapeSql($task['task_name']);
    $type = escapeSql($approval_row['approval_type']);
    $required_user = intval($approval_row['approval_required_user_id']);
    $created_by = intval($approval_row['approval_created_by']);
    $ticket_id = intval($row['ticket_id']);

    // Validate approver (deny)
    if ($required_user > 0 && $required_user !== $session_user_id) {
        flashAlert("You cannot approve that task", 'error');
        redirect();
        exit;
    }
    if ($required_user == 0 && $type == 'any' && $created_by == $session_user_id) {
        flashAlert("You cannot approve your own task", 'error');
        redirect();
        exit;
    }

    // Approve
    mysqli_query($mysqli, "UPDATE task_approvals SET approval_status = 'approved', approval_approved_by = $session_user_id WHERE approval_id = $approval_id AND approval_task_id = $task_id AND approval_scope = 'internal'");

    // Notify
    mysqli_query($mysqli, "INSERT INTO notifications SET notification_type = 'Ticket', notification = '$session_name approved ticket task $task_name', notification_action = 'ticket.php?ticket_id=$ticket_id', notification_client_id = 0, notification_user_id = $created_by");
    // TODO: Email agent

    // Logging
    logAudit("Task", "Edit", "$session_name approved task $task_name (approval $approval_id)", 0, $task_id);

    flashAlert("Approved");
    redirect();

}

if (isset($_GET['delete_ticket_task_approver'])) {

    validateCSRFToken();

    $approval_id = intval($_GET['delete_ticket_task_approver']);
    $approval = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT approval_task_id FROM task_approvals WHERE approval_id = $approval_id"));
    if (!$approval) {
        flashAlert('That approval request was already removed.', 'error');
        redirect();
    }
    [$task, $row] = taskForRequest($approval['approval_task_id'], 3);

    mysqli_query($mysqli, "DELETE FROM task_approvals WHERE approval_id = $approval_id");

    logAudit("Task", "Delete", "$session_name deleted the approval request on task " . escapeSql($task['task_name']), $client_id, intval($task['task_id']));

    flashAlert("Approval request deleted", 'error');

    redirect();

}

if (isset($_GET['complete_all_tasks']) || isset($_GET['undo_complete_all_tasks'])) {

    validateCSRFToken();

    $complete = isset($_GET['complete_all_tasks']);
    $row = ticketForRequest($complete ? $_GET['complete_all_tasks'] : $_GET['undo_complete_all_tasks'], 2);
    $ticket_id = intval($row['ticket_id']);

    if ($complete) {
        mysqli_query($mysqli, "UPDATE tasks SET task_completed_at = NOW(), task_completed_by = $session_user_id WHERE task_ticket_id = $ticket_id AND task_completed_at IS NULL");
    } else {
        mysqli_query($mysqli, "UPDATE tasks SET task_completed_at = NULL, task_completed_by = NULL WHERE task_ticket_id = $ticket_id AND task_completed_at IS NOT NULL");
    }

    mysqli_query($mysqli, "INSERT INTO ticket_replies SET ticket_reply = '" . ($complete ? 'Marked all tasks complete' : 'Marked all tasks incomplete') . "', ticket_reply_time_worked = '00:00:00', ticket_reply_type = 'Internal', ticket_reply_by = $session_user_id, ticket_reply_ticket_id = $ticket_id");

    logAudit("Ticket", "Edit", "$session_name marked all tasks " . ($complete ? 'complete' : 'as incomplete') . " for ticket " . escapeSql(ticketReference($row)), $client_id, $ticket_id);

    flashAlert($complete ? "Marked all tasks Complete" : "Marked all tasks Incomplete", $complete ? 'success' : 'error');

    redirect();

}
