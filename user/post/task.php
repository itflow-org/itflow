<?php

/*
 * ITFlow - GET/POST request handler for tasks
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['add_task'])) {

    enforceUserPermission('module_support', 2);

    $ticket_id = intval($_POST['ticket_id']);
    $task_name = sanitizeInput($_POST['name']);

    // Get Client ID from tickets using the ticket_id
    $sql = mysqli_query($mysqli, "SELECT * FROM tickets WHERE ticket_id = $ticket_id");
    $row = mysqli_fetch_array($sql);
    $client_id = intval($row['ticket_client_id']);
    
    mysqli_query($mysqli, "INSERT INTO tasks SET task_name = '$task_name', task_ticket_id = $ticket_id");

    $task_id = mysqli_insert_id($mysqli);

    // Logging
    logAction("Task", "Create", "$session_name created task $task_name", $client_id, $task_id);

    $_SESSION['alert_message'] = "You created Task <strong>$task_name</strong>";

    redirect();
}

if (isset($_POST['edit_ticket_task'])) {

    enforceUserPermission('module_support', 2);

    $task_id = intval($_POST['task_id']);
    $task_name = sanitizeInput($_POST['name']);
    $task_order = intval($_POST['order']);
    $task_completion_estimate = intval($_POST['completion_estimate']);

    // Get Client ID
    $sql = mysqli_query($mysqli, "SELECT * FROM tasks LEFT JOIN tickets ON ticket_id = task_ticket_id WHERE task_id = $task_id");
    $row = mysqli_fetch_array($sql);
    $client_id = intval($row['ticket_client_id']);
    mysqli_query($mysqli, "UPDATE tasks SET task_name = '$task_name', task_order = $task_order, task_completion_estimate = $task_completion_estimate WHERE task_id = $task_id");

    // Logging
    logAction("Task", "Edit", "$session_name edited task $task_name", $client_id, $task_id);

    $_SESSION['alert_message'] = "Task <strong>$task_name</strong> edited";

    redirect();
}

if (isset($_POST['edit_ticket_template_task'])) {

    enforceUserPermission('module_support', 2);

    $task_template_id = intval($_POST['task_template_id']);
    $task_name = sanitizeInput($_POST['name']);
    $task_order = intval($_POST['order']);
    $task_completion_estimate = intval($_POST['completion_estimate']);

    mysqli_query($mysqli, "UPDATE task_templates SET task_template_name = '$task_name', task_template_order = $task_order, task_template_completion_estimate = $task_completion_estimate WHERE task_template_id = $task_template_id");

    // Logging
    logAction("Task", "Edit", "$session_name edited task $task_name", 0, $task_template_id);

    $_SESSION['alert_message'] = "Task <strong>$task_name</strong> edited";

    redirect();
}


if (isset($_GET['delete_task'])) {

    enforceUserPermission('module_support', 3);

    // CSRF Check
    validateCSRFToken($_GET['csrf_token']);

    $task_id = intval($_GET['delete_task']);

    // Get Client ID, task name from tasks and tickets using the task_id
    $sql = mysqli_query($mysqli, "SELECT * FROM tasks LEFT JOIN tickets ON ticket_id = task_ticket_id WHERE task_id = $task_id");
    $row = mysqli_fetch_array($sql);
    $client_id = intval($row['ticket_client_id']);
    $task_name = sanitizeInput($row['task_name']);

    mysqli_query($mysqli, "DELETE FROM tasks WHERE task_id = $task_id");

    // Logging
    logAction("Task", "Delete", "$session_name deleted task $task_name", $client_id, $task_id);

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "Task <strong>$task_name</strong> deleted";

    redirect();
}

if (isset($_GET['complete_task'])) {

    enforceUserPermission('module_support', 2);

    $task_id = intval($_GET['complete_task']);

    // Get Client ID
    $sql = mysqli_query($mysqli, "SELECT * FROM tasks LEFT JOIN tickets ON ticket_id = task_ticket_id WHERE task_id = $task_id");
    $row = mysqli_fetch_array($sql);
    $client_id = intval($row['ticket_client_id']);
    $task_name = sanitizeInput($row['task_name']);
    $task_completion_estimate = intval($row['task_completion_estimate']);
    $ticket_id = intval($row['ticket_id']);

    mysqli_query($mysqli, "UPDATE tasks SET task_completed_at = NOW(), task_completed_by = $session_user_id WHERE task_id = $task_id");

    // Convert task completion estimate from minutes to TIME format
    $time_worked = gmdate("H:i:s", $task_completion_estimate * 60); // Convert minutes to HH:MM:SS

    // Add reply
    mysqli_query($mysqli, "INSERT INTO ticket_replies SET ticket_reply = 'Completed Task - $task_name', ticket_reply_time_worked = '$time_worked', ticket_reply_type = 'Internal', ticket_reply_by = $session_user_id, ticket_reply_ticket_id = $ticket_id");

    $ticket_reply_id = mysqli_insert_id($mysqli);

    // Logging
    logAction("Task", "Edit", "$session_name completed task $task_name", $client_id, $task_id);

    $_SESSION['alert_message'] = "Task <strong>$task_name</strong> Completed";

    redirect();
}

if (isset($_GET['undo_complete_task'])) {

    enforceUserPermission('module_support', 2);

    $task_id = intval($_GET['undo_complete_task']);

    // Get Client ID
    $sql = mysqli_query($mysqli, "SELECT * FROM tasks LEFT JOIN tickets ON ticket_id = task_ticket_id WHERE task_id = $task_id");
    $row = mysqli_fetch_array($sql);
    $client_id = intval($row['ticket_client_id']);
    $task_name = sanitizeInput($row['task_name']);
    $ticket_id = intval($row['ticket_id']);

    mysqli_query($mysqli, "UPDATE tasks SET task_completed_at = NULL, task_completed_by = NULL WHERE task_id = $task_id");

    // Add reply
    mysqli_query($mysqli, "INSERT INTO ticket_replies SET ticket_reply = 'Undo Completed Task - $task_name', ticket_reply_time_worked = '00:01:00', ticket_reply_type = 'Internal', ticket_reply_by = $session_user_id, ticket_reply_ticket_id = $ticket_id");

    $ticket_reply_id = mysqli_insert_id($mysqli);

    // Logging
    logAction("Task", "Edit", "$session_name marked task $task_name as incomplete", $client_id, $task_id);

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "Task <strong>$task_name</strong> marked as incomplete";

    redirect();

}

if (isset($_GET['complete_all_tasks'])) {

    enforceUserPermission('module_support', 2);

    $ticket_id = intval($_GET['complete_all_tasks']);

    // Get Client ID
    $sql = mysqli_query($mysqli, "SELECT * FROM tickets WHERE ticket_id = $ticket_id");
    $row = mysqli_fetch_array($sql);
    $client_id = intval($row['ticket_client_id']);

    mysqli_query($mysqli, "UPDATE tasks SET task_completed_at = NOW(), task_completed_by = $session_user_id WHERE task_ticket_id = $ticket_id AND task_completed_at IS NULL");

    // Add reply
    mysqli_query($mysqli, "INSERT INTO ticket_replies SET ticket_reply = 'Marked all tasks complete', ticket_reply_type = 'Internal', ticket_reply_by = $session_user_id, ticket_reply_ticket_id = $ticket_id");

    $ticket_reply_id = mysqli_insert_id($mysqli);

    // Logging
    logAction("Ticket", "Edit", "$session_name marked all tasks complete for ticket", $client_id, $ticket_id);

    $_SESSION['alert_message'] = "Marked all tasks Complete";

    redirect();
}

if (isset($_GET['undo_complete_all_tasks'])) {

    enforceUserPermission('module_support', 2);

    $ticket_id = intval($_GET['undo_complete_all_tasks']);

    // Get Client ID
    $sql = mysqli_query($mysqli, "SELECT * FROM tickets WHERE ticket_id = $ticket_id");
    $row = mysqli_fetch_array($sql);
    $client_id = intval($row['ticket_client_id']);

    mysqli_query($mysqli, "UPDATE tasks SET task_completed_at = NULL, task_completed_by = NULL WHERE task_ticket_id = $ticket_id AND task_completed_at IS NOT NULL");

    // Add reply
    mysqli_query($mysqli, "INSERT INTO ticket_replies SET ticket_reply = 'Marked all tasks incomplete', ticket_reply_type = 'Internal', ticket_reply_by = $session_user_id, ticket_reply_ticket_id = $ticket_id");

    $ticket_reply_id = mysqli_insert_id($mysqli);

    // Logging
    logAction("Ticket", "Edit", "$session_name marked all tasks as incomplete for ticket", $client_id, $ticket_id);

    $_SESSION['alert_message'] = "Marked all tasks Incomplete";

    redirect();
}