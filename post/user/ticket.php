<?php

/*
 * ITFlow - GET/POST request handler for client tickets
 */

if (isset($_POST['add_ticket'])) {

    enforceUserPermission('module_support', 2);

    $client_id = intval($_POST['client']);
    $assigned_to = intval($_POST['assigned_to']);
    if ($assigned_to == 0) {
        $ticket_status = 1;
    } else {
        $ticket_status = 2;
    }
    $contact = intval($_POST['contact']);
    $category_id = intval($_POST['category']);
    $subject = sanitizeInput($_POST['subject']);
    $priority = sanitizeInput($_POST['priority']);
    $details = mysqli_real_escape_string($mysqli, $_POST['details']);
    $vendor_ticket_number = sanitizeInput($_POST['vendor_ticket_number']);
    $vendor_id = intval($_POST['vendor']);
    $asset_id = intval($_POST['asset']);
    $location_id = intval($_POST['location']);
    $project_id = intval($_POST['project']);
    $use_primary_contact = intval($_POST['use_primary_contact']);
    $ticket_template_id = intval($_POST['ticket_template_id']);

    // Add the primary contact as the ticket contact if "Use primary contact" is checked
    if ($use_primary_contact == 1) {
        $sql = mysqli_query($mysqli, "SELECT contact_id FROM contacts WHERE contact_client_id = $client_id AND contact_primary = 1");
        $row = mysqli_fetch_array($sql);
        $contact = intval($row['contact_id']);
    }

    if (!isset($_POST['billable'])) {
        $billable = 1;
    } else {
        $billable = intval($_POST['billable']);
    }

    //Get the next Ticket Number and add 1 for the new ticket number
    $ticket_number = $config_ticket_next_number;
    $new_config_ticket_next_number = $config_ticket_next_number + 1;

    // Sanitize Config Vars from get_settings.php and Session Vars from check_login.php
    $config_ticket_prefix = sanitizeInput($config_ticket_prefix);
    $config_ticket_from_name = sanitizeInput($config_ticket_from_name);
    $config_ticket_from_email = sanitizeInput($config_ticket_from_email);
    $config_base_url = sanitizeInput($config_base_url);

    //Generate a unique URL key for clients to access
    $url_key = randomString(156);

    mysqli_query($mysqli, "UPDATE settings SET config_ticket_next_number = $new_config_ticket_next_number WHERE company_id = 1");

    mysqli_query($mysqli, "INSERT INTO tickets SET ticket_prefix = '$config_ticket_prefix', ticket_number = $ticket_number, ticket_category = $category_id, ticket_subject = '$subject', ticket_details = '$details', ticket_priority = '$priority', ticket_billable = '$billable', ticket_status = '$ticket_status', ticket_vendor_ticket_number = '$vendor_ticket_number', ticket_vendor_id = $vendor_id, ticket_location_id = $location_id, ticket_asset_id = $asset_id, ticket_created_by = $session_user_id, ticket_assigned_to = $assigned_to, ticket_contact_id = $contact, ticket_url_key = '$url_key', ticket_client_id = $client_id, ticket_invoice_id = 0, ticket_project_id = $project_id");

    $ticket_id = mysqli_insert_id($mysqli);

    // Add Tasks from Template if Template was selected
    if($ticket_template_id) {
        // Get Associated Tasks from the ticket template
        $sql_task_templates = mysqli_query($mysqli, "SELECT * FROM task_templates WHERE task_template_ticket_template_id = $ticket_template_id");

        if (mysqli_num_rows($sql_task_templates) > 0) {
            while ($row = mysqli_fetch_array($sql_task_templates)) {
                $task_order = intval($row['task_template_order']);
                $task_name = sanitizeInput($row['task_template_name']);
                $task_completion_estimate = intval($row['task_template_completion_estimate']);

                mysqli_query($mysqli,"INSERT INTO tasks SET task_name = '$task_name', task_order = $task_order, task_completion_estimate = $task_completion_estimate, task_ticket_id = $ticket_id");
            }
        }
    }

    // Add Watchers
    if (!empty($_POST['watchers'])) {
        foreach ($_POST['watchers'] as $watcher) {
            $watcher_email = sanitizeInput($watcher);
            mysqli_query($mysqli, "INSERT INTO ticket_watchers SET watcher_email = '$watcher_email', watcher_ticket_id = $ticket_id");
        }
    }

    // E-mail client
    if (!empty($config_smtp_host) && $config_ticket_client_general_notifications == 1) {

        // Get contact/ticket details
        $sql = mysqli_query($mysqli, "SELECT contact_name, contact_email, ticket_prefix, ticket_number, ticket_category, ticket_subject, ticket_details, ticket_priority, ticket_status, ticket_created_by, ticket_assigned_to, ticket_client_id FROM tickets 
              LEFT JOIN clients ON ticket_client_id = client_id 
              LEFT JOIN contacts ON ticket_contact_id = contact_id
              WHERE ticket_id = $ticket_id");
        $row = mysqli_fetch_array($sql);

        $contact_name = sanitizeInput($row['contact_name']);
        $contact_email = sanitizeInput($row['contact_email']);
        $ticket_prefix = sanitizeInput($row['ticket_prefix']);
        $ticket_number = intval($row['ticket_number']);
        $ticket_category = sanitizeInput($row['ticket_category']);
        $ticket_subject = sanitizeInput($row['ticket_subject']);
        $ticket_details = mysqli_escape_string($mysqli, $row['ticket_details']);
        $ticket_priority = sanitizeInput($row['ticket_priority']);
        $ticket_status = sanitizeInput($row['ticket_status']);
        $ticket_status_name = sanitizeInput(getTicketStatusName($row['ticket_status']));
        $client_id = intval($row['ticket_client_id']);
        $ticket_created_by = intval($row['ticket_created_by']);
        $ticket_assigned_to = intval($row['ticket_assigned_to']);

        // Get Company Phone Number
        $sql = mysqli_query($mysqli, "SELECT company_name, company_phone FROM companies WHERE company_id = 1");
        $row = mysqli_fetch_array($sql);
        $company_name = sanitizeInput($row['company_name']);
        $company_phone = sanitizeInput(formatPhoneNumber($row['company_phone']));

        // Verify contact email is valid
        if (filter_var($contact_email, FILTER_VALIDATE_EMAIL)) {

            $subject = "Ticket created [$ticket_prefix$ticket_number] - $ticket_subject";
            $body = "<i style=\'color: #808080\'>##- Please type your reply above this line -##</i><br><br>Hello $contact_name,<br><br>A ticket regarding \"$ticket_subject\" has been created for you.<br><br>--------------------------------<br>$ticket_details--------------------------------<br><br>Ticket: $ticket_prefix$ticket_number<br>Subject: $ticket_subject<br>Status: Open<br>Portal: <a href=\'https://$config_base_url/guest_view_ticket.php?ticket_id=$ticket_id&url_key=$url_key\'>View ticket</a><br><br>--<br>$company_name - Support<br>$config_ticket_from_email<br>$company_phone";

            // Email Ticket Contact
            // Queue Mail
            $data = [];

            $data[] = [
                'from' => $config_ticket_from_email,
                'from_name' => $config_ticket_from_name,
                'recipient' => $contact_email,
                'recipient_name' => $contact_name,
                'subject' => $subject,
                'body' => $body
            ];

            // Also Email all the watchers
            $sql_watchers = mysqli_query($mysqli, "SELECT watcher_email FROM ticket_watchers WHERE watcher_ticket_id = $ticket_id");
            $body .= "<br><br>----------------------------------------<br>DO NOT REPLY - YOU ARE RECEIVING THIS EMAIL BECAUSE YOU ARE A WATCHER";
            while ($row = mysqli_fetch_array($sql_watchers)) {
                $watcher_email = sanitizeInput($row['watcher_email']);

                // Queue Mail
                $data[] = [
                    'from' => $config_ticket_from_email,
                    'from_name' => $config_ticket_from_name,
                    'recipient' => $watcher_email,
                    'recipient_name' => $watcher_email,
                    'subject' => $subject,
                    'body' => $body
                ];
            }
            addToMailQueue($mysqli, $data);
        }
    }

    // Custom action/notif handler
    customAction('ticket_create', $ticket_id);

    // Logging
    mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Ticket', log_action = 'Create', log_description = '$session_name created ticket $config_ticket_prefix$ticket_number - $ticket_subject', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $ticket_id");

    $_SESSION['alert_message'] = "You created Ticket $ticket_subject <strong>$config_ticket_prefix$ticket_number</strong>";

    header("Location: ticket.php?ticket_id=" . $ticket_id);
}

if (isset($_POST['edit_ticket'])) {

    enforceUserPermission('module_support', 2);

    $ticket_id = intval($_POST['ticket_id']);
    $contact_id = intval($_POST['contact']);
    $notify = intval($_POST['contact_notify']);
    $category = intval($_POST['category']);
    $subject = sanitizeInput($_POST['subject']);
    $billable = intval($_POST['billable']);
    $priority = sanitizeInput($_POST['priority']);
    $details = mysqli_real_escape_string($mysqli, $_POST['details']);
    $vendor_ticket_number = sanitizeInput($_POST['vendor_ticket_number']);
    $vendor_id = intval($_POST['vendor']);
    $asset_id = intval($_POST['asset']);
    $location_id = intval($_POST['location']);
    $project_id = intval($_POST['project']);
    $client_id = intval($_POST['client_id']);
    $ticket_number = sanitizeInput($_POST['ticket_number']);

    mysqli_query($mysqli, "UPDATE tickets SET ticket_category = $category, ticket_subject = '$subject', ticket_priority = '$priority', ticket_billable = $billable, ticket_details = '$details', ticket_vendor_ticket_number = '$vendor_ticket_number', ticket_contact_id = $contact_id, ticket_vendor_id = $vendor_id, ticket_location_id = $location_id, ticket_asset_id = $asset_id, ticket_project_id = $project_id WHERE ticket_id = $ticket_id");

    // Notify new contact if selected
    if ($notify && !empty($config_smtp_host)) {

        // Get contact/ticket details
        $sql = mysqli_query($mysqli, "SELECT contact_name, contact_email, ticket_prefix, ticket_number, ticket_category, ticket_subject, ticket_details, ticket_priority, ticket_status_name, ticket_created_by, ticket_assigned_to, ticket_client_id FROM tickets 
            LEFT JOIN clients ON ticket_client_id = client_id 
            LEFT JOIN contacts ON ticket_contact_id = contact_id
            LEFT JOIN ticket_statuses ON ticket_status = ticket_status_id                                                                                                                                                            
            WHERE ticket_id = $ticket_id 
            AND ticket_closed_at IS NULL");
        $row = mysqli_fetch_array($sql);

        $contact_name = sanitizeInput($row['contact_name']);
        $contact_email = sanitizeInput($row['contact_email']);
        $ticket_prefix = sanitizeInput($row['ticket_prefix']);
        $ticket_number = intval($row['ticket_number']);
        $ticket_category = sanitizeInput($row['ticket_category']);
        $ticket_subject = sanitizeInput($row['ticket_subject']);
        $ticket_details = mysqli_escape_string($mysqli, $row['ticket_details']);
        $ticket_priority = sanitizeInput($row['ticket_priority']);
        $ticket_status = sanitizeInput($row['ticket_status_name']);
        $client_id = intval($row['ticket_client_id']);
        $ticket_created_by = intval($row['ticket_created_by']);
        $ticket_assigned_to = intval($row['ticket_assigned_to']);

        // Get Company Phone Number
        $sql = mysqli_query($mysqli, "SELECT company_name, company_phone FROM companies WHERE company_id = 1");
        $row = mysqli_fetch_array($sql);
        $company_name = sanitizeInput($row['company_name']);
        $company_phone = sanitizeInput(formatPhoneNumber($row['company_phone']));

        // Email content
        $data = []; // Queue array

        $subject = "Ticket Created - [$ticket_prefix$ticket_number] - $ticket_subject";
        $body = "<i style=\'color: #808080\'>##- Please type your reply above this line -##</i><br><br>Hello $contact_name,<br><br>A ticket regarding \"$ticket_subject\" has been created for you.<br><br>--------------------------------<br>$ticket_details--------------------------------<br><br>Ticket: $ticket_prefix$ticket_number<br>Subject: $ticket_subject<br>Status: $ticket_status<br>Portal: <a href=\'https://$config_base_url/guest_view_ticket.php?ticket_id=$ticket_id&url_key=$url_key\'>View ticket</a><br><br>--<br>$company_name - Support<br>$config_ticket_from_email<br>$company_phone";


        // Only add contact to email queue if email is valid
        if (filter_var($contact_email, FILTER_VALIDATE_EMAIL)) {
            $data[] = [
                'from' => $config_ticket_from_email,
                'from_name' => $config_ticket_from_name,
                'recipient' => $contact_email,
                'recipient_name' => $contact_name,
                'subject' => $subject,
                'body' => $body
            ];
        }

        addToMailQueue($mysqli, $data);
    }

    // Custom action/notif handler
    customAction('ticket_update', $ticket_id);

    //Logging
    mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Ticket', log_action = 'Modify', log_description = '$session_name modified ticket $ticket_number - $subject', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $ticket_id");

    $_SESSION['alert_message'] = "Ticket <strong>$ticket_number</strong> updated";

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if (isset($_POST['edit_ticket_priority'])) {

    enforceUserPermission('module_support', 2);

    $ticket_id = intval($_POST['ticket_id']);
    $priority = sanitizeInput($_POST['priority']);
    $client_id = intval($_POST['client_id']);

    mysqli_query($mysqli, "UPDATE tickets SET ticket_priority = '$priority' WHERE ticket_id = $ticket_id");

    //Logging
    mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Ticket', log_action = 'Modify', log_description = '$session_name edited ticket priority', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $ticket_id");

    customAction('ticket_update', $ticket_id);

    $_SESSION['alert_message'] = "Ticket priority updated";

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if (isset($_POST['edit_ticket_contact'])) {

    enforceUserPermission('module_support', 2);

    $ticket_id = intval($_POST['ticket_id']);
    $contact_id = intval($_POST['contact']);
    $client_id = intval($_POST['client_id']);
    $ticket_number = sanitizeInput($_POST['ticket_number']);
    $notify = intval($_POST['contact_notify']);

    mysqli_query($mysqli, "UPDATE tickets SET ticket_contact_id = $contact_id WHERE ticket_id = $ticket_id");

    // Notify new contact if selected
    if ($notify && !empty($config_smtp_host)) {

        // Get contact/ticket details
        $sql = mysqli_query($mysqli, "SELECT contact_name, contact_email, ticket_prefix, ticket_number, ticket_category, ticket_subject, ticket_details, ticket_priority, ticket_status_name, ticket_created_by, ticket_assigned_to, ticket_client_id FROM tickets 
            LEFT JOIN clients ON ticket_client_id = client_id 
            LEFT JOIN contacts ON ticket_contact_id = contact_id
            LEFT JOIN ticket_statuses ON ticket_status = ticket_status_id                                                                                                                                                            
            WHERE ticket_id = $ticket_id 
            AND ticket_closed_at IS NULL");
        $row = mysqli_fetch_array($sql);

        $contact_name = sanitizeInput($row['contact_name']);
        $contact_email = sanitizeInput($row['contact_email']);
        $ticket_prefix = sanitizeInput($row['ticket_prefix']);
        $ticket_number = intval($row['ticket_number']);
        $ticket_category = sanitizeInput($row['ticket_category']);
        $ticket_subject = sanitizeInput($row['ticket_subject']);
        $ticket_details = mysqli_escape_string($mysqli, $row['ticket_details']);
        $ticket_priority = sanitizeInput($row['ticket_priority']);
        $ticket_status = sanitizeInput($row['ticket_status_name']);
        $client_id = intval($row['ticket_client_id']);
        $ticket_created_by = intval($row['ticket_created_by']);
        $ticket_assigned_to = intval($row['ticket_assigned_to']);

        // Get Company Phone Number
        $sql = mysqli_query($mysqli, "SELECT company_name, company_phone FROM companies WHERE company_id = 1");
        $row = mysqli_fetch_array($sql);
        $company_name = sanitizeInput($row['company_name']);
        $company_phone = sanitizeInput(formatPhoneNumber($row['company_phone']));

        // Email content
        $data = []; // Queue array

        $subject = "Ticket Created - [$ticket_prefix$ticket_number] - $ticket_subject";
        $body = "<i style=\'color: #808080\'>##- Please type your reply above this line -##</i><br><br>Hello $contact_name,<br><br>A ticket regarding \"$ticket_subject\" has been created for you.<br><br>--------------------------------<br>$ticket_details--------------------------------<br><br>Ticket: $ticket_prefix$ticket_number<br>Subject: $ticket_subject<br>Status: $ticket_status<br>Portal: <a href=\'https://$config_base_url/guest_view_ticket.php?ticket_id=$ticket_id&url_key=$url_key\'>View ticket</a><br><br>--<br>$company_name - Support<br>$config_ticket_from_email<br>$company_phone";


        // Only add contact to email queue if email is valid
        if (filter_var($contact_email, FILTER_VALIDATE_EMAIL)) {
            $data[] = [
                'from' => $config_ticket_from_email,
                'from_name' => $config_ticket_from_name,
                'recipient' => $contact_email,
                'recipient_name' => $contact_name,
                'subject' => $subject,
                'body' => $body
            ];
        }

        addToMailQueue($mysqli, $data);
    }

    // Custom action/notif handler
    customAction('ticket_update', $ticket_id);

    //Logging
    mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Ticket', log_action = 'Modify', log_description = '$session_name changed contact for ticket $ticket_number', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $ticket_id");

    $_SESSION['alert_message'] = "Ticket <strong>$ticket_number</strong> contact updated";

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if (isset($_POST['add_ticket_watcher'])) {

    enforceUserPermission('module_support', 2);

    $ticket_id = intval($_POST['ticket_id']);
    $client_id = intval($_POST['client_id']);
    $ticket_number = sanitizeInput($_POST['ticket_number']);
    $watcher_email = sanitizeInput($_POST['watcher_email']);
    $notify = intval($_POST['watcher_notify']);

    mysqli_query($mysqli, "INSERT INTO ticket_watchers SET watcher_email = '$watcher_email', watcher_ticket_id = $ticket_id");

    // Notify watcher
    if ($notify && !empty($config_smtp_host)) {

        // Get contact/ticket details
        $sql = mysqli_query($mysqli, "SELECT ticket_prefix, ticket_number, ticket_category, ticket_subject, ticket_details, ticket_priority, ticket_status_name, ticket_url_key, ticket_created_by, ticket_assigned_to, ticket_client_id FROM tickets 
            LEFT JOIN clients ON ticket_client_id = client_id
            LEFT JOIN contacts ON ticket_contact_id = contact_id
            LEFT JOIN ticket_statuses ON ticket_status = ticket_status_id                                                                                                                                                         
            WHERE ticket_id = $ticket_id
            AND ticket_closed_at IS NULL");
        $row = mysqli_fetch_array($sql);

        $ticket_prefix = sanitizeInput($row['ticket_prefix']);
        $ticket_number = intval($row['ticket_number']);
        $ticket_category = sanitizeInput($row['ticket_category']);
        $ticket_subject = sanitizeInput($row['ticket_subject']);
        $ticket_details = mysqli_escape_string($mysqli, $row['ticket_details']);
        $ticket_priority = sanitizeInput($row['ticket_priority']);
        $ticket_status = sanitizeInput($row['ticket_status_name']);
        $url_key = sanitizeInput($row['ticket_url_key']);
        $client_id = intval($row['ticket_client_id']);
        $ticket_created_by = intval($row['ticket_created_by']);
        $ticket_assigned_to = intval($row['ticket_assigned_to']);

        // Get Company Phone Number
        $sql = mysqli_query($mysqli, "SELECT company_name, company_phone FROM companies WHERE company_id = 1");
        $row = mysqli_fetch_array($sql);
        $company_name = sanitizeInput($row['company_name']);
        $company_phone = sanitizeInput(formatPhoneNumber($row['company_phone']));

        // Email content
        $data = []; // Queue array

        $subject = "Ticket Notification - [$ticket_prefix$ticket_number] - $ticket_subject";
        $body = "<i style=\'color: #808080\'>##- Please type your reply above this line -##</i><br><br>Hello,<br><br>You are now a watcher on a ticket regarding \"$ticket_subject\".<br><br>--------------------------------<br>$ticket_details--------------------------------<br><br>Ticket: $ticket_prefix$ticket_number<br>Subject: $ticket_subject<br>Status: $ticket_status<br>Guest link: https://$config_base_url/guest_view_ticket.php?ticket_id=$ticket_id&url_key=$url_key<br><br>--<br>$company_name - Support<br>$config_ticket_from_email<br>$company_phone";

        // Only add watcher to email queue if email is valid
        if (filter_var($watcher_email, FILTER_VALIDATE_EMAIL)) {
            $data[] = [
                'from' => $config_ticket_from_email,
                'from_name' => $config_ticket_from_name,
                'recipient' => $watcher_email,
                'recipient_name' => $watcher_email,
                'subject' => $subject,
                'body' => $body
            ];
        }

        addToMailQueue($mysqli, $data);
    }

    //Logging
    mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Ticket', log_action = 'Edit', log_description = '$session_name added watcher $watcher_email to ticket $ticket_number', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $ticket_id");

    $_SESSION['alert_message'] = "You added $watcher_email as a watcher to Ticket <strong>$ticket_number</strong>";

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if (isset($_POST['edit_ticket_watchers'])) {

    enforceUserPermission('module_support', 2);

    $ticket_id = intval($_POST['ticket_id']);
    $client_id = intval($_POST['client_id']);
    $ticket_number = sanitizeInput($_POST['ticket_number']);

    // Add Watchers
    if (!empty($_POST['watchers'])) {

        // Remove all watchers first
        mysqli_query($mysqli, "DELETE FROM ticket_watchers WHERE watcher_ticket_id = $ticket_id");

        //Add the Watchers
        foreach ($_POST['watchers'] as $watcher) {
            $watcher_email = sanitizeInput($watcher);
            mysqli_query($mysqli, "INSERT INTO ticket_watchers SET watcher_email = '$watcher_email', watcher_ticket_id = $ticket_id");
        }
    }

    //Logging
    mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Ticket', log_action = 'Edit', log_description = '$session_name added watchers to ticket $ticket_number', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $ticket_id");

    $_SESSION['alert_message'] = "Ticket <strong>$ticket_number</strong> watchers updated";

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if (isset($_GET['delete_ticket_watcher'])) {

    enforceUserPermission('module_support', 2);

    $watcher_id = intval($_GET['delete_ticket_watcher']);

    mysqli_query($mysqli, "DELETE FROM ticket_watchers WHERE watcher_id = $watcher_id");


    $_SESSION['alert_message'] = "You <b>removed</b> a ticket watcher";

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if (isset($_POST['edit_ticket_asset'])) {

    enforceUserPermission('module_support', 2);

    $ticket_id = intval($_POST['ticket_id']);
    $asset_id = intval($_POST['asset']);
    $client_id = intval($_POST['client_id']);
    $ticket_number = sanitizeInput($_POST['ticket_number']);

    mysqli_query($mysqli, "UPDATE tickets SET ticket_asset_id = $asset_id WHERE ticket_id = $ticket_id");

    //Logging
    mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Ticket', log_action = 'Edit', log_description = '$session_name edited asset for ticket $ticket_number', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $ticket_id");

    $_SESSION['alert_message'] = "Ticket <strong>$ticket_number</strong> asset updated";

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if (isset($_POST['edit_ticket_vendor'])) {

    enforceUserPermission('module_support', 2);

    $ticket_id = intval($_POST['ticket_id']);
    $vendor_id = intval($_POST['vendor']);
    $client_id = intval($_POST['client_id']);
    $ticket_number = sanitizeInput($_POST['ticket_number']);

    mysqli_query($mysqli, "UPDATE tickets SET ticket_vendor_id = $vendor_id WHERE ticket_id = $ticket_id");

    //Logging
    mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Ticket', log_action = 'Edit', log_description = '$session_name edited vendor for ticket $ticket_number', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $ticket_id");

    $_SESSION['alert_message'] = "Ticket <strong>$ticket_number</strong> vendor updated";

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if (isset($_POST['edit_ticket_priority'])) {

    enforceUserPermission('module_support', 2);

    $ticket_id = intval($_POST['ticket_id']);
    $priority = sanitizeInput($_POST['priority']);
    $client_id = intval($_POST['client_id']);

    mysqli_query($mysqli, "UPDATE tickets SET ticket_priority = '$priority' WHERE ticket_id = $ticket_id");

    //Logging
    mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Ticket', log_action = 'Modify', log_description = '$session_name edited ticket priority', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $ticket_id");

    // Custom action/notif handler
    customAction('ticket_update', $ticket_id);

    $_SESSION['alert_message'] = "Ticket priority updated";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

    customAction('ticket_update', $ticket_id);
}

if (isset($_POST['assign_ticket'])) {

    enforceUserPermission('module_support', 2);

    // POST variables
    $ticket_id = intval($_POST['ticket_id']);
    $assigned_to = intval($_POST['assigned_to']);
    $ticket_status = intval($_POST['ticket_status']);

    // New > Open as assigned
    if ($ticket_status == 1 && $assigned_to !== 0) {
        $ticket_status = 2;
    }

    // Allow for un-assigning tickets
    if ($assigned_to == 0) {
        $ticket_reply = "Ticket unassigned.";
        $agent_name = "No One";
    } else {
        // Get & verify assigned agent details
        $agent_details_sql = mysqli_query($mysqli, "SELECT user_name, user_email FROM users LEFT JOIN user_settings ON users.user_id = user_settings.user_id WHERE users.user_id = $assigned_to AND user_settings.user_role > 1");
        $agent_details = mysqli_fetch_array($agent_details_sql);

        $agent_name = sanitizeInput($agent_details['user_name']);
        $agent_email = sanitizeInput($agent_details['user_email']);
        $ticket_reply = "Ticket re-assigned to $agent_name.";

        if (!$agent_name) {
            $_SESSION['alert_type'] = "error";
            $_SESSION['alert_message'] = "Invalid agent!";
            header("Location: " . $_SERVER["HTTP_REFERER"]);
            exit();
        }
    }

    // Get & verify ticket details
    $ticket_details_sql = mysqli_query($mysqli, "SELECT ticket_prefix, ticket_number, ticket_subject, ticket_client_id, client_name FROM tickets LEFT JOIN clients ON ticket_client_id = client_id WHERE ticket_id = '$ticket_id' AND ticket_status != 5");
    $ticket_details = mysqli_fetch_array($ticket_details_sql);

    $ticket_prefix = sanitizeInput($ticket_details['ticket_prefix']);
    $ticket_number = intval($ticket_details['ticket_number']);
    $ticket_subject = sanitizeInput($ticket_details['ticket_subject']);
    $client_id = intval($ticket_details['ticket_client_id']);
    $client_name = sanitizeInput($ticket_details['client_name']);

    if (!$ticket_subject) {
        $_SESSION['alert_type'] = "error";
        $_SESSION['alert_message'] = "Invalid ticket!";
        header("Location: " . $_SERVER["HTTP_REFERER"]);
        exit();
    }

    // Update ticket & insert reply
    mysqli_query($mysqli, "UPDATE tickets SET ticket_assigned_to = $assigned_to, ticket_status = '$ticket_status' WHERE ticket_id = $ticket_id");

    mysqli_query($mysqli, "INSERT INTO ticket_replies SET ticket_reply = '$ticket_reply', ticket_reply_type = 'Internal', ticket_reply_time_worked = '00:01:00', ticket_reply_by = $session_user_id, ticket_reply_ticket_id = $ticket_id");

    // Logging
    mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Ticket', log_action = 'Edit', log_description = '$session_name reassigned ticket $ticket_prefix$ticket_number - $ticket_subject to $agent_name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $ticket_id");


    // Notification
    if ($session_user_id != $assigned_to && $assigned_to != 0) {

        // App Notification
        mysqli_query($mysqli, "INSERT INTO notifications SET notification_type = 'Ticket', notification = 'Ticket $ticket_prefix$ticket_number - Subject: $ticket_subject has been assigned to you by $session_name', notification_action = 'ticket.php?ticket_id=$ticket_id', notification_client_id = $client_id, notification_user_id = $assigned_to");

        // Email Notification
        if (!empty($config_smtp_host)) {

            // Sanitize Config vars from get_settings.php
            $config_ticket_from_name = sanitizeInput($config_ticket_from_name);
            $config_ticket_from_email = sanitizeInput($config_ticket_from_email);
            $company_name = sanitizeInput($session_company_name);

            $subject = "$config_app_name - Ticket $ticket_prefix$ticket_number assigned to you - $ticket_subject";
            $body = "Hi $agent_name, <br><br>A ticket has been assigned to you!<br><br>Client: $client_name<br>Ticket Number: $ticket_prefix$ticket_number<br> Subject: $ticket_subject<br><br>https://$config_base_url/ticket.php?ticket_id=$ticket_id <br><br>Thanks, <br>$session_name<br>$company_name";

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
            addToMailQueue($mysqli, $data);
        }
    }

    customAction('ticket_assign', $ticket_id);

    $_SESSION['alert_message'] = "Ticket <strong>$ticket_prefix$ticket_number</strong> assigned to <strong>$agent_name</strong>";

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if (isset($_GET['delete_ticket'])) {

    enforceUserPermission('module_support', 3);

    // CSRF Check
    validateCSRFToken($_GET['csrf_token']);

    $ticket_id = intval($_GET['delete_ticket']);

    // Get Ticket and Client ID for logging and alert message
    $sql = mysqli_query($mysqli, "SELECT ticket_prefix, ticket_number, ticket_subject, ticket_status, ticket_closed_at, ticket_client_id FROM tickets WHERE ticket_id = $ticket_id");
    $row = mysqli_fetch_array($sql);
    $ticket_prefix = sanitizeInput($row['ticket_prefix']);
    $ticket_number = sanitizeInput($row['ticket_number']);
    $ticket_subject = sanitizeInput($row['ticket_subject']);
    $ticket_status = sanitizeInput($row['ticket_status']);
    $ticket_closed_at = sanitizeInput($row['ticket_closed_at']);
    $client_id = intval($row['ticket_client_id']);

    if (empty($ticket_closed_at)) {
        mysqli_query($mysqli, "DELETE FROM tickets WHERE ticket_id = $ticket_id");

        // Delete all ticket replies
        mysqli_query($mysqli, "DELETE FROM ticket_replies WHERE ticket_reply_ticket_id = $ticket_id");

        // Delete all ticket views
        mysqli_query($mysqli, "DELETE FROM ticket_views WHERE view_ticket_id = $ticket_id");

        // Logging
        mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Ticket', log_action = 'Delete', log_description = '$session_name deleted ticket $ticket_prefix$ticket_number - $ticket_subject along with all replies', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $ticket_id");

        $_SESSION['alert_type'] = "error";
        $_SESSION['alert_message'] = "Ticket <strong>$ticket_prefix$ticket_number</strong> along with all replies deleted";

        customAction('ticket_delete', $ticket_id);

        header("Location: tickets.php");
    }
}

if (isset($_POST['bulk_assign_ticket'])) {

    enforceUserPermission('module_support', 2);

    // POST variables
    $assign_to = intval($_POST['assign_to']);

    // Get a Ticket Count
    $ticket_count = count($_POST['ticket_ids']);

    // Assign Tech to Selected Tickets
    if (!empty($_POST['ticket_ids'])) {
        foreach ($_POST['ticket_ids'] as $ticket_id) {
            $ticket_id = intval($ticket_id);

            $sql = mysqli_query($mysqli, "SELECT * FROM tickets LEFT JOIN ticket_statuses ON ticket_status = ticket_status_id WHERE ticket_id = $ticket_id");
            $row = mysqli_fetch_array($sql);

            $ticket_prefix = sanitizeInput($row['ticket_prefix']);
            $ticket_number = intval($row['ticket_number']);
            $ticket_status = intval($row['ticket_status']);
            $ticket_name = sanitizeInput($row['ticket_name']);
            $ticket_subject = sanitizeInput($row['ticket_subject']);
            $client_id = intval($row['ticket_client_id']);

            if ($ticket_status == 1 && $assigned_to !== 0) {
                $ticket_status = 2;
            }

            // Allow for un-assigning tickets
            if ($assign_to == 0) {
                $ticket_reply = "Ticket unassigned, pending re-assignment.";
                $agent_name = "No One";
            } else {
                // Get & verify assigned agent details
                $agent_details_sql = mysqli_query($mysqli, "SELECT user_name, user_email FROM users LEFT JOIN user_settings ON users.user_id = user_settings.user_id WHERE users.user_id = $assign_to AND user_settings.user_role > 1");
                $agent_details = mysqli_fetch_array($agent_details_sql);

                $agent_name = sanitizeInput($agent_details['user_name']);
                $agent_email = sanitizeInput($agent_details['user_email']);
                $ticket_reply = "Ticket re-assigned to $agent_name.";

                if (!$agent_name) {
                    $_SESSION['alert_type'] = "error";
                    $_SESSION['alert_message'] = "Invalid agent!";
                    header("Location: " . $_SERVER["HTTP_REFERER"]);
                    exit();
                }
            }

            // Update ticket & insert reply
            mysqli_query($mysqli, "UPDATE tickets SET ticket_assigned_to = $assign_to, ticket_status = $ticket_status WHERE ticket_id = $ticket_id");

            mysqli_query($mysqli, "INSERT INTO ticket_replies SET ticket_reply = '$ticket_reply', ticket_reply_type = 'Internal', ticket_reply_time_worked = '00:01:00', ticket_reply_by = $session_user_id, ticket_reply_ticket_id = $ticket_id");

            // Logging
            mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Ticket', log_action = 'Edit', log_description = '$session_name reassigned ticket $ticket_prefix$ticket_number - $ticket_subject to $agent_name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $ticket_id");

            customAction('ticket_assign', $ticket_id);

            $tickets_assigned_body .= "$ticket_prefix$ticket_number - $ticket_subject<br>";
        } // End For Each Ticket ID Loop

        // Notification
        if ($session_user_id != $assign_to && $assign_to != 0) {

            // App Notification
            mysqli_query($mysqli, "INSERT INTO notifications SET notification_type = 'Ticket', notification = '$ticket_count Tickets have been assigned to you by $session_name', notification_action = 'tickets.php?status=Open&assigned=$assign_to', notification_client_id = $client_id, notification_user_id = $assign_to");

            // Agent Email Notification
            if (!empty($config_smtp_host)) {

                // Sanitize Config vars from get_settings.php
                $config_ticket_from_name = sanitizeInput($config_ticket_from_name);
                $config_ticket_from_email = sanitizeInput($config_ticket_from_email);
                $company_name = sanitizeInput($session_company_name);

                $subject = "$config_app_name - $ticket_count tickets have been assigned to you";
                $body = "Hi $agent_name, <br><br>$session_name assigned $ticket_count tickets to you!<br><br>$tickets_assigned_body<br>Thanks, <br>$session_name<br>$company_name";

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
                addToMailQueue($mysqli, $data);
            }
        }
    }

    $_SESSION['alert_message'] = "You assigned <b>$ticket_count</b> Tickets to <b>$agent_name</b>";

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if (isset($_POST['bulk_edit_ticket_priority'])) {

    enforceUserPermission('module_support', 2);

    // POST variables
    $priority = sanitizeInput($_POST['bulk_priority']);

    // Get a Ticket Count
    $ticket_count = count($_POST['ticket_ids']);

    // Assign Tech to Selected Tickets
    if (!empty($_POST['ticket_ids'])) {
        foreach ($_POST['ticket_ids'] as $ticket_id) {
            $ticket_id = intval($ticket_id);

            $sql = mysqli_query($mysqli, "SELECT * FROM tickets WHERE ticket_id = $ticket_id");
            $row = mysqli_fetch_array($sql);

            $ticket_prefix = sanitizeInput($row['ticket_prefix']);
            $ticket_number = intval($row['ticket_number']);
            $ticket_subject = sanitizeInput($row['ticket_subject']);
            $current_ticket_priority = sanitizeInput($row['ticket_priority']);
            $client_id = intval($row['ticket_client_id']);

            // Update ticket & insert reply
            mysqli_query($mysqli, "UPDATE tickets SET ticket_priority = '$priority' WHERE ticket_id = $ticket_id");

            mysqli_query($mysqli, "INSERT INTO ticket_replies SET ticket_reply = '$session_name updated the priority from $current_ticket_priority to $priority', ticket_reply_type = 'Internal', ticket_reply_time_worked = '00:01:00', ticket_reply_by = $session_user_id, ticket_reply_ticket_id = $ticket_id");

            // Logging
            mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Ticket', log_action = 'Edit', log_description = '$session_name updated the priority on ticket $ticket_prefix$ticket_number - $ticket_subject from $current_ticket_priority to $priority', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $ticket_id");

            customAction('ticket_update', $ticket_id);
        } // End For Each Ticket ID Loop
    }

    $_SESSION['alert_message'] = "You updated the priority for <b>$ticket_count</b> Tickets to <b>$priority</b>";

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if (isset($_POST['bulk_merge_tickets'])) {

    enforceUserPermission('module_support', 2);

    $ticket_count = count($_POST['ticket_ids']); // Get a ticket count
    $merge_into_ticket_number = intval($_POST['merge_into_ticket_number']); // Parent ticket *number*
    $merge_comment = sanitizeInput($_POST['merge_comment']); // Merge comment

    // NEW PARENT ticket details
    // Get merge into ticket id (as it may differ from the number)
    $sql = mysqli_query($mysqli, "SELECT ticket_id FROM tickets WHERE ticket_number = $merge_into_ticket_number");
    if (mysqli_num_rows($sql) == 0) {
        $_SESSION['alert_message'] = "Cannot merge into that ticket.";
        header("Location: " . $_SERVER["HTTP_REFERER"]);
        exit();
    }
    $merge_row = mysqli_fetch_array($sql);
    $merge_into_ticket_id = intval($merge_row['ticket_id']); // Parent ticket ID

    // Update & Close the selected tickets
    if (!empty($_POST['ticket_ids'])) {
        foreach ($_POST['ticket_ids'] as $ticket_id) {
            $ticket_id = intval($ticket_id);

            if ($ticket_id !== $merge_into_ticket_id) {

                $sql = mysqli_query($mysqli, "SELECT * FROM tickets WHERE ticket_id = $ticket_id");
                $row = mysqli_fetch_array($sql);

                $ticket_prefix = sanitizeInput($row['ticket_prefix']);
                $ticket_number = intval($row['ticket_number']);
                $ticket_subject = sanitizeInput($row['ticket_subject']);
                $ticket_details = mysqli_escape_string($mysqli, $row['ticket_details']);
                $current_ticket_priority = sanitizeInput($row['ticket_priority']);
                $client_id = intval($row['ticket_client_id']);

                // Update current ticket
                mysqli_query($mysqli, "INSERT INTO ticket_replies SET ticket_reply = 'Ticket $ticket_prefix$ticket_number bulk merged into $ticket_prefix$merge_into_ticket_number. Comment: $merge_comment', ticket_reply_time_worked = '00:01:00', ticket_reply_type = '$ticket_reply_type', ticket_reply_by = $session_user_id, ticket_reply_ticket_id = $ticket_id");
                mysqli_query($mysqli, "UPDATE tickets SET ticket_status = '5', ticket_resolved_at = NOW(), ticket_closed_at = NOW() WHERE ticket_id = $ticket_id") or die(mysqli_error($mysqli));

                //Update new parent ticket
                mysqli_query($mysqli, "INSERT INTO ticket_replies SET ticket_reply = 'Ticket $ticket_prefix$ticket_number was bulk merged into this ticket with comment: $merge_comment.<br><br><b>$ticket_subject</b><br>$ticket_details', ticket_reply_time_worked = '00:01:00', ticket_reply_type = 'Internal', ticket_reply_by = $session_user_id, ticket_reply_ticket_id = $merge_into_ticket_id");

                // Logging
                mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Ticket', log_action = 'Merged', log_description = 'Merged ticket $ticket_prefix$ticket_number into $ticket_prefix$merge_into_ticket_number', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");

                // Custom action/notif handler
                customAction('ticket_merge', $ticket_id);

            }
        } // End For Each Ticket ID Loop
    }

    mysqli_query($mysqli, "UPDATE tickets SET ticket_updated_at = NOW() WHERE ticket_id = $merge_into_ticket_id");

    $_SESSION['alert_message'] = "$ticket_count tickets merged into $ticket_prefix$merge_into_ticket_number";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['bulk_resolve_tickets'])) {

    enforceUserPermission('module_support', 2);

    // POST variables
    $details = mysqli_escape_string($mysqli, $_POST['bulk_details']);
    $private_note = intval($_POST['bulk_private_note']);
    if ($private_note == 1) {
        $ticket_reply_type = 'Internal';
    } else {
        $ticket_reply_type = 'Public';
    }

    // Get a Ticket Count
    $ticket_count = count($_POST['ticket_ids']);

    // Close Selected Tickets
    if (!empty($_POST['ticket_ids'])) {
        foreach ($_POST['ticket_ids'] as $ticket_id) {
            $ticket_id = intval($ticket_id);

            $sql = mysqli_query($mysqli, "SELECT * FROM tickets WHERE ticket_id = $ticket_id");
            $row = mysqli_fetch_array($sql);

            $ticket_prefix = sanitizeInput($row['ticket_prefix']);
            $ticket_number = intval($row['ticket_number']);
            $ticket_subject = sanitizeInput($row['ticket_subject']);
            $current_ticket_priority = sanitizeInput($row['ticket_priority']);
            $url_key = sanitizeInput($row['ticket_url_key']);
            $client_id = intval($row['ticket_client_id']);

            // Update ticket & insert reply
            mysqli_query($mysqli, "UPDATE tickets SET ticket_status = 4, ticket_resolved_at = NOW() WHERE ticket_id = $ticket_id");

            mysqli_query($mysqli, "INSERT INTO ticket_replies SET ticket_reply = '$details', ticket_reply_type = '$ticket_reply_type', ticket_reply_time_worked = '00:01:00', ticket_reply_by = $session_user_id, ticket_reply_ticket_id = $ticket_id");

            // Logging
            mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Ticket', log_action = 'Resolve', log_description = '$session_name resolved $ticket_prefix$ticket_number - $ticket_subject in a bulk action', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $ticket_id");

            customAction('ticket_resolve', $ticket_id);

            // Client notification email
            if (!empty($config_smtp_host) && $config_ticket_client_general_notifications == 1 && $private_note == 0) {

                // Get Contact details
                $ticket_sql = mysqli_query($mysqli, "SELECT contact_name, contact_email FROM tickets 
                    LEFT JOIN contacts ON ticket_contact_id = contact_id
                    WHERE ticket_id = $ticket_id
                ");
                $row = mysqli_fetch_array($ticket_sql);

                $contact_name = sanitizeInput($row['contact_name']);
                $contact_email = sanitizeInput($row['contact_email']);

                // Sanitize Config vars from get_settings.php
                $from_name = sanitizeInput($config_ticket_from_name);
                $from_email = sanitizeInput($config_ticket_from_email);
                $base_url = sanitizeInput($config_base_url);

                // Get Company Info
                $sql = mysqli_query($mysqli, "SELECT company_name, company_phone FROM companies WHERE company_id = 1");
                $row = mysqli_fetch_array($sql);
                $company_name = sanitizeInput($row['company_name']);
                $company_phone = sanitizeInput(formatPhoneNumber($row['company_phone']));

                // Check email valid
                if (filter_var($contact_email, FILTER_VALIDATE_EMAIL)) {

                    $data = [];

                    $subject = "Ticket resolved - [$ticket_prefix$ticket_number] - $ticket_subject | (pending closure)";
                    $body = "<i style=\'color: #808080\'>##- Please type your reply above this line -##</i><br><br>Hello $contact_name,<br><br>Your ticket regarding \"$ticket_subject\" has been marked as solved and is pending closure.<br><br>$details<br><br> If your request/issue is resolved, you can simply ignore this email. If you need further assistance, please reply or <a href=\'https://$config_base_url/guest_view_ticket.php?ticket_id=$ticket_id&url_key=$url_key\'>re-open</a> to let us know! <br><br>Ticket: $ticket_prefix$ticket_number<br>Subject: $ticket_subject<br>Portal: https://$base_url/portal/ticket.php?id=$ticket_id<br><br>--<br>$company_name - Support<br>$config_ticket_from_email<br>$company_phone";

                    // Email Ticket Contact
                    // Queue Mail

                    $data[] = [
                        'from' => $from_email,
                        'from_name' => $from_name,
                        'recipient' => $contact_email,
                        'recipient_name' => $contact_name,
                        'subject' => $subject,
                        'body' => $body
                    ];

                    // Also Email all the watchers
                    $sql_watchers = mysqli_query($mysqli, "SELECT watcher_email FROM ticket_watchers WHERE watcher_ticket_id = $ticket_id");
                    $body .= "<br><br>----------------------------------------<br>DO NOT REPLY - YOU ARE RECEIVING THIS EMAIL BECAUSE YOU ARE A WATCHER";
                    while ($row = mysqli_fetch_array($sql_watchers)) {
                        $watcher_email = sanitizeInput($row['watcher_email']);

                        // Queue Mail
                        $data[] = [
                            'from' => $from_email,
                            'from_name' => $from_name,
                            'recipient' => $watcher_email,
                            'recipient_name' => $watcher_email,
                            'subject' => $subject,
                            'body' => $body
                        ];
                    }
                }
                addToMailQueue($mysqli, $data);
            } // End Mail IF
        } // End Loop
    } // End Array Empty Check

    $_SESSION['alert_message'] = "You closed <b>$ticket_count</b> Tickets";

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if (isset($_POST['bulk_ticket_reply'])) {

    enforceUserPermission('module_support', 2);

    // POST variables
    $ticket_reply = mysqli_escape_string($mysqli, $_POST['bulk_reply_details']);
    $ticket_status = intval($_POST['bulk_status']);
    $private_note = intval($_POST['bulk_private_reply']);
    if ($private_note == 1) {
        $ticket_reply_type = 'Internal';
    } else {
        $ticket_reply_type = 'Public';
    }

    // Get a Ticket Count
    $ticket_count = count($_POST['ticket_ids']);

    // Loop Through Tickets and Add Reply along with Email notifications
    if (!empty($_POST['ticket_ids'])) {
        foreach ($_POST['ticket_ids'] as $ticket_id) {
            $ticket_id = intval($ticket_id);

            $sql = mysqli_query($mysqli, "SELECT * FROM tickets WHERE ticket_id = $ticket_id");
            $row = mysqli_fetch_array($sql);

            $ticket_prefix = sanitizeInput($row['ticket_prefix']);
            $ticket_number = intval($row['ticket_number']);
            $ticket_subject = sanitizeInput($row['ticket_subject']);
            $current_ticket_priority = sanitizeInput($row['ticket_priority']);
            $url_key = sanitizeInput($row['ticket_url_key']);
            $client_id = intval($row['ticket_client_id']);

            // Add reply
            mysqli_query($mysqli, "INSERT INTO ticket_replies SET ticket_reply = '$ticket_reply', ticket_reply_time_worked = '00:01:00', ticket_reply_type = '$ticket_reply_type', ticket_reply_by = $session_user_id, ticket_reply_ticket_id = $ticket_id");

            $ticket_reply_id = mysqli_insert_id($mysqli);

            // Update Ticket Status
            mysqli_query($mysqli, "UPDATE tickets SET ticket_status = '$ticket_status' WHERE ticket_id = $ticket_id");

            // Logging
            mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Ticket Reply', log_action = 'Create', log_description = '$session_name replied to ticket $ticket_prefix$ticket_number - $ticket_subject and was a $ticket_reply_type reply', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $ticket_reply_id");

            // Custom action/notif handler
            if ($ticket_reply_type == 'Internal') {
                customAction('ticket_reply_agent_internal', $ticket_id);
            } else {
                customAction('reply_reply_agent_public', $ticket_id);
            }

            // Resolve the ticket, if set
            if ($ticket_status == 4) {
                mysqli_query($mysqli, "UPDATE tickets SET ticket_resolved_at = NOW() WHERE ticket_id = $ticket_id");
                mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Ticket', log_action = 'Resolved', log_description = 'Ticket ID $ticket_id resolved', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id, log_entity_id = $ticket_id");
                customAction('ticket_resolve', $ticket_id);
            }

            // Get Contact Details
            $sql = mysqli_query(
                $mysqli,
                "SELECT contact_name, contact_email, ticket_created_by, ticket_assigned_to
                FROM tickets
                LEFT JOIN contacts ON ticket_contact_id = contact_id
                WHERE ticket_id = $ticket_id"
            );

            $row = mysqli_fetch_array($sql);

            $contact_name = sanitizeInput($row['contact_name']);
            $contact_email = sanitizeInput($row['contact_email']);
            $ticket_created_by = intval($row['ticket_created_by']);
            $ticket_assigned_to = intval($row['ticket_assigned_to']);

            // Sanitize Config vars from get_settings.php
            $from_name = sanitizeInput($config_ticket_from_name);
            $from_email = sanitizeInput($config_ticket_from_email);
            $base_url = sanitizeInput($config_base_url);

            $sql = mysqli_query($mysqli, "SELECT company_name, company_phone FROM companies WHERE company_id = 1");
            $row = mysqli_fetch_array($sql);
            $company_name = sanitizeInput($row['company_name']);
            $company_phone = sanitizeInput(formatPhoneNumber($row['company_phone']));

            // Send e-mail to client if public update & email is set up
            if ($private_note == 0 && !empty($config_smtp_host)) {

                if (filter_var($contact_email, FILTER_VALIDATE_EMAIL)) {

                    $subject = "Ticket update - [$ticket_prefix$ticket_number] - $ticket_subject";
                    $body = "<i style=\'color: #808080\'>##- Please type your reply above this line -##</i><br><br>Hello $contact_name,<br><br>Your ticket regarding $ticket_subject has been updated.<br><br>--------------------------------<br>$ticket_reply<br>--------------------------------<br><br>Ticket: $ticket_prefix$ticket_number<br>Subject: $ticket_subject<br>Status: $ticket_status_name<br>Portal: <a href=\'https://$config_base_url/guest_view_ticket.php?ticket_id=$ticket_id&url_key=$url_key\'>View ticket</a><br><br>--<br>$company_name - Support<br>$from_email<br>$company_phone";

                    $data = [];

                    // Email Ticket Contact
                    // Queue Mail
                    $data[] = [
                        'from' => $from_email,
                        'from_name' => $from_name,
                        'recipient' => $contact_email,
                        'recipient_name' => $contact_name,
                        'subject' => $subject,
                        'body' => $body
                    ];

                    // Also Email all the watchers
                    $sql_watchers = mysqli_query($mysqli, "SELECT watcher_email FROM ticket_watchers WHERE watcher_ticket_id = $ticket_id");
                    $body .= "<br><br>----------------------------------------<br>DO NOT REPLY - YOU ARE RECEIVING THIS EMAIL BECAUSE YOU ARE A WATCHER";
                    while ($row = mysqli_fetch_array($sql_watchers)) {
                        $watcher_email = sanitizeInput($row['watcher_email']);

                        // Queue Mail
                        $data[] = [
                            'from' => $from_email,
                            'from_name' => $from_name,
                            'recipient' => $watcher_email,
                            'recipient_name' => $watcher_email,
                            'subject' => $subject,
                            'body' => $body
                        ];
                    }
                }
                addToMailQueue($mysqli, $data);
            } //End Mail IF

            // Notification for assigned ticket user
            if ($session_user_id != $ticket_assigned_to && $ticket_assigned_to != 0) {

                mysqli_query($mysqli, "INSERT INTO notifications SET notification_type = 'Ticket', notification = '$session_name updated Ticket $ticket_prefix$ticket_number - Subject: $ticket_subject that is assigned to you', notification_action = 'ticket.php?ticket_id=$ticket_id', notification_client_id = $client_id, notification_user_id = $ticket_assigned_to");
            }

            // Notification for user that opened the ticket
            if ($session_user_id != $ticket_created_by && $ticket_created_by != 0) {

                mysqli_query($mysqli, "INSERT INTO notifications SET notification_type = 'Ticket', notification = '$session_name updated Ticket $ticket_prefix$ticket_number - Subject: $ticket_subject that you opened', notification_action = 'ticket.php?ticket_id=$ticket_id', notification_client_id = $client_id, notification_user_id = $ticket_created_by");
            }
        } // End Ticket Lopp

    }

    $_SESSION['alert_message'] = "You updated <b>$ticket_count</b> tickets";

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}


// Currently not UI Frontend for this
if (isset($_POST['bulk_add_ticket_project'])) {

    enforceUserPermission('module_support', 2);

    // POST variables
    $project_id = intval($_POST['project_id']);

    // Get Project Name
    $sql = mysqli_query($mysqli, "SELECT * FROM projects WHERE project_id = $project_id");
    $row = mysqli_fetch_array($sql);
    $project_name = sanitizeInput($row['project_name']);

    // Get a Ticket Count
    $ticket_count = count($_POST['ticket_ids']);

    // Assign Project to Selected Tickets
    if (!empty($_POST['ticket_ids'])) {
        foreach ($_POST['ticket_ids'] as $ticket_id) {
            $ticket_id = intval($ticket_id);

            $sql = mysqli_query($mysqli, "SELECT * FROM tickets WHERE ticket_id = $ticket_id");
            $row = mysqli_fetch_array($sql);

            $ticket_prefix = sanitizeInput($row['ticket_prefix']);
            $ticket_number = intval($row['ticket_number']);
            $ticket_subject = sanitizeInput($row['ticket_subject']);
            $current_ticket_priority = sanitizeInput($row['ticket_priority']);
            $client_id = intval($row['ticket_client_id']);

            // Update ticket & insert reply
            mysqli_query($mysqli, "UPDATE tickets SET ticket_project_id = $project_id WHERE ticket_id = $ticket_id");

            // Logging
            mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Project', log_action = 'Edit', log_description = '$session_name added ticket $ticket_prefix$ticket_number - $ticket_subject to project $project_name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $project_id");
        } // End For Each Ticket ID Loop
    }

    $_SESSION['alert_message'] = "You added <b>$ticket_count</b> Tickets to the project <b>$project_name</b>";

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if (isset($_POST['bulk_add_asset_ticket'])) {

    enforceUserPermission('module_support', 2);

    // CSRF Check
    validateCSRFToken($_POST['csrf_token']);

    $client_id = intval($_POST['bulk_client']);
    $assigned_to = intval($_POST['bulk_assigned_to']);
    if ($assigned_to == 0) {
        $ticket_status = 1;
    } else {
        $ticket_status = 2;
    }
    $subject = sanitizeInput($_POST['bulk_subject']);
    $priority = sanitizeInput($_POST['bulk_priority']);
    $category_id = intval($_POST['bulk_category']);
    $details = mysqli_real_escape_string($mysqli, $_POST['bulk_details']);
    $project_id = intval($_POST['bulk_project']);
    $use_primary_contact = intval($_POST['use_primary_contact']);
    $ticket_template_id = intval($_POST['bulk_ticket_template_id']);
    $billable = intval($_POST['bulk_billable']);

    // Check to see if adding a ticket by template
    if($ticket_template_id) {
        $sql = mysqli_query($mysqli, "SELECT * FROM ticket_templates WHERE ticket_template_id = $ticket_template_id");
        $row = mysqli_fetch_array($sql);

        // Override Template Subject
        if(empty($subject)) {
            $subject = sanitizeInput($row['ticket_template_subject']);
        }
        $details = mysqli_escape_string($mysqli, $row['ticket_template_details']);

        // Get Associated Tasks from the ticket template
        $sql_task_templates = mysqli_query($mysqli, "SELECT * FROM task_templates WHERE task_template_ticket_template_id = $ticket_template_id");

    }

    // Get a Asset Count
    $asset_count = count($_POST['asset_ids']);

    // Create ticket for each selected asset
    if (!empty($_POST['asset_ids'])) {
        foreach ($_POST['asset_ids'] as $asset_id) {
            $asset_id = intval($asset_id);

            $sql = mysqli_query($mysqli, "SELECT * FROM assets WHERE asset_id = $asset_id");
            $row = mysqli_fetch_array($sql);

            $asset_name = sanitizeInput($row['asset_name']);

            $subject_asset_prepended = "$asset_name - $subject";

             // Get the next Ticket Number and update the config
            $sql_ticket_number = mysqli_query($mysqli, "SELECT config_ticket_next_number FROM settings WHERE company_id = 1");
            $ticket_number_row = mysqli_fetch_array($sql_ticket_number);
            $ticket_number = intval($ticket_number_row['config_ticket_next_number']);

            // Sanitize Config Vars from get_settings.php and Session Vars from check_login.php
            $config_ticket_prefix = sanitizeInput($config_ticket_prefix);
            $config_ticket_from_name = sanitizeInput($config_ticket_from_name);
            $config_ticket_from_email = sanitizeInput($config_ticket_from_email);
            $config_base_url = sanitizeInput($config_base_url);

            //Generate a unique URL key for clients to access
            $url_key = randomString(156);

            // Increment the config ticket next number
            $new_config_ticket_next_number = $ticket_number + 1;

            mysqli_query($mysqli, "UPDATE settings SET config_ticket_next_number = $new_config_ticket_next_number WHERE company_id = 1");

            mysqli_query($mysqli, "INSERT INTO tickets SET ticket_prefix = '$config_ticket_prefix', ticket_number = $ticket_number, ticket_category = $category_id, ticket_subject = '$subject_asset_prepended', ticket_details = '$details', ticket_priority = '$priority', ticket_billable = $billable, ticket_status = $ticket_status, ticket_asset_id = $asset_id, ticket_created_by = $session_user_id, ticket_assigned_to = $assigned_to, ticket_url_key = '$url_key', ticket_client_id = $client_id, ticket_project_id = $project_id");

            $ticket_id = mysqli_insert_id($mysqli);

            // Update the next ticket number in the database
            mysqli_query($mysqli, "UPDATE settings SET config_ticket_next_number = $new_config_ticket_next_number WHERE company_id = 1");

            // Add Tasks
            if (!empty($_POST['tasks'])) {
                foreach ($_POST['tasks'] as $task) {
                    $task_name = sanitizeInput($task);
                    // Check that task_name is not-empty (For some reason the !empty on the array doesnt work here like in watchers)
                    if (!empty($task_name)) {
                        mysqli_query($mysqli,"INSERT INTO tasks SET task_name = '$task_name', task_ticket_id = $ticket_id");
                    }
                }
            }

            // Add Tasks from Template if Template was selected
            if($ticket_template_id) {
                if (mysqli_num_rows($sql_task_templates) > 0) {
                    while ($row = mysqli_fetch_array($sql_task_templates)) {
                        $task_order = intval($row['task_template_order']);
                        $task_name = sanitizeInput($row['task_template_name']);

                        mysqli_query($mysqli,"INSERT INTO tasks SET task_name = '$task_name', task_order = $task_order, task_ticket_id = $ticket_id");
                    }
                }
            }

            // Custom action/notif handler
            customAction('ticket_create', $ticket_id);
        }

        // Logging
        mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Ticket', log_action = 'Bulk Create', log_description = '$session_name created $asset_count tickets under assets', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id");

        $_SESSION['alert_message'] = "You created <b>$asset_count</b> tickets for the selected assets";

    }

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}


if (isset($_POST['add_ticket_reply'])) {

    enforceUserPermission('module_support', 2);

    $ticket_id = intval($_POST['ticket_id']);
    $ticket_reply = mysqli_real_escape_string($mysqli, $_POST['ticket_reply']);
    $ticket_status = intval($_POST['status']);
    // Handle the time inputs for hours, minutes, and seconds
    $hours = intval($_POST['hours']);
    $minutes = intval($_POST['minutes']);
    $seconds = intval($_POST['seconds']);

    // Combine into a single time string
    $ticket_reply_time_worked = sanitizeInput(sprintf("%02d:%02d:%02d", $hours, $minutes, $seconds));

    $client_id = intval($_POST['client_id']);

    $send_email = 0;

    if ($_POST['public_reply_type'] == 1 ){
        $ticket_reply_type = 'Public';
    } elseif ($_POST['public_reply_type'] == 2 ) {
        $ticket_reply_type = 'Public';
        $send_email = 1;
    } else {
        $ticket_reply_type = 'Internal';
    }

    // Add reply
    mysqli_query($mysqli, "INSERT INTO ticket_replies SET ticket_reply = '$ticket_reply', ticket_reply_time_worked = '$ticket_reply_time_worked', ticket_reply_type = '$ticket_reply_type', ticket_reply_by = $session_user_id, ticket_reply_ticket_id = $ticket_id");

    $ticket_reply_id = mysqli_insert_id($mysqli);

    // Update Ticket Status & Last Response Field
    mysqli_query($mysqli, "UPDATE tickets SET ticket_status = $ticket_status WHERE ticket_id = $ticket_id");

    // Resolve the ticket, if set
    if ($ticket_status == 4) {
        mysqli_query($mysqli, "UPDATE tickets SET ticket_resolved_at = NOW() WHERE ticket_id = $ticket_id");
        mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Ticket', log_action = 'Resolved', log_description = 'Ticket ID $ticket_id resolved', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id, log_entity_id = $ticket_id");
    }

    // Get Ticket Details
    $ticket_sql = mysqli_query($mysqli, "SELECT contact_name, contact_email, ticket_prefix, ticket_number, ticket_subject, ticket_status, ticket_status_name, ticket_url_key, ticket_client_id, ticket_created_by, ticket_assigned_to 
        FROM tickets 
        LEFT JOIN clients ON ticket_client_id = client_id 
        LEFT JOIN contacts ON ticket_contact_id = contact_id
        LEFT JOIN ticket_statuses ON ticket_status = ticket_status_id
        WHERE ticket_id = $ticket_id
    ");

    $row = mysqli_fetch_array($ticket_sql);

    $contact_name = sanitizeInput($row['contact_name']);
    $contact_email = sanitizeInput($row['contact_email']);
    $ticket_prefix = sanitizeInput($row['ticket_prefix']);
    $ticket_number = intval($row['ticket_number']);
    $ticket_subject = sanitizeInput($row['ticket_subject']);
    $ticket_status = intval($row['ticket_status']);
    $ticket_status_name = sanitizeInput($row['ticket_status_name']);
    $url_key = sanitizeInput($row['ticket_url_key']);
    $client_id = intval($row['ticket_client_id']);
    $ticket_created_by = intval($row['ticket_created_by']);
    $ticket_assigned_to = intval($row['ticket_assigned_to']);

    // Sanitize Config vars from get_settings.php
    $config_ticket_from_name = sanitizeInput($config_ticket_from_name);
    $config_ticket_from_email = sanitizeInput($config_ticket_from_email);
    $config_base_url = sanitizeInput($config_base_url);

    $sql = mysqli_query($mysqli, "SELECT company_name, company_phone FROM companies WHERE company_id = 1");
    $row = mysqli_fetch_array($sql);
    $company_name = sanitizeInput($row['company_name']);
    $company_phone = sanitizeInput(formatPhoneNumber($row['company_phone']));

    // Send e-mail to client if public update & email is set up
    if ($ticket_reply_type == 'Public' && $send_email == 1 && !empty($config_smtp_host)) {

        if (filter_var($contact_email, FILTER_VALIDATE_EMAIL)) {

            // Slightly different email subject/text depending on if this update set auto-close

            if ($ticket_status == 4) {
                // Resolved
                $subject = "Ticket resolved - [$ticket_prefix$ticket_number] - $ticket_subject | (pending closure)";
                $body = "<i style=\'color: #808080\'>##- Please type your reply above this line -##</i><br><br>Hello $contact_name,<br><br>Your ticket regarding $ticket_subject has been marked as solved and is pending closure.<br><br>--------------------------------<br>$ticket_reply<br>--------------------------------<br><br>If your request/issue is resolved, you can simply ignore this email. If you need further assistance, please reply or <a href=\'https://$config_base_url/guest_view_ticket.php?ticket_id=$ticket_id&url_key=$url_key\'>re-open</a> to let us know! <br><br>Ticket: $ticket_prefix$ticket_number<br>Subject: $ticket_subject<br>Status: $ticket_status_name<br>Portal: <a href=\'https://$config_base_url/guest_view_ticket.php?ticket_id=$ticket_id&url_key=$url_key\'>View ticket</a><br><br>--<br>$company_name - Support<br>$config_ticket_from_email<br>$company_phone";            } else {
                // Anything else
                $subject = "Ticket update - [$ticket_prefix$ticket_number] - $ticket_subject";
                $body = "<i style=\'color: #808080\'>##- Please type your reply above this line -##</i><br><br>Hello $contact_name,<br><br>Your ticket regarding $ticket_subject has been updated.<br><br>--------------------------------<br>$ticket_reply<br>--------------------------------<br><br>Ticket: $ticket_prefix$ticket_number<br>Subject: $ticket_subject<br>Status: $ticket_status_name<br>Portal: <a href=\'https://$config_base_url/guest_view_ticket.php?ticket_id=$ticket_id&url_key=$url_key\'>View ticket</a><br><br>--<br>$company_name - Support<br>$config_ticket_from_email<br>$company_phone";
            }

            $data = [];

            // Email Ticket Contact
            // Queue Mail
            $data[] = [
                'from' => $config_ticket_from_email,
                'from_name' => $config_ticket_from_name,
                'recipient' => $contact_email,
                'recipient_name' => $contact_name,
                'subject' => $subject,
                'body' => $body
            ];

            // Also Email all the watchers
            $sql_watchers = mysqli_query($mysqli, "SELECT watcher_email FROM ticket_watchers WHERE watcher_ticket_id = $ticket_id");
            $body .= "<br><br>----------------------------------------<br>DO NOT REPLY - YOU ARE RECEIVING THIS EMAIL BECAUSE YOU ARE A WATCHER";
            while ($row = mysqli_fetch_array($sql_watchers)) {
                $watcher_email = sanitizeInput($row['watcher_email']);

                // Queue Mail
                $data[] = [
                    'from' => $config_ticket_from_email,
                    'from_name' => $config_ticket_from_name,
                    'recipient' => $watcher_email,
                    'recipient_name' => $watcher_email,
                    'subject' => $subject,
                    'body' => $body
                ];
            }
            addToMailQueue($mysqli, $data);
        }
    }
    //End Mail IF

    // Notification for assigned ticket user
    if ($session_user_id != $ticket_assigned_to && $ticket_assigned_to != 0) {

        mysqli_query($mysqli, "INSERT INTO notifications SET notification_type = 'Ticket', notification = '$session_name updated Ticket $ticket_prefix$ticket_number - Subject: $ticket_subject that is assigned to you', notification_action = 'ticket.php?ticket_id=$ticket_id', notification_client_id = $client_id, notification_user_id = $ticket_assigned_to");
    }

    // Notification for user that opened the ticket
    if ($session_user_id != $ticket_created_by && $ticket_created_by != 0) {

        mysqli_query($mysqli, "INSERT INTO notifications SET notification_type = 'Ticket', notification = '$session_name updated Ticket $ticket_prefix$ticket_number - Subject: $ticket_subject that you opened', notification_action = 'ticket.php?ticket_id=$ticket_id', notification_client_id = $client_id, notification_user_id = $ticket_created_by");
    }

    // Custom action/notif handler
    if ($ticket_reply_type == 'Internal') {
        customAction('ticket_reply_agent_internal', $ticket_id);
    } else {
        customAction('reply_reply_agent_public', $ticket_id);
    }

    // Logging
    mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Ticket Reply', log_action = 'Create', log_description = '$session_name replied to ticket $ticket_prefix$ticket_number - $ticket_subject and was a $ticket_reply_type reply', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $ticket_reply_id");

    $_SESSION['alert_message'] = "Ticket <strong>$ticket_prefix$ticket_number</strong> has been updated with your reply and was <strong>$ticket_reply_type</strong>";

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if (isset($_POST['edit_ticket_reply'])) {

    enforceUserPermission('module_support', 2);

    $ticket_reply_id = intval($_POST['ticket_reply_id']);
    $ticket_reply = mysqli_real_escape_string($mysqli, $_POST['ticket_reply']);
    $ticket_reply_type = sanitizeInput($_POST['ticket_reply_type']);
    $ticket_reply_time_worked = sanitizeInput($_POST['time']);

    $client_id = intval($_POST['client_id']);

    mysqli_query($mysqli, "UPDATE ticket_replies SET ticket_reply = '$ticket_reply', ticket_reply_type = '$ticket_reply_type', ticket_reply_time_worked = '$ticket_reply_time_worked' WHERE ticket_reply_id = $ticket_reply_id AND ticket_reply_type != 'Client'") or die(mysqli_error($mysqli));

    //Logging
    mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Ticket Reply', log_action = 'Modify', log_description = '$session_name modified ticket reply', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $ticket_reply_id");

    $_SESSION['alert_message'] = "Ticket reply updated";

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if (isset($_GET['archive_ticket_reply'])) {

    enforceUserPermission('module_support', 2);

    $ticket_reply_id = intval($_GET['archive_ticket_reply']);

    mysqli_query($mysqli, "UPDATE ticket_replies SET ticket_reply_archived_at = NOW() WHERE ticket_reply_id = $ticket_reply_id");

    //Logging
    mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Ticket Reply', log_action = 'Archive', log_description = '$session_name arhived ticket reply', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id, log_entity_id = $ticket_reply_id");

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "Ticket reply archived";

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if (isset($_POST['merge_ticket'])) {

    enforceUserPermission('module_support', 2);

    $ticket_id = intval($_POST['ticket_id']); // Child ticket ID to be closed
    $merge_into_ticket_number = intval($_POST['merge_into_ticket_number']); // Parent ticket *number*
    $merge_comment = sanitizeInput($_POST['merge_comment']); // Merge comment
    $move_replies = intval($_POST['merge_move_replies']); // Whether to move replies to the new parent ticket
    $ticket_reply_type = 'Internal'; // Default all replies to internal

    // Get current ticket details
    $sql = mysqli_query($mysqli, "SELECT ticket_prefix, ticket_number, ticket_subject, ticket_details FROM tickets WHERE ticket_id = $ticket_id");
    if (mysqli_num_rows($sql) == 0) {
        $_SESSION['alert_message'] = "No ticket with that ID found.";
        header("Location: " . $_SERVER["HTTP_REFERER"]);
        exit();
    }
    // CURRENT ticket details
    $row = mysqli_fetch_array($sql);
    $ticket_prefix = sanitizeInput($row['ticket_prefix']);
    $ticket_number = intval($row['ticket_number']);
    $ticket_subject = sanitizeInput($row['ticket_subject']);
    $ticket_details = mysqli_escape_string($mysqli, $row['ticket_details']);

    // NEW PARENT ticket details
    // Get merge into ticket id (as it may differ from the number)
    $sql = mysqli_query($mysqli, "SELECT ticket_id FROM tickets WHERE ticket_number = $merge_into_ticket_number");
    if (mysqli_num_rows($sql) == 0) {
        $_SESSION['alert_message'] = "Cannot merge into that ticket.";
        header("Location: " . $_SERVER["HTTP_REFERER"]);
        exit();
    }
    $merge_row = mysqli_fetch_array($sql);
    $merge_into_ticket_id = intval($merge_row['ticket_id']);

    // Sanity check
    if ($ticket_number == $merge_into_ticket_number) {
        $_SESSION['alert_message'] = "Cannot merge into the same ticket.";
        header("Location: " . $_SERVER["HTTP_REFERER"]);
        exit();
    }

    // Move ticket replies from child > parent
    if ($move_replies) {
        mysqli_query($mysqli, "UPDATE ticket_replies SET ticket_reply_ticket_id = $merge_into_ticket_id WHERE ticket_reply_ticket_id = $ticket_id");
    }

    // Update current ticket
    mysqli_query($mysqli, "INSERT INTO ticket_replies SET ticket_reply = 'Ticket $ticket_prefix$ticket_number merged into $ticket_prefix$merge_into_ticket_number. Comment: $merge_comment', ticket_reply_time_worked = '00:01:00', ticket_reply_type = '$ticket_reply_type', ticket_reply_by = $session_user_id, ticket_reply_ticket_id = $ticket_id");
    mysqli_query($mysqli, "UPDATE tickets SET ticket_status = '5', ticket_resolved_at = NOW(), ticket_closed_at = NOW() WHERE ticket_id = $ticket_id") or die(mysqli_error($mysqli));

    //Update new parent ticket
    mysqli_query($mysqli, "INSERT INTO ticket_replies SET ticket_reply = 'Ticket $ticket_prefix$ticket_number was merged into this ticket with comment: $merge_comment.<br><br><b>$ticket_subject</b><br>$ticket_details', ticket_reply_time_worked = '00:01:00', ticket_reply_type = '$ticket_reply_type', ticket_reply_by = $session_user_id, ticket_reply_ticket_id = $merge_into_ticket_id");
    mysqli_query($mysqli, "UPDATE tickets SET ticket_updated_at = NOW() WHERE ticket_id = $merge_into_ticket_id");

    // Logging
    mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Ticket', log_action = 'Merged', log_description = 'Merged ticket $ticket_prefix$ticket_number into $ticket_prefix$merge_into_ticket_number', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");

    customAction('ticket_merge', $ticket_id);

    $_SESSION['alert_message'] = "Ticket merged into $ticket_prefix$merge_into_ticket_number";

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if (isset($_POST['change_client_ticket'])) {

    enforceUserPermission('module_support', 2);

    $ticket_id = intval($_POST['ticket_id']);
    $client_id = intval($_POST['new_client_id']);
    $contact_id = intval($_POST['new_contact_id']);

    // Set any/all existing replies to internal
    mysqli_query($mysqli, "UPDATE ticket_replies SET ticket_reply_type = 'Internal' WHERE ticket_reply_ticket_id = $ticket_id");

    // Update ticket client & contact
    mysqli_query($mysqli, "UPDATE tickets SET ticket_client_id = $client_id, ticket_contact_id = $contact_id WHERE ticket_id = $ticket_id LIMIT 1");

    //Logging
    mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Ticket Reply', log_action = 'Modify', log_description = '$session_name modified ticket - client changed', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $ticket_id");

    customAction('ticket_update', $ticket_id);

    $_SESSION['alert_message'] = "Ticket client updated";

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if (isset($_GET['resolve_ticket'])) {

    enforceUserPermission('module_support', 2);

    // CSRF Check
    validateCSRFToken($_GET['csrf_token']);

    $ticket_id = intval($_GET['resolve_ticket']);

    mysqli_query($mysqli, "UPDATE tickets SET ticket_status = 4, ticket_resolved_at = NOW() WHERE ticket_id = $ticket_id");

    //Logging
    mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Ticket', log_action = 'Resolved', log_description = 'Ticket ID $ticket_id resolved', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id, log_entity_id = $ticket_id");

    customAction('ticket_resolve', $ticket_id);

    // Client notification email
    if (!empty($config_smtp_host) && $config_ticket_client_general_notifications == 1) {

        // Get details
        $ticket_sql = mysqli_query($mysqli, "SELECT contact_name, contact_email, ticket_prefix, ticket_number, ticket_subject, ticket_status_name, ticket_assigned_to, ticket_url_key, ticket_client_id FROM tickets 
            LEFT JOIN clients ON ticket_client_id = client_id 
            LEFT JOIN contacts ON ticket_contact_id = contact_id
            LEFT JOIN ticket_statuses ON ticket_status = ticket_status_id
            WHERE ticket_id = $ticket_id
        ");
        $row = mysqli_fetch_array($ticket_sql);

        $contact_name = sanitizeInput($row['contact_name']);
        $contact_email = sanitizeInput($row['contact_email']);
        $ticket_prefix = sanitizeInput($row['ticket_prefix']);
        $ticket_number = intval($row['ticket_number']);
        $ticket_subject = sanitizeInput($row['ticket_subject']);
        $client_id = intval($row['ticket_client_id']);
        $ticket_assigned_to = intval($row['ticket_assigned_to']);
        $ticket_status = sanitizeInput($row['ticket_status_name']);
        $url_key = sanitizeInput($row['ticket_url_key']);

        // Sanitize Config vars from get_settings.php
        $config_ticket_from_name = sanitizeInput($config_ticket_from_name);
        $config_ticket_from_email = sanitizeInput($config_ticket_from_email);
        $config_base_url = sanitizeInput($config_base_url);

        // Get Company Info
        $sql = mysqli_query($mysqli, "SELECT company_name, company_phone FROM companies WHERE company_id = 1");
        $row = mysqli_fetch_array($sql);
        $company_name = sanitizeInput($row['company_name']);
        $company_phone = sanitizeInput(formatPhoneNumber($row['company_phone']));

        // Check email valid
        if (filter_var($contact_email, FILTER_VALIDATE_EMAIL)) {

            $data = [];

            $subject = "Ticket resolved - [$ticket_prefix$ticket_number] - $ticket_subject | (pending closure)";
            $body = "<i style=\'color: #808080\'>##- Please type your reply above this line -##</i><br><br>Hello $contact_name,<br><br>Your ticket regarding $ticket_subject has been marked as solved and is pending closure.<br><br>If your request/issue is resolved, you can simply ignore this email. If you need further assistance, please reply or <a href=\'https://$config_base_url/guest_view_ticket.php?ticket_id=$ticket_id&url_key=$url_key\'>re-open</a> to let us know! <br><br>Ticket: $ticket_prefix$ticket_number<br>Subject: $ticket_subject<br>Status: $ticket_status<br>Portal: <a href=\'https://$config_base_url/guest_view_ticket.php?ticket_id=$ticket_id&url_key=$url_key\'>View ticket</a><br><br>--<br>$company_name - Support<br>$config_ticket_from_email<br>$company_phone";

            // Email Ticket Contact
            // Queue Mail

            $data[] = [
                'from' => $config_ticket_from_email,
                'from_name' => $config_ticket_from_name,
                'recipient' => $contact_email,
                'recipient_name' => $contact_name,
                'subject' => $subject,
                'body' => $body
            ];

            // Also Email all the watchers
            $sql_watchers = mysqli_query($mysqli, "SELECT watcher_email FROM ticket_watchers WHERE watcher_ticket_id = $ticket_id");
            $body .= "<br><br>----------------------------------------<br>DO NOT REPLY - YOU ARE RECEIVING THIS EMAIL BECAUSE YOU ARE A WATCHER";
            while ($row = mysqli_fetch_array($sql_watchers)) {
                $watcher_email = sanitizeInput($row['watcher_email']);

                // Queue Mail
                $data[] = [
                    'from' => $config_ticket_from_email,
                    'from_name' => $config_ticket_from_name,
                    'recipient' => $watcher_email,
                    'recipient_name' => $watcher_email,
                    'subject' => $subject,
                    'body' => $body
                ];
            }
            addToMailQueue($mysqli, $data);
        }
    }
    //End Mail IF

    $_SESSION['alert_message'] = "Ticket resolved";
    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if (isset($_GET['close_ticket'])) {

    enforceUserPermission('module_support', 2);

    // CSRF Check
    validateCSRFToken($_GET['csrf_token']);

    $ticket_id = intval($_GET['close_ticket']);

    mysqli_query($mysqli, "UPDATE tickets SET ticket_status = 5, ticket_closed_at = NOW(), ticket_closed_by = $session_user_id WHERE ticket_id = $ticket_id") or die(mysqli_error($mysqli));

    mysqli_query($mysqli, "INSERT INTO ticket_replies SET ticket_reply = 'Ticket closed.', ticket_reply_type = 'Internal', ticket_reply_time_worked = '00:01:00', ticket_reply_by = $session_user_id, ticket_reply_ticket_id = $ticket_id");

    //Logging
    mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Ticket', log_action = 'Closed', log_description = 'Ticket ID $ticket_id Closed', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id, log_entity_id = $ticket_id");

    customAction('ticket_close', $ticket_id);

    // Client notification email
    if (!empty($config_smtp_host) && $config_ticket_client_general_notifications == 1) {

        // Get details
        $ticket_sql = mysqli_query($mysqli, "SELECT contact_name, contact_email, ticket_prefix, ticket_number, ticket_subject, ticket_url_key FROM tickets 
            LEFT JOIN clients ON ticket_client_id = client_id 
            LEFT JOIN contacts ON ticket_contact_id = contact_id
            WHERE ticket_id = $ticket_id
        ");
        $row = mysqli_fetch_array($ticket_sql);

        $contact_name = sanitizeInput($row['contact_name']);
        $contact_email = sanitizeInput($row['contact_email']);
        $ticket_prefix = sanitizeInput($row['ticket_prefix']);
        $ticket_number = intval($row['ticket_number']);
        $ticket_subject = sanitizeInput($row['ticket_subject']);
        $url_key = sanitizeInput($row['ticket_url_key']);

        // Sanitize Config vars from get_settings.php
        $config_ticket_from_name = sanitizeInput($config_ticket_from_name);
        $config_ticket_from_email = sanitizeInput($config_ticket_from_email);
        $config_base_url = sanitizeInput($config_base_url);

        // Get Company Info
        $sql = mysqli_query($mysqli, "SELECT company_name, company_phone FROM companies WHERE company_id = 1");
        $row = mysqli_fetch_array($sql);
        $company_name = sanitizeInput($row['company_name']);
        $company_phone = sanitizeInput(formatPhoneNumber($row['company_phone']));

        // Check email valid
        if (filter_var($contact_email, FILTER_VALIDATE_EMAIL)) {

            $data = [];

            $subject = "Ticket closed - [$ticket_prefix$ticket_number] - $ticket_subject | (do not reply)";
            //$body = "Hello $contact_name,<br><br>Your ticket regarding \"$ticket_subject\" has been closed. <br><br> We hope the request/issue was resolved to your satisfaction. If you need further assistance, please raise a new ticket using the below details. Please do not reply to this email. <br><br>Ticket: $ticket_prefix$ticket_number<br>Subject: $ticket_subject<br>Portal: https://$config_base_url/portal/ticket.php?id=$ticket_id<br><br>--<br>$company_name - Support<br>$config_ticket_from_email<br>$company_phone";
            $body = "Hello $contact_name,<br><br>Your ticket regarding \"$ticket_subject\" has been closed. <br><br> We hope the request/issue was resolved to your satisfaction, please provide your feedback <a href=\'https://$config_base_url/guest_view_ticket.php?ticket_id=$ticket_id&url_key=$url_key\'>here</a>. <br>If you need further assistance, please raise a new ticket using the below details. Please do not reply to this email. <br><br>Ticket: $ticket_prefix$ticket_number<br>Subject: $ticket_subject<br>Portal: https://$config_base_url/portal/ticket.php?id=$ticket_id<br><br>--<br>$company_name - Support<br>$config_ticket_from_email<br>$company_phone";

            // Email Ticket Contact
            // Queue Mail

            $data[] = [
                'from' => $config_ticket_from_email,
                'from_name' => $config_ticket_from_name,
                'recipient' => $contact_email,
                'recipient_name' => $contact_name,
                'subject' => $subject,
                'body' => $body
            ];

            // Also Email all the watchers
            $sql_watchers = mysqli_query($mysqli, "SELECT watcher_email FROM ticket_watchers WHERE watcher_ticket_id = $ticket_id");
            $body .= "<br><br>----------------------------------------<br>DO NOT REPLY - YOU ARE RECEIVING THIS EMAIL BECAUSE YOU ARE A WATCHER";
            while ($row = mysqli_fetch_array($sql_watchers)) {
                $watcher_email = sanitizeInput($row['watcher_email']);

                // Queue Mail
                $data[] = [
                    'from' => $config_ticket_from_email,
                    'from_name' => $config_ticket_from_name,
                    'recipient' => $watcher_email,
                    'recipient_name' => $watcher_email,
                    'subject' => $subject,
                    'body' => $body
                ];
            }
            addToMailQueue($mysqli, $data);
        }
    }
    //End Mail IF

    $_SESSION['alert_message'] = "Ticket Closed, this cannot not be reopened but you may start another one";
    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if (isset($_GET['reopen_ticket'])) {

    enforceUserPermission('module_support', 2);

    $ticket_id = intval($_GET['reopen_ticket']);

    mysqli_query($mysqli, "UPDATE tickets SET ticket_status = 2, ticket_resolved_at = NULL WHERE ticket_id = $ticket_id");

    //Logging
    mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Ticket', log_action = 'Reopened', log_description = 'Ticket ID $ticket_id reopened', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id, log_entity_id = $ticket_id");

    customAction('ticket_update', $ticket_id);

    $_SESSION['alert_message'] = "Ticket re-opened";
    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if (isset($_POST['add_invoice_from_ticket'])) {

    enforceUserPermission('module_support', 2);
    enforceUserPermission('module_sales', 2);

    $invoice_id = intval($_POST['invoice_id']);
    $ticket_id = intval($_POST['ticket_id']);
    $date = sanitizeInput($_POST['date']);
    $category = intval($_POST['category']);
    $scope = sanitizeInput($_POST['scope']);

    $sql = mysqli_query(
        $mysqli,
        "SELECT * FROM tickets
        LEFT JOIN clients ON ticket_client_id = client_id
        LEFT JOIN contacts ON ticket_contact_id = contact_id 
        LEFT JOIN assets ON ticket_asset_id = asset_id
        LEFT JOIN locations ON ticket_location_id = location_id
        WHERE ticket_id = $ticket_id"
    );

    $row = mysqli_fetch_array($sql);
    $client_id = intval($row['client_id']);
    $client_net_terms = intval($row['client_net_terms']);
    if ($client_net_terms == 0) {
        $client_net_terms = $config_default_net_terms;
    }

    $ticket_prefix = sanitizeInput($row['ticket_prefix']);
    $ticket_number = intval($row['ticket_number']);
    $ticket_category = sanitizeInput($row['ticket_category']);
    $ticket_subject = sanitizeInput($row['ticket_subject']);
    $ticket_created_at = sanitizeInput($row['ticket_created_at']);
    $ticket_updated_at = sanitizeInput($row['ticket_updated_at']);
    $ticket_closed_at = sanitizeInput($row['ticket_closed_at']);

    $contact_id = intval($row['contact_id']);
    $contact_name = sanitizeInput($row['contact_name']);
    $contact_email = sanitizeInput($row['contact_email']);

    $asset_id = intval($row['asset_id']);

    $location_name = sanitizeInput($row['location_name']);

    if ($invoice_id == 0) {

        //Get the last Invoice Number and add 1 for the new invoice number
        $invoice_number = $config_invoice_next_number;
        $new_config_invoice_next_number = $config_invoice_next_number + 1;
        mysqli_query($mysqli, "UPDATE settings SET config_invoice_next_number = $new_config_invoice_next_number WHERE company_id = 1");

        //Generate a unique URL key for clients to access
        $url_key = randomString(156);

        mysqli_query($mysqli, "INSERT INTO invoices SET invoice_prefix = '$config_invoice_prefix', invoice_number = $invoice_number, invoice_scope = '$scope', invoice_date = '$date', invoice_due = DATE_ADD('$date', INTERVAL $client_net_terms day), invoice_currency_code = '$session_company_currency', invoice_category_id = $category, invoice_status = 'Draft', invoice_url_key = '$url_key', invoice_client_id = $client_id");
        $invoice_id = mysqli_insert_id($mysqli);
    }

    //Add Item
    $item_name = sanitizeInput($_POST['item_name']);
    $item_description = sanitizeInput($_POST['item_description']);
    $qty = floatval($_POST['qty']);
    $price = floatval($_POST['price']);
    $tax_id = intval($_POST['tax_id']);

    $subtotal = $price * $qty;

    if ($tax_id > 0) {
        $sql = mysqli_query($mysqli, "SELECT * FROM taxes WHERE tax_id = $tax_id");
        $row = mysqli_fetch_array($sql);
        $tax_percent = floatval($row['tax_percent']);
        $tax_amount = $subtotal * $tax_percent / 100;
    } else {
        $tax_amount = 0;
    }

    $total = $subtotal + $tax_amount;

    mysqli_query($mysqli, "INSERT INTO invoice_items SET item_name = '$item_name', item_description = '$item_description', item_quantity = $qty, item_price = $price, item_subtotal = $subtotal, item_tax = $tax_amount, item_total = $total, item_order = 1, item_tax_id = $tax_id, item_invoice_id = $invoice_id");

    //Update Invoice Balances

    $sql = mysqli_query($mysqli, "SELECT * FROM invoices WHERE invoice_id = $invoice_id");
    $row = mysqli_fetch_array($sql);

    $new_invoice_amount = floatval($row['invoice_amount']) + $total;

    mysqli_query($mysqli, "UPDATE invoices SET invoice_amount = $new_invoice_amount WHERE invoice_id = $invoice_id");

    mysqli_query($mysqli, "INSERT INTO history SET history_status = 'Draft', history_description = 'Invoice created from Ticket $ticket_prefix$ticket_number', history_invoice_id = $invoice_id");

    // Add internal note to ticket, and link to invoice in database
    mysqli_query($mysqli, "INSERT INTO ticket_replies SET ticket_reply = 'Created invoice <a href=\"invoice.php?invoice_id=$invoice_id\">$config_invoice_prefix$invoice_number</a> for this ticket.', ticket_reply_type = 'Internal', ticket_reply_time_worked = '00:01:00', ticket_reply_by = $session_user_id, ticket_reply_ticket_id = $ticket_id");
    mysqli_query($mysqli, "UPDATE tickets SET ticket_invoice_id = $invoice_id WHERE ticket_id = $ticket_id");

    // Logging
    mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Invoice', log_action = 'Create', log_description = '$config_invoice_prefix$invoice_number created from Ticket $ticket_prefix$ticket_number', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Invoice created from ticket";

    header("Location: invoice.php?invoice_id=$invoice_id");
}

if (isset($_POST['export_client_tickets_csv'])) {

    enforceUserPermission('module_support', 2);

    $client_id = intval($_POST['client_id']);

    //get records from database
    $sql = mysqli_query($mysqli, "SELECT * FROM clients WHERE client_id = $client_id");
    $row = mysqli_fetch_array($sql);

    $client_name = $row['client_name'];

    $sql = mysqli_query(
        $mysqli,
        "SELECT * FROM tickets
        LEFT JOIN ticket_statuses ON ticket_status = ticket_status_id
        WHERE ticket_client_id = $client_id ORDER BY ticket_number ASC"
    );

    if ($sql->num_rows > 0) {
        $delimiter = ",";
        $filename = $client_name . "-Tickets-" . date('Y-m-d') . ".csv";

        //create a file pointer
        $f = fopen('php://memory', 'w');

        //set column headers
        $fields = array('Ticket Number', 'Priority', 'Status', 'Subject', 'Date Opened', 'Date Resolved', 'Date Closed');
        fputcsv($f, $fields, $delimiter);

        //output each row of the data, format line as csv and write to file pointer
        while ($row = $sql->fetch_assoc()) {
            $lineData = array($config_ticket_prefix . $row['ticket_number'], $row['ticket_priority'], $row['ticket_status_name'], $row['ticket_subject'], $row['ticket_created_at'], $row['ticket_resolved_at'], $row['ticket_closed_at']);
            fputcsv($f, $lineData, $delimiter);
        }

        //move back to beginning of file
        fseek($f, 0);

        //set headers to download file rather than displayed
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '";');

        //output all remaining data on a file pointer
        fpassthru($f);
    }
    exit;
}

if (isset($_POST['add_recurring_ticket'])) {

    enforceUserPermission('module_support', 2);

    require_once 'post/user/ticket_recurring_model.php';

    $start_date = sanitizeInput($_POST['start_date']);

    // If no contact is selected automatically choose the primary contact for the client
    if ($client_id > 0 && $contact_id == 0) {
        $sql = mysqli_query($mysqli, "SELECT contact_id FROM contacts WHERE contact_client_id = $client_id AND contact_primary = 1");
        $row = mysqli_fetch_array($sql);
        $contact_id = intval($row['contact_id']);
    }

    // Add recurring (scheduled) ticket
    mysqli_query($mysqli, "INSERT INTO scheduled_tickets SET scheduled_ticket_subject = '$subject', scheduled_ticket_details = '$details', scheduled_ticket_priority = '$priority', scheduled_ticket_frequency = '$frequency', scheduled_ticket_billable = $billable, scheduled_ticket_start_date = '$start_date', scheduled_ticket_next_run = '$start_date', scheduled_ticket_assigned_to = $assigned_to, scheduled_ticket_created_by = $session_user_id, scheduled_ticket_client_id = $client_id, scheduled_ticket_contact_id = $contact_id, scheduled_ticket_asset_id = $asset_id");

    $scheduled_ticket_id = mysqli_insert_id($mysqli);

    // Logging
    mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Recurring Ticket', log_action = 'Create', log_description = '$session_name created recurring ticket for $subject - $frequency', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $scheduled_ticket_id");

    $_SESSION['alert_message'] = "Recurring ticket <strong>$subject - $frequency</strong> created";

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if (isset($_POST['edit_recurring_ticket'])) {

    enforceUserPermission('module_support', 2);

    require_once 'post/user/ticket_recurring_model.php';

    $scheduled_ticket_id = intval($_POST['scheduled_ticket_id']);
    $next_run_date = sanitizeInput($_POST['next_date']);

    // If no contact is selected automatically choose the primary contact for the client
    if ($client_id > 0 && $contact_id == 0) {
        $sql = mysqli_query($mysqli, "SELECT contact_id FROM contacts WHERE contact_client_id = $client_id AND contact_primary = 1");
        $row = mysqli_fetch_array($sql);
        $contact_id = intval($row['contact_id']);
    }

    // Edit scheduled ticket
    mysqli_query($mysqli, "UPDATE scheduled_tickets SET scheduled_ticket_subject = '$subject', scheduled_ticket_details = '$details', scheduled_ticket_priority = '$priority', scheduled_ticket_frequency = '$frequency', scheduled_ticket_billable = $billable, scheduled_ticket_next_run = '$next_run_date', scheduled_ticket_assigned_to = $assigned_to, scheduled_ticket_asset_id = $asset_id, scheduled_ticket_contact_id = $contact_id WHERE scheduled_ticket_id = $scheduled_ticket_id");

    // Logging
    mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Recurring Ticket', log_action = 'Modify', log_description = '$session_name modified recurring ticket for $subject - $frequency', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $scheduled_ticket_id");

    $_SESSION['alert_message'] = "Recurring ticket <strong>$subject - $frequency</strong> updated";

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if (isset($_GET['delete_recurring_ticket'])) {

    enforceUserPermission('module_support', 3);

    $scheduled_ticket_id = intval($_GET['delete_recurring_ticket']);

    // Get Scheduled Ticket Subject Ticket Prefix, Number and Client ID for logging and alert message
    $sql = mysqli_query($mysqli, "SELECT * FROM scheduled_tickets WHERE scheduled_ticket_id = $scheduled_ticket_id");
    $row = mysqli_fetch_array($sql);
    $subject = sanitizeInput($row['scheduled_ticket_subject']);
    $frequency = sanitizeInput($row['scheduled_ticket_frequency']);

    $client_id = intval($row['scheduled_ticket_client_id']);

    // Delete
    mysqli_query($mysqli, "DELETE FROM scheduled_tickets WHERE scheduled_ticket_id = $scheduled_ticket_id");

    //Logging
    mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Scheduled Ticket', log_action = 'Delete', log_description = '$session_name deleted recurring ticket for $subject - $frequency', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $scheduled_ticket_id");

    $_SESSION['alert_message'] = "Recurring ticket <strong>$subject - $frequency</strong> deleted";

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if (isset($_POST['bulk_delete_scheduled_tickets']) || isset($_POST['bulk_delete_recurring_tickets'])) {

    enforceUserPermission('module_support', 3);
    validateCSRFToken($_POST['csrf_token']);

    $count = 0; // Default 0
    $scheduled_ticket_ids = $_POST['scheduled_ticket_ids']; // Get array of recurring scheduled tickets IDs to be deleted

    if (!empty($scheduled_ticket_ids)) {

        // Cycle through array and delete each recurring scheduled ticket
        foreach ($scheduled_ticket_ids as $scheduled_ticket_id) {

            $scheduled_ticket_id = intval($scheduled_ticket_id);
            mysqli_query($mysqli, "DELETE FROM scheduled_tickets WHERE scheduled_ticket_id = $scheduled_ticket_id");
            mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Scheduled Ticket', log_action = 'Delete', log_description = '$session_name deleted recurring ticket (bulk)', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id, log_entity_id = $scheduled_ticket_id");

            $count++;
        }

        // Logging
        mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Scheduled Ticket', log_action = 'Delete', log_description = '$session_name bulk deleted $count recurring tickets', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");

        $_SESSION['alert_message'] = "Deleted $count recurring ticket(s)";
    }

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if (isset($_POST['set_billable_status'])) {

    enforceUserPermission('module_support', 2);
    enforceUserPermission('module_sales', 2);

    $ticket_id = intval($_POST['ticket_id']);
    $billable_status = sanitizeInput($_POST['billable_status']);

    mysqli_query(
        $mysqli,
        "UPDATE tickets SET
        ticket_billable = '$billable_status'
        WHERE ticket_id = $ticket_id"
    );

    //Logging
    mysqli_query(
        $mysqli,
        "INSERT INTO logs SET
        log_type = 'Ticket',
        log_action = 'Modify',
        log_description = '$session_name modified ticket billable status',
        log_ip = '$session_ip',
        log_user_agent = '$session_user_agent',
        log_user_id = $session_user_id,
        log_entity_id = $ticket_id"
    );

    $_SESSION['alert_message'] = "Ticket billable status updated";

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if (isset($_POST['edit_ticket_schedule'])) {

    enforceUserPermission('module_support', 2);

    $ticket_id = intval($_POST['ticket_id']);
    $onsite = intval($_POST['onsite']);
    $schedule = sanitizeInput($_POST['scheduled_date_time']);
    $ticket_link = "ticket.php?ticket_id=$ticket_id";
    $full_ticket_url = "https://$config_base_url/portal/ticket.php?ticket_id=$ticket_id";
    $ticket_link_html = "<a href=\"$full_ticket_url\">$ticket_link</a>";

    mysqli_query(
        $mysqli,
        "UPDATE tickets SET
        ticket_schedule = '$schedule',
        ticket_onsite = $onsite,
        ticket_status = 3
        WHERE ticket_id = $ticket_id"
    );


    // Check for other conflicting scheduled items based on 2 hr window
    //TODO make this configurable
    $start = date('Y-m-d H:i:s', strtotime($schedule) - 7200);
    $end = date('Y-m-d H:i:s', strtotime($schedule) + 7200);
    $sql = mysqli_query($mysqli, "SELECT * FROM tickets WHERE ticket_schedule BETWEEN '$start' AND '$end' AND ticket_id != $ticket_id");
    if (mysqli_num_rows($sql) > 0) {
        $conflicting_tickets = [];
        while ($row = mysqli_fetch_array($sql)) {
            $conflicting_tickets[] = $row['ticket_id'] . " - " . $row['ticket_subject'] . " @ " . $row['ticket_schedule'];
        }
    }
    $sql = mysqli_query($mysqli, "SELECT * FROM tickets 
        LEFT JOIN clients ON ticket_client_id = client_id
        LEFT JOIN contacts ON ticket_contact_id = contact_id
        LEFT JOIN locations on contact_location_id = location_id
        LEFT JOIN users ON ticket_assigned_to = user_id
        WHERE ticket_id = $ticket_id
    ");

    $row = mysqli_fetch_array($sql);

    $client_id = intval($row['ticket_client_id']);
    $client_name = sanitizeInput($row['client_name']);
    $ticket_details = sanitizeInput($row['ticket_details']);
    $contact_name = sanitizeInput($row['contact_name']);
    $contact_email = sanitizeInput($row['contact_email']);
    $ticket_prefix = sanitizeInput($row['ticket_prefix']);
    $ticket_number = intval($row['ticket_number']);
    $ticket_subject = sanitizeInput($row['ticket_subject']);
    $user_name = sanitizeInput($row['user_name']);
    $user_email = sanitizeInput($row['user_email']);
    $cal_subject = $ticket_number . ": " . $client_name . " - " . $ticket_subject;
    $ticket_details_truncated = substr($ticket_details, 0, 100);
    $cal_description = $ticket_details_truncated . " - " . $full_ticket_url;
    $cal_location = sanitizeInput($row["location_address"]);
    $email_datetime = date('l, F j, Y \a\t g:ia', strtotime($schedule));

    // Sanitize Config Vars
    $config_ticket_from_email = sanitizeInput($config_ticket_from_email);
    $config_ticket_from_name = sanitizeInput($config_ticket_from_name);
    $session_company_name = sanitizeInput($session_company_name);


    /// Create iCal event
    $cal_str = createiCalStr($schedule, $cal_subject, $cal_description, $cal_location);

    // Notify the agent of the scheduled work
    $data[] = [
            'from' => $config_ticket_from_email,
            'from_name' => $config_ticket_from_name,
            'recipient' => $user_email,
            'recipient_name' => $user_name,
            'subject' => "Ticket Scheduled - [$ticket_prefix$ticket_number] - $ticket_subject",
            'body' => "Hello, " . $user_name . "<br><br>The ticket regarding $ticket_subject has been scheduled for $email_datetime.<br><br>--------------------------------<br><a href=\"https://$config_base_url/ticket.php?id=$ticket_id\">$ticket_link</a><br>--------------------------------<br><br>Please do not reply to this email. <br><br>Ticket: $ticket_prefix$ticket_number<br>Subject: $ticket_subject<br>Portal: https://$config_base_url/ticket.php?id=$ticket_id<br><br>~<br>$session_company_name<br>Support Department<br>$config_ticket_from_email",
            'cal_str' => $cal_str
        ];

    if ($config_ticket_client_general_notifications) {
        // Notify the ticket contact of the scheduled work
        $data[] = [
            'from' => $config_ticket_from_email,
            'from_name' => $config_ticket_from_name,
            'recipient' => $contact_email,
            'recipient_name' => $contact_name,
            'subject' => "Ticket Scheduled - [$ticket_prefix$ticket_number] - $ticket_subject",
            'body' => mysqli_escape_string($mysqli, "<div class='header'>
                                Hello, $contact_name
                            </div>
                            Your ticket regarding $ticket_subject has been scheduled for $email_datetime.
                            <br><br>
                            <a href='https://$config_base_url/portal/ticket.php?id=$ticket_id' class='link-button'>Access your ticket here</a>
                            <br><br>
                            Please do not reply to this email.
                            <br><br>
                            <strong>Ticket:</strong> $ticket_prefix$ticket_number<br>
                            <strong>Subject:</strong> $ticket_subject<br>
                            <br><br>
                            <div class='footer'>
                                ~<br>
                                $session_company_name<br>
                                Support Department<br>
                                $config_ticket_from_email<br>
                            </div>
                            <div class='no-reply'>
                                This is an automated message. Please do not reply directly to this email.
                            </div>"),
            'cal_str' => $cal_str
        ];

        // Notify the watchers of the scheduled work
        $sql_watchers = mysqli_query($mysqli, "SELECT watcher_email FROM ticket_watchers WHERE watcher_ticket_id = $ticket_id");

        while ($row = mysqli_fetch_array($sql_watchers)) {
            $watcher_email = sanitizeInput($row['watcher_email']);
            $data[] = [
                'from' => $config_ticket_from_email,
                'from_name' => $config_ticket_from_name,
                'recipient' => $watcher_email,
                'recipient_name' => $watcher_email,
                'subject' => "Ticket Scheduled - [$ticket_prefix$ticket_number] - $ticket_subject",
                'body' => mysqli_escape_string($mysqli, nullable_htmlentities("<div class='header'>
            Hello,
        </div>
        The ticket regarding $ticket_subject has been scheduled for $email_datetime.
        <br><br>
        <a href='https://$config_base_url/portal/ticket.php?id=$ticket_id' class='link-button'>$ticket_link</a>
        <br><br>
        Please do not reply to this email.
        <br><br>
        <strong>Ticket:</strong> $ticket_prefix$ticket_number<br>
        <strong>Subject:</strong> $ticket_subject<br>
        <strong>Portal:</strong> <a href='https://$config_base_url/portal/ticket.php?id=$ticket_id'>Access the ticket here</a>
        <br><br>
        <div class='footer'>
            ~<br>
            $session_company_name<br>
            Support Department<br>
            $config_ticket_from_email<br>
        </div>
        <div class='no-reply'>
            This is an automated message. Please do not reply directly to this email.
        </div>")),
                'cal_str' => $cal_str
            ];
        }
    }

    // Send
    $response = addToMailQueue($mysqli, $data);

    // Update ticket reply
    $ticket_reply_note = "Ticket scheduled for $email_datetime " . (boolval($onsite) ? '(onsite).' : '(remote).');
    mysqli_query($mysqli, "INSERT INTO ticket_replies SET ticket_reply = '$ticket_reply_note', ticket_reply_type = 'Internal', ticket_reply_time_worked = '00:01:00', ticket_reply_by = $session_user_id, ticket_reply_ticket_id = $ticket_id");

    //Logging
    mysqli_query(
        $mysqli,
        "INSERT INTO logs SET
            log_type = 'Ticket',
            log_action = 'Modify',
            log_description = '$session_name modified ticket schedule',
            log_ip = '$session_ip',
            log_user_agent = '$session_user_agent',
            log_user_id = $session_user_id,
            log_entity_id = $ticket_id"
    );

    customAction('ticket_schedule', $ticket_id);


    if (empty($conflicting_tickets)) {
        $_SESSION['alert_message'] = "Ticket scheduled for $email_datetime";
        header("Location: " . $_SERVER["HTTP_REFERER"]);
    } else {
        $_SESSION['alert_type'] = "error";
        $_SESSION['alert_message'] = "Ticket scheduled for $email_datetime. Yet there are conflicting tickets scheduled for the same time: <br>" . implode(", <br>", $conflicting_tickets);
        header("Location: calendar_events.php");
    }

}

if (isset($_GET['cancel_ticket_schedule'])) {

    enforceUserPermission('module_support', 2);

    $ticket_id = intval($_GET['cancel_ticket_schedule']);

    $sql = mysqli_query($mysqli, "SELECT * FROM tickets WHERE ticket_id = $ticket_id");
    $row = mysqli_fetch_array($sql);

    $client_id = intval($row['ticket_client_id']);
    $ticket_prefix = sanitizeInput($row['ticket_prefix']);
    $ticket_number = intval($row['ticket_number']);
    $ticket_subject = sanitizeInput($row['ticket_subject']);
    $ticket_schedule = sanitizeInput($row['ticket_schedule']);
    $ticket_cal_str = sanitizeInput($row['ticket_cal_str']);

    mysqli_query($mysqli, "UPDATE tickets SET ticket_schedule = NULL, ticket_status = 2 WHERE ticket_id = $ticket_id");

    // Sanitize Config Vars
    $config_ticket_from_email = sanitizeInput($config_ticket_from_email);
    $config_ticket_from_name = sanitizeInput($config_ticket_from_name);
    $session_company_name = sanitizeInput($session_company_name);

    //Create iCal event
    $cal_str = createiCalStrCancel($ticket_cal_str);

    //Send emails

    $sql = mysqli_query($mysqli, "SELECT * FROM tickets 
        LEFT JOIN clients ON ticket_client_id = client_id
        LEFT JOIN contacts ON ticket_contact_id = contact_id
        LEFT JOIN locations on contact_location_id = location_id
        LEFT JOIN users ON ticket_assigned_to = user_id
        WHERE ticket_id = $ticket_id
    ");
    $row = mysqli_fetch_array($sql);

    $client_id = intval($row['ticket_client_id']);
    $client_name = sanitizeInput($row['client_name']);
    $ticket_details = sanitizeInput($row['ticket_details']);
    $contact_name = sanitizeInput($row['contact_name']);
    $contact_email = sanitizeInput($row['contact_email']);
    $ticket_prefix = sanitizeInput($row['ticket_prefix']);
    $ticket_number = intval($row['ticket_number']);
    $ticket_subject = sanitizeInput($row['ticket_subject']);
    $user_name = sanitizeInput($row['user_name']);
    $user_email = sanitizeInput($row['user_email']);

    // Notify the agent of the cancellation
    $data[] = [
            // User Email
            'from' => $config_ticket_from_email,
            'from_name' => $config_ticket_from_name,
            'recipient' => $user_email,
            'recipient_name' => $user_name,
            'subject' => "Ticket Schedule Cancelled - [$ticket_prefix$ticket_number] - $ticket_subject",
            'body' => "Hello, " . $user_name . "<br><br>Scheduled work for the ticket regarding $ticket_subject has been cancelled.<br><br>--------------------------------<br><a href=\"https://$config_base_url/ticket.php?id=$ticket_id\">$ticket_link</a><br>--------------------------------<br><br>Please do not reply to this email. <br><br>Ticket: $ticket_prefix$ticket_number<br>Subject: $ticket_subject<br>Portal: https://$config_base_url/ticket.php?id=$ticket_id<br><br>~<br>$session_company_name<br>Support Department<br>$config_ticket_from_email",
            'cal_str' => $cal_str
        ];

    if ($config_ticket_client_general_notifications) {
        // Notify the ticket contact of the cancellation
        $data[] = [
            'from' => $config_ticket_from_email,
            'from_name' => $config_ticket_from_name,
            'recipient' => $contact_email,
            'recipient_name' => $contact_name,
            'subject' => "Ticket Schedule Cancelled - [$ticket_prefix$ticket_number] - $ticket_subject",
            'body' => mysqli_escape_string($mysqli, "<div class='header'>
                                Hello, $contact_name
                            </div>
                            Scheduled work for your ticket regarding $ticket_subject has been cancelled.
                            <br><br>
                            <a href='https://$config_base_url/portal/ticket.php?id=$ticket_id' class='link-button'>Access your ticket here</a>
                            <br><br>
                            Please do not reply to this email.
                            <br><br>
                            <strong>Ticket:</strong> $ticket_prefix$ticket_number<br>
                            <strong>Subject:</strong> $ticket_subject<br>
                            <br><br>
                            <div class='footer'>
                                ~<br>
                                $session_company_name<br>
                                Support Department<br>
                                $config_ticket_from_email<br>
                            </div>
                            <div class='no-reply'>
                                This is an automated message. Please do not reply directly to this email.
                            </div>"),
            'cal_str' => $cal_str
        ];

        // Notify the watchers of the cancellation
        $sql_watchers = mysqli_query($mysqli, "SELECT watcher_email FROM ticket_watchers WHERE watcher_ticket_id = $ticket_id");
        while ($row = mysqli_fetch_assoc($sql_watchers)) {
            $watcher_email = sanitizeInput($row['watcher_email']);
            $data[] = [
                'from' => $config_ticket_from_email,
                'from_name' => $config_ticket_from_name,
                'recipient' => $watcher_email,
                'recipient_name' => $watcher_email,
                'subject' => "Ticket Schedule Cancelled - [$ticket_prefix$ticket_number] - $ticket_subject",
                'body' => mysqli_escape_string($mysqli, nullable_htmlentities("<div class='header'>
            Hello,
        </div>
        Scheduled work for the ticket regarding $ticket_subject has been cancelled.
        <br><br>
        <a href='https://$config_base_url/portal/ticket.php?id=$ticket_id' class='link-button'>$ticket_link</a>
        <br><br>
        Please do not reply to this email.
        <br><br>
        <strong>Ticket:</strong> $ticket_prefix$ticket_number<br>
        <strong>Subject:</strong> $ticket_subject<br>
        <strong>Portal:</strong> <a href='https://$config_base_url/portal/ticket.php?id=$ticket_id'>Access the ticket here</a>
        <br><br>
        <div class='footer'>
            ~<br>
            $session_company_name<br>
            Support Department<br>
            $config_ticket_from_email<br>
        </div>
        <div class='no-reply'>
            This is an automated message. Please do not reply directly to this email.
        </div>")),
                'cal_str' => $cal_str
            ];
        }
    }

    // Send email(s)
    addToMailQueue($mysqli, $data);

    // Update ticket reply
    $ticket_reply_note = "Ticket schedule cancelled.";
    mysqli_query($mysqli, "INSERT INTO ticket_replies SET ticket_reply = '$ticket_reply_note', ticket_reply_type = 'Internal', ticket_reply_time_worked = '00:01:00', ticket_reply_by = $session_user_id, ticket_reply_ticket_id = $ticket_id");

    //Logging
    mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Ticket', log_action = 'Modify', log_description = '$session_name cancelled ticket schedule', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id, log_entity_id = $ticket_id");

    customAction('ticket_unschedule', $ticket_id);

    $_SESSION['alert_message'] = "Ticket schedule cancelled";

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}
