<?php

/*
 * ITFlow - GET/POST request handler for tasks
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['add_project'])) {

    validateCSRFToken($_POST['csrf_token']);

    enforceUserPermission('module_support', 2);

    $project_name = escapeSql($_POST['name']);
    $project_description = escapeSql($_POST['description']);
    $due_date = escapeSql($_POST['due_date']);
    $project_manager = intval($_POST['project_manager']);
    $client_id = intval($_POST['client_id']);
    $project_template_id = intval($_POST['project_template_id']);

    // Don't Enforce Client Access if Project doesn't have an assigned client
    if ($client_id) {
        enforceClientAccess();
    }

    // Sanitize Project Prefix
    $config_project_prefix = escapeSql($config_project_prefix);

    // Atomically increment and get the new project number
    mysqli_query($mysqli, "
        UPDATE settings
        SET
            config_project_next_number = LAST_INSERT_ID(config_project_next_number),
            config_project_next_number = config_project_next_number + 1
        WHERE company_id = 1
    ");

    $project_number = mysqli_insert_id($mysqli);

    mysqli_query($mysqli, "INSERT INTO projects SET project_prefix = '$config_project_prefix', project_number = $project_number, project_name = '$project_name', project_description = '$project_description', project_due = '$due_date', project_manager = $project_manager, project_client_id = $client_id");

    $project_id = mysqli_insert_id($mysqli);

    // If project template is selected add Ticket Templates and convert them to real tickets
    if($project_template_id) {
         // Get Associated Ticket Templates
        $sql_ticket_templates = mysqli_query($mysqli, "SELECT * FROM ticket_templates, project_template_ticket_templates
            WHERE ticket_templates.ticket_template_id = project_template_ticket_templates.ticket_template_id
            AND project_template_ticket_templates.project_template_id = $project_template_id");
        $ticket_template_count = mysqli_num_rows($sql_ticket_templates);

        while ($row = mysqli_fetch_assoc($sql_ticket_templates)) {
            $ticket_template_id = intval($row['ticket_template_id']);
            $ticket_template_order = intval($row['ticket_template_order']);
            $ticket_template_subject = escapeSql($row['ticket_template_subject']);
            $ticket_template_details = mysqli_escape_string($mysqli, $row['ticket_template_details']);

            // Atomically increment and get the new ticket number
            mysqli_query($mysqli, "
                UPDATE settings
                SET
                    config_ticket_next_number = LAST_INSERT_ID(config_ticket_next_number),
                    config_ticket_next_number = config_ticket_next_number + 1
                WHERE company_id = 1
            ");

            $ticket_number = mysqli_insert_id($mysqli);

            mysqli_query($mysqli, "INSERT INTO tickets SET ticket_prefix = '$config_ticket_prefix', ticket_number = $ticket_number, ticket_subject = '$ticket_template_subject', ticket_details = '$ticket_template_details', ticket_priority = 'Low', ticket_status = 1, ticket_created_by = $session_user_id, ticket_client_id = $client_id, ticket_project_id = $project_id");

            $ticket_id = mysqli_insert_id($mysqli);

            // Task Templates for Ticket template and add the to the ticket
            $sql_task_templates = mysqli_query($mysqli,
                "SELECT * FROM task_templates WHERE task_template_ticket_template_id = $ticket_template_id");
            $task_template_count = mysqli_num_rows($sql_task_templates);

            while ($row = mysqli_fetch_assoc($sql_task_templates)) {
                $task_template_id = intval($row['task_template_id']);
                $task_template_order = intval($row['task_template_order']);
                $task_template_name = escapeSql($row['task_template_name']);

                mysqli_query($mysqli,"INSERT INTO tasks SET task_name = '$task_template_name', task_order = $task_template_order, task_ticket_id = $ticket_id");
            } // End task Loop
        } // End Ticket Loop
    } // End If Project Template

    logAction("Project", "Create", "$session_name created project $project_name", $client_id, $project_id);

    flash_alert("You created Project <strong>$project_name</strong>");

    redirect();

}

if (isset($_POST['edit_project'])) {

    validateCSRFToken($_POST['csrf_token']);

    enforceUserPermission('module_support', 2);

    $project_id = intval($_POST['project_id']);
    $project_name = escapeSql($_POST['name']);
    $project_description = escapeSql($_POST['description']);
    $due_date = escapeSql($_POST['due_date']);
    $project_manager = intval($_POST['project_manager']);
    $client_id = intval($_POST['client_id']);

    // Don't Enforce Client Access if Project doesn't have an assigned client
    if ($client_id) {
        enforceClientAccess();
    }

    mysqli_query($mysqli, "UPDATE projects SET project_name = '$project_name', project_description = '$project_description', project_due = '$due_date', project_manager = $project_manager, project_client_id = $client_id WHERE project_id = $project_id");

    logAction("Project", "Edit", "$session_name edited project $project_name", $client_id, $project_id);

    flash_alert("Project <strong>$project_name</strong> edited");

    redirect();

}

if (isset($_GET['close_project'])) {

    validateCSRFToken($_GET['csrf_token']);

    enforceUserPermission('module_support', 2);

    $project_id = intval($_GET['close_project']);

    // Get Project Name and Client ID for logging
    $sql = mysqli_query($mysqli, "SELECT project_name, project_client_id FROM projects WHERE project_id = $project_id");
    $row = mysqli_fetch_assoc($sql);
    $project_name = escapeSql($row['project_name']);
    $client_id = intval($row['project_client_id']);

    // Don't Enforce Client Access if Project doesn't have an assigned client
    if ($client_id) {
        enforceClientAccess();
    }

    mysqli_query($mysqli, "UPDATE projects SET project_completed_at = NOW() WHERE project_id = $project_id");

    logAction("Project", "Close", "$session_name closed project $project_name", $client_id, $project_id);

    flash_alert("Project <strong>$project_name</strong> closed");

    redirect();

}

if (isset($_GET['archive_project'])) {

    validateCSRFToken($_GET['csrf_token']);

    enforceUserPermission('module_support', 2);

    $project_id = intval($_GET['archive_project']);

    // Get Project Name and Client ID for logging
    $sql = mysqli_query($mysqli, "SELECT project_name, project_client_id FROM projects WHERE project_id = $project_id");
    $row = mysqli_fetch_assoc($sql);
    $project_name = escapeSql($row['project_name']);
    $client_id = intval($row['project_client_id']);

    // Don't Enforce Client Access if Project doesn't have an assigned client
    if ($client_id) {
        enforceClientAccess();
    }

    mysqli_query($mysqli, "UPDATE projects SET project_archived_at = NOW() WHERE project_id = $project_id");

    logAction("Project", "Archive", "$session_name archived project $project_name", $client_id, $project_id);

    flash_alert("Project <strong>$project_name</strong> archived", 'error');

    redirect();

}

if (isset($_GET['restore_project'])) {

    validateCSRFToken($_GET['csrf_token']);

    enforceUserPermission('module_support', 2);

    $project_id = intval($_GET['restore_project']);

    // Get Project Name and Client ID for logging
    $sql = mysqli_query($mysqli, "SELECT project_name, project_client_id FROM projects WHERE project_id = $project_id");
    $row = mysqli_fetch_assoc($sql);
    $project_name = escapeSql($row['project_name']);
    $client_id = escapeSql($row['project_client_id']);

    // Don't Enforce Client Access if Project doesn't have an assigned client
    if ($client_id) {
        enforceClientAccess();
    }

    mysqli_query($mysqli, "UPDATE projects SET project_archived_at = NULL WHERE project_id = $project_id");

    logAction("Project", "Restore", "$session_name restored project $project_name", $client_id, $project_id);

    flash_alert("Project <strong>$project_name</strong> restored");

    redirect();

}

if (isset($_GET['delete_project'])) {

    validateCSRFToken($_GET['csrf_token']);

    enforceUserPermission('module_support', 3);

    $project_id = intval($_GET['delete_project']);

    // Get Project Name and Client ID for logging
    $sql = mysqli_query($mysqli, "SELECT project_name, project_client_id FROM projects WHERE project_id = $project_id");
    $row = mysqli_fetch_assoc($sql);
    $project_name = escapeSql($row['project_name']);
    $client_id = intval($row['project_client_id']);

    // Don't Enforce Client Access if Project doesn't have an assigned client
    if ($client_id) {
        enforceClientAccess();
    }

    mysqli_query($mysqli, "DELETE FROM projects WHERE project_id = $project_id");

    logAction("Project", "Delete", "$session_name deleted project $project_name", $client_id, $project_id);

    flash_alert("Project <strong>$project_name</strong> Deleted", 'error');

    redirect();

}

if (isset($_POST['link_ticket_to_project'])) {

    validateCSRFToken($_POST['csrf_token']);

    enforceUserPermission('module_support', 2);

    $project_id = intval($_POST['project_id']);

    // Get Project Name and Client ID for logging
    $sql = mysqli_query($mysqli, "SELECT project_client_id, project_name FROM projects WHERE project_id = $project_id");
    $row = mysqli_fetch_assoc($sql);
    $client_id = intval($row['project_client_id']);
    $project_name = escapeSql($row['project_name']);

    // Don't Enforce Client Access if Project doesn't have an assigned client
    if ($client_id) {
        enforceClientAccess();
    }

    // Add Tickets
    if (isset($_POST['tickets'])) {

        // Get Selected Count
        $count = count($_POST['tickets']);

        foreach ($_POST['tickets'] as $ticket) {
            $ticket_id = intval($ticket);

            // Get Ticket Info
            $sql = mysqli_query($mysqli, "SELECT ticket_prefix, ticket_number, ticket_subject FROM tickets WHERE ticket_id = $ticket_id");
            $row = mysqli_fetch_assoc($sql);
            $ticket_prefix = escapeSql($row['ticket_prefix']);
            $ticket_number = intval($row['ticket_number']);
            $ticket_subject = escapeSql($row['ticket_subject']);

            mysqli_query($mysqli, "UPDATE tickets SET ticket_project_id = $project_id WHERE ticket_id = $ticket_id");

            logAction("Project", "Edit", "$session_name added ticket $ticket_prefix$ticket_number - $ticket_subject to project $project_name", $client_id, $project_id);

        }

        logAction("Project", "Bulk Edit", "$session_name added $count ticket(s) to project $project_name", $client_id, $project_id);

        flash_alert("<strong>$count</strong> Ticket(s) added to <strong>$project_name</strong>");
    }

    redirect();

}

if (isset($_POST['link_closed_ticket_to_project'])) {

    validateCSRFToken($_POST['csrf_token']);

    enforceUserPermission('module_support', 2);

    $project_id = intval($_POST['project_id']);
    $ticket_number = intval($_POST['ticket_number']);

    // Get Project Name and Client ID for logging
    $sql = mysqli_query($mysqli, "SELECT project_client_id, project_name FROM projects WHERE project_id = $project_id");
    $row = mysqli_fetch_assoc($sql);
    $client_id = intval($row['project_client_id']);
    $project_name = escapeSql($row['project_name']);

    // Don't Enforce Client Access if Project doesn't have an assigned client
    if ($client_id) {
        enforceClientAccess();
    }

    // Get ticket details
    $sql = mysqli_query($mysqli, "SELECT ticket_id, ticket_prefix, ticket_number, ticket_subject, ticket_updated_at FROM tickets WHERE ticket_number = $ticket_number");
    if (mysqli_num_rows($sql) == 0) {
        flash_alert("Cannot merge into that ticket.", 'error');
        redirect();
    }
    $row = mysqli_fetch_assoc($sql);
    $ticket_id = intval($row['ticket_id']);
    $ticket_prefix = escapeSql($row['ticket_prefix']);
    $ticket_number = intval($row['ticket_number']);
    $ticket_subject = escapeSql($row['ticket_subject']);
    $ticket_updated = escapeSql($row['ticket_updated_at']); // So we don't mess with the last response

    mysqli_query($mysqli, "UPDATE tickets SET ticket_project_id = $project_id, ticket_updated_at = '$ticket_updated' WHERE ticket_id = $ticket_id");

    logAction("Project", "Edit", "$session_name added ticket $ticket_prefix$ticket_number - $ticket_subject to project $project_name", $client_id, $project_id);

    flash_alert("Ticket added to <strong>$project_name</strong>");

    redirect();

}
