<?php

// Ticket Templates

// Import shared code from user-side tickets/tasks as we reuse functions
require_once 'post/user/ticket.php';
require_once 'post/user/task.php';

if (isset($_POST['add_ticket_template'])) {

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
    logAction("Ticket Template", "Create", "$session_name created ticket template $name", 0, $ticket_template_id);

    $_SESSION['alert_message'] = "Ticket Template <strong>$name</strong> created";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['edit_ticket_template'])) {

    $ticket_template_id = intval($_POST['ticket_template_id']);
    $name = sanitizeInput($_POST['name']);
    $description = sanitizeInput($_POST['description']);
    $subject = sanitizeInput($_POST['subject']);
    $details = mysqli_real_escape_string($mysqli, $_POST['details']);

    mysqli_query($mysqli, "UPDATE ticket_templates SET ticket_template_name = '$name', ticket_template_description = '$description', ticket_template_subject = '$subject', ticket_template_details = '$details' WHERE ticket_template_id = $ticket_template_id");

    // Logging
    logAction("Ticket Template", "Edit", "$session_name edited ticket template $name", 0, $ticket_template_id);

    $_SESSION['alert_message'] = "Ticket Template <strong>$name</strong> edited";

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if (isset($_GET['delete_ticket_template'])) {

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
    logAction("Ticket Template", "Delete", "$session_name deleted ticket template $ticket_template_name");

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "Ticket Template <strong>$ticket_template_name</strong> and its associated tasks deleted";

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if (isset($_POST['add_ticket_template_task'])) {

    $ticket_template_id = intval($_POST['ticket_template_id']);
    $task_name = sanitizeInput($_POST['task_name']);

    mysqli_query($mysqli, "INSERT INTO task_templates SET task_template_name = '$task_name', task_template_ticket_template_id = $ticket_template_id");

    $task_template_id = mysqli_insert_id($mysqli);

    // Logging
    mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Task Template', log_action = 'Create', log_description = '$session_name created task template $task_name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id, log_entity_id = $ticket_template_id");

    // Logging
    logAction("Ticket Template", "Edit", "$session_name added task $task_name to ticket template", 0, $ticket_template_id);

    $_SESSION['alert_message'] = "Added Task <strong>$task_name</strong>";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_GET['delete_task_template'])) {

    $task_template_id = intval($_GET['delete_task_template']);

    // Get task template name
    $sql = mysqli_query($mysqli, "SELECT * FROM task_templates WHERE task_template_id = $task_template_id");
    $row = mysqli_fetch_array($sql);
    $task_template_name = sanitizeInput($row['task_template_name']);

    mysqli_query($mysqli, "DELETE FROM task_templates WHERE task_template_id = $task_template_id");

    // Logging
    logAction("Ticket Template", "Edit", "$session_name deleted task $task_template_name from ticket template");

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "Task <strong>$task_template_name</strong> deleted";

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}
