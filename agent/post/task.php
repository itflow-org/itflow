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

    flashAlert("Task <strong>" . escapeHtml(stripslashes($task_name)) . "</strong> added");

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

    flashAlert("Task <strong>" . escapeHtml(stripslashes($task_name)) . "</strong> saved");

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

    flashAlert("Task <strong>" . stripslashes($task_name) . "</strong> saved");

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

    flashAlert("Task <strong>" . escapeHtml($task['task_name']) . "</strong> " . ($complete ? 'completed' : 'marked not done'), $complete ? 'success' : 'error');

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

    $task_name = escapeSql($task['task_name']);
    $ticket_id = intval($row['ticket_id']);
    $reference = ticketReference($row);
    $by = stripslashes($session_name);

    // Who is asked: the agent named, or the client's - the ticket's contact, or its technical or billing contacts
    $recipients = [];
    if ($scope == 'internal' && $type == 'specific' && $session_user_id !== $required_user_id) {
        mysqli_query($mysqli, "INSERT INTO notifications SET notification_type = 'Ticket', notification = '$session_name needs your approval for ticket " . escapeSql($reference) . " task $task_name', notification_action = 'ticket.php?ticket_id=$ticket_id', notification_client_id = 0, notification_user_id = $required_user_id");
        $agent = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT user_name, user_email FROM users WHERE user_id = $required_user_id AND user_archived_at IS NULL"));
        if ($agent) {
            $recipients[] = ['email' => $agent['user_email'], 'name' => $agent['user_name'], 'footnote' => "You get this because $by asked you to approve it."];
        }
    } elseif ($scope == 'client') {
        $where = $type == 'technical' ? "contact_client_id = $client_id AND contact_technical = 1"
            : ($type == 'billing' ? "contact_client_id = $client_id AND contact_billing = 1" : "contact_id = " . intval($row['ticket_contact_id']));
        $sql_contacts = mysqli_query($mysqli, "SELECT contact_name, contact_email FROM contacts WHERE $where AND contact_archived_at IS NULL AND contact_email != ''");
        while ($contact = mysqli_fetch_assoc($sql_contacts)) {
            $recipients[] = ['email' => $contact['contact_email'], 'name' => $contact['contact_name'],
                'footnote' => $type == 'any' ? "You get this as the contact on ticket $reference." : "You get this as a " . $type . " contact for " . $row['client_name'] . "."];
        }
    }
    emailQueue($recipients, "Ticket task approval required - [$reference] - " . $row['ticket_subject'], [
        'marker' => true, 'kind' => 'Approval needed', 'tone' => 'warning', 'preview' => "$by needs your approval - " . $task['task_name'],
        'title' => 'Your approval is needed',
        'intro' => ["$by needs your approval for a task on ticket $reference before the work goes ahead."],
        'summary' => ['Task' => (string) $task['task_name'], 'Ticket' => $reference, 'Subject' => (string) $row['ticket_subject'], 'Status' => (string) $row['ticket_status_name']],
        'button' => ['label' => 'Review and approve', 'url' => "https://$config_base_url/guest/guest_approve_ticket_task.php?task_approval_id=$approval_id&url_key=$approval_url_key"],
        'after' => ['Want to know more first? Just reply to this email.'],
        'contact' => ticketEmailContactLine(),
    ], ['from' => ticketEmailFrom()]);

    // Logging
    logAudit("Task", "Edit", "$session_name added task approver for $task_name", $client_id, $task_id);

    flashAlert("Approver added to task <strong>" . stripslashes($task_name) . "</strong>");
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
        flashAlert('That approval request no longer exists.', 'error');
        redirect();
    }

    $task_name = escapeSql($task['task_name']);
    $type = escapeSql($approval_row['approval_type']);
    $required_user = intval($approval_row['approval_required_user_id']);
    $created_by = intval($approval_row['approval_created_by']);
    $ticket_id = intval($row['ticket_id']);

    // Validate approver (deny)
    if ($required_user > 0 && $required_user !== $session_user_id) {
        flashAlert('This approval is for another agent.', 'error');
        redirect();
        exit;
    }
    if ($required_user == 0 && $type == 'any' && $created_by == $session_user_id) {
        flashAlert('You asked for this approval - someone else has to give it.', 'error');
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

    flashAlert("Task <strong>" . stripslashes($task_name) . "</strong> approved");
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

    flashAlert($complete ? "All tasks marked complete" : "All tasks marked not done", $complete ? 'success' : 'error');

    redirect();

}
