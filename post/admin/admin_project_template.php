<?php

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['add_project_template'])) {

    $name = sanitizeInput($_POST['name']);
    $description = sanitizeInput($_POST['description']);

    mysqli_query($mysqli, "INSERT INTO project_templates SET project_template_name = '$name', project_template_description = '$description'");

    $project_template_id = mysqli_insert_id($mysqli);

    // Logging
    logAction("Project Template", "Create", "$session_name created project template $name", 0, $project_template_id);

    $_SESSION['alert_message'] = "Project Template <strong>$name</strong> created";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['edit_project_template'])) {

    $project_template_id = intval($_POST['project_template_id']);
    $name = sanitizeInput($_POST['name']);
    $description = sanitizeInput($_POST['description']);

    mysqli_query($mysqli, "UPDATE project_templates SET project_template_name = '$name', project_template_description = '$description' WHERE project_template_id = $project_template_id");

    // Logging
    logAction("Project Template", "Edit", "$session_name edited project template $name", 0, $project_template_id);

    $_SESSION['alert_message'] = "Project Template <strong>$name</strong> edited";

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if (isset($_POST['edit_ticket_template_order'])) {

    $ticket_template_id = intval($_POST['ticket_template_id']);
    $project_template_id = intval($_POST['project_template_id']);
    $order = intval($_POST['order']);

    mysqli_query($mysqli, "UPDATE project_template_ticket_templates SET ticket_template_order = $order WHERE ticket_template_id = $ticket_template_id AND project_template_id = $project_template_id");

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if (isset($_POST['add_ticket_template_to_project_template'])) {

    $project_template_id = intval($_POST['project_template_id']);
    $ticket_template_id = intval($_POST['ticket_template_id']);
    $order = intval($_POST['order']);

    mysqli_query($mysqli, "INSERT INTO project_template_ticket_templates SET project_template_id = $project_template_id, ticket_template_id = $ticket_template_id, ticket_template_order = $order");

    // Logging
    logAction("Project Template", "Edit", "$session_name added ticket template to project_template", 0, $project_template_id);

    $_SESSION['alert_message'] = "Ticket template added";

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if (isset($_POST['remove_ticket_template_from_project_template'])) {

    validateTechRole();
    $ticket_template_id = intval($_POST['ticket_template_id']);
    $project_template_id = intval($_POST['project_template_id']);

    mysqli_query($mysqli, "DELETE FROM project_template_ticket_templates WHERE project_template_id = $project_template_id AND ticket_template_id = $ticket_template_id");

    // Logging
    logAction("Project Template", "Edit", "$session_name removed ticket template from project template", 0, $project_template_id);

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "Ticket template removed";

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if (isset($_GET['delete_project_template'])) {

    $project_template_id = intval($_GET['delete_project_template']);

    // Get project template name
    $sql = mysqli_query($mysqli, "SELECT * FROM project_templates WHERE project_template_id = $project_template_id");
    $row = mysqli_fetch_array($sql);
    $project_template_name = sanitizeInput($row['project_template_name']);

    mysqli_query($mysqli, "DELETE FROM project_templates WHERE project_template_id = $project_template_id");

    // Remove Associated Ticket Templates
    mysqli_query($mysqli, "DELETE FROM project_template_ticket_templates WHERE project_template_id = $project_template_id");

    // Logging
    logAction("Project Template", "Delete", "$session_name deleted project template $project_template_name and its associated ticket templates and tasks");

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "Project Template <strong>$project_template_name</strong> and its associated ticket templates and tasks deleted";

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}
