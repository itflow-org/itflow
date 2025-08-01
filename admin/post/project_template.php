<?php

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['add_project_template'])) {

    $name = sanitizeInput($_POST['name']);
    $description = sanitizeInput($_POST['description']);

    mysqli_query($mysqli, "INSERT INTO project_templates SET project_template_name = '$name', project_template_description = '$description'");

    $project_template_id = mysqli_insert_id($mysqli);

    logAction("Project Template", "Create", "$session_name created project template $name", 0, $project_template_id);

    flash_alert("Project Template <strong>$name</strong> created");

    redirect();

}

if (isset($_POST['edit_project_template'])) {

    $project_template_id = intval($_POST['project_template_id']);
    $name = sanitizeInput($_POST['name']);
    $description = sanitizeInput($_POST['description']);

    mysqli_query($mysqli, "UPDATE project_templates SET project_template_name = '$name', project_template_description = '$description' WHERE project_template_id = $project_template_id");

    logAction("Project Template", "Edit", "$session_name edited project template $name", 0, $project_template_id);

    flash_alert("Project Template <strong>$name</strong> edited");

    redirect();

}

if (isset($_POST['edit_ticket_template_order'])) {

    $ticket_template_id = intval($_POST['ticket_template_id']);
    $project_template_id = intval($_POST['project_template_id']);
    $order = intval($_POST['order']);

    mysqli_query($mysqli, "UPDATE project_template_ticket_templates SET ticket_template_order = $order WHERE ticket_template_id = $ticket_template_id AND project_template_id = $project_template_id");

    redirect();

}

if (isset($_POST['add_ticket_template_to_project_template'])) {

    $project_template_id = intval($_POST['project_template_id']);
    $ticket_template_id = intval($_POST['ticket_template_id']);
    $order = intval($_POST['order']);

    mysqli_query($mysqli, "INSERT INTO project_template_ticket_templates SET project_template_id = $project_template_id, ticket_template_id = $ticket_template_id, ticket_template_order = $order");

    logAction("Project Template", "Edit", "$session_name added ticket template to project_template", 0, $project_template_id);

    flash_alert("Ticket template added");

    redirect();

}

if (isset($_POST['remove_ticket_template_from_project_template'])) {

    validateTechRole();
    $ticket_template_id = intval($_POST['ticket_template_id']);
    $project_template_id = intval($_POST['project_template_id']);

    mysqli_query($mysqli, "DELETE FROM project_template_ticket_templates WHERE project_template_id = $project_template_id AND ticket_template_id = $ticket_template_id");

    logAction("Project Template", "Edit", "$session_name removed ticket template from project template", 0, $project_template_id);

    flash_alert("Ticket template removed", 'error');

    redirect();

}

if (isset($_GET['delete_project_template'])) {

    $project_template_id = intval($_GET['delete_project_template']);

    $project_template_name = sanitizeInput(getFieldById('project_templates', $project_template_id, 'project_template_name'));

    mysqli_query($mysqli, "DELETE FROM project_templates WHERE project_template_id = $project_template_id");

    // Remove Associated Ticket Templates
    mysqli_query($mysqli, "DELETE FROM project_template_ticket_templates WHERE project_template_id = $project_template_id");

    logAction("Project Template", "Delete", "$session_name deleted project template $project_template_name and its associated ticket templates and tasks");

    flash_alert("Project Template <strong>$project_template_name</strong> and its associated ticket templates and tasks deleted", 'error');

    redirect();
    
}
