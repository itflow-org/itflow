<?php

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

    // Logging
    mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Project Template', log_action = 'Edit', log_description = '$session_name edited Project template $name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id, log_entity_id = $project_template_id");

    $_SESSION['alert_message'] = "You edited Project Template <strong>$name</strong>";

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if (isset($_POST['edit_ticket_template_order'])) {

    validateTechRole();
    $ticket_template_id = intval($_POST['ticket_template_id']);
    $project_template_id = intval($_POST['project_template_id']);
    $order = intval($_POST['order']);

    mysqli_query($mysqli, "UPDATE project_template_ticket_templates SET ticket_template_order = $order WHERE ticket_template_id = $ticket_template_id AND project_template_id = $project_template_id");

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if (isset($_POST['add_ticket_template_to_project_template'])) {

    validateTechRole();
    $project_template_id = intval($_POST['project_template_id']);
    $ticket_template_id = intval($_POST['ticket_template_id']);
    $order = intval($_POST['order']);

    mysqli_query($mysqli, "INSERT INTO project_template_ticket_templates SET project_template_id = $project_template_id, ticket_template_id = $ticket_template_id, ticket_template_order = $order");

    // Logging
    mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Project Template', log_action = 'Edit', log_description = '$session_name added a ticket template to project template', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id, log_entity_id = $project_template_id");

    $_SESSION['alert_message'] = "You added a ticket template to the project template";

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if (isset($_POST['remove_ticket_template_from_project_template'])) {

    validateTechRole();
    $ticket_template_id = intval($_POST['ticket_template_id']);
    $project_template_id = intval($_POST['project_template_id']);

    mysqli_query($mysqli, "DELETE FROM project_template_ticket_templates WHERE project_template_id = $project_template_id AND ticket_template_id = $ticket_template_id");

    // Logging
    mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Project Template', log_action = 'Edit', log_description = '$session_name removed a ticket template from a project template', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id, log_entity_id = $project_template_id");

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

    // Remove Associated Ticket Templates
    mysqli_query($mysqli, "DELETE FROM project_template_ticket_templates WHERE project_template_id = $project_template_id");

    // Logging
    mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Project Template', log_action = 'Delete', log_description = '$session_name deleted ticket template $project_template_name and its associated ticket templates and its tasks', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id, log_entity_id = $project_template_id");

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "You Deleted Project Template <strong>$project_template_name</strong> and its associated ticket templates and tasks";

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}
