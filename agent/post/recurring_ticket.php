<?php

/*
 * ITFlow - GET/POST request handler for recurring tickets
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['add_recurring_ticket'])) {

    enforceUserPermission('module_support', 2);

    require_once 'ticket_recurring_model.php';

    $start_date = sanitizeInput($_POST['start_date']);

    mysqli_query($mysqli, "INSERT INTO recurring_tickets SET recurring_ticket_subject = '$subject', recurring_ticket_details = '$details', recurring_ticket_priority = '$priority', recurring_ticket_frequency = '$frequency', recurring_ticket_billable = $billable, recurring_ticket_start_date = '$start_date', recurring_ticket_next_run = '$start_date', recurring_ticket_assigned_to = $assigned_to, recurring_ticket_created_by = $session_user_id, recurring_ticket_client_id = $client_id, recurring_ticket_contact_id = $contact_id, recurring_ticket_asset_id = $asset_id, recurring_ticket_category = $category");

    $recurring_ticket_id = mysqli_insert_id($mysqli);

    // Add Additional Assets
    if (isset($_POST['additional_assets'])) {
        foreach ($_POST['additional_assets'] as $additional_asset) {
            $additional_asset_id = intval($additional_asset);
            mysqli_query($mysqli, "INSERT INTO recurring_ticket_assets SET recurring_ticket_id = $recurring_ticket_id, asset_id = $additional_asset_id");
        }
    }

    logAction("Recurring Ticket", "Create", "$session_name created recurring ticket for $subject - $frequency", $client_id, $recurring_ticket_id);

    flash_alert("Recurring ticket <strong>$subject - $frequency</strong> created");

    redirect();

}

if (isset($_POST['edit_recurring_ticket'])) {

    enforceUserPermission('module_support', 2);

    require_once 'ticket_recurring_model.php';

    $recurring_ticket_id = intval($_POST['recurring_ticket_id']);
    $next_run_date = sanitizeInput($_POST['next_date']);

    mysqli_query($mysqli, "UPDATE recurring_tickets SET recurring_ticket_subject = '$subject', recurring_ticket_details = '$details', recurring_ticket_priority = '$priority', recurring_ticket_frequency = '$frequency', recurring_ticket_billable = $billable, recurring_ticket_next_run = '$next_run_date', recurring_ticket_assigned_to = $assigned_to, recurring_ticket_asset_id = $asset_id, recurring_ticket_contact_id = $contact_id, recurring_ticket_category = $category WHERE recurring_ticket_id = $recurring_ticket_id");

    // Add Additional Assets
    if (isset($_POST['additional_assets'])) {
        mysqli_query($mysqli, "DELETE FROM recurring_ticket_assets WHERE recurring_ticket_id = $recurring_ticket_id");
        foreach ($_POST['additional_assets'] as $additional_asset) {
            $additional_asset_id = intval($additional_asset);
            mysqli_query($mysqli, "INSERT INTO recurring_ticket_assets SET recurring_ticket_id = $recurring_ticket_id, asset_id = $additional_asset_id");
        }
    }

    logAction("Recurring Ticket", "Edit", "$session_name edited recurring ticket $subject", $client_id, $recurring_ticket_id);

    flash_alert("Recurring ticket <strong>$subject - $frequency</strong> updated");

    redirect();

}

if (isset($_POST['bulk_force_recurring_tickets'])) {

    validateCSRFToken($_POST['csrf_token']);

    enforceUserPermission('module_support', 2);

    if (isset($_POST['recurring_ticket_ids'])) {

        // Cycle through array and pop each recurring scheduled ticket
        foreach ($_POST['recurring_ticket_ids'] as $recurring_ticket_id) {

            $recurring_ticket_id = intval($recurring_ticket_id);
            $sql = mysqli_query($mysqli, "SELECT * FROM recurring_tickets WHERE recurring_ticket_id = $recurring_ticket_id");

            if (mysqli_num_rows($sql) > 0) {
                $row = mysqli_fetch_array($sql);
                $subject = sanitizeInput($row['recurring_ticket_subject']);
                $details = mysqli_real_escape_string($mysqli, $row['recurring_ticket_details']);
                $priority = sanitizeInput($row['recurring_ticket_priority']);
                $frequency = sanitizeInput(strtolower($row['recurring_ticket_frequency']));
                $billable = intval($row['recurring_ticket_billable']);
                $old_next_recurring_date = sanitizeInput($row['recurring_ticket_next_run']);
                $created_id = intval($row['recurring_ticket_created_by']);
                $assigned_id = intval($row['recurring_ticket_assigned_to']);
                $contact_id = intval($row['recurring_ticket_contact_id']);
                $client_id = intval($row['recurring_ticket_client_id']);
                $asset_id = intval($row['recurring_ticket_asset_id']);
                $category = intval($row['recurring_ticket_category']);
                $url_key = randomString(156);

                $ticket_status = 1; // Default
                if ($assigned_id > 0) {
                    $ticket_status = 2; // Set to open if we've auto-assigned an agent
                }

                // Sanitize Config Vars from get_settings.php and Session Vars from check_login.php
                $config_ticket_prefix = sanitizeInput($config_ticket_prefix);
                $config_ticket_from_name = sanitizeInput($config_ticket_from_name);
                $config_ticket_from_email = sanitizeInput($config_ticket_from_email);
                $config_base_url = sanitizeInput($config_base_url);

                // Assign this new ticket the next ticket number & increment config_ticket_next_number by 1 (for the next ticket)
                $ticket_number_sql = mysqli_fetch_array(mysqli_query($mysqli, "SELECT config_ticket_next_number FROM settings WHERE company_id = 1"));
                $ticket_number = intval($ticket_number_sql['config_ticket_next_number']);
                $new_config_ticket_next_number = $ticket_number + 1;
                mysqli_query($mysqli, "UPDATE settings SET config_ticket_next_number = $new_config_ticket_next_number WHERE company_id = 1");

                // Raise the ticket
                mysqli_query($mysqli, "INSERT INTO tickets SET ticket_prefix = '$config_ticket_prefix', ticket_number = $ticket_number, ticket_source = 'Recurring', ticket_subject = '$subject', ticket_details = '$details', ticket_priority = '$priority', ticket_status = '$ticket_status', ticket_billable = $billable, ticket_url_key = '$url_key', ticket_created_by = $created_id, ticket_assigned_to = $assigned_id, ticket_contact_id = $contact_id, ticket_client_id = $client_id, ticket_asset_id = $asset_id, ticket_category = $category, ticket_recurring_ticket_id = $recurring_ticket_id");
                $id = mysqli_insert_id($mysqli);

                // Copy Additional Assets from Recurring ticket to new ticket
                mysqli_query($mysqli, "INSERT INTO ticket_assets (ticket_id, asset_id)
                SELECT $id, asset_id
                FROM recurring_ticket_assets
                WHERE recurring_ticket_id = $recurring_ticket_id");

                // Notifications

                customAction('ticket_create', $id);

                // Get client/contact/ticket details
                $sql = mysqli_query(
                    $mysqli,
                    "SELECT client_name, contact_name, contact_email, ticket_prefix, ticket_number, ticket_priority, ticket_subject, ticket_details FROM tickets
                LEFT JOIN clients ON ticket_client_id = client_id
                LEFT JOIN contacts ON ticket_contact_id = contact_id
                WHERE ticket_id = $id"
                );
                $row = mysqli_fetch_array($sql);

                $contact_name = sanitizeInput($row['contact_name']);
                $contact_email = sanitizeInput($row['contact_email']);
                $client_name = sanitizeInput($row['client_name']);
                $contact_name = sanitizeInput($row['contact_name']);
                $contact_email = sanitizeInput($row['contact_email']);
                $ticket_prefix = sanitizeInput($row['ticket_prefix']);
                $ticket_number = intval($row['ticket_number']);
                $ticket_priority = sanitizeInput($row['ticket_priority']);
                $ticket_subject = sanitizeInput($row['ticket_subject']);
                $ticket_details = mysqli_real_escape_string($mysqli, $row['ticket_details']);

                $data = [];

                // Notify client by email their ticket has been raised, if general notifications are turned on & there is a valid contact email
                if (!empty($config_smtp_host) && $config_ticket_client_general_notifications == 1 && filter_var($contact_email, FILTER_VALIDATE_EMAIL)) {

                    $email_subject = "Ticket Created - [$ticket_prefix$ticket_number] - $ticket_subject (scheduled)";
                    $email_body = "<i style=\'color: #808080\'>##- Please type your reply above this line -##</i><br><br>Hello $contact_name,<br><br>A ticket regarding \"$ticket_subject\" has been automatically created for you.<br><br>--------------------------------<br>$ticket_details--------------------------------<br><br>Ticket: $ticket_prefix$ticket_number<br>Subject: $ticket_subject<br>Status: Open<br>Portal: https://$config_base_url/client/ticket.php?id=$id<br><br>--<br>$company_name - Support<br>$config_ticket_from_email<br>$company_phone";

                    $email = [
                        'from' => $config_ticket_from_email,
                        'from_name' => $config_ticket_from_name,
                        'recipient' => $contact_email,
                        'recipient_name' => $contact_name,
                        'subject' => $email_subject,
                        'body' => $email_body
                    ];

                    $data[] = $email;

                }

                // Add to the mail queue
                addToMailQueue($data);

                // Set the next run date (based on the scheduled date, rather than now, so things keep their schedule)
                $dt_old_next_recurring_date = new DateTime($old_next_recurring_date);
                if ($frequency == "weekly") {
                    $next_run = date_add($dt_old_next_recurring_date, date_interval_create_from_date_string('1 week'));
                } elseif ($frequency == "monthly") {
                    $next_run = date_add($dt_old_next_recurring_date, date_interval_create_from_date_string('1 month'));
                } elseif ($frequency == "quarterly") {
                    $next_run = date_add($dt_old_next_recurring_date, date_interval_create_from_date_string('3 months'));
                } elseif ($frequency == "biannually") {
                    $next_run = date_add($dt_old_next_recurring_date, date_interval_create_from_date_string('6 months'));
                } elseif ($frequency == "annually") {
                    $next_run = date_add($dt_old_next_recurring_date, date_interval_create_from_date_string('12 months'));
                }

                // Update the run date
                $next_run = $next_run->format('Y-m-d');
                mysqli_query($mysqli, "UPDATE recurring_tickets SET recurring_ticket_next_run = '$next_run' WHERE recurring_ticket_id = $recurring_ticket_id");

                logAction("Ticket", "Create", "$session_name force created recurring scheduled $frequency ticket - $config_ticket_prefix$ticket_number - $subject", $client_id, $id);

            }

        }

        flash_alert("$count Recurring Tickets Forced");
    }

    redirect();

}

if (isset($_GET['force_recurring_ticket'])) {

    validateCSRFToken($_GET['csrf_token']);

    enforceUserPermission('module_support', 2);

    $recurring_ticket_id = intval($_GET['force_recurring_ticket']);

    $sql = mysqli_query($mysqli, "SELECT * FROM recurring_tickets WHERE recurring_ticket_id = $recurring_ticket_id");

    if (mysqli_num_rows($sql) > 0) {
        $row = mysqli_fetch_array($sql);
        $subject = sanitizeInput($row['recurring_ticket_subject']);
        $details = mysqli_real_escape_string($mysqli, $row['recurring_ticket_details']);
        $priority = sanitizeInput($row['recurring_ticket_priority']);
        $frequency = sanitizeInput(strtolower($row['recurring_ticket_frequency']));
        $billable = intval($row['recurring_ticket_billable']);
        $old_next_recurring_date = sanitizeInput($row['recurring_ticket_next_run']);
        $created_id = intval($row['recurring_ticket_created_by']);
        $assigned_id = intval($row['recurring_ticket_assigned_to']);
        $contact_id = intval($row['recurring_ticket_contact_id']);
        $client_id = intval($row['recurring_ticket_client_id']);
        $asset_id = intval($row['recurring_ticket_asset_id']);
        $category = intval($row['recurring_ticket_category']);
        $url_key = randomString(156);

        $ticket_status = 1; // Default
        if ($assigned_id > 0) {
            $ticket_status = 2; // Set to open if we've auto-assigned an agent
        }

        // Sanitize Config Vars from get_settings.php and Session Vars from check_login.php
        $config_ticket_prefix = sanitizeInput($config_ticket_prefix);
        $config_ticket_from_name = sanitizeInput($config_ticket_from_name);
        $config_ticket_from_email = sanitizeInput($config_ticket_from_email);
        $config_base_url = sanitizeInput($config_base_url);

        // Assign this new ticket the next ticket number & increment config_ticket_next_number by 1 (for the next ticket)
        $ticket_number = $config_ticket_next_number;
        $new_config_ticket_next_number = $config_ticket_next_number + 1;
        mysqli_query($mysqli, "UPDATE settings SET config_ticket_next_number = $new_config_ticket_next_number WHERE company_id = 1");

        // Raise the ticket
        mysqli_query($mysqli, "INSERT INTO tickets SET ticket_prefix = '$config_ticket_prefix', ticket_number = $ticket_number, ticket_source = 'Recurring', ticket_subject = '$subject', ticket_details = '$details', ticket_priority = '$priority', ticket_status = '$ticket_status', ticket_billable = $billable, ticket_url_key = '$url_key', ticket_created_by = $created_id, ticket_assigned_to = $assigned_id, ticket_contact_id = $contact_id, ticket_client_id = $client_id, ticket_asset_id = $asset_id, ticket_category = $category, ticket_recurring_ticket_id = $recurring_ticket_id");
        $id = mysqli_insert_id($mysqli);

        // Copy Additional Assets from Recurring ticket to new ticket
        mysqli_query($mysqli, "INSERT INTO ticket_assets (ticket_id, asset_id)
        SELECT $id, asset_id
        FROM recurring_ticket_assets
        WHERE recurring_ticket_id = $recurring_ticket_id");

        // Notifications

        customAction('ticket_create', $id);

        // Get client/contact/ticket details
        $sql = mysqli_query(
            $mysqli,
            "SELECT client_name, contact_name, contact_email, ticket_prefix, ticket_number, ticket_priority, ticket_subject, ticket_details FROM tickets
                LEFT JOIN clients ON ticket_client_id = client_id
                LEFT JOIN contacts ON ticket_contact_id = contact_id
                WHERE ticket_id = $id"
        );
        $row = mysqli_fetch_array($sql);

        $contact_name = sanitizeInput($row['contact_name']);
        $contact_email = sanitizeInput($row['contact_email']);
        $client_name = sanitizeInput($row['client_name']);
        $contact_name = sanitizeInput($row['contact_name']);
        $contact_email = sanitizeInput($row['contact_email']);
        $ticket_prefix = sanitizeInput($row['ticket_prefix']);
        $ticket_number = intval($row['ticket_number']);
        $ticket_priority = sanitizeInput($row['ticket_priority']);
        $ticket_subject = sanitizeInput($row['ticket_subject']);
        $ticket_details = mysqli_real_escape_string($mysqli, $row['ticket_details']);

        $data = [];

        // Notify client by email their ticket has been raised, if general notifications are turned on & there is a valid contact email
        if (!empty($config_smtp_host) && $config_ticket_client_general_notifications == 1 && filter_var($contact_email, FILTER_VALIDATE_EMAIL)) {

            $email_subject = "Ticket created - [$ticket_prefix$ticket_number] - $ticket_subject (scheduled)";
            $email_body = "<i style=\'color: #808080\'>##- Please type your reply above this line -##</i><br><br>Hello $contact_name,<br><br>A ticket regarding \"$ticket_subject\" has been automatically created for you.<br><br>--------------------------------<br>$ticket_details--------------------------------<br><br>Ticket: $ticket_prefix$ticket_number<br>Subject: $ticket_subject<br>Status: Open<br>Portal: https://$config_base_url/client/ticket.php?id=$id<br><br>--<br>$company_name - Support<br>$config_ticket_from_email<br>$company_phone";

            $email = [
                'from' => $config_ticket_from_email,
                'from_name' => $config_ticket_from_name,
                'recipient' => $contact_email,
                'recipient_name' => $contact_name,
                'subject' => $email_subject,
                'body' => $email_body
            ];

            $data[] = $email;

        }

        // Add to the mail queue
        addToMailQueue($data);

        // Set the next run date (based on the scheduled date, rather than now, so things keep their schedule)
        $dt_old_next_recurring_date = new DateTime($old_next_recurring_date);
        if ($frequency == "weekly") {
            $next_run = date_add($dt_old_next_recurring_date, date_interval_create_from_date_string('1 week'));
        } elseif ($frequency == "monthly") {
            $next_run = date_add($dt_old_next_recurring_date, date_interval_create_from_date_string('1 month'));
        } elseif ($frequency == "quarterly") {
            $next_run = date_add($dt_old_next_recurring_date, date_interval_create_from_date_string('3 months'));
        } elseif ($frequency == "biannually") {
            $next_run = date_add($dt_old_next_recurring_date, date_interval_create_from_date_string('6 months'));
        } elseif ($frequency == "annually") {
            $next_run = date_add($dt_old_next_recurring_date, date_interval_create_from_date_string('12 months'));
        }

        // Update the run date
        $next_run = $next_run->format('Y-m-d');
        mysqli_query($mysqli, "UPDATE recurring_tickets SET recurring_ticket_next_run = '$next_run' WHERE recurring_ticket_id = $recurring_ticket_id");

        logAction("Ticket", "Create", "$session_name force created recurring scheduled $frequency ticket - $config_ticket_prefix$ticket_number - $subject", $client_id, $id);

        flash_alert("Recurring Ticket Forced");

        redirect();

    } else {
        flash_alert("Recurring Ticket Force failed", 'error');
        redirect();
    }

}

if (isset($_GET['delete_recurring_ticket'])) {

    validateCSRFToken($_GET['csrf_token']);

    enforceUserPermission('module_support', 3);

    $recurring_ticket_id = intval($_GET['delete_recurring_ticket']);

    // Get Scheduled Ticket Subject Ticket Prefix, Number and Client ID for logging and alert message
    $sql = mysqli_query($mysqli, "SELECT * FROM recurring_tickets WHERE recurring_ticket_id = $recurring_ticket_id");
    $row = mysqli_fetch_array($sql);
    $subject = sanitizeInput($row['recurring_ticket_subject']);
    $frequency = sanitizeInput($row['recurring_ticket_frequency']);

    $client_id = intval($row['recurring_ticket_client_id']);

    // Delete
    mysqli_query($mysqli, "DELETE FROM recurring_tickets WHERE recurring_ticket_id = $recurring_ticket_id");

    logAction("Recurring Ticket", "Delete", "$session_name deleted recurring ticket $subject", $client_id, $recurring_ticket_id);

    flash_alert("Recurring ticket <strong>$subject - $frequency</strong> deleted", 'error');

    redirect();

}

if (isset($_POST['bulk_delete_recurring_tickets'])) {

    validateCSRFToken($_POST['csrf_token']);

    enforceUserPermission('module_support', 3);

    if (isset($_POST['recurring_ticket_ids'])) {

        $count = count($_POST['recurring_ticket_ids']);

        // Cycle through array and delete each recurring scheduled ticket
        foreach ($_POST['recurring_ticket_ids'] as $recurring_ticket_id) {

            $recurring_ticket_id = intval($recurring_ticket_id);
            mysqli_query($mysqli, "DELETE FROM recurring_tickets WHERE recurring_ticket_id = $recurring_ticket_id");

            logAction("Recurring Ticket", "Delete", "$session_name deleted recurring ticket", 0, $recurring_ticket_id);

        }

        logAction("Recurring Ticket", "Bulk Delete", "$session_name deleted $count recurring ticket(s)");

        flash_alert("Deleted <strong>$count</strong> recurring ticket(s)", 'error');
    }

    redirect();

}

if (isset($_POST['bulk_assign_recurring_ticket'])) {

    enforceUserPermission('module_support', 2);

    // POST variables
    $assign_to = intval($_POST['assign_to']);

    // Get a Recurring Ticket Count
    $recurring_ticket_count = count($_POST['recurring_ticket_ids']);

    // Assign Tech to Selected Recurring Tickets
    if (!empty($_POST['recurring_ticket_ids'])) {
        foreach ($_POST['recurring_ticket_ids'] as $recurring_ticket_id) {
            $recurring_ticket_id = intval($recurring_ticket_id);

            $sql = mysqli_query($mysqli, "SELECT * FROM recurring_tickets WHERE recurring_ticket_id = $recurring_ticket_id");
            $row = mysqli_fetch_array($sql);

            $recurring_ticket_name = sanitizeInput($row['recurring_ticket_name']);
            $recurring_ticket_subject = sanitizeInput($row['recurring_ticket_subject']);
            $client_id = intval($row['recurring_ticket_client_id']);

            // Allow for un-assigning tickets
            if ($assign_to == 0) {
                $ticket_reply = "Ticket unassigned, pending re-assignment.";
                $agent_name = "No One";
            } else {
                // Get & verify assigned agent details
                $agent_details_sql = mysqli_query($mysqli, "SELECT user_name, user_email FROM users LEFT JOIN user_settings ON users.user_id = user_settings.user_id WHERE users.user_id = $assign_to");
                $agent_details = mysqli_fetch_array($agent_details_sql);

                $agent_name = sanitizeInput($agent_details['user_name']);
                $agent_email = sanitizeInput($agent_details['user_email']);

                if (!$agent_name) {
                    flash_alert("Invalid agent!", 'error');
                    redirect();
                }
            }

            // Update recurring ticket
            mysqli_query($mysqli, "UPDATE recurring_tickets SET recurring_ticket_assigned_to = $assign_to WHERE recurring_ticket_id = $recurring_ticket_id");

            logAction("Recurring_Ticket", "Edit", "$session_name reassigned recurring ticket $recurring_ticket_subject to $agent_name", $client_id, $recurring_ticket_id);

            $tickets_assigned_body .= "$recurring_ticket_subject<br>";
        } // End For Each Ticket ID Loop

        // Notification
        if ($session_user_id != $assign_to && $assign_to != 0) {

            // App Notification
            mysqli_query($mysqli, "INSERT INTO notifications SET notification_type = 'Recurring Ticket', notification = '$recurring_ticket_count Recurring Tickets have been assigned to you by $session_name', notification_action = 'recurring_tickets.php?assigned=$assign_to', notification_client_id = $client_id, notification_user_id = $assign_to");

            // Agent Email Notification
            if (!empty($config_smtp_host)) {

                // Sanitize Config vars from get_settings.php
                $config_ticket_from_name = sanitizeInput($config_ticket_from_name);
                $config_ticket_from_email = sanitizeInput($config_ticket_from_email);
                $company_name = sanitizeInput($session_company_name);

                $subject = "$config_app_name - $recurring_ticket_count recurring tickets have been assigned to you";
                $body = "Hi $agent_name, <br><br>$session_name assigned $recurring_ticket_count recurring tickets to you!<br><br>$tickets_assigned_body<br>Thanks, <br>$session_name<br>$company_name";

                // Email Ticket Agent
                // Queue Mail
                $data = [
                    [
                        'from' => $config_ticket_from_email,
                        'from_name' => $config_ticket_from_name,
                        'recipient' => $agent_email,
                        'recipient_name' => $agent_name,
                        'subject' => $subject,
                        'body' => $body,
                    ]
                ];
                addToMailQueue($data);
            }
        }
    }

    flash_alert("Assigned <strong>$recurring_ticket_count</strong> Recurring Tickets to <strong>$agent_name</strong>");

    redirect();

}

if (isset($_POST['bulk_edit_recurring_ticket_priority'])) {

    enforceUserPermission('module_support', 2);

    $priority = sanitizeInput($_POST['bulk_priority']);

    // Assign Tech to Selected Recurring Tickets
    if (isset($_POST['recurring_ticket_ids'])) {

        // Get a Ticket Count
        $recurring_ticket_count = count($_POST['recurring_ticket_ids']);

        foreach ($_POST['recurring_ticket_ids'] as $recurring_ticket_id) {
            $recurring_ticket_id = intval($recurring_ticket_id);

            $sql = mysqli_query($mysqli, "SELECT * FROM recurring_tickets WHERE recurring_ticket_id = $recurring_ticket_id");
            $row = mysqli_fetch_array($sql);

            $recurring_ticket_subject = sanitizeInput($row['recurring_ticket_subject']);
            $original_recurring_ticket_priority = sanitizeInput($row['recurring_ticket_priority']);
            $client_id = intval($row['ticket_client_id']);

            // Update recurring ticket
            mysqli_query($mysqli, "UPDATE recurring_tickets SET recurring_ticket_priority = '$priority' WHERE recurring_ticket_id = $recurring_ticket_id");

            logAction("Ticket", "Edit", "$session_name updated the priority on recurring ticket $ticket_subject from $original_recurring_ticket_priority to $priority", $client_id, $recurring_ticket_id);

            customAction('recurring_ticket_update', $recurring_ticket_id);
        } // End For Each Recurring Ticket ID Loop

        logAction("Recurring Ticket", " Bulk Edit", "$session_name updated the priority to $priority on $recurring_ticket_count Recurring Tickets");

        flash_alert("Priority updated to <strong>$priority</strong> for <strong>$recurring_ticket_count</strong> Recurring Tickets");
    }

    redirect();

}

if (isset($_POST['bulk_edit_recurring_ticket_category'])) {

    enforceUserPermission('module_support', 2);

    $category_id = intval($_POST['bulk_category']);

    if (isset($_POST['recurring_ticket_ids'])) {

        $recurring_ticket_count = count($_POST['recurring_ticket_ids']);

        foreach ($_POST['recurring_ticket_ids'] as $recurring_ticket_id) {
            $recurring_ticket_id = intval($recurring_ticket_id);

            $sql = mysqli_query($mysqli, "SELECT recurring_ticket_subject, category_name, recurring_ticket_client_id FROM recurring_tickets LEFT JOIN categories ON recurring_ticket_category = category_id WHERE recurring_ticket_id = $recurring_ticket_id");
            $row = mysqli_fetch_array($sql);

            $recurring_ticket_subject = sanitizeInput($row['recurring_ticket_subject']);
            $previous_recurring_ticket_category_name = sanitizeInput($row['category_name']);
            $client_id = intval($row['recurring_ticket_client_id']);

            $category_name = sanitizeInput(getFieldById('categories', $category_id, 'category_name'));

            mysqli_query($mysqli, "UPDATE recurring_tickets SET recurring_ticket_category = '$category_id' WHERE recurring_ticket_id = $recurring_ticket_id");

            logAction("Recurring Ticket", "Edit", "$session_name updated the category on recurring ticket $recurring_ticket_subject from $previous_recurring_ticket_category_name to $category_name", $client_id, $recurring_ticket_id);

            customAction('recurring_ticket_update', $recurring_ticket_id);
        }

        logAction("Recurring Ticket", " Bulk Edit", "$session_name updated the category to $category_name for $recurring_ticket_count Recurring Tickets");

        flash_alert("Category set to $category_name for <strong>$recurring_ticket_count</strong> Recurring Tickets");
    }

    redirect();

}

if (isset($_POST['bulk_edit_recurring_ticket_billable'])) {

    enforceUserPermission('module_support', 2);
    enforceUserPermission('module_sales', 2);

    $billable = intval($_POST['billable']);
    if ($billable) {
        $billable_status = "Billable";
    } else {
        $billable_status = "Not Billable";
    }

    if (isset($_POST['recurring_ticket_ids'])) {

        $recurring_ticket_count = count($_POST['recurring_ticket_ids']);

        foreach ($_POST['recurring_ticket_ids'] as $recurring_ticket_id) {
            $recurring_ticket_id = intval($recurring_ticket_id);

            $sql = mysqli_query($mysqli, "SELECT recurring_ticket_subject, recurring_ticket_client_id FROM recurring_tickets WHERE recurring_ticket_id = $recurring_ticket_id");
            $row = mysqli_fetch_array($sql);

            $recurring_ticket_subject = sanitizeInput($row['recurring_ticket_subject']);
            $previous_recurring_ticket_billable = intval($row['recurring_ticket_billable']);
            if ($previous_recurring_ticket_billable) {
                $previous_billable_status = "Billable";
            } else {
                $previous_billable_status = "Not Billable";
            }
            $client_id = intval($row['recurring_ticket_client_id']);

            mysqli_query($mysqli, "UPDATE recurring_tickets SET recurring_ticket_billable = $billable WHERE recurring_ticket_id = $recurring_ticket_id");

            logAction("Recurring Ticket", "Edit", "$session_name updated the billable status on recurring ticket $recurring_ticket_subject from $previous_billable_status to $billable_status", $client_id, $recurring_ticket_id);

            customAction('recurring_ticket_update', $recurring_ticket_id);
        }

        logAction("Recurring Ticket", " Bulk Edit", "$session_name updated the billable status to $billable_status for $recurring_ticket_count Recurring Tickets");

        flash_alert("Billable status set to $billable_status for <strong>$recurring_ticket_count</strong> Recurring Tickets");
    }

    redirect();

}

if (isset($_POST['bulk_edit_recurring_ticket_next_run_date'])) {

    enforceUserPermission('module_support', 2);

    $next_run_date = sanitizeInput($_POST['next_run_date']);

    if (isset($_POST['recurring_ticket_ids'])) {

        $recurring_ticket_count = count($_POST['recurring_ticket_ids']);

        foreach ($_POST['recurring_ticket_ids'] as $recurring_ticket_id) {
            $recurring_ticket_id = intval($recurring_ticket_id);

            $sql = mysqli_query($mysqli, "SELECT recurring_ticket_subject, recurring_ticket_client_id FROM recurring_tickets WHERE recurring_ticket_id = $recurring_ticket_id");
            $row = mysqli_fetch_array($sql);

            $recurring_ticket_subject = sanitizeInput($row['recurring_ticket_subject']);
            $previous_recurring_ticket_next_run_date = sanitizeInput($row['recurring_ticket_next_run']);
            $client_id = intval($row['recurring_ticket_client_id']);

            mysqli_query($mysqli, "UPDATE recurring_tickets SET recurring_ticket_next_run = '$next_run_date' WHERE recurring_ticket_id = $recurring_ticket_id");

            logAction("Recurring Ticket", "Edit", "$session_name updated the Next run date on recurring ticket $recurring_ticket_subject from $previous_recurring_ticket_next_run_date to $next_run_date", $client_id, $recurring_ticket_id);

            customAction('recurring_ticket_update', $recurring_ticket_id);
        }

        logAction("Recurring Ticket", " Bulk Edit", "$session_name updated the Next run date to $next_run_date for $recurring_ticket_count Recurring Tickets");

        flash_alert("Next run date set to <strong>$next_run_date</strong> for <strong>$recurring_ticket_count</strong> Recurring Tickets");
    }

    redirect();

}
