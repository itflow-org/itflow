<?php

/*
 * ITFlow - GET/POST request handler for tasks
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['add_project'])) {

    enforceUserPermission('module_support', 2);

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
    logAction("Project", "Create", "$session_name created project $project_name", $client_id, $project_id);

    $_SESSION['alert_message'] = "You created Project <strong>$project_name</strong>";

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if (isset($_POST['edit_project'])) {

    enforceUserPermission('module_support', 2);

    $project_id = intval($_POST['project_id']);
    $project_name = sanitizeInput($_POST['name']);
    $project_description = sanitizeInput($_POST['description']);
    $due_date = sanitizeInput($_POST['due_date']);
    $project_manager = intval($_POST['project_manager']);
    $client_id = intval($_POST['client_id']);

    mysqli_query($mysqli, "UPDATE projects SET project_name = '$project_name', project_description = '$project_description', project_due = '$due_date', project_manager = $project_manager, project_client_id = $client_id WHERE project_id = $project_id");

    // Logging
    logAction("Project", "Edit", "$session_name edited project $project_name", $client_id, $project_id);

    $_SESSION['alert_message'] = "Project <strong>$project_name</strong> edited";

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if (isset($_GET['close_project'])) {

    enforceUserPermission('module_support', 2);

    $project_id = intval($_GET['close_project']);

    // Get Project Name and Client ID for logging
    $sql = mysqli_query($mysqli, "SELECT project_name, project_client_id FROM projects WHERE project_id = $project_id");
    $row = mysqli_fetch_array($sql);
    $project_name = sanitizeInput($row['project_name']);
    $client_id = intval($row['project_client_id']);

    mysqli_query($mysqli, "UPDATE projects SET project_completed_at = NOW() WHERE project_id = $project_id");

    // Logging
    logAction("Project", "Close", "$session_name closed project $project_name", $client_id, $project_id);

    $_SESSION['alert_message'] = "Project <strong>$project_name</strong> closed";

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if (isset($_GET['archive_project'])) {

    enforceUserPermission('module_support', 2);

    $project_id = intval($_GET['archive_project']);

    // Get Project Name and Client ID for logging
    $sql = mysqli_query($mysqli, "SELECT project_name, project_client_id FROM projects WHERE project_id = $project_id");
    $row = mysqli_fetch_array($sql);
    $project_name = sanitizeInput($row['project_name']);
    $client_id = intval($row['project_client_id']);

    mysqli_query($mysqli, "UPDATE projects SET project_archived_at = NOW() WHERE project_id = $project_id");

    // Logging
    logAction("Project", "Archive", "$session_name archived project $project_name", $client_id, $project_id);

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "Project <strong>$project_name</strong> archived";

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if (isset($_GET['unarchive_project'])) {

    enforceUserPermission('module_support', 2);

    $project_id = intval($_GET['unarchive_project']);

    // Get Project Name and Client ID for logging
    $sql = mysqli_query($mysqli, "SELECT project_name, project_client_id FROM projects WHERE project_id = $project_id");
    $row = mysqli_fetch_array($sql);
    $project_name = sanitizeInput($row['project_name']);
    $client_id = sanitizeInput($row['project_client_id']);

    mysqli_query($mysqli, "UPDATE projects SET project_archived_at = NULL WHERE project_id = $project_id");

    // Logging
    logAction("Project", "Unarchive", "$session_name unarchived project $project_name", $client_id, $project_id);

    $_SESSION['alert_message'] = "Project <strong>$project_name</strong> unarchived";

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if (isset($_GET['delete_project'])) {

    enforceUserPermission('module_support', 3);

    // CSRF Check
    validateCSRFToken($_GET['csrf_token']);

    $project_id = intval($_GET['delete_project']);

    // Get Project Name and Client ID for logging
    $sql = mysqli_query($mysqli, "SELECT project_name, project_client_id FROM projects WHERE project_id = $project_id");
    $row = mysqli_fetch_array($sql);
    $project_name = sanitizeInput($row['project_name']);
    $client_id = intval($row['project_client_id']);

    mysqli_query($mysqli, "DELETE FROM projects WHERE project_id = $project_id");

    // Logging
    logAction("Project", "Delete", "$session_name deleted project $project_name", $client_id, $project_id);

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "Project <strong>$project_name</strong> Deleted";

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if (isset($_POST['link_ticket_to_project'])) {

    enforceUserPermission('module_support', 2);
    $project_id = intval($_POST['project_id']);

    // Get Project Name and Client ID for logging
    $sql = mysqli_query($mysqli, "SELECT project_client_id, project_name FROM projects WHERE project_id = $project_id");
    $row = mysqli_fetch_array($sql);
    $client_id = intval($row['project_client_id']);
    $project_name = sanitizeInput($row['project_name']);

    // Add Tickets
    if (isset($_POST['tickets'])) {

        // Get Selected Count
        $count = count($_POST['tickets']);

        foreach ($_POST['tickets'] as $ticket) {
            $ticket_id = intval($ticket);

            // Get Ticket Info
            $sql = mysqli_query($mysqli, "SELECT ticket_prefix, ticket_number, ticket_subject FROM tickets WHERE ticket_id = $ticket_id");
            $row = mysqli_fetch_array($sql);
            $ticket_prefix = sanitizeInput($row['ticket_prefix']);
            $ticket_number = intval($row['ticket_number']);
            $ticket_subject = sanitizeInput($row['ticket_subject']);

            mysqli_query($mysqli, "UPDATE tickets SET ticket_project_id = $project_id WHERE ticket_id = $ticket_id");

            // Logging
            logAction("Project", "Edit", "$session_name added ticket $ticket_prefix$ticket_number - $ticket_subject to project $project_name", $client_id, $project_id);

        }

        // Bulk Logging
        logAction("Project", "Bulk Edit", "$session_name added $count ticket(s) to project $project_name", $client_id, $project_id);

        $_SESSION['alert_message'] = "<strong>$count</strong> Ticket(s) added to <strong>$project_name</strong>";
    }

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}
