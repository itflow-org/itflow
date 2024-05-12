<?php

/*
 * ITFlow - GET/POST request handler for tasks
 */

if (isset($_POST['add_project'])) {

    validateTechRole();

    $project_name = sanitizeInput($_POST['name']);
    $project_description = sanitizeInput($_POST['description']);
    $due_date = sanitizeInput($_POST['due_date']);
    $project_manager = intval($_POST['project_manager']);
    $client_id = intval($_POST['client_id']);
    $project_template_id = intval($_POST['project_template_id']);

    // Sanitize Project Prefix
    $config_project_prefix = sanitizeInput($config_project_prefix);

    // Get the next Project Number and add 1 for the new Project number
    $project_number = $config_project_next_number;
    $new_config_project_next_number = $config_project_next_number + 1;

    mysqli_query($mysqli, "UPDATE settings SET config_project_next_number = $new_config_project_next_number WHERE company_id = 1");
    
    mysqli_query($mysqli, "INSERT INTO projects SET project_prefix = '$config_project_prefix', project_number = $project_number, project_name = '$project_name', project_description = '$project_description', project_due = '$due_date', project_manager = $project_manager, project_client_id = $client_id");

    $project_id = mysqli_insert_id($mysqli);

    // If project template is selected add Ticket Templates and convert them to real tickets
    if($project_template_id) {
         // Get Associated Ticket Templates
        $sql_ticket_templates = mysqli_query($mysqli, "SELECT * FROM ticket_templates, project_template_ticket_templates
            WHERE ticket_templates.ticket_template_id = project_template_ticket_templates.ticket_template_id
            AND project_template_ticket_templates.project_template_id = $project_template_id");
        $ticket_template_count = mysqli_num_rows($sql_ticket_templates);

        while ($row = mysqli_fetch_array($sql_ticket_templates)) {
            $ticket_template_id = intval($row['ticket_template_id']);
            $ticket_template_order = intval($row['ticket_template_order']);
            $ticket_template_subject = sanitizeInput($row['ticket_template_subject']);
            $ticket_template_details = mysqli_escape_string($mysqli, $row['ticket_template_details']);

            // Get the next Ticket Number and add 1 for the new ticket number
            $ticket_number = $config_ticket_next_number;
            $new_config_ticket_next_number = $config_ticket_next_number + 1;
            mysqli_query($mysqli, "UPDATE settings SET config_ticket_next_number = $new_config_ticket_next_number WHERE company_id = 1");

            mysqli_query($mysqli, "INSERT INTO tickets SET ticket_prefix = '$config_ticket_prefix', ticket_number = $ticket_number, ticket_subject = '$ticket_template_subject', ticket_details = '$ticket_template_details', ticket_priority = 'Low', ticket_status = 1, ticket_created_by = $session_user_id, ticket_client_id = $client_id, ticket_project_id = $project_id");

            $config_ticket_next_number = $config_ticket_next_number + 1;

            $ticket_id = mysqli_insert_id($mysqli);

            // Task Templates for Ticket template and add the to the ticket
            $sql_task_templates = mysqli_query($mysqli,
                "SELECT * FROM task_templates WHERE task_template_ticket_template_id = $ticket_template_id");
            $task_template_count = mysqli_num_rows($sql_task_templates);

            while ($row = mysqli_fetch_array($sql_task_templates)) {
                $task_template_id = intval($row['task_template_id']);
                $task_template_order = intval($row['task_template_order']);
                $task_template_name = sanitizeInput($row['task_template_name']);

                mysqli_query($mysqli,"INSERT INTO tasks SET task_name = '$task_template_name', task_order = $task_template_order, task_ticket_id = $ticket_id");
            } // End task Loop
        } // End Ticket Loop
    } // End If Project Template

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
    $project_manager = intval($_POST['project_manager']);
    $client_id = intval($_POST['client_id']);

    mysqli_query($mysqli, "UPDATE projects SET project_name = '$project_name', project_description = '$project_description', project_due = '$due_date', project_manager = $project_manager WHERE project_id = $project_id");

    // Logging
    mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Project', log_action = 'Edit', log_description = '$session_name edited project $project_name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $project_id");

    $_SESSION['alert_message'] = "You edited Project <strong>$project_name</strong>";

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if (isset($_GET['close_project'])) {

    validateTechRole();

    $project_id = intval($_GET['close_project']);

    // Get Project Name and client id for logging
    $sql = mysqli_query($mysqli, "SELECT * FROM projects WHERE project_id = $project_id");
    $row = mysqli_fetch_array($sql);
    $client_id = intval($row['project_client_id']);
    $project_name = sanitizeInput($row['project_name']);

    mysqli_query($mysqli, "UPDATE projects SET project_completed_at = NOW() WHERE project_id = $project_id");

    // Logging
    mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Project', log_action = 'Close', log_description = '$session_name closed project $project_name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $project_id");

    $_SESSION['alert_message'] = "You closed Project <strong>$project_name</strong>";

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

if (isset($_POST['add_project_ticket'])) {

    validateTechRole();
    $project_id = intval($_POST['project_id']);
    $ticket_id = intval($_POST['ticket_id']);

    // Get Project Name
    $sql = mysqli_query($mysqli, "SELECT * FROM projects WHERE project_id = $project_id");
    $row = mysqli_fetch_array($sql);
    $client_id = intval($row['project_client_id']);
    $project_name = sanitizeInput($row['project_name']);

    // Get Ticket Info
    $sql = mysqli_query($mysqli, "SELECT * FROM tickets WHERE ticket_project_id = $project_id");
    $row = mysqli_fetch_array($sql);
    $ticket_subject = sanitizeInput($row['ticket_subject']);
    
    mysqli_query($mysqli, "UPDATE tickets SET ticket_project_id = $project_id WHERE ticket_id = $ticket_id");

    // Logging
    mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Project', log_action = 'Edit', log_description = '$session_name added a ticket $ticket_subject to project $project_name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $project_id");

    $_SESSION['alert_message'] = "You added Ticket <strong>$ticket_subject</strong> to <strong>$project_name</strong>";

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}