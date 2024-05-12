<?php

/*
 * ITFlow - GET/POST request handler for tasks
 */

if (isset($_POST['add_task'])) {

    validateTechRole();

    $ticket_id = intval($_POST['ticket_id']);
    $task_name = sanitizeInput($_POST['name']);

    // Get Client ID from tickets using the ticket_id
    $sql = mysqli_query($mysqli, "SELECT * FROM tickets WHERE ticket_id = $ticket_id");
    $row = mysqli_fetch_array($sql);
    $client_id = intval($row['ticket_client_id']);
    
    mysqli_query($mysqli, "INSERT INTO tasks SET task_name = '$task_name', task_ticket_id = $ticket_id");

    $task_id = mysqli_insert_id($mysqli);

    // Logging
    mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Task', log_action = 'Create', log_description = '$session_name created task $task_name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $task_id");

    $_SESSION['alert_message'] = "You created Task <strong>$task_name</strong>";

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if (isset($_POST['edit_task'])) {

    validateTechRole();

    $task_id = intval($_POST['task_id']);
    $task_name = sanitizeInput($_POST['name']);

    // Get Client ID
    $sql = mysqli_query($mysqli, "SELECT * FROM tasks LEFT JOIN tickets ON ticket_id = task_ticket_id WHERE task_id = $task_id");
    $row = mysqli_fetch_array($sql);
    $client_id = intval($row['ticket_client_id']);

    mysqli_query($mysqli, "UPDATE tasks SET task_name = '$task_name' WHERE task_id = $task_id");

    // Logging
    mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Task', log_action = 'Edit', log_description = '$session_name edited task $task_name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $task_id");

    $_SESSION['alert_message'] = "You edited Task <strong>$task_name</strong>";

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}


if (isset($_GET['delete_task'])) {

    validateTechRole();

    $task_id = intval($_GET['delete_task']);

    // Get Client ID, task name from tasks and tickets using the task_id
    $sql = mysqli_query($mysqli, "SELECT * FROM tasks LEFT JOIN tickets ON ticket_id = task_ticket_id WHERE task_id = $task_id");
    $row = mysqli_fetch_array($sql);
    $client_id = intval($row['ticket_client_id']);
    $task_name = sanitizeInput($row['task_name']);

    mysqli_query($mysqli, "DELETE FROM tasks WHERE task_id = $task_id");

    // Logging
    mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Task', log_action = 'Delete', log_description = '$session_name deleted task $task_name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $task_id");

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "You Deleted Task <strong>$task_name</strong>";

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if (isset($_GET['complete_task'])) {

    validateTechRole();

    $task_id = intval($_GET['complete_task']);

    // Get Client ID
    $sql = mysqli_query($mysqli, "SELECT * FROM tasks LEFT JOIN tickets ON ticket_id = task_ticket_id WHERE task_id = $task_id");
    $row = mysqli_fetch_array($sql);
    $client_id = intval($row['ticket_client_id']);
    $task_name = sanitizeInput($row['task_name']);
    $ticket_id = intval($row['ticket_id']);

    mysqli_query($mysqli, "UPDATE tasks SET task_completed_at = NOW(), task_completed_by = $session_user_id WHERE task_id = $task_id");

    // Add reply
    mysqli_query($mysqli, "INSERT INTO ticket_replies SET ticket_reply = 'Completed Task - $task_name', ticket_reply_time_worked = '00:05:00', ticket_reply_type = 'Internal', ticket_reply_by = $session_user_id, ticket_reply_ticket_id = $ticket_id");

    $ticket_reply_id = mysqli_insert_id($mysqli);

    // Logging
    mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Task', log_action = 'Edit', log_description = '$session_name completed task $task_name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $task_id");

    $_SESSION['alert_message'] = "You completed Task <strong>$task_name</strong> Great Job!<i class='far fa-4x fa-smile-wink ml-2'></i>";

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}