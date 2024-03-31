<?php

/*
 * ITFlow - GET/POST request handler for tasks
 */

if (isset($_POST['add_project'])) {

    validateTechRole();

    $project_name = sanitizeInput($_POST['name']);
    $project_description = sanitizeInput($_POST['description']);
    $due_date = sanitizeInput($_POST['due_date']);
    $client_id = intval($_POST['client_id']);
    
    mysqli_query($mysqli, "INSERT INTO projects SET project_name = '$project_name', project_description = '$project_description', project_due = '$due_date', project_client_id = $client_id");

    $project_id = mysqli_insert_id($mysqli);

    // Logging
    mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Project', log_action = 'Create', log_description = '$session_name created project $project_name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $project_id");

    $_SESSION['alert_message'] = "You created Project <strong>$project_name</strong>";

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if (isset($_POST['edit_project'])) {

    validateTechRole();

    $project_id = intval($_POST['project_id']);
    $project_name = sanitizeInput($_POST['name']);
    $project_description = sanitizeInput($_POST['description']);
    $due_date = sanitizeInput($_POST['due_date']);
    $client_id = intval($_POST['client_id']);

    mysqli_query($mysqli, "UPDATE projects SET project_name = '$project_name', project_description = '$project_description', project_due = '$due_date' WHERE project_id = $project_id");

    // Logging
    mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Project', log_action = 'Edit', log_description = '$session_name edited project $project_name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $project_id");

    $_SESSION['alert_message'] = "You edited Project <strong>$project_name</strong>";

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}


if (isset($_GET['delete_project'])) {

    validateTechRole();

    $project_id = intval($_GET['delete_project']);

    // Get Client ID
    $sql = mysqli_query($mysqli, "SELECT * FROM projects WHERE project_id = $project_id");
    $row = mysqli_fetch_array($sql);
    $client_id = intval($row['project_client_id']);
    $project_name = sanitizeInput($row['project_name']);

    mysqli_query($mysqli, "DELETE FROM projects WHERE project_id = $project_id");

    // Logging
    mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Projects', log_action = 'Delete', log_description = '$session_name deleted project $project_name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $project_id");

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "You Deleted Project <strong>$project_name</strong>";

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}
