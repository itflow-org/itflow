<?php

/*
 * ITFlow - GET/POST request handler for admin
 */

if (isset($_POST['add_project_template'])) {

    validateTechRole();
    $name = sanitizeInput($_POST['name']);
    $description = sanitizeInput($_POST['description']);

    mysqli_query($mysqli, "INSERT INTO project_templates SET project_template_name = '$name', project_template_description = '$description'");

    $project_template_id = mysqli_insert_id($mysqli);

    // Logging
    mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Project Template', log_action = 'Create', log_description = '$session_name created project template $name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id, log_entity_id = $project_template_id");

    $_SESSION['alert_message'] = "You created Project Template <strong>$name</strong>";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['edit_project_template'])) {

    validateTechRole();
    $project_template_id = intval($_POST['project_template_id']);
    $name = sanitizeInput($_POST['name']);
    $description = sanitizeInput($_POST['description']);

    mysqli_query($mysqli, "UPDATE project_templates SET project_template_name = '$name', project_template_description = '$description' WHERE project_template_id = $project_template_id");

    $ticket_template_id = mysqli_insert_id($mysqli);

    // Logging
    mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Project Template', log_action = 'Edit', log_description = '$session_name edited Project template $name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id, log_entity_id = $project_template_id");

    $_SESSION['alert_message'] = "You edited Project Template <strong>$name</strong>";

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if (isset($_POST['add_ticket_template_to_project_template'])) {

    validateTechRole();
    $project_template_id = intval($_POST['project_template_id']);
    $ticket_template_id = intval($_POST['ticket_template_id']);

    mysqli_query($mysqli, "UPDATE ticket_templates SET ticket_template_project_template_id = $project_template_id WHERE ticket_template_id = $ticket_template_id");

    // Logging
    mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Project Template', log_action = 'Edit', log_description = '$session_name added a ticket template to project template', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id, log_entity_id = $project_template_id");

    $_SESSION['alert_message'] = "You added a ticket template to the project template";

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if (isset($_GET['remove_ticket_template_from_project_template'])) {

    validateTechRole();
    $ticket_template_id = intval($_GET['remove_ticket_template_from_project_template']);

    mysqli_query($mysqli, "UPDATE ticket_templates SET ticket_template_project_template_id = 0 WHERE ticket_template_id = $ticket_template_id");

    // Logging
    mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Project Template', log_action = 'Edit', log_description = '$session_name removed a ticket template from a project template', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id, log_entity_id = $ticket_template_id");

    $_SESSION['alert_message'] = "You removed ticket template from the project template";

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if (isset($_GET['delete_project_template'])) {

    validateTechRole();

    $project_template_id = intval($_GET['delete_project_template']);

    // Get project template name
    $sql = mysqli_query($mysqli, "SELECT * FROM project_templates WHERE project_template_id = $project_template_id");
    $row = mysqli_fetch_array($sql);
    $project_template_name = sanitizeInput($row['project_template_name']);

    mysqli_query($mysqli, "DELETE FROM project_templates WHERE project_template_id = $project_template_id");

    // Delete Associated Ticket Tasks
    // First we need to get ticket_template_id Get ticket Templates
    $sql_ticket_templates = mysqli_query($mysqli, "SELECT * FROM ticket_templates WHERE ticket_template_project_template_id = $project_template_id");
    while($row = mysqli_fetch_array($sql_ticket_templates)) {
        $ticket_template_id = intval($row['ticket_template_id']);
        mysqli_query($mysqli, "DELETE FROM task_templates WHERE task_template_ticket_template_id = $ticket_template_id");
    }

    // Then Delete Associated Ticket Templates
    mysqli_query($mysqli, "DELETE FROM ticket_templates WHERE ticket_template_project_template_id = $project_template_id");

    // Logging
    mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Project Template', log_action = 'Delete', log_description = '$session_name deleted ticket template $project_template_name and its associated ticket templates and its tasks', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id, log_entity_id = $project_template_id");

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "You Deleted Project Template <strong>$project_template_name</strong> and its associated ticket templates and tasks";

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if (isset($_POST['add_ticket_template'])) {

    validateTechRole();
    $name = sanitizeInput($_POST['name']);
    $description = sanitizeInput($_POST['description']);
    $subject = sanitizeInput($_POST['subject']);
    $details = mysqli_real_escape_string($mysqli, $_POST['details']);
    $project_template_id = intval($_POST['project_template']);

    mysqli_query($mysqli, "INSERT INTO ticket_templates SET ticket_template_name = '$name', ticket_template_description = '$description', ticket_template_subject = '$subject', ticket_template_details = '$details', ticket_template_project_template_id = $project_template_id");

    $ticket_template_id = mysqli_insert_id($mysqli);

    // Add Tasks to ticket template
    if (!empty($_POST['tasks'])) {
        foreach($_POST['tasks'] as $task) {
            $task_template_name = sanitizeInput($task);
            if (!empty($task_template_name)) {
                mysqli_query($mysqli,"INSERT INTO task_templates SET task_template_name = '$task_template_name', task_templates_ticket_template_id = $ticket_template_id");
            }
        }
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
    $project_template_id = intval($_POST['project_template']);

    mysqli_query($mysqli, "UPDATE ticket_templates SET ticket_template_name = '$name', ticket_template_description = '$description', ticket_template_subject = '$subject', ticket_template_details = '$details', ticket_template_project_template_id = $project_template_id WHERE ticket_template_id = $ticket_template_id");

    $ticket_template_id = mysqli_insert_id($mysqli);

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

if (isset($_POST['add_ticket_status'])) {

    validateTechRole();
    $name = sanitizeInput($_POST['name']);
    $color = sanitizeInput($_POST['color']);

    mysqli_query($mysqli, "INSERT INTO ticket_statuses SET ticket_status_name = '$name', ticket_status_color = '$color'");

    $ticket_status_id = mysqli_insert_id($mysqli);

    // Logging
    mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Ticket Status', log_action = 'Create', log_description = '$session_name created ticket status $name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id, log_entity_id = $ticket_status_id");

    $_SESSION['alert_message'] = "You created Ticket Status <strong>$name</strong>";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['edit_ticket_status'])) {

    validateTechRole();
    $ticket_status_id = intval($_POST['ticket_status_id']);
    $name = sanitizeInput($_POST['name']);
    $color = sanitizeInput($_POST['color']);
    $status = intval($_POST['status']);

    mysqli_query($mysqli, "UPDATE ticket_statuses SET ticket_status_name = '$name', ticket_status_color = '$color', ticket_status_active = $status WHERE ticket_status_id = $ticket_status_id");

    $ticket_status_id = mysqli_insert_id($mysqli);

    // Logging
    mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Ticket Status', log_action = 'Edit', log_description = '$session_name edited ticket status $name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id, log_entity_id = $ticket_status_id");

    $_SESSION['alert_message'] = "You edited Ticket Status <strong>$name</strong>";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_GET['delete_ticket_status'])) {

    validateTechRole();

    $ticket_status_id = intval($_GET['delete_ticket_status']);

    // Get ticket status name for logging and notification
    $sql = mysqli_query($mysqli, "SELECT * FROM ticket_statuses WHERE ticket_status_id = $ticket_status_id");
    $row = mysqli_fetch_array($sql);
    $ticket_status_name = sanitizeInput($row['ticket_status_name']);

    mysqli_query($mysqli, "DELETE FROM ticket_statuses WHERE ticket_status_id = $ticket_status_id");

    // Logging
    mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Ticket Status', log_action = 'Delete', log_description = '$session_name deleted ticket_status $ticket_status_name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id, log_entity_id = $ticket_status_id");

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "You Deleted Ticket Status <strong>$ticket_status_name</strong>";

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}