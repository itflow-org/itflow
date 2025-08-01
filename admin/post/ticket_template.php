<?php

// Ticket Templates

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

// Import shared code from user-side tickets/tasks as we reuse functions
require_once '../user/post/ticket.php';
require_once '../user/post/task.php';

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

    logAction("Ticket Template", "Create", "$session_name created ticket template $name", 0, $ticket_template_id);

    flash_alert("Ticket Template <strong>$name</strong> created");

    redirect();

}

if (isset($_POST['edit_ticket_template'])) {

    $ticket_template_id = intval($_POST['ticket_template_id']);
    $name = sanitizeInput($_POST['name']);
    $description = sanitizeInput($_POST['description']);
    $subject = sanitizeInput($_POST['subject']);
    $details = mysqli_real_escape_string($mysqli, $_POST['details']);

    mysqli_query($mysqli, "UPDATE ticket_templates SET ticket_template_name = '$name', ticket_template_description = '$description', ticket_template_subject = '$subject', ticket_template_details = '$details' WHERE ticket_template_id = $ticket_template_id");

    logAction("Ticket Template", "Edit", "$session_name edited ticket template $name", 0, $ticket_template_id);

    flash_alert("Ticket Template <strong>$name</strong> edited");

    redirect();
}

if (isset($_GET['delete_ticket_template'])) {

    $ticket_template_id = intval($_GET['delete_ticket_template']);

    $ticket_template_name = sanitizeInput(getFieldById('ticket_templates', $ticket_template_id, 'ticket_template_name'));

    mysqli_query($mysqli, "DELETE FROM ticket_templates WHERE ticket_template_id = $ticket_template_id");

    // Delete Associations
    mysqli_query($mysqli, "DELETE FROM task_templates WHERE task_template_ticket_template_id = $ticket_template_id");
    mysqli_query($mysqli, "DELETE FROM project_template_ticket_templates WHERE ticket_template_id = $ticket_template_id");

    logAction("Ticket Template", "Delete", "$session_name deleted ticket template $ticket_template_name");

    flash_alert("Ticket Template <strong>$ticket_template_name</strong> and its associated tasks deleted", 'error');

    redirect();

}

if (isset($_POST['add_ticket_template_task'])) {

    $ticket_template_id = intval($_POST['ticket_template_id']);
    $task_name = sanitizeInput($_POST['task_name']);

    mysqli_query($mysqli, "INSERT INTO task_templates SET task_template_name = '$task_name', task_template_ticket_template_id = $ticket_template_id");

    $task_template_id = mysqli_insert_id($mysqli);

    logAction("Ticket Template", "Create", "$session_name created task $task_name for ticket template", 0, $ticket_template_id);

    flash_alert("Added Task <strong>$task_name</strong>");

    redirect();

}

if (isset($_GET['delete_task_template'])) {

    $task_template_id = intval($_GET['delete_task_template']);

    $task_template_name = sanitizeInput(getFieldById('tags', $task_template_id, 'task_template_name'));

    mysqli_query($mysqli, "DELETE FROM task_templates WHERE task_template_id = $task_template_id");

    logAction("Ticket Template", "Edit", "$session_name deleted task $task_template_name from ticket template");

    flash_alert("Task <strong>$task_template_name</strong> deleted", 'error');

    redirect();
    
}
