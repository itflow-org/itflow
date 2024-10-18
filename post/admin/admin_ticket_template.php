<?php

// Ticket Templates

// Import shared code from user-side tickets/tasks as we reuse functions
require_once 'post/user/ticket.php';
require_once 'post/user/task.php';

if (isset($_POST['add_ticket_template'])) {

    validateTechRole();
    $name = sanitizeInput($_POST['name']);
    $description = sanitizeInput($_POST['description']);
    $subject = sanitizeInput($_POST['subject']);
    $details = mysqli_real_escape_string($mysqli, $_POST['details']);
    $project_template_id = intval($_POST['project_template']);

    mysqli_query($mysqli, "INSERT INTO ticket_templates SET ticket_template_name = '$name', ticket_template_description = '$description', ticket_template_subject = '$subject', ticket_template_details = '$details'");

    $ticket_template_id = mysqli_insert_id($mysqli);

    if($project_template_id) {
        mysqli_query($mysqli, "INSERT INTO project_template_ticket_templates SET project_template_id = $project_template_id, ticket_template_id = $ticket_template_id");
    }

    // Logging
    mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Ticket Template', log_action = 'Create', log_description = '$session_name created ticket template $name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id, log_entity_id = $ticket_template_id");

    $_SESSION['alert_message'] = "You created Ticket Template <strong>$name</strong>";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['edit_ticket_template'])) {

    validateTechRole();
    $ticket_template_id = intval($_POST['ticket_template_id']);
    $name = sanitizeInput($_POST['name']);
    $description = sanitizeInput($_POST['description']);
    $subject = sanitizeInput($_POST['subject']);
    $details = mysqli_real_escape_string($mysqli, $_POST['details']);

    mysqli_query($mysqli, "UPDATE ticket_templates SET ticket_template_name = '$name', ticket_template_description = '$description', ticket_template_subject = '$subject', ticket_template_details = '$details' WHERE ticket_template_id = $ticket_template_id");

    // Logging
    mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Ticket Template', log_action = 'Edit', log_description = '$session_name edited ticket template $name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id, log_entity_id = $ticket_template_id");

    $_SESSION['alert_message'] = "You edited Ticket Template <strong>$name</strong>";

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if (isset($_GET['delete_ticket_template'])) {

    validateTechRole();

    $ticket_template_id = intval($_GET['delete_ticket_template']);

    // Get ticket template name
    $sql = mysqli_query($mysqli, "SELECT * FROM ticket_templates WHERE ticket_template_id = $ticket_template_id");
    $row = mysqli_fetch_array($sql);
    $ticket_template_name = sanitizeInput($row['ticket_template_name']);

    mysqli_query($mysqli, "DELETE FROM ticket_templates WHERE ticket_template_id = $ticket_template_id");

    // Delete Associated Tasks
    mysqli_query($mysqli, "DELETE FROM task_templates WHERE task_template_ticket_template_id = $ticket_template_id");

    // Remove from Associated Project Templates
    mysqli_query($mysqli, "DELETE FROM project_template_ticket_templates WHERE ticket_template_id = $ticket_template_id");

    // Logging
    mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Ticket Template', log_action = 'Delete', log_description = '$session_name deleted ticket template $ticket_template_name and its tasks', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id, log_entity_id = $ticket_template_id");

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "You Deleted Ticket Template <strong>$ticket_template_name</strong> and its associated tasks";

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if (isset($_POST['add_ticket_template_task'])) {

    validateTechRole();
    $ticket_template_id = intval($_POST['ticket_template_id']);
    $task_name = sanitizeInput($_POST['task_name']);

    mysqli_query($mysqli, "INSERT INTO task_templates SET task_template_name = '$task_name', task_template_ticket_template_id = $ticket_template_id");

    $task_template_id = mysqli_insert_id($mysqli);

    // Logging
    mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Task Template', log_action = 'Create', log_description = '$session_name created task template $task_name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id, log_entity_id = $ticket_template_id");

    $_SESSION['alert_message'] = "You created Task Template <strong>$task_name</strong>";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_GET['delete_task_template'])) {

    validateTechRole();

    $task_template_id = intval($_GET['delete_task_template']);

    // Get task template name
    $sql = mysqli_query($mysqli, "SELECT * FROM task_templates WHERE task_template_id = $task_template_id");
    $row = mysqli_fetch_array($sql);
    $task_template_name = sanitizeInput($row['task_template_name']);

    mysqli_query($mysqli, "DELETE FROM task_templates WHERE task_template_id = $task_template_id");

    // Logging
    mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Task Template', log_action = 'Delete', log_description = '$session_name deleted task template $task_template_name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id, log_entity_id = $task_template_id");

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "You Deleted Task Template <strong>$task_template_name</strong>";

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}
