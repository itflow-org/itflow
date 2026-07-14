<?php

/*
 * ITFlow - GET/POST request handler for tasks
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['add_task'])) {

    validateCSRFToken($_POST['csrf_token']);

    enforceUserPermission('module_support', 2);

    $ticket_id = intval($_POST['ticket_id']);
    $task_name = escapeSql($_POST['name']);

    // Get Client ID from tickets using the ticket_id
    $client_id = intval(getFieldById('tickets', $ticket_id, 'ticket_client_id'));

    mysqli_query($mysqli, "INSERT INTO tasks SET task_name = '$task_name', task_ticket_id = $ticket_id");

    $task_id = mysqli_insert_id($mysqli);

    logAudit("Task", "Create", "$session_name created task $task_name", $client_id, $task_id);

    flashAlert("You created Task <strong>$task_name</strong>");

    redirect();

}

if (isset($_POST['edit_ticket_task'])) {

    validateCSRFToken($_POST['csrf_token']);

    enforceUserPermission('module_support', 2);

    $task_id = intval($_POST['task_id']);
    $task_name = escapeSql($_POST['name']);
    $task_order = intval($_POST['order']);
    $task_completion_estimate = intval($_POST['completion_estimate']);

    // Get Client ID
    $sql = mysqli_query($mysqli, "SELECT * FROM tasks LEFT JOIN tickets ON ticket_id = task_ticket_id WHERE task_id = $task_id");
    $row = mysqli_fetch_assoc($sql);
    $client_id = intval($row['ticket_client_id']);

    mysqli_query($mysqli, "UPDATE tasks SET task_name = '$task_name', task_order = $task_order, task_completion_estimate = $task_completion_estimate WHERE task_id = $task_id");

    logAudit("Task", "Edit", "$session_name edited task $task_name", $client_id, $task_id);

    flashAlert("Task <strong>$task_name</strong> edited");

    redirect();

}

if (isset($_POST['edit_ticket_template_task'])) {

    validateCSRFToken($_POST['csrf_token']);

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

    validateCSRFToken($_GET['csrf_token']);

    enforceUserPermission('module_support', 3);

    $task_id = intval($_GET['delete_task']);

    // Get Client ID, task name from tasks and tickets using the task_id
    $sql = mysqli_query($mysqli, "SELECT * FROM tasks LEFT JOIN tickets ON ticket_id = task_ticket_id WHERE task_id = $task_id");
    $row = mysqli_fetch_assoc($sql);
    $client_id = intval($row['ticket_client_id']);
    $task_name = escapeSql($row['task_name']);

    mysqli_query($mysqli, "DELETE FROM tasks WHERE task_id = $task_id");

    logAudit("Task", "Delete", "$session_name deleted task $task_name", $client_id, $task_id);

    flashAlert("Task <strong>$task_name</strong> deleted", 'error');

    redirect();

}

if (isset($_GET['complete_task'])) {

    validateCSRFToken($_GET['csrf_token']);

    enforceUserPermission('module_support', 2);

    $task_id = intval($_GET['complete_task']);

    // Get Client ID
    $sql = mysqli_query($mysqli, "SELECT * FROM tasks LEFT JOIN tickets ON ticket_id = task_ticket_id WHERE task_id = $task_id");
    $row = mysqli_fetch_assoc($sql);
    $client_id = intval($row['ticket_client_id']);
    $task_name = escapeSql($row['task_name']);
    $task_completion_estimate = intval($row['task_completion_estimate']);
    $ticket_id = intval($row['ticket_id']);

    mysqli_query($mysqli, "UPDATE tasks SET task_completed_at = NOW(), task_completed_by = $session_user_id WHERE task_id = $task_id");

    // Convert task completion estimate from minutes to TIME format
    $time_worked = gmdate("H:i:s", $task_completion_estimate * 60); // Convert minutes to HH:MM:SS

    // Add reply
    mysqli_query($mysqli, "INSERT INTO ticket_replies SET ticket_reply = 'Completed Task - $task_name', ticket_reply_time_worked = '$time_worked', ticket_reply_type = 'Internal', ticket_reply_by = $session_user_id, ticket_reply_ticket_id = $ticket_id");

    $ticket_reply_id = mysqli_insert_id($mysqli);

    logAudit("Task", "Edit", "$session_name completed task $task_name", $client_id, $task_id);

    flashAlert("Task <strong>$task_name</strong> Completed");

    redirect();

}

if (isset($_GET['undo_complete_task'])) {

    validateCSRFToken($_GET['csrf_token']);

    enforceUserPermission('module_support', 2);

    $task_id = intval($_GET['undo_complete_task']);

    // Get Client ID
    $sql = mysqli_query($mysqli, "SELECT * FROM tasks LEFT JOIN tickets ON ticket_id = task_ticket_id WHERE task_id = $task_id");
    $row = mysqli_fetch_assoc($sql);
    $client_id = intval($row['ticket_client_id']);
    $task_name = escapeSql($row['task_name']);
    $ticket_id = intval($row['ticket_id']);

    mysqli_query($mysqli, "UPDATE tasks SET task_completed_at = NULL, task_completed_by = NULL WHERE task_id = $task_id");

    // Add reply
    mysqli_query($mysqli, "INSERT INTO ticket_replies SET ticket_reply = 'Undo Completed Task - $task_name', ticket_reply_time_worked = '00:01:00', ticket_reply_type = 'Internal', ticket_reply_by = $session_user_id, ticket_reply_ticket_id = $ticket_id");

    $ticket_reply_id = mysqli_insert_id($mysqli);

    logAudit("Task", "Edit", "$session_name marked task $task_name as incomplete", $client_id, $task_id);

    flashAlert("Task <strong>$task_name</strong> marked as incomplete", 'error');

    redirect();

}

if (isset($_POST['add_ticket_task_approver'])) {

    validateCSRFToken($_POST['csrf_token']);

    enforceUserPermission('module_support', 2);

    $task_id = intval($_POST['task_id']);
    $scope = escapeSql($_POST['approval_scope']);
    $type = escapeSql($_POST['approval_type']);
    $approval_url_key = randomString(32);

    $required_user_id = "NULL";
    if ($type == 'specific') {
        $required_user_id = intval($_POST['approval_required_user_id']);
    }

    mysqli_query($mysqli, "INSERT INTO task_approvals SET approval_scope = '$scope', approval_type = '$type', approval_required_user_id = $required_user_id, approval_status = 'pending', approval_created_by = $session_user_id, approval_url_key = '$approval_url_key', approval_task_id = $task_id");

    $approval_id = mysqli_insert_id($mysqli);

    // Task/Ticket Info
    $tt_row = mysqli_fetch_assoc(mysqli_query($mysqli, "
        SELECT * FROM tasks
        LEFT JOIN tickets ON ticket_id = task_ticket_id
        LEFT JOIN ticket_statuses ON ticket_status = ticket_status_id
        WHERE task_id = $task_id LIMIT 1
        ")
    );
    $task_name = escapeSql($tt_row['task_name']);
    $ticket_id = intval($tt_row['task_ticket_id']);
    $ticket_prefix = escapeSql($tt_row['ticket_prefix']);
    $ticket_number = intval($tt_row['ticket_number']);
    $ticket_subject = escapeSql($tt_row['ticket_subject']);
    $ticket_status = escapeSql($tt_row['ticket_status_name']);
    $ticket_url_key = escapeSql($tt_row['ticket_url_key']);
    $ticket_contact_id = intval($tt_row['ticket_contact_id']);
    $client_id = intval($tt_row['ticket_client_id']);

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

        if (!empty($config_smtp_host)) {
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

    if (!empty($config_smtp_host) && $scope == 'client' && $type == 'any') {

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

    if (!empty($config_smtp_host) && $scope == 'client' && $type == 'technical') {

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

    if (!empty($config_smtp_host) && $scope == 'client' && $type == 'billing') {

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

    validateCSRFToken($_GET['csrf_token']);

    enforceUserPermission('module_support', 2);

    $task_id = intval($_GET['approve_ticket_task']);
    $approval_id = intval($_GET['approval_id']);

    $approval_row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT * FROM task_approvals LEFT JOIN tasks on task_id = approval_task_id WHERE approval_id = $approval_id AND approval_task_id = $task_id AND approval_scope = 'internal'"));

    $task_name = escapeHtml($approval_row['task_name']);
    $scope = escapeHtml($approval_row['approval_scope']);
    $type = escapeHtml($approval_row['approval_type']);
    $required_user = intval($approval_row['approval_required_user_id']);
    $created_by = intval($approval_row['approval_created_by']);
    $ticket_id = intval($approval_row['task_ticket_id']);

    if (!$approval_row) {
        flashAlert("Cannot find/approve that task", 'error');
        redirect();
        exit;
    }

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

    validateCSRFToken($_GET['csrf_token']);

    enforceUserPermission('module_support', 3);

    $approval_id = intval($_GET['delete_ticket_task_approver']);

    mysqli_query($mysqli, "DELETE FROM task_approvals WHERE approval_id = $approval_id");

    logAudit("Task", "Delete", "$session_name deleted task approval request ($approval_id)", 0, 0);

    flashAlert("Approval request deleted", 'error');

    redirect();

}

if (isset($_GET['complete_all_tasks'])) {

    validateCSRFToken($_GET['csrf_token']);

    enforceUserPermission('module_support', 2);

    $ticket_id = intval($_GET['complete_all_tasks']);

    // Get Client ID
    $client_id = intval(getFieldById('tickets', $ticket_id, 'ticket_client_id'));

    mysqli_query($mysqli, "UPDATE tasks SET task_completed_at = NOW(), task_completed_by = $session_user_id WHERE task_ticket_id = $ticket_id AND task_completed_at IS NULL");

    // Add reply
    mysqli_query($mysqli, "INSERT INTO ticket_replies SET ticket_reply = 'Marked all tasks complete', ticket_reply_type = 'Internal', ticket_reply_by = $session_user_id, ticket_reply_ticket_id = $ticket_id");

    $ticket_reply_id = mysqli_insert_id($mysqli);

    logAudit("Ticket", "Edit", "$session_name marked all tasks complete for ticket", $client_id, $ticket_id);

    flashAlert("Marked all tasks Complete");

    redirect();

}

if (isset($_GET['undo_complete_all_tasks'])) {

    validateCSRFToken($_GET['csrf_token']);

    enforceUserPermission('module_support', 2);

    $ticket_id = intval($_GET['undo_complete_all_tasks']);

    // Get Client ID
    $client_id = intval(getFieldById('tickets', $ticket_id, 'ticket_client_id'));

    mysqli_query($mysqli, "UPDATE tasks SET task_completed_at = NULL, task_completed_by = NULL WHERE task_ticket_id = $ticket_id AND task_completed_at IS NOT NULL");

    // Add reply
    mysqli_query($mysqli, "INSERT INTO ticket_replies SET ticket_reply = 'Marked all tasks incomplete', ticket_reply_type = 'Internal', ticket_reply_by = $session_user_id, ticket_reply_ticket_id = $ticket_id");

    $ticket_reply_id = mysqli_insert_id($mysqli);

    logAudit("Ticket", "Edit", "$session_name marked all tasks as incomplete for ticket", $client_id, $ticket_id);

    flashAlert("Marked all tasks Incomplete", 'error');

    redirect();

}
