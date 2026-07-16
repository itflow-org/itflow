<?php

/*
 * ITFlow - GET/POST request handler for client tickets
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['add_ticket'])) {

    validateCSRFToken($_POST['csrf_token']);

    enforceUserPermission('module_support', 2);

    $client_id = intval($_POST['client_id']);
    $assigned_to = intval($_POST['assigned_to']);
    if ($assigned_to == 0) {
        $ticket_status = 1;
    } else {
        $ticket_status = 2;
    }
    $contact_id = intval($_POST['contact_id']);
    $category_id = intval($_POST['category_id']);
    $subject = escapeSql($_POST['subject']);
    $priority = escapeSql($_POST['priority']);
    $details = mysqli_real_escape_string($mysqli, $_POST['details']);
    $vendor_ticket_number = escapeSql($_POST['vendor_ticket_number']);
    $vendor_id = intval($_POST['vendor_id']);
    $asset_id = intval($_POST['asset_id']);
    $location_id = intval($_POST['location_id']);
    $project_id = intval($_POST['project_id']);
    $use_primary_contact = intval($_POST['use_primary_contact'] ?? 0);
    $ticket_template_id = intval($_POST['ticket_template_id']);
    $billable = intval($_POST['billable'] ?? 0);
    // Validate/clean due field
    $dueInput = $_POST['due'] ?? null;
    if ($dueInput === null || trim($dueInput) === '') {
        $due = 'NULL'; // prepare as SQL-safe string
    } else {
        $d = DateTime::createFromFormat('Y-m-d\TH:i', $dueInput); // for <input type="datetime-local">
        if ($d !== false) {
            $due = "'" . $d->format('Y-m-d H:i:s') . "'"; // wrap in quotes for SQL
        } else {
            $due = 'NULL'; // fallback if invalid
        }
    }

    enforceClientAccess();

    // Add the primary contact as the ticket contact if "Use primary contact" is checked
    if ($use_primary_contact == 1) {
        $sql = mysqli_query($mysqli, "SELECT contact_id FROM contacts WHERE contact_client_id = $client_id AND contact_primary = 1");
        $row = mysqli_fetch_assoc($sql);
        $contact_id = intval($row['contact_id']);
    }

    // Atomically increment and get the new ticket number
    mysqli_query($mysqli, "
        UPDATE settings
        SET
            config_ticket_next_number = LAST_INSERT_ID(config_ticket_next_number),
            config_ticket_next_number = config_ticket_next_number + 1
        WHERE company_id = 1
    ");

    $ticket_number = mysqli_insert_id($mysqli);

    // Sanitize Config Vars from get_settings.php and Session Vars from check_login.php
    $config_ticket_prefix = escapeSql($config_ticket_prefix);
    $config_ticket_from_name = escapeSql($config_ticket_from_name);
    $config_ticket_from_email = escapeSql($config_ticket_from_email);
    $config_base_url = escapeSql($config_base_url);

    //Generate a unique URL key for clients to access
    $url_key = randomString(32);

    mysqli_query($mysqli, "INSERT INTO tickets SET ticket_prefix = '$config_ticket_prefix', ticket_number = $ticket_number, ticket_source = 'Agent', ticket_category = $category_id, ticket_subject = '$subject', ticket_details = '$details', ticket_priority = '$priority', ticket_billable = '$billable', ticket_status = '$ticket_status', ticket_vendor_ticket_number = '$vendor_ticket_number', ticket_vendor_id = $vendor_id, ticket_location_id = $location_id, ticket_asset_id = $asset_id, ticket_created_by = $session_user_id, ticket_assigned_to = $assigned_to, ticket_contact_id = $contact_id, ticket_url_key = '$url_key', ticket_due_at = $due, ticket_client_id = $client_id, ticket_invoice_id = 0, ticket_project_id = $project_id");

    $ticket_id = mysqli_insert_id($mysqli);

    // Add Tasks from Template if Template was selected
    if($ticket_template_id) {
        // Get Associated Tasks from the ticket template
        $sql_task_templates = mysqli_query($mysqli, "SELECT * FROM task_templates WHERE task_template_ticket_template_id = $ticket_template_id");

        if (mysqli_num_rows($sql_task_templates) > 0) {
            while ($row = mysqli_fetch_assoc($sql_task_templates)) {
                $task_order = intval($row['task_template_order']);
                $task_name = escapeSql($row['task_template_name']);
                $task_completion_estimate = intval($row['task_template_completion_estimate']);

                mysqli_query($mysqli,"INSERT INTO tasks SET task_name = '$task_name', task_order = $task_order, task_completion_estimate = $task_completion_estimate, task_ticket_id = $ticket_id");
            }
        }
    }

    // Add Watchers
    if (isset($_POST['watchers'])) {
        foreach ($_POST['watchers'] as $watcher) {
            $watcher_email = escapeSql($watcher);
            mysqli_query($mysqli, "INSERT INTO ticket_watchers SET watcher_email = '$watcher_email', watcher_ticket_id = $ticket_id");
        }
    }

    // Add Additional Assets
    if (isset($_POST['additional_assets'])) {
        foreach ($_POST['additional_assets'] as $additional_asset) {
            $additional_asset_id = intval($additional_asset);
            mysqli_query($mysqli, "INSERT INTO ticket_assets SET ticket_id = $ticket_id, asset_id = $additional_asset_id");
        }
    }

    // E-mail client
    if ((!empty($config_smtp_provider) || !empty($config_smtp_provider)) && $config_ticket_client_general_notifications == 1) {

        // Get contact/ticket details
        $sql = mysqli_query($mysqli, "SELECT contact_name, contact_email, ticket_prefix, ticket_number, ticket_category, ticket_subject, ticket_details, ticket_priority, ticket_status, ticket_created_by, ticket_assigned_to, ticket_client_id FROM tickets
              LEFT JOIN clients ON ticket_client_id = client_id
              LEFT JOIN contacts ON ticket_contact_id = contact_id
              WHERE ticket_id = $ticket_id");
        $row = mysqli_fetch_assoc($sql);

        $contact_name = escapeSql($row['contact_name']);
        $contact_email = escapeSql($row['contact_email']);
        $ticket_prefix = escapeSql($row['ticket_prefix']);
        $ticket_number = intval($row['ticket_number']);
        $ticket_category = escapeSql($row['ticket_category']);
        $ticket_subject = escapeSql($row['ticket_subject']);
        $ticket_details = mysqli_escape_string($mysqli, $row['ticket_details']);
        $ticket_priority = escapeSql($row['ticket_priority']);
        $ticket_status = escapeSql($row['ticket_status']);
        $ticket_status_name = escapeSql(getTicketStatusName($row['ticket_status']));
        $client_id = intval($row['ticket_client_id']);
        $ticket_created_by = intval($row['ticket_created_by']);
        $ticket_assigned_to = intval($row['ticket_assigned_to']);

        // Get Company Phone Number
        $sql = mysqli_query($mysqli, "SELECT company_name, company_phone, company_phone_country_code FROM companies WHERE company_id = 1");
        $row = mysqli_fetch_assoc($sql);
        $company_name = escapeSql($row['company_name']);
        $company_phone = escapeSql(formatPhoneNumber($row['company_phone'], $row['company_phone_country_code']));

        // EMAILING

        $subject = "Ticket Created [$ticket_prefix$ticket_number] - $ticket_subject";
        $body = "<i style=\'color: #808080\'>##- Please type your reply above this line -##</i><br><br>Hello $contact_name,<br><br>A ticket regarding \"$ticket_subject\" has been created for you.<br><br>--------------------------------<br>$ticket_details--------------------------------<br><br>Ticket: $ticket_prefix$ticket_number<br>Subject: $ticket_subject<br>Status: Open<br>Portal: <a href=\'https://$config_base_url/guest/guest_view_ticket.php?ticket_id=$ticket_id&url_key=$url_key\'>View ticket</a><br><br>--<br>$company_name - Support<br>$config_ticket_from_email<br>$company_phone";

        // Verify contact email is valid
        if (filter_var($contact_email, FILTER_VALIDATE_EMAIL)) {


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
        }

        // Also Email all the watchers
        $sql_watchers = mysqli_query($mysqli, "SELECT watcher_email FROM ticket_watchers WHERE watcher_ticket_id = $ticket_id");
        $body .= "<br><br>----------------------------------------<br>YOU HAVE BEEN ADDED AS A COLLABORATOR FOR THIS TICKET";
        while ($row = mysqli_fetch_assoc($sql_watchers)) {
            $watcher_email = escapeSql($row['watcher_email']);

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
        addToMailQueue($data);

        // END EMAILING

    }

    // Custom action/notif handler
    triggerCustomAction('ticket_create', $ticket_id);

    logAudit("Ticket", "Create", "$session_name created ticket $config_ticket_prefix$ticket_number - $ticket_subject", $client_id, $ticket_id);

    flashAlert("Ticket <strong>$config_ticket_prefix$ticket_number</strong> created");

    redirect("ticket.php?client_id=$client_id&ticket_id=$ticket_id");

}

if (isset($_POST['edit_ticket'])) {

    validateCSRFToken($_POST['csrf_token']);

    enforceUserPermission('module_support', 2);

    $ticket_id = intval($_POST['ticket_id']);
    $contact_id = intval($_POST['contact_id']);
    $assigned_to = intval($_POST['assigned_to']);
    $notify = intval($_POST['contact_notify'] ?? 0);
    $category_id = intval($_POST['category_id']);
    $ticket_subject = escapeSql($_POST['subject']);
    $billable = intval($_POST['billable'] ?? 0);
    $ticket_priority = escapeSql($_POST['priority']);
    $details = mysqli_real_escape_string($mysqli, $_POST['details']);
    $vendor_ticket_number = escapeSql($_POST['vendor_ticket_number']);
    $vendor_id = intval($_POST['vendor_id']);
    $asset_id = intval($_POST['asset_id']);
    $location_id = intval($_POST['location_id']);
    $project_id = intval($_POST['project_id']);
    // Validate/clean due field
    $dueInput = $_POST['due'] ?? null;
    if ($dueInput === null || trim($dueInput) === '') {
        $due = 'NULL'; // prepare as SQL-safe string
    } else {
        $d = DateTime::createFromFormat('Y-m-d\TH:i', $dueInput); // for <input type="datetime-local">
        if ($d !== false) {
            $due = "'" . $d->format('Y-m-d H:i:s') . "'"; // wrap in quotes for SQL
        } else {
            $due = 'NULL'; // fallback if invalid
        }
    }

    $client_id = intval(getFieldById('tickets', $ticket_id, 'ticket_client_id'));

    // Don't Enforce Client Access if Ticket doesn't have an assigned client
    if ($client_id) {
        enforceClientAccess();
    }

    mysqli_query($mysqli, "UPDATE tickets SET ticket_category = $category_id, ticket_subject = '$ticket_subject', ticket_priority = '$ticket_priority', ticket_billable = $billable, ticket_details = '$details', ticket_due_at = $due, ticket_vendor_ticket_number = '$vendor_ticket_number', ticket_contact_id = $contact_id, ticket_assigned_to = $assigned_to, ticket_vendor_id = $vendor_id, ticket_location_id = $location_id, ticket_asset_id = $asset_id, ticket_project_id = $project_id WHERE ticket_id = $ticket_id");

    // Add Additional Assets
    if (isset($_POST['additional_assets'])) {
        mysqli_query($mysqli, "DELETE FROM ticket_assets WHERE ticket_id = $ticket_id");
        foreach ($_POST['additional_assets'] as $additional_asset) {
            $additional_asset_id = intval($additional_asset);
            mysqli_query($mysqli, "INSERT INTO ticket_assets SET ticket_id = $ticket_id, asset_id = $additional_asset_id");
        }
    } else {
        // If no additional assets are provided, delete them all
        // This handles cases where the assets input might be cleared or not set at all.
        mysqli_query($mysqli, "DELETE FROM ticket_assets WHERE ticket_id = $ticket_id");
    }

    // Get contact/ticket details after update for logging / email purposes
    $sql = mysqli_query($mysqli, "SELECT contact_name, contact_email, ticket_prefix, ticket_number, ticket_category, ticket_details, ticket_status_name, ticket_created_by, ticket_assigned_to, ticket_url_key, ticket_client_id FROM tickets
        LEFT JOIN clients ON ticket_client_id = client_id
        LEFT JOIN contacts ON ticket_contact_id = contact_id
        LEFT JOIN ticket_statuses ON ticket_status = ticket_status_id
        WHERE ticket_id = $ticket_id
        AND ticket_closed_at IS NULL");
    $row = mysqli_fetch_assoc($sql);

    $contact_name = escapeSql($row['contact_name']);
    $contact_email = escapeSql($row['contact_email']);
    $ticket_prefix = escapeSql($row['ticket_prefix']);
    $ticket_number = intval($row['ticket_number']);
    $ticket_category = escapeSql($row['ticket_category']);
    $ticket_details = mysqli_escape_string($mysqli, $row['ticket_details']);
    $ticket_status = escapeSql($row['ticket_status_name']);
    $ticket_created_by = intval($row['ticket_created_by']);
    $ticket_assigned_to = intval($row['ticket_assigned_to']);
    $url_key = escapeSql($row['ticket_url_key']);
    $client_id = intval($row['ticket_client_id']);

    // Notify new contact if selected
    if ($notify && (!empty($config_smtp_provider) || !empty($config_smtp_provider))) {

        // Get Company Name Phone Number and Sanitize for Email Sending
        $sql = mysqli_query($mysqli, "SELECT company_name, company_phone, company_phone_country_code FROM companies WHERE company_id = 1");
        $row = mysqli_fetch_assoc($sql);
        $company_name = escapeSql($row['company_name']);
        $company_phone = escapeSql(formatPhoneNumber($row['company_phone'], $row['company_phone_country_code']));

        // Email content
        $data = []; // Queue array

        $subject = "Ticket Created - [$ticket_prefix$ticket_number] - $ticket_subject";
        $body = "<i style=\'color: #808080\'>##- Please type your reply above this line -##</i><br><br>Hello $contact_name,<br><br>A ticket regarding \"$ticket_subject\" has been created for you.<br><br>--------------------------------<br>$ticket_details--------------------------------<br><br>Ticket: $ticket_prefix$ticket_number<br>Subject: $ticket_subject<br>Status: $ticket_status<br>Portal: <a href=\'https://$config_base_url/guest/guest_view_ticket.php?ticket_id=$ticket_id&url_key=$url_key\'>View ticket</a><br><br>--<br>$company_name - Support<br>$config_ticket_from_email<br>$company_phone";


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

        addToMailQueue($data);
    }

    // Custom action/notif handler
    triggerCustomAction('ticket_update', $ticket_id);

    logAudit("Ticket", "Edit", "$session_name edited ticket $ticket_prefix$ticket_number", $client_id, $ticket_id);

    flashAlert("Ticket <strong>$ticket_prefix$ticket_number</strong> updated");

    redirect();

}

if (isset($_POST['edit_ticket_priority'])) {

    validateCSRFToken($_POST['csrf_token']);

    enforceUserPermission('module_support', 2);

    $ticket_id = intval($_POST['ticket_id']);
    $priority = escapeSql($_POST['priority']);

    // Get ticket details before updating
    $sql = mysqli_query($mysqli, "SELECT
        ticket_prefix, ticket_number, ticket_priority, ticket_status_name, ticket_client_id
        FROM tickets
        LEFT JOIN ticket_statuses ON ticket_status = ticket_status_id
        WHERE ticket_id = $ticket_id"
    );
    $row = mysqli_fetch_assoc($sql);
    $ticket_prefix = escapeSql($row['ticket_prefix']);
    $ticket_number = intval($row['ticket_number']);
    $original_priority = escapeSql($row['ticket_priority']);
    $ticket_status = escapeSql($row['ticket_status_name']);
    $client_id = intval($row['ticket_client_id']);

    // Don't Enforce Client Access if Ticket doesn't have an assigned client
    if ($client_id) {
        enforceClientAccess();
    }

    mysqli_query($mysqli, "UPDATE tickets SET ticket_priority = '$priority' WHERE ticket_id = $ticket_id");

    // Update Ticket History
    mysqli_query($mysqli, "INSERT INTO ticket_history SET ticket_history_status = '$ticket_status', ticket_history_description = '$session_name changed priority from $original_priority to $priority', ticket_history_ticket_id = $ticket_id");

    logAudit("Ticket", "Edit", "$session_name changed priority from $original_priority to $priority for ticket $ticket_prefix$ticket_number", $client_id, $ticket_id);

    triggerCustomAction('ticket_update', $ticket_id);

    flashAlert("Priority updated from <strong>$original_priority</strong> to <strong>$priority</strong>");

    redirect();

}

if (isset($_POST['edit_ticket_contact'])) {

    validateCSRFToken($_POST['csrf_token']);

    enforceUserPermission('module_support', 2);

    $ticket_id = intval($_POST['ticket_id']);
    $contact_id = intval($_POST['contact']);
    $notify = intval($_POST['contact_notify']) ?? 0;

    // Get Original contact, and ticket details
    $sql = mysqli_query($mysqli, "SELECT
        contact_name, ticket_prefix, ticket_number, ticket_status_name, ticket_subject, ticket_details, ticket_url_key, ticket_client_id
        FROM tickets
        LEFT JOIN contacts ON ticket_contact_id = contact_id
        LEFT JOIN ticket_statuses ON ticket_status = ticket_status_id
        WHERE ticket_id = $ticket_id"
    );
    $row = mysqli_fetch_assoc($sql);

    // Original contact
    $original_contact_name = !empty($row['contact_name']) ? escapeSql($row['contact_name']) : 'No one';

    // Ticket details
    $ticket_prefix = escapeSql($row['ticket_prefix']);
    $ticket_number = intval($row['ticket_number']);
    $ticket_status = escapeSql($row['ticket_status_name']);
    $ticket_subject = escapeSql($row['ticket_subject']);
    $ticket_details = mysqli_escape_string($mysqli, $row['ticket_details']);
    $url_key = escapeSql($row['ticket_url_key']);
    $client_id = intval($row['ticket_client_id']);

    // Don't Enforce Client Access if Ticket doesn't have an assigned client
    if ($client_id) {
        enforceClientAccess();
    }

    // Update the contact
    mysqli_query($mysqli, "UPDATE tickets SET ticket_contact_id = $contact_id WHERE ticket_id = $ticket_id");

    // Get New contact details
    $sql = mysqli_query($mysqli, "SELECT contact_name, contact_email FROM contacts WHERE contact_id = $contact_id");
    $row = mysqli_fetch_assoc($sql);

    $contact_name = !empty($row['contact_name']) ? escapeSql($row['contact_name']) : 'No one';
    $contact_email = escapeSql($row['contact_email']);

    // Notify new contact (if selected, valid & configured)
    if ($notify && filter_var($contact_email, FILTER_VALIDATE_EMAIL) && (!empty($config_smtp_provider) || !empty($config_smtp_provider))) {

        // Get Company Phone Number
        $sql = mysqli_query($mysqli, "SELECT company_name, company_phone, company_phone_country_code FROM companies WHERE company_id = 1");
        $row = mysqli_fetch_assoc($sql);
        $company_name = escapeSql($row['company_name']);
        $company_phone = escapeSql(formatPhoneNumber($row['company_phone'], $row['company_phone_country_code']));

        $config_ticket_from_email = escapeSql($config_ticket_from_email);
        $config_ticket_from_name = escapeSql($config_ticket_from_name);

        // Email content
        $data = []; // Queue array

        $subject = "Ticket Created - [$ticket_prefix$ticket_number] - $ticket_subject";
        $body = "<i style=\'color: #808080\'>##- Please type your reply above this line -##</i><br><br>Hello $contact_name,<br><br>A ticket regarding \"$ticket_subject\" has been created for you.<br><br>--------------------------------<br>$ticket_details--------------------------------<br><br>Ticket: $ticket_prefix$ticket_number<br>Subject: $ticket_subject<br>Status: $ticket_status<br>Portal: <a href=\'https://$config_base_url/guest/guest_view_ticket.php?ticket_id=$ticket_id&url_key=$url_key\'>View ticket</a><br><br>--<br>$company_name - Support<br>$config_ticket_from_email<br>$company_phone";

        $data[] = [
            'from' => $config_ticket_from_email,
            'from_name' => $config_ticket_from_name,
            'recipient' => $contact_email,
            'recipient_name' => $contact_name,
            'subject' => $subject,
            'body' => $body
        ];

        addToMailQueue($data);
    }

    // Custom action/notif handler
    triggerCustomAction('ticket_update', $ticket_id);

    // Update Ticket History
    mysqli_query($mysqli, "INSERT INTO ticket_history SET ticket_history_status = '$ticket_status', ticket_history_description = '$session_name changed the contact from $original_contact_name to $contact_name', ticket_history_ticket_id = $ticket_id");

    logAudit("Ticket", "Edit", "$session_name changed the contact from $original_contact_name to $contact_name for ticket $ticket_prefix$ticket_number", $client_id, $ticket_id);

    flashAlert("Contact changed from <strong>$original_contact_name</strong> to <strong>$contact_name</strong>");

    redirect();

}

if (isset($_POST['edit_ticket_project'])) {

    validateCSRFToken($_POST['csrf_token']);

    enforceUserPermission('module_support', 2);

    $ticket_id = intval($_POST['ticket_id']);
    $project_id = intval($_POST['project']);

    $project_name = escapeSql(getFieldById('projects', $project_id, 'project_name'));
    $client_id = intval(getFieldById('tickets', $ticket_id, 'ticket_client_id'));
    $ticket_prefix = escapeSql(getFieldById('tickets', $ticket_id, 'ticket_prefix'));
    $ticket_number = escapeSql(getFieldById('tickets', $ticket_id, 'ticket_number'));

    // Don't Enforce Client Access if Ticket doesn't have an assigned client
    if ($client_id) {
        enforceClientAccess();
    }

    mysqli_query($mysqli, "UPDATE tickets SET ticket_project_id = $project_id WHERE ticket_id = $ticket_id");

    logAudit("Ticket", "Edit", "$session_name set ticket $ticket_prefix$ticket_number project to $project_name", $client_id, $ticket_id);

    flashAlert("Project changed to <strong>$project_name</strong> for Ticket <strong>$ticket_prefix$ticket_number</strong>");

    redirect();

}

if (isset($_POST['add_ticket_watcher'])) {

    validateCSRFToken($_POST['csrf_token']);

    enforceUserPermission('module_support', 2);

    $ticket_id = intval($_POST['ticket_id']);
    $watcher_emails = preg_split("/,| |;/", $_POST['watcher_email']); // Split on comma, semicolon or space, we sanitize later
    $notify = intval($_POST['watcher_notify'] ?? 0);

    // Get contact/ticket details
    $sql = mysqli_query($mysqli, "SELECT ticket_prefix, ticket_number, ticket_category, ticket_subject, ticket_details, ticket_priority, ticket_status_name, ticket_url_key, ticket_created_by, ticket_assigned_to, ticket_client_id FROM tickets
    LEFT JOIN clients ON ticket_client_id = client_id
    LEFT JOIN contacts ON ticket_contact_id = contact_id
    LEFT JOIN ticket_statuses ON ticket_status = ticket_status_id
    WHERE ticket_id = $ticket_id
    AND ticket_closed_at IS NULL");
    $row = mysqli_fetch_assoc($sql);

    $ticket_prefix = escapeSql($row['ticket_prefix']);
    $ticket_number = intval($row['ticket_number']);
    $ticket_category = escapeSql($row['ticket_category']);
    $ticket_subject = escapeSql($row['ticket_subject']);
    $ticket_details = mysqli_escape_string($mysqli, $row['ticket_details']);
    $ticket_priority = escapeSql($row['ticket_priority']);
    $ticket_status = escapeSql($row['ticket_status_name']);
    $url_key = escapeSql($row['ticket_url_key']);
    $client_id = intval($row['ticket_client_id']);
    $ticket_created_by = intval($row['ticket_created_by']);
    $ticket_assigned_to = intval($row['ticket_assigned_to']);

    // Don't Enforce Client Access if Ticket doesn't have an assigned client
    if ($client_id) {
        enforceClientAccess();
    }

    // Get Company Phone Number
    $sql = mysqli_query($mysqli, "SELECT company_name, company_phone, company_phone_country_code FROM companies WHERE company_id = 1");
    $row = mysqli_fetch_assoc($sql);
    $company_name = escapeSql($row['company_name']);
    $company_phone = escapeSql(formatPhoneNumber($row['company_phone'], $row['company_phone_country_code']));

    // Process each watcher in list
    foreach ($watcher_emails as $watcher_email) {

        if (filter_var($watcher_email, FILTER_VALIDATE_EMAIL)) {

            $watcher_email = escapeSql($watcher_email);

            mysqli_query($mysqli, "INSERT INTO ticket_watchers SET watcher_email = '$watcher_email', watcher_ticket_id = $ticket_id");

            // Notify watcher
            if ($notify && (!empty($config_smtp_provider))) {



                // Email content
                $data = []; // Queue array

                $subject = "Ticket Notification - [$ticket_prefix$ticket_number] - $ticket_subject";
                $body = "<i style=\'color: #808080\'>##- Please type your reply above this line -##</i><br><br>Hello,<br><br>You have been added as a collaborator on this ticket regarding \"$ticket_subject\".<br><br>--------------------------------<br>$ticket_details--------------------------------<br><br>Ticket: $ticket_prefix$ticket_number<br>Subject: $ticket_subject<br>Status: $ticket_status<br>Guest link: https://$config_base_url/guest/guest_view_ticket.php?ticket_id=$ticket_id&url_key=$url_key<br><br>--<br>$company_name - Support<br>$config_ticket_from_email<br>$company_phone";

                $data[] = [
                    'from' => $config_ticket_from_email,
                    'from_name' => $config_ticket_from_name,
                    'recipient' => $watcher_email,
                    'recipient_name' => $watcher_email,
                    'subject' => $subject,
                    'body' => $body
                ];

                addToMailQueue($data);
            }

            logAudit("Ticket", "Edit", "$session_name added $watcher_email as a watcher for ticket $ticket_prefix$ticket_number", $client_id, $ticket_id);
        }

    }

    flashAlert("Added watcher(s)");

    redirect();

}

if (isset($_GET['delete_ticket_watcher'])) {

    validateCSRFToken($_GET['csrf_token']);

    enforceUserPermission('module_support', 2);

    $watcher_id = intval($_GET['delete_ticket_watcher']);

    // Get ticket / watcher details for logging
    $sql = mysqli_query($mysqli, "SELECT watcher_email, ticket_prefix, ticket_number, ticket_status_name, ticket_client_id, ticket_id FROM ticket_watchers
        LEFT JOIN tickets ON watcher_ticket_id = ticket_id
        LEFT JOIN ticket_statuses ON ticket_status = ticket_status_id
        WHERE watcher_id = $watcher_id"
    );
    $row = mysqli_fetch_assoc($sql);

    $ticket_prefix = escapeSql($row['ticket_prefix']);
    $ticket_number = intval($row['ticket_number']);
    $ticket_status_name = escapeSql($row['ticket_status_name']);
    $watcher_email = escapeSql($row['watcher_email']);
    $client_id = intval($row['ticket_client_id']);
    $ticket_id = intval($row['ticket_id']);

    // Don't Enforce Client Access if Ticket doesn't have an assigned client
    if ($client_id) {
        enforceClientAccess();
    }

    mysqli_query($mysqli, "DELETE FROM ticket_watchers WHERE watcher_id = $watcher_id");

    // History
    mysqli_query($mysqli, "INSERT INTO ticket_history SET ticket_history_status = '$ticket_status_name', ticket_history_description = '$session_name removed ticket $watcher_email as a watcher', ticket_history_ticket_id = $ticket_id");

    logAudit("Ticket", "Edit", "$session_name removed $watcher_email as a watcher for ticket $ticket_prefix$ticket_number", $client_id, $ticket_id);

    flashAlert("Removed ticket watcher <strong>$watcher_email</strong>", 'error');

    redirect();

}

if (isset($_GET['delete_ticket_additional_asset'])) {

    validateCSRFToken($_GET['csrf_token']);

    enforceUserPermission('module_support', 2);

    $asset_id = intval($_GET['delete_ticket_additional_asset']);
    $ticket_id = intval($_GET['ticket_id']);

    // Get ticket / asset details for logging
    $sql = mysqli_query($mysqli, "SELECT asset_name, ticket_prefix, ticket_number, ticket_status_name, ticket_client_id FROM assets
        JOIN tickets ON ticket_id = $ticket_id
        JOIN ticket_statuses ON ticket_status = ticket_status_id
        WHERE asset_id = $asset_id"
    );
    $row = mysqli_fetch_assoc($sql);

    $ticket_prefix = escapeSql($row['ticket_prefix']);
    $ticket_number = intval($row['ticket_number']);
    $ticket_status_name = escapeSql($row['ticket_status_name']);
    $asset_name = escapeSql($row['asset_name']);
    $client_id = intval($row['ticket_client_id']);

    // Don't Enforce Client Access if Ticket doesn't have an assigned client
    if ($client_id) {
        enforceClientAccess();
    }

    mysqli_query($mysqli, "DELETE FROM ticket_assets WHERE ticket_id = $ticket_id AND asset_id = $asset_id");

    // History
    mysqli_query($mysqli, "INSERT INTO ticket_history SET ticket_history_status = '$ticket_status_name', ticket_history_description = '$session_name removed additional asset $asset_name', ticket_history_ticket_id = $ticket_id");

    logAudit("Ticket", "Edit", "$session_name removed asset $asset_name from ticket $ticket_prefix$ticket_number", $client_id, $ticket_id);

    flashAlert("Removed asset <strong>$asset_name</strong> from ticket.", 'error');

    redirect();

}

if (isset($_POST['edit_ticket_asset'])) {

    validateCSRFToken($_POST['csrf_token']);

    enforceUserPermission('module_support', 2);

    $ticket_id = intval($_POST['ticket_id']);
    $asset_id = intval($_POST['asset']);

    $client_id = intval(getFieldById('tickets', $ticket_id, 'ticket_client_id'));

    // Don't Enforce Client Access if Ticket doesn't have an assigned client
    if ($client_id) {
        enforceClientAccess();
    }

    mysqli_query($mysqli, "UPDATE tickets SET ticket_asset_id = $asset_id WHERE ticket_id = $ticket_id");

    // Add Additional Assets
    if (isset($_POST['additional_assets'])) {
        mysqli_query($mysqli, "DELETE FROM ticket_assets WHERE ticket_id = $ticket_id");
        foreach ($_POST['additional_assets'] as $additional_asset) {
            $additional_asset_id = intval($additional_asset);
            mysqli_query($mysqli, "INSERT INTO ticket_assets SET ticket_id = $ticket_id, asset_id = $additional_asset_id");
        }
    } else {
        // If no additional assets are provided, delete them all
        // This handles cases where the assets input might be cleared or not set at all.
        mysqli_query($mysqli, "DELETE FROM ticket_assets WHERE ticket_id = $ticket_id");
    }

    // Get ticket / asset details for logging
    $sql = mysqli_query($mysqli, "SELECT asset_name, ticket_prefix, ticket_number, ticket_status_name, ticket_client_id FROM assets
        LEFT JOIN tickets ON ticket_asset_id = asset_id
        LEFT JOIN ticket_statuses ON ticket_status = ticket_status_id
        WHERE ticket_id = $ticket_id"
    );
    $row = mysqli_fetch_assoc($sql);

    $ticket_prefix = escapeSql($row['ticket_prefix']);
    $ticket_number = intval($row['ticket_number']);
    $ticket_status_name = escapeSql($row['ticket_status_name']);
    $asset_name = escapeSql($row['asset_name']);
    $client_id = intval($row['ticket_client_id']);

    logAudit("Ticket", "Edit", "$session_name changed asset to $asset_name for ticket $ticket_prefix$ticket_number", $client_id, $ticket_id);

    flashAlert("Ticket <strong>$ticket_prefix$ticket_number</strong> asset updated to <strong>$asset_name</strong>");

    redirect();

}

if (isset($_POST['edit_ticket_vendor'])) {

    validateCSRFToken($_POST['csrf_token']);

    enforceUserPermission('module_support', 2);

    $ticket_id = intval($_POST['ticket_id']);
    $vendor_id = intval($_POST['vendor']);

    $client_id = intval(getFieldById('tickets', $ticket_id, 'ticket_client_id'));

    // Don't Enforce Client Access if Ticket doesn't have an assigned client
    if ($client_id) {
        enforceClientAccess();
    }

    mysqli_query($mysqli, "UPDATE tickets SET ticket_vendor_id = $vendor_id WHERE ticket_id = $ticket_id");

    // Get ticket / vendor details for logging
    $sql = mysqli_query($mysqli, "SELECT vendor_name, ticket_prefix, ticket_number, ticket_status_name, ticket_client_id FROM vendors
        LEFT JOIN tickets ON ticket_vendor_id = $vendor_id
        LEFT JOIN ticket_statuses ON ticket_status = ticket_status_id
        WHERE ticket_id = $ticket_id"
    );
    $row = mysqli_fetch_assoc($sql);

    $ticket_prefix = escapeSql($row['ticket_prefix']);
    $ticket_number = intval($row['ticket_number']);
    $ticket_status_name = escapeSql($row['ticket_status_name']);
    $vendor_name = escapeSql($row['vendor_name']);
    $client_id = intval($row['ticket_client_id']);

    logAudit("Ticket", "Edit", "$session_name set vendor to $vendor_name for ticket $ticket_prefix$ticket_number", $client_id, $ticket_id);

    flashAlert("Set vendor to <strong>$vendor_name</strong> for ticket <strong>$ticket_prefix$ticket_number</strong>");

    redirect();

}

if (isset($_POST['assign_ticket'])) {

    validateCSRFToken($_POST['csrf_token']);

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
        $agent_details_sql = mysqli_query($mysqli, "SELECT user_name, user_email FROM users WHERE users.user_id = $assigned_to");
        $agent_details = mysqli_fetch_assoc($agent_details_sql);

        $agent_name = escapeSql($agent_details['user_name']);
        $agent_email = escapeSql($agent_details['user_email']);
        $ticket_reply = "Ticket re-assigned to $agent_name.";

        if (!$agent_name) {
            flashAlert("Invalid agent!", 'error');
            redirect();
        }
    }

    // Get & verify ticket details
    $ticket_details_sql = mysqli_query($mysqli, "SELECT ticket_prefix, ticket_number, ticket_subject, ticket_client_id, client_name FROM tickets LEFT JOIN clients ON ticket_client_id = client_id WHERE ticket_id = '$ticket_id' AND ticket_status != 5");
    $ticket_details = mysqli_fetch_assoc($ticket_details_sql);

    $ticket_prefix = escapeSql($ticket_details['ticket_prefix']);
    $ticket_number = intval($ticket_details['ticket_number']);
    $ticket_subject = escapeSql($ticket_details['ticket_subject']);
    $client_id = intval($ticket_details['ticket_client_id']);
    $client_name = escapeSql($ticket_details['client_name']);

    // Don't Enforce Client Access if Ticket doesn't have an assigned client
    if ($client_id) {
        enforceClientAccess();
    }

    if (!$ticket_subject) {
        flashAlert("Invalid ticket!", 'error');
        redirect();
    }

    if ($client_id) {
        $client_uri = "&client_id=$client_id";
    } else {
        $client_uri = '';
    }

    // Update ticket & insert reply
    mysqli_query($mysqli, "UPDATE tickets SET ticket_assigned_to = $assigned_to, ticket_status = '$ticket_status' WHERE ticket_id = $ticket_id");

    mysqli_query($mysqli, "INSERT INTO ticket_replies SET ticket_reply = '$ticket_reply', ticket_reply_type = 'Internal', ticket_reply_time_worked = '00:01:00', ticket_reply_by = $session_user_id, ticket_reply_ticket_id = $ticket_id");

    logAudit("Ticket", "Edit", "$session_name reassigned $ticket_prefix$ticket_number to $agent_name", $client_id, $ticket_id);

    // Notification
    if ($session_user_id != $assigned_to && $assigned_to != 0) {

        // App Notification
        mysqli_query($mysqli, "INSERT INTO notifications SET notification_type = 'Ticket', notification = 'Ticket $ticket_prefix$ticket_number - Subject: $ticket_subject has been assigned to you by $session_name', notification_action = '/agent/ticket.php?ticket_id=$ticket_id$client_uri', notification_client_id = $client_id, notification_user_id = $assigned_to");

        // Email Notification
        if (!empty($config_smtp_provider)) {

            // Sanitize Config vars from get_settings.php
            $config_ticket_from_name = escapeSql($config_ticket_from_name);
            $config_ticket_from_email = escapeSql($config_ticket_from_email);
            $company_name = escapeSql($session_company_name);

            $subject = "$config_app_name - Ticket $ticket_prefix$ticket_number assigned to you - $ticket_subject";
            $body = "Hi $agent_name, <br><br>A ticket has been assigned to you!<br><br>Client: $client_name<br>Ticket Number: $ticket_prefix$ticket_number<br> Subject: $ticket_subject<br><br>https://$config_base_url/agent/ticket.php?ticket_id=$ticket_id$client_uri <br><br>Thanks, <br>$session_name<br>$company_name";

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

    triggerCustomAction('ticket_assign', $ticket_id);

    flashAlert("Ticket <strong>$ticket_prefix$ticket_number</strong> assigned to <strong>$agent_name</strong>");

    redirect();

}

if (isset($_GET['delete_ticket'])) {

    validateCSRFToken($_GET['csrf_token']);

    enforceUserPermission('module_support', 3);

    $ticket_id = intval($_GET['delete_ticket']);

    // Get Ticket and Client ID for logging and alert message
    $sql = mysqli_query($mysqli, "SELECT ticket_prefix, ticket_number, ticket_subject, ticket_status, ticket_closed_at, ticket_client_id FROM tickets WHERE ticket_id = $ticket_id");
    $row = mysqli_fetch_assoc($sql);
    $ticket_prefix = escapeSql($row['ticket_prefix']);
    $ticket_number = escapeSql($row['ticket_number']);
    $ticket_subject = escapeSql($row['ticket_subject']);
    $ticket_status = escapeSql($row['ticket_status']);
    $ticket_closed_at = escapeSql($row['ticket_closed_at']);
    $client_id = intval($row['ticket_client_id']);

    // Don't Enforce Client Access if Ticket doesn't have an assigned client
    if ($client_id) {
        enforceClientAccess();
    }

    if (empty($ticket_closed_at)) {
        mysqli_query($mysqli, "DELETE FROM tickets WHERE ticket_id = $ticket_id");

        // Delete all ticket replies
        mysqli_query($mysqli, "DELETE FROM ticket_replies WHERE ticket_reply_ticket_id = $ticket_id");

        // Delete all ticket views
        mysqli_query($mysqli, "DELETE FROM ticket_views WHERE view_ticket_id = $ticket_id");

        // Delete ticket watchers
        mysqli_query($mysqli, "DELETE FROM ticket_watchers WHERE watcher_ticket_id = $ticket_id");

        // Delete Ticket Attachements
        mysqli_query($mysqli, "DELETE FROM ticket_attachments WHERE ticket_attachment_ticket_id = $ticket_id");
        removeDirectory("../uploads/tickets/$ticket_id");

        // No Need to delete ticket assets as this is cascadely deleted via the database.

        logAudit("Ticket", "Delete", "$session_name deleted $ticket_prefix$ticket_number along with all replies", $client_id);

        flashAlert("Ticket <strong>$ticket_prefix$ticket_number</strong> along with all replies deleted", 'error');

        triggerCustomAction('ticket_delete', $ticket_id);

        redirect("tickets.php");
    }

}

if (isset($_POST['bulk_delete_tickets'])) {

    validateCSRFToken($_POST['csrf_token']);

    enforceUserPermission('module_support', 3);

    if (isset($_POST['ticket_ids'])) {

        $count = count($_POST['ticket_ids']);

        // Cycle through array and delete each recurring scheduled ticket
        foreach ($_POST['ticket_ids'] as $ticket_id) {

            $ticket_id = intval($ticket_id);

            $client_id = intval(getFieldById('tickets', $ticket_id, 'ticket_client_id'));

            // Don't Enforce Client Access if Ticket doesn't have an assigned client
            if ($client_id) {
                enforceClientAccess();
            }

            mysqli_query($mysqli, "DELETE FROM tickets WHERE ticket_id = $ticket_id");

            // Delete all ticket replies
            mysqli_query($mysqli, "DELETE FROM ticket_replies WHERE ticket_reply_ticket_id = $ticket_id");

            // Delete all ticket views
            mysqli_query($mysqli, "DELETE FROM ticket_views WHERE view_ticket_id = $ticket_id");

            // Delete ticket watchers
            mysqli_query($mysqli, "DELETE FROM ticket_watchers WHERE watcher_ticket_id = $ticket_id");

            // Delete Ticket Attachements
            mysqli_query($mysqli, "DELETE FROM ticket_attachments WHERE ticket_attachment_ticket_id = $ticket_id");
            removeDirectory("../uploads/tickets/$ticket_id");

            // No Need to delete ticket assets as this is cascadely deleted via the database.

            logAudit("Ticket", "Delete", "$session_name deleted ticket", 0, $ticket_id);

        }

        logAudit("Ticket", "Bulk Delete", "$session_name deleted $count ticket(s)");

        flashAlert("Deleted <strong>$count</strong> ticket(s)", 'error');
    }

    redirect();

}

if (isset($_POST['bulk_assign_ticket'])) {

    validateCSRFToken($_POST['csrf_token']);

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
            $row = mysqli_fetch_assoc($sql);

            $ticket_prefix = escapeSql($row['ticket_prefix']);
            $ticket_number = intval($row['ticket_number']);
            $ticket_status = intval($row['ticket_status']);
            $ticket_name = escapeSql($row['ticket_name']);
            $ticket_subject = escapeSql($row['ticket_subject']);
            $client_id = intval($row['ticket_client_id']);

            // Don't Enforce Client Access if Ticket doesn't have an assigned client
            if ($client_id) {
                enforceClientAccess();
            }

            if ($ticket_status == 1 && $assigned_to !== 0) {
                $ticket_status = 2;
            }

            // Allow for un-assigning tickets
            if ($assign_to == 0) {
                $ticket_reply = "Ticket unassigned, pending re-assignment.";
                $agent_name = "No One";
            } else {
                // Get & verify assigned agent details
                $agent_details_sql = mysqli_query($mysqli, "SELECT user_name, user_email FROM users LEFT JOIN user_settings ON users.user_id = user_settings.user_id WHERE users.user_id = $assign_to");
                $agent_details = mysqli_fetch_assoc($agent_details_sql);

                $agent_name = escapeSql($agent_details['user_name']);
                $agent_email = escapeSql($agent_details['user_email']);
                $ticket_reply = "Ticket re-assigned to $agent_name.";

                if (!$agent_name) {
                    flashAlert("Invalid agent!", 'error');
                    redirect();
                }
            }

            // Update ticket & insert reply
            mysqli_query($mysqli, "UPDATE tickets SET ticket_assigned_to = $assign_to, ticket_status = $ticket_status WHERE ticket_id = $ticket_id");

            mysqli_query($mysqli, "INSERT INTO ticket_replies SET ticket_reply = '$ticket_reply', ticket_reply_type = 'Internal', ticket_reply_time_worked = '00:01:00', ticket_reply_by = $session_user_id, ticket_reply_ticket_id = $ticket_id");

            logAudit("Ticket", "Edit", "$session_name reassigned ticket $ticket_prefix$ticket_number to $agent_name", $client_id, $ticket_id);

            triggerCustomAction('ticket_assign', $ticket_id);

            $tickets_assigned_body .= "$ticket_prefix$ticket_number - $ticket_subject<br>";
        } // End For Each Ticket ID Loop

        // Notification
        if ($session_user_id != $assign_to && $assign_to != 0) {

            // App Notification
            mysqli_query($mysqli, "INSERT INTO notifications SET notification_type = 'Ticket', notification = '$ticket_count Tickets have been assigned to you by $session_name', notification_action = 'tickets.php?status=Open&assigned=$assign_to', notification_client_id = $client_id, notification_user_id = $assign_to");

            // Agent Email Notification
            if (!empty($config_smtp_provider)) {

                // Sanitize Config vars from get_settings.php
                $config_ticket_from_name = escapeSql($config_ticket_from_name);
                $config_ticket_from_email = escapeSql($config_ticket_from_email);
                $company_name = escapeSql($session_company_name);

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
                addToMailQueue($data);
            }
        }
    }

    flashAlert("You assigned <b>$ticket_count</b> Tickets to <b>$agent_name</b>");

    redirect();

}

if (isset($_POST['bulk_edit_ticket_priority'])) {

    validateCSRFToken($_POST['csrf_token']);

    enforceUserPermission('module_support', 2);

    // POST variables
    $priority = escapeSql($_POST['bulk_priority']);

    // Assign Tech to Selected Tickets
    if (isset($_POST['ticket_ids'])) {

        // Get a Ticket Count
        $ticket_count = count($_POST['ticket_ids']);

        foreach ($_POST['ticket_ids'] as $ticket_id) {
            $ticket_id = intval($ticket_id);

            $sql = mysqli_query($mysqli, "SELECT * FROM tickets WHERE ticket_id = $ticket_id");
            $row = mysqli_fetch_assoc($sql);

            $ticket_prefix = escapeSql($row['ticket_prefix']);
            $ticket_number = intval($row['ticket_number']);
            $ticket_subject = escapeSql($row['ticket_subject']);
            $original_ticket_priority = escapeSql($row['ticket_priority']);
            $client_id = intval($row['ticket_client_id']);

            // Don't Enforce Client Access if Ticket doesn't have an assigned client
            if ($client_id) {
                enforceClientAccess();
            }

            // Update ticket & insert reply
            mysqli_query($mysqli, "UPDATE tickets SET ticket_priority = '$priority' WHERE ticket_id = $ticket_id");

            mysqli_query($mysqli, "INSERT INTO ticket_replies SET ticket_reply = '$session_name updated the priority from $current_ticket_priority to $priority', ticket_reply_type = 'Internal', ticket_reply_time_worked = '00:01:00', ticket_reply_by = $session_user_id, ticket_reply_ticket_id = $ticket_id");

            logAudit("Ticket", "Edit", "$session_name updated the priority on ticket $ticket_prefix$ticket_number - $ticket_subject from $original_ticket_priority to $priority", $client_id, $ticket_id);

            triggerCustomAction('ticket_update', $ticket_id);
        } // End For Each Ticket ID Loop

        logAudit("Ticket", " Bulk Edit", "$session_name updated the priority on $ticket_count");

        flashAlert("You updated the priority for <strong>$ticket_count</strong> Tickets to <strong>$priority</strong>");
    }

    redirect();

}

if (isset($_POST['bulk_edit_ticket_category'])) {

    validateCSRFToken($_POST['csrf_token']);

    enforceUserPermission('module_support', 2);

    // POST variables
    $category_id = intval($_POST['bulk_category']);

    // Assign Tech to Selected Tickets
    if (isset($_POST['ticket_ids'])) {

        // Get a Ticket Count
        $ticket_count = count($_POST['ticket_ids']);

        foreach ($_POST['ticket_ids'] as $ticket_id) {
            $ticket_id = intval($ticket_id);

            $sql = mysqli_query($mysqli, "SELECT ticket_prefix, ticket_number, ticket_subject, category_name, ticket_client_id FROM tickets LEFT JOIN categories ON ticket_category = category_id WHERE ticket_id = $ticket_id");
            $row = mysqli_fetch_assoc($sql);

            $ticket_prefix = escapeSql($row['ticket_prefix']);
            $ticket_number = intval($row['ticket_number']);
            $ticket_subject = escapeSql($row['ticket_subject']);
            $previous_ticket_category_name = escapeSql($row['category_name']);
            $client_id = intval($row['ticket_client_id']);

            // Don't Enforce Client Access if Ticket doesn't have an assigned client
            if ($client_id) {
                enforceClientAccess();
            }

            // Get Category Name
            $category_name = escapeSql(getFieldById('categories', $category_id, 'category_name'));

            // Update ticket
            mysqli_query($mysqli, "UPDATE tickets SET ticket_category = '$category_id' WHERE ticket_id = $ticket_id");

            logAudit("Ticket", "Edit", "$session_name updated the category on ticket $ticket_prefix$ticket_number - $ticket_subject from $previous_category_name to $category_name", $client_id, $ticket_id);

            triggerCustomAction('ticket_update', $ticket_id);
        } // End For Each Ticket ID Loop

        logAudit("Ticket", " Bulk Edit", "$session_name updated the category to $category_name on $ticket_count");

        flashAlert("Category set to $category_name for <strong>$ticket_count</strong> Tickets");
    }

    redirect();

}

if (isset($_POST['bulk_merge_tickets'])) {

    validateCSRFToken($_POST['csrf_token']);

    enforceUserPermission('module_support', 2);

    $merge_into_ticket_id = intval($_POST['merge_into_ticket_id']); // Parent ticket id
    $merge_comment = escapeSql($_POST['merge_comment']); // Merge comment
    $ticket_reply_type = 'Internal'; // Default all replies to internal

    // NEW PARENT ticket details
    // Get merge into ticket id (as it may differ from the number)
    $sql = mysqli_query($mysqli, "SELECT ticket_id, ticket_number FROM tickets WHERE ticket_id = $merge_into_ticket_id");
    if (mysqli_num_rows($sql) == 0) {
        flashAlert("Cannot merge into that ticket.", 'error');
        redirect();
    }
    $merge_row = mysqli_fetch_assoc($sql);
    $merge_into_ticket_number = intval($merge_row['ticket_number']); // Parent ticket Number

    // Update & Close the selected tickets
    if (isset($_POST['ticket_ids'])) {

        $ticket_count = count($_POST['ticket_ids']); // Get a ticket count

        foreach ($_POST['ticket_ids'] as $ticket_id) {
            $ticket_id = intval($ticket_id);

            if ($ticket_id !== $merge_into_ticket_id) {

                $sql = mysqli_query($mysqli, "SELECT * FROM tickets WHERE ticket_id = $ticket_id");
                $row = mysqli_fetch_assoc($sql);

                $ticket_prefix = escapeSql($row['ticket_prefix']);
                $ticket_number = intval($row['ticket_number']);
                $ticket_subject = escapeSql($row['ticket_subject']);
                $ticket_details = mysqli_escape_string($mysqli, $row['ticket_details']);
                $current_ticket_priority = escapeSql($row['ticket_priority']);
                $ticket_first_response_at = escapeSql($row['ticket_first_response_at']);
                $client_id = intval($row['ticket_client_id']);

                // Don't Enforce Client Access if Ticket doesn't have an assigned client
                if ($client_id) {
                    enforceClientAccess();
                }

                // Update current ticket
                if (empty($ticket_first_response_at)) {
                    mysqli_query($mysqli, "UPDATE tickets SET ticket_first_response_at = NOW() WHERE ticket_id = $ticket_id");
                }
                mysqli_query($mysqli, "INSERT INTO ticket_replies SET ticket_reply = 'Ticket $ticket_prefix$ticket_number bulk merged into <a href=\"ticket.php?ticket_id=$merge_into_ticket_id\">$ticket_prefix$merge_into_ticket_number</a>. Comment: $merge_comment', ticket_reply_time_worked = '00:01:00', ticket_reply_type = '$ticket_reply_type', ticket_reply_by = $session_user_id, ticket_reply_ticket_id = $ticket_id");
                mysqli_query($mysqli, "UPDATE tickets SET ticket_status = '5', ticket_resolved_at = NOW(), ticket_closed_at = NOW(), ticket_closed_by = $session_user_id WHERE ticket_id = $ticket_id") or die(mysqli_error($mysqli));

                // Update new parent ticket
                mysqli_query($mysqli, "INSERT INTO ticket_replies SET ticket_reply = 'Ticket $ticket_prefix$ticket_number was bulk merged into this ticket with comment: $merge_comment.<br><br><b>$ticket_subject</b><br>$ticket_details', ticket_reply_time_worked = '00:01:00', ticket_reply_type = 'Internal', ticket_reply_by = $session_user_id, ticket_reply_ticket_id = $merge_into_ticket_id");

                logAudit("Ticket", "Merged", "$session_name Merged ticket $ticket_prefix$ticket_number into $ticket_prefix$merge_into_ticket_number", $client_id, $ticket_id);

                // Custom action/notif handler
                triggerCustomAction('ticket_merge', $ticket_id);

            }
        } // End For Each Ticket ID Loop

        mysqli_query($mysqli, "UPDATE tickets SET ticket_updated_at = NOW() WHERE ticket_id = $merge_into_ticket_id");

        flashAlert("<strong>$ticket_count</strong> tickets merged into <strong>$ticket_prefix$merge_into_ticket_number</strong>");

    }

    redirect();

}

if (isset($_POST['bulk_resolve_tickets'])) {

    validateCSRFToken($_POST['csrf_token']);

    enforceUserPermission('module_support', 2);

    // POST variables
    $details = mysqli_escape_string($mysqli, $_POST['bulk_details']);
    $ticket_reply_time_worked = escapeSql($_POST['time']);
    $private_note = intval($_POST['bulk_private_note']);
    if ($private_note == 1) {
        $ticket_reply_type = 'Internal';
    } else {
        $ticket_reply_type = 'Public';
    }

    // Resolve Selected Tickets
    if (isset($_POST['ticket_ids'])) {

        // Intitialze the counts before the loop
        $ticket_count = 0;
        $skipped_count = 0;

        foreach ($_POST['ticket_ids'] as $ticket_id) {
            $ticket_id = intval($ticket_id);

            // Check to make sure Tasks are complete before resolving
            $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('task_id') AS num FROM tasks WHERE task_completed_at IS NULL AND task_ticket_id = $ticket_id"));
            $num_of_open_tasks = $row['num'];

            if ($num_of_open_tasks == 0) {
                // Count the Ticket Loop
                $ticket_count++;

                $sql = mysqli_query($mysqli, "SELECT * FROM tickets WHERE ticket_id = $ticket_id");
                $row = mysqli_fetch_assoc($sql);

                $ticket_prefix = escapeSql($row['ticket_prefix']);
                $ticket_number = intval($row['ticket_number']);
                $ticket_subject = escapeSql($row['ticket_subject']);
                $current_ticket_priority = escapeSql($row['ticket_priority']);
                $url_key = escapeSql($row['ticket_url_key']);
                $ticket_first_response_at = escapeSql($row['ticket_first_response_at']);
                $client_id = intval($row['ticket_client_id']);

                // Don't Enforce Client Access if Ticket doesn't have an assigned client
                if ($client_id) {
                    enforceClientAccess();
                }

                // Mark FR time if required
                if (empty($ticket_first_response_at)) {
                    mysqli_query($mysqli, "UPDATE tickets SET ticket_first_response_at = NOW() WHERE ticket_id = $ticket_id");
                }

                // Update ticket & insert reply
                mysqli_query($mysqli, "UPDATE tickets SET ticket_status = 4, ticket_resolved_at = NOW() WHERE ticket_id = $ticket_id");

                mysqli_query($mysqli, "INSERT INTO ticket_replies SET ticket_reply = '$details', ticket_reply_type = '$ticket_reply_type', ticket_reply_time_worked = '$ticket_reply_time_worked', ticket_reply_by = $session_user_id, ticket_reply_ticket_id = $ticket_id");

                logAudit("Ticket", "Resolve", "$session_name resolved $ticket_prefix$ticket_number - $ticket_subject", $client_id, $ticket_id);

                triggerCustomAction('ticket_resolve', $ticket_id);

                // Client notification email
                if ((!empty($config_smtp_provider)) && $config_ticket_client_general_notifications == 1 && $private_note == 0) {

                    // Get Contact details
                    $ticket_sql = mysqli_query($mysqli, "SELECT contact_name, contact_email FROM tickets
                        LEFT JOIN contacts ON ticket_contact_id = contact_id
                        WHERE ticket_id = $ticket_id
                    ");
                    $row = mysqli_fetch_assoc($ticket_sql);

                    $contact_name = escapeSql($row['contact_name']);
                    $contact_email = escapeSql($row['contact_email']);

                    // Sanitize Config vars from get_settings.php
                    $from_name = escapeSql($config_ticket_from_name);
                    $from_email = escapeSql($config_ticket_from_email);
                    $base_url = escapeSql($config_base_url);

                    // Get Company Info
                    $sql = mysqli_query($mysqli, "SELECT company_name, company_phone, company_phone_country_code FROM companies WHERE company_id = 1");
                    $row = mysqli_fetch_assoc($sql);
                    $company_name = escapeSql($row['company_name']);
                    $company_phone = escapeSql(formatPhoneNumber($row['company_phone'], $row['company_phone_country_code']));

                    // EMAIL
                    $subject = "Ticket resolved - [$ticket_prefix$ticket_number] - $ticket_subject | (pending closure)";
                    $body = "<i style=\'color: #808080\'>##- Please type your reply above this line -##</i><br><br>Hello $contact_name,<br><br>Your ticket regarding \"$ticket_subject\" has been marked as solved and is pending closure.<br><br>$details<br><br> If your request/issue is resolved, you can simply ignore this email. If you need further assistance, please reply or <a href=\'https://$config_base_url/guest/guest_view_ticket.php?ticket_id=$ticket_id&url_key=$url_key\'>re-open</a> to let us know! <br><br>Ticket: $ticket_prefix$ticket_number<br>Subject: $ticket_subject<br>Portal: https://$base_url/client/ticket.php?id=$ticket_id<br><br>--<br>$company_name - Support<br>$config_ticket_from_email<br>$company_phone";

                    // Check email valid
                    if (filter_var($contact_email, FILTER_VALIDATE_EMAIL)) {

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
                    }

                    // Also Email all the watchers
                    $sql_watchers = mysqli_query($mysqli, "SELECT watcher_email FROM ticket_watchers WHERE watcher_ticket_id = $ticket_id");
                    $body .= "<br><br>----------------------------------------<br>YOU ARE A COLLABORATOR ON THIS TICKET";
                    while ($row = mysqli_fetch_assoc($sql_watchers)) {
                        $watcher_email = escapeSql($row['watcher_email']);

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
                    addToMailQueue($data);
                } // End Mail IF
            } else {
                 $skipped_count++;
            } // End Task Check
        } // End Loop
    } // End Array Empty Check

    flashAlert("Resolved <strong>$ticket_count</strong> Tickets");

    if ($skipped_count > 0) {
        flashAlert("Resolved <strong>$ticket_count</strong> Tickets <strong>$skipped_count</strong> ticket(s) could not be resolved because they have open tasks.", 'info');
    }

    redirect();

}

if (isset($_POST['bulk_ticket_reply'])) {

    validateCSRFToken($_POST['csrf_token']);

    enforceUserPermission('module_support', 2);

    // POST variables
    $ticket_reply = mysqli_escape_string($mysqli, $_POST['bulk_reply_details']);
    $ticket_status = intval($_POST['bulk_status']);
    $ticket_reply_time_worked = escapeSql($_POST['time']);
    $private_note = intval($_POST['bulk_private_reply']);
    if ($private_note == 1) {
        $ticket_reply_type = 'Internal';
    } else {
        $ticket_reply_type = 'Public';
    }

    // Loop Through Tickets and Add Reply along with Email notifications
    if (isset($_POST['ticket_ids'])) {

        // Get a Ticket Count
        $ticket_count = count($_POST['ticket_ids']);

        foreach ($_POST['ticket_ids'] as $ticket_id) {
            $ticket_id = intval($ticket_id);

            $sql = mysqli_query($mysqli, "SELECT * FROM tickets WHERE ticket_id = $ticket_id");
            $row = mysqli_fetch_assoc($sql);

            $ticket_prefix = escapeSql($row['ticket_prefix']);
            $ticket_number = intval($row['ticket_number']);
            $ticket_subject = escapeSql($row['ticket_subject']);
            $current_ticket_priority = escapeSql($row['ticket_priority']);
            $url_key = escapeSql($row['ticket_url_key']);
            $ticket_first_response_at = escapeSql($row['ticket_first_response_at']);
            $client_id = intval($row['ticket_client_id']);

            // Don't Enforce Client Access if Ticket doesn't have an assigned client
            if ($client_id) {
                enforceClientAccess();
            }

            if ($client_id) {
                $client_uri = "&client_id=$client_id";
            } else {
                $client_uri = '';
            }

            // Mark FR time if required
            if (empty($ticket_first_response_at)) {
                mysqli_query($mysqli, "UPDATE tickets SET ticket_first_response_at = NOW() WHERE ticket_id = $ticket_id");
            }

            // Add reply
            mysqli_query($mysqli, "INSERT INTO ticket_replies SET ticket_reply = '$ticket_reply', ticket_reply_time_worked = '$ticket_reply_time_worked', ticket_reply_type = '$ticket_reply_type', ticket_reply_by = $session_user_id, ticket_reply_ticket_id = $ticket_id");

            $ticket_reply_id = mysqli_insert_id($mysqli);

            // Update Ticket Status
            mysqli_query($mysqli, "UPDATE tickets SET ticket_status = '$ticket_status' WHERE ticket_id = $ticket_id");

            logAudit("Ticket", "Reply", "$session_name replied to ticket $ticket_prefix$ticket_number - $ticket_subject and was a $ticket_reply_type reply", $client_id, $ticket_id);

            // Custom action/notif handler
            if ($ticket_reply_type == 'Internal') {
                triggerCustomAction('ticket_reply_agent_internal', $ticket_id);
            } else {
                triggerCustomAction('reply_reply_agent_public', $ticket_id);
            }

            // Resolve the ticket, if set
            if ($ticket_status == 4) {
                mysqli_query($mysqli, "UPDATE tickets SET ticket_resolved_at = NOW() WHERE ticket_id = $ticket_id");

                // Logging
                logAudit("Ticket", "Resolved", "$session_name resolved Ticket $ticket_prefix$ticket_number", $client_id, $ticket_id);

                triggerCustomAction('ticket_resolve', $ticket_id);
            }

            // Get Contact Details
            $sql = mysqli_query(
                $mysqli,
                "SELECT contact_name, contact_email, ticket_created_by, ticket_assigned_to
                FROM tickets
                LEFT JOIN contacts ON ticket_contact_id = contact_id
                WHERE ticket_id = $ticket_id"
            );

            $row = mysqli_fetch_assoc($sql);

            $contact_name = escapeSql($row['contact_name']);
            $contact_email = escapeSql($row['contact_email']);
            $ticket_created_by = intval($row['ticket_created_by']);
            $ticket_assigned_to = intval($row['ticket_assigned_to']);

            // Sanitize Config vars from get_settings.php
            $from_name = escapeSql($config_ticket_from_name);
            $from_email = escapeSql($config_ticket_from_email);
            $base_url = escapeSql($config_base_url);

            $sql = mysqli_query($mysqli, "SELECT company_name, company_phone, company_phone_country_code FROM companies WHERE company_id = 1");
            $row = mysqli_fetch_assoc($sql);
            $company_name = escapeSql($row['company_name']);
            $company_phone = escapeSql(formatPhoneNumber($row['company_phone'], $row['company_phone_country_code']));

            // Send e-mail to client if public update & email is set up
            if ($private_note == 0 && (!empty($config_smtp_provider))) {

                $subject = "Ticket update - [$ticket_prefix$ticket_number] - $ticket_subject";
                $body = "<i style=\'color: #808080\'>##- Please type your reply above this line -##</i><br><br>Hello $contact_name,<br><br>Your ticket regarding $ticket_subject has been updated.<br><br>--------------------------------<br>$ticket_reply<br>--------------------------------<br><br>Ticket: $ticket_prefix$ticket_number<br>Subject: $ticket_subject<br>Status: $ticket_status_name<br>Portal: <a href=\'https://$config_base_url/guest/guest_view_ticket.php?ticket_id=$ticket_id&url_key=$url_key\'>View ticket</a><br><br>--<br>$company_name - Support<br>$from_email<br>$company_phone";

                if (filter_var($contact_email, FILTER_VALIDATE_EMAIL)) {

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

                }

                // Also Email all the watchers
                $sql_watchers = mysqli_query($mysqli, "SELECT watcher_email FROM ticket_watchers WHERE watcher_ticket_id = $ticket_id");
                $body .= "<br><br>----------------------------------------<br>YOU ARE A COLLABORATOR ON THIS TICKET";
                while ($row = mysqli_fetch_assoc($sql_watchers)) {
                    $watcher_email = escapeSql($row['watcher_email']);

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
                addToMailQueue($data);
            } //End Mail IF

            // Notification for assigned ticket user
            if ($session_user_id != $ticket_assigned_to && $ticket_assigned_to != 0) {

                mysqli_query($mysqli, "INSERT INTO notifications SET notification_type = 'Ticket', notification = '$session_name updated Ticket $ticket_prefix$ticket_number - Subject: $ticket_subject that is assigned to you', notification_action = '/agent/ticket.php?ticket_id=$ticket_id$client_uri', notification_client_id = $client_id, notification_user_id = $ticket_assigned_to");
            }

            // Notification for user that opened the ticket
            if ($session_user_id != $ticket_created_by && $ticket_created_by != 0) {

                mysqli_query($mysqli, "INSERT INTO notifications SET notification_type = 'Ticket', notification = '$session_name updated Ticket $ticket_prefix$ticket_number - Subject: $ticket_subject that you opened', notification_action = '/agent/ticket.php?ticket_id=$ticket_id$client_uri', notification_client_id = $client_id, notification_user_id = $ticket_created_by");
            }
        } // End Ticket Lopp

    }

    flashAlert("Updated <strong>$ticket_count</strong> tickets");

    redirect();

}


// Currently not UI Frontend for this
if (isset($_POST['bulk_add_ticket_project'])) {

    validateCSRFToken($_POST['csrf_token']);

    enforceUserPermission('module_support', 2);

    // POST variables
    $project_id = intval($_POST['project_id']);

    // Get Project Name
    $sql = mysqli_query($mysqli, "SELECT project_name FROM projects WHERE project_id = $project_id");
    $row = mysqli_fetch_assoc($sql);
    $project_name = escapeSql($row['project_name']);

    // Assign Project to Selected Tickets
    if (isset($_POST['ticket_ids'])) {

        // Get a Ticket Count
        $ticket_count = count($_POST['ticket_ids']);

        foreach ($_POST['ticket_ids'] as $ticket_id) {
            $ticket_id = intval($ticket_id);

            $sql = mysqli_query($mysqli, "SELECT * FROM tickets WHERE ticket_id = $ticket_id");
            $row = mysqli_fetch_assoc($sql);

            $ticket_prefix = escapeSql($row['ticket_prefix']);
            $ticket_number = intval($row['ticket_number']);
            $ticket_subject = escapeSql($row['ticket_subject']);
            $current_ticket_priority = escapeSql($row['ticket_priority']);
            $client_id = intval($row['ticket_client_id']);

            // Don't Enforce Client Access if Ticket doesn't have an assigned client
            if ($client_id) {
                enforceClientAccess();
            }

            // Update ticket & insert reply
            mysqli_query($mysqli, "UPDATE tickets SET ticket_project_id = $project_id WHERE ticket_id = $ticket_id");

            logAudit("Ticket", "Reply", "$session_name added ticket $ticket_prefix$ticket_number - $ticket_subject to project $project_name", $client_id, $ticket_id);


        } // End For Each Ticket ID Loop

        flashAlert("<strong>$ticket_count</strong> Tickets added to Project <strong>$project_name</strong>");

    }

    redirect();

}

if (isset($_POST['bulk_add_asset_ticket'])) {

    validateCSRFToken($_POST['csrf_token']);

    enforceUserPermission('module_support', 2);

    $assigned_to = intval($_POST['bulk_assigned_to']);
    if ($assigned_to == 0) {
        $ticket_status = 1;
    } else {
        $ticket_status = 2;
    }
    $subject = escapeSql($_POST['bulk_subject']);
    $priority = escapeSql($_POST['bulk_priority']);
    $category_id = intval($_POST['bulk_category']);
    $details = mysqli_real_escape_string($mysqli, $_POST['bulk_details']);
    $project_id = intval($_POST['bulk_project']);
    $use_primary_contact = intval($_POST['use_primary_contact']);
    $ticket_template_id = intval($_POST['bulk_ticket_template_id']);
    $billable = intval($_POST['bulk_billable'] ?? 0);

    // Check to see if adding a ticket by template
    if($ticket_template_id) {
        $sql = mysqli_query($mysqli, "SELECT * FROM ticket_templates WHERE ticket_template_id = $ticket_template_id");
        $row = mysqli_fetch_assoc($sql);

        // Override Template Subject
        if(empty($subject)) {
            $subject = escapeSql($row['ticket_template_subject']);
        }
        $details = mysqli_escape_string($mysqli, $row['ticket_template_details']);

        // Get Associated Tasks from the ticket template
        $sql_task_templates = mysqli_query($mysqli, "SELECT * FROM task_templates WHERE task_template_ticket_template_id = $ticket_template_id");

    }

    // Create ticket for each selected asset
    if (isset($_POST['asset_ids'])) {

        // Get a Asset Count
        $asset_count = count($_POST['asset_ids']);

        foreach ($_POST['asset_ids'] as $asset_id) {
            $asset_id = intval($asset_id);

            $sql = mysqli_query($mysqli, "SELECT * FROM assets WHERE asset_id = $asset_id");
            $row = mysqli_fetch_assoc($sql);

            $asset_name = escapeSql($row['asset_name']);
            $client_id = intval($row['asset_client_id']);

            // Don't Enforce Client Access if Ticket doesn't have an assigned client
            if ($client_id) {
                enforceClientAccess();
            }

            $subject_asset_prepended = "$asset_name - $subject";

            // Atomically increment and get the new ticket number
            mysqli_query($mysqli, "
                UPDATE settings
                SET
                    config_ticket_next_number = LAST_INSERT_ID(config_ticket_next_number),
                    config_ticket_next_number = config_ticket_next_number + 1
                WHERE company_id = 1
            ");

            $ticket_number = mysqli_insert_id($mysqli);

            // Sanitize Config Vars from get_settings.php and Session Vars from check_login.php
            $config_ticket_prefix = escapeSql($config_ticket_prefix);
            $config_ticket_from_name = escapeSql($config_ticket_from_name);
            $config_ticket_from_email = escapeSql($config_ticket_from_email);
            $config_base_url = escapeSql($config_base_url);

            //Generate a unique URL key for clients to access
            $url_key = randomString(32);

            mysqli_query($mysqli, "INSERT INTO tickets SET ticket_prefix = '$config_ticket_prefix', ticket_number = $ticket_number, ticket_category = $category_id, ticket_subject = '$subject_asset_prepended', ticket_details = '$details', ticket_priority = '$priority', ticket_billable = $billable, ticket_status = $ticket_status, ticket_asset_id = $asset_id, ticket_created_by = $session_user_id, ticket_assigned_to = $assigned_to, ticket_url_key = '$url_key', ticket_client_id = $client_id, ticket_project_id = $project_id");

            $ticket_id = mysqli_insert_id($mysqli);

            // Add Tasks
            if (!empty($_POST['tasks'])) {
                foreach ($_POST['tasks'] as $task) {
                    $task_name = escapeSql($task);
                    // Check that task_name is not-empty (For some reason the !empty on the array doesnt work here like in watchers)
                    if (!empty($task_name)) {
                        mysqli_query($mysqli,"INSERT INTO tasks SET task_name = '$task_name', task_ticket_id = $ticket_id");
                    }
                }
            }

            // Add Tasks from Template if Template was selected
            if($ticket_template_id) {
                if (mysqli_num_rows($sql_task_templates) > 0) {
                    while ($row = mysqli_fetch_assoc($sql_task_templates)) {
                        $task_order = intval($row['task_template_order']);
                        $task_name = escapeSql($row['task_template_name']);

                        mysqli_query($mysqli,"INSERT INTO tasks SET task_name = '$task_name', task_order = $task_order, task_ticket_id = $ticket_id");
                    }
                }
            }

            // Custom action/notif handler
            triggerCustomAction('ticket_create', $ticket_id);
        }

        logAudit("Ticket", "Bulk Create", "$session_name created $asset_count tickets for $asset_count");

        flashAlert("You created <b>$asset_count</b> tickets for the selected assets");

    }

    redirect();

}

if (isset($_POST['add_ticket_reply'])) {

    validateCSRFToken($_POST['csrf_token']);

    enforceUserPermission('module_support', 2);

    $ticket_id = intval($_POST['ticket_id']);
    $ticket_reply = $_POST['ticket_reply']; // Reply is SQL escaped below
    $ticket_status = intval($_POST['status']);
    $client_id = intval($_POST['client_id']);

    // Don't Enforce Client Access if Ticket doesn't have an assigned client
    if ($client_id) {
        enforceClientAccess();
    }

    // Time tracking, inputs & combine into string
    $hours = intval($_POST['hours']);
    $minutes = intval($_POST['minutes']);
    $seconds = intval($_POST['seconds']);
    $ticket_reply_time_worked = escapeSql(sprintf("%02d:%02d:%02d", $hours, $minutes, $seconds));

    // Defaults
    $send_email = 0;
    $ticket_reply_id = 0;
    if ($_POST['public_reply_type'] == 1 ){
        $ticket_reply_type = 'Public';
    } elseif ($_POST['public_reply_type'] == 2 ) {
        $ticket_reply_type = 'Public';
        $send_email = 1;
    } else {
        $ticket_reply_type = 'Internal';
    }
    // Add Signature to the end of the ticket reply if not Internal and if there is reply
    if ($ticket_reply !== '' && $ticket_reply_type !== 'Internal' && $send_email == 1) {
        $ticket_reply .= getFieldById('user_settings',$session_user_id,'user_config_signature', 'raw');
    }

    $ticket_reply = mysqli_escape_string($mysqli, $ticket_reply); // SQL Escape Ticket Reply

    // Update Ticket Status & updated at (in case status didn't change)
    mysqli_query($mysqli, "UPDATE tickets SET ticket_status = $ticket_status, ticket_updated_at = NOW() WHERE ticket_id = $ticket_id");

    // Resolve the ticket, if set
    if ($ticket_status == 4) {
        mysqli_query($mysqli, "UPDATE tickets SET ticket_resolved_at = NOW() WHERE ticket_id = $ticket_id");

        logAudit("Ticket", "Resolved", "$session_name resolved Ticket ticket ID $ticket_id", $client_id, $ticket_id);
    }

    // Process reply actions, if we have a reply to work with (e.g. we're not just editing the status)
    if (!empty($ticket_reply)) {

        // Add reply
        mysqli_query($mysqli, "INSERT INTO ticket_replies SET ticket_reply = '$ticket_reply', ticket_reply_time_worked = '$ticket_reply_time_worked', ticket_reply_type = '$ticket_reply_type', ticket_reply_by = $session_user_id, ticket_reply_ticket_id = $ticket_id");

        $ticket_reply_id = mysqli_insert_id($mysqli);

        // Get Ticket Details
        $ticket_sql = mysqli_query($mysqli, "SELECT contact_name, contact_email, ticket_prefix, ticket_number, ticket_subject, ticket_status, ticket_status_name, ticket_url_key, ticket_first_response_at, ticket_created_by, ticket_assigned_to, ticket_client_id
        FROM tickets
        LEFT JOIN clients ON ticket_client_id = client_id
        LEFT JOIN contacts ON ticket_contact_id = contact_id
        LEFT JOIN ticket_statuses ON ticket_status = ticket_status_id
        WHERE ticket_id = $ticket_id
        ");

        $row = mysqli_fetch_assoc($ticket_sql);

        $contact_name = escapeSql($row['contact_name']);
        $contact_email = escapeSql($row['contact_email']);
        $ticket_prefix = escapeSql($row['ticket_prefix']);
        $ticket_number = intval($row['ticket_number']);
        $ticket_subject = escapeSql($row['ticket_subject']);
        $ticket_status = intval($row['ticket_status']);
        $ticket_status_name = escapeSql($row['ticket_status_name']);
        $url_key = escapeSql($row['ticket_url_key']);
        $ticket_first_response_at = escapeSql($row['ticket_first_response_at']);
        $ticket_created_by = intval($row['ticket_created_by']);
        $ticket_assigned_to = intval($row['ticket_assigned_to']);
        $client_id = intval($row['ticket_client_id']);

        if ($client_id) {
            $client_uri = "&client_id=$client_id";
        } else {
            $client_uri = '';
        }

        // Sanitize Config vars from get_settings.php
        $config_ticket_from_name = escapeSql($config_ticket_from_name);
        $config_ticket_from_email = escapeSql($config_ticket_from_email);
        $config_base_url = escapeSql($config_base_url);

        $sql = mysqli_query($mysqli, "SELECT company_name, company_phone, company_phone_country_code FROM companies WHERE company_id = 1");
        $row = mysqli_fetch_assoc($sql);
        $company_name = escapeSql($row['company_name']);
        $company_phone = escapeSql(formatPhoneNumber($row['company_phone'], $row['company_phone_country_code']));

        // Send e-mail to client if public update & email is set up
        if ($ticket_reply_type == 'Public' && $send_email == 1 && (!empty($config_smtp_provider))) {

            // Slightly different email subject/text depending on if this update set auto-close

            if ($ticket_status == 4) {
                // Resolved
                $subject = "Ticket resolved - [$ticket_prefix$ticket_number] - $ticket_subject | (pending closure)";
                $body = "<i style=\'color: #808080\'>##- Please type your reply above this line -##</i><br><br>Hello $contact_name,<br><br>Your ticket regarding $ticket_subject has been marked as solved and is pending closure.<br><br>--------------------------------<br>$ticket_reply<br>--------------------------------<br><br>If your request/issue is resolved, you can simply ignore this email. If you need further assistance, please reply or <a href=\'https://$config_base_url/guest/guest_view_ticket.php?ticket_id=$ticket_id&url_key=$url_key\'>re-open</a> to let us know! <br><br>Ticket: $ticket_prefix$ticket_number<br>Subject: $ticket_subject<br>Status: $ticket_status_name<br>Portal: <a href=\'https://$config_base_url/guest/guest_view_ticket.php?ticket_id=$ticket_id&url_key=$url_key\'>View ticket</a><br><br>--<br>$company_name - Support<br>$config_ticket_from_email<br>$company_phone";
            } else {
                // Anything else
                $subject = "Ticket update - [$ticket_prefix$ticket_number] - $ticket_subject";
                $body = "<i style=\'color: #808080\'>##- Please type your reply above this line -##</i><br><br>Hello $contact_name,<br><br>Your ticket regarding $ticket_subject has been updated.<br><br>--------------------------------<br>$ticket_reply<br>--------------------------------<br><br>Ticket: $ticket_prefix$ticket_number<br>Subject: $ticket_subject<br>Status: $ticket_status_name<br>Portal: <a href=\'https://$config_base_url/guest/guest_view_ticket.php?ticket_id=$ticket_id&url_key=$url_key\'>View ticket</a><br><br>--<br>$company_name - Support<br>$config_ticket_from_email<br>$company_phone";
            }

            if (filter_var($contact_email, FILTER_VALIDATE_EMAIL)) {

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
            }

            // Also Email all the watchers
            $sql_watchers = mysqli_query($mysqli, "SELECT watcher_email FROM ticket_watchers WHERE watcher_ticket_id = $ticket_id");
            $body .= "<br><br>----------------------------------------<br>YOU ARE A COLLABORATOR ON THIS TICKET";
            while ($row = mysqli_fetch_assoc($sql_watchers)) {
                $watcher_email = escapeSql($row['watcher_email']);

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
            addToMailQueue($data);

        }
        //End Mail IF

        // Notification for assigned ticket user
        if ($session_user_id != $ticket_assigned_to && $ticket_assigned_to != 0) {
            mysqli_query($mysqli, "INSERT INTO notifications SET notification_type = 'Ticket', notification = '$session_name updated Ticket $ticket_prefix$ticket_number - Subject: $ticket_subject that is assigned to you', notification_action = '/agent/ticket.php?ticket_id=$ticket_id$client_uri', notification_client_id = $client_id, notification_user_id = $ticket_assigned_to");
        }

        // Notification for user that opened the ticket
        if ($session_user_id != $ticket_created_by && $ticket_created_by != 0) {
            mysqli_query($mysqli, "INSERT INTO notifications SET notification_type = 'Ticket', notification = '$session_name updated Ticket $ticket_prefix$ticket_number - Subject: $ticket_subject that you opened', notification_action = '/agent/ticket.php?ticket_id=$ticket_id$client_uri', notification_client_id = $client_id, notification_user_id = $ticket_created_by");
        }

        // Handle first response
        if (empty($ticket_first_response_at) && $ticket_reply_type == 'Public') {
            mysqli_query($mysqli, "UPDATE tickets SET ticket_first_response_at = NOW() WHERE ticket_id = $ticket_id");
        }

        // Custom action/notif handler
        if ($ticket_reply_type == 'Internal') {
            triggerCustomAction('ticket_reply_agent_internal', $ticket_id);
        } else {
            triggerCustomAction('reply_reply_agent_public', $ticket_id);
        }

        flashAlert("Ticket <strong>$ticket_prefix$ticket_number</strong> has been updated with your reply and was <strong>$ticket_reply_type</strong>");

    } else {
        flashAlert("Ticket updated");
    }

    logAudit("Ticket", "Reply", "$session_name replied to ticket $ticket_prefix$ticket_number - $ticket_subject and was a $ticket_reply_type reply", $client_id, $ticket_id);

    redirect();

}

if (isset($_POST['edit_ticket_reply'])) {

    validateCSRFToken($_POST['csrf_token']);

    enforceUserPermission('module_support', 2);

    $ticket_reply_id = intval($_POST['ticket_reply_id']);
    $ticket_reply = mysqli_real_escape_string($mysqli, $_POST['ticket_reply']);
    $ticket_reply_type = escapeSql($_POST['ticket_reply_type']);
    $ticket_reply_time_worked = escapeSql($_POST['time']);

    $client_id = intval($_POST['client_id']);

    // Don't Enforce Client Access if Ticket doesn't have an assigned client
    if ($client_id) {
        enforceClientAccess();
    }

    mysqli_query($mysqli, "UPDATE ticket_replies SET ticket_reply = '$ticket_reply', ticket_reply_type = '$ticket_reply_type', ticket_reply_time_worked = '$ticket_reply_time_worked' WHERE ticket_reply_id = $ticket_reply_id AND ticket_reply_type != 'Client'") or die(mysqli_error($mysqli));

    logAudit("Ticket", "Reply", "$session_name edited ticket_reply", $client_id, $ticket_reply_id);

    flashAlert("Ticket reply updated");

    redirect();

}

if (isset($_POST['redact_ticket_reply'])) {

    validateCSRFToken($_POST['csrf_token']);

    enforceUserPermission('module_support', 2);

    $ticket_reply_id = intval($_POST['ticket_reply_id']);
    $ticket_reply = mysqli_real_escape_string($mysqli, $_POST['ticket_reply']);

    $client_id = intval($_POST['client_id']);

    // Don't Enforce Client Access if Ticket doesn't have an assigned client
    if ($client_id) {
        enforceClientAccess();
    }

    mysqli_query($mysqli, "UPDATE ticket_replies SET ticket_reply = '$ticket_reply' WHERE ticket_reply_id = $ticket_reply_id");

    logAudit("Ticket", "Reply", "$session_name redacted ticket_reply", $client_id, $ticket_reply_id);

    flashAlert("Ticket reply redacted");

    redirect();

}

if (isset($_GET['archive_ticket_reply'])) {

    validateCSRFToken($_GET['csrf_token']);

    enforceUserPermission('module_support', 2);

    $ticket_reply_id = intval($_GET['archive_ticket_reply']);

    $ticket_id = intval(getFieldById('ticket_replies', $ticket_reply_id, 'ticket_reply_ticket_id'));
    $client_id = intval(getFieldById('tickets', $ticket_id, 'ticket_client_id'));

    // Don't Enforce Client Access if Ticket doesn't have an assigned client
    if ($client_id) {
        enforceClientAccess();
    }

    mysqli_query($mysqli, "UPDATE ticket_replies SET ticket_reply_archived_at = NOW() WHERE ticket_reply_id = $ticket_reply_id");

    logAudit("Ticket Reply", "Archive", "$session_name archived ticket_reply", $client_id, $ticket_reply_id);

    flashAlert("Ticket reply archived", 'error');

    redirect();

}

if (isset($_POST['merge_ticket'])) {

    validateCSRFToken($_POST['csrf_token']);

    enforceUserPermission('module_support', 2);

    $ticket_id = intval($_POST['ticket_id']); // Child ticket ID to be closed
    $merge_into_ticket_id = intval($_POST['merge_into_ticket_id']); // Parent ticket id
    $merge_comment = escapeSql($_POST['merge_comment']); // Merge comment
    $move_replies = intval($_POST['merge_move_replies']); // Whether to move replies to the new parent ticket
    $ticket_reply_type = 'Internal'; // Default all replies to internal

    // Get current ticket details
    $sql = mysqli_query($mysqli, "SELECT ticket_prefix, ticket_number, ticket_subject, ticket_details FROM tickets WHERE ticket_id = $ticket_id");
    if (mysqli_num_rows($sql) == 0) {
        flashAlert("No ticket with that ID found.", 'error');
        redirect();
    }
    // CURRENT ticket details
    $row = mysqli_fetch_assoc($sql);
    $ticket_prefix = escapeSql($row['ticket_prefix']);
    $ticket_number = intval($row['ticket_number']);
    $ticket_subject = escapeSql($row['ticket_subject']);
    $ticket_details = mysqli_escape_string($mysqli, $row['ticket_details']);
    $ticket_first_response_at = escapeSql($row['ticket_first_response_at']);

    // NEW PARENT ticket details
    // Get merge into ticket id (as it may differ from the number)
    $sql = mysqli_query($mysqli, "SELECT ticket_id, ticket_number, ticket_client_id FROM tickets WHERE ticket_id = $merge_into_ticket_id");
    if (mysqli_num_rows($sql) == 0) {
        flashAlert("Cannot merge into that ticket.", 'error');
        redirect();
    }
    $merge_row = mysqli_fetch_assoc($sql);
    $client_id = intval($merge_row['ticket_client_id']);
    // Don't Enforce Client Access if Ticket doesn't have an assigned client
    if ($client_id) {
        enforceClientAccess();
    }
    $merge_into_ticket_number = intval($merge_row['ticket_number']);
    if ($client_id) {
        $has_client = "&client_id=$client_id";
    } else {
        $has_client = "";
    }
    // Sanity check
    if ($ticket_id == $merge_into_ticket_id) {
        flashAlert("Cannot merge into the same ticket.", 'error');
        redirect();
    }

    // Move ticket replies from child > parent
    if ($move_replies) {
        mysqli_query($mysqli, "UPDATE ticket_replies SET ticket_reply_ticket_id = $merge_into_ticket_id WHERE ticket_reply_ticket_id = $ticket_id");
    }

    // Update current ticket
    if (empty($ticket_first_response_at)) {
        mysqli_query($mysqli, "UPDATE tickets SET ticket_first_response_at = NOW() WHERE ticket_id = $ticket_id");
    }

    mysqli_query($mysqli, "INSERT INTO ticket_replies SET ticket_reply = 'Ticket $ticket_prefix$ticket_number merged into <a href=\"ticket.php?ticket_id=$merge_into_ticket_id\">$ticket_prefix$merge_into_ticket_number</a>. Comment: $merge_comment', ticket_reply_time_worked = '00:01:00', ticket_reply_type = '$ticket_reply_type', ticket_reply_by = $session_user_id, ticket_reply_ticket_id = $ticket_id");

    mysqli_query($mysqli, "UPDATE tickets SET ticket_status = '5', ticket_resolved_at = NOW(), ticket_closed_at = NOW(), ticket_closed_by = $session_user_id WHERE ticket_id = $ticket_id") or die(mysqli_error($mysqli));

    //Update new parent ticket
    mysqli_query($mysqli, "INSERT INTO ticket_replies SET ticket_reply = 'Ticket $ticket_prefix$ticket_number was merged into this ticket with comment: $merge_comment.<br><br><b>$ticket_subject</b><br>$ticket_details', ticket_reply_time_worked = '00:01:00', ticket_reply_type = '$ticket_reply_type', ticket_reply_by = $session_user_id, ticket_reply_ticket_id = $merge_into_ticket_id");

    mysqli_query($mysqli, "UPDATE tickets SET ticket_updated_at = NOW() WHERE ticket_id = $merge_into_ticket_id");

    logAudit("Ticket", "Merged", "$session_name Merged ticket $ticket_prefix$ticket_number into $ticket_prefix$merge_into_ticket_number");

    triggerCustomAction('ticket_merge', $ticket_id);

    flashAlert("Ticket merged into $ticket_prefix$merge_into_ticket_number");

    redirect("ticket.php?ticket_id=$merge_into_ticket_id$has_client");

}

if (isset($_POST['change_client_ticket'])) {

    validateCSRFToken($_POST['csrf_token']);

    enforceUserPermission('module_support', 2);

    $ticket_id = intval($_POST['ticket_id']);
    $client_id = intval($_POST['new_client_id']);
    $contact_id = intval($_POST['new_contact_id']);

    // Don't Enforce Client Access if Ticket doesn't have an assigned client
    if ($client_id) {
        enforceClientAccess();
    }

    // Set any/all existing replies to internal
    mysqli_query($mysqli, "UPDATE ticket_replies SET ticket_reply_type = 'Internal' WHERE ticket_reply_ticket_id = $ticket_id");

    // Update ticket client & contact
    mysqli_query($mysqli, "UPDATE tickets SET ticket_client_id = $client_id, ticket_contact_id = $contact_id WHERE ticket_id = $ticket_id LIMIT 1");

    logAudit("Ticket", "Change", "$session_name changed ticket client", $client_id, $ticket_id);

    triggerCustomAction('ticket_update', $ticket_id);

    flashAlert("Ticket client updated");

    redirect();

}

if (isset($_GET['resolve_ticket'])) {

    validateCSRFToken($_GET['csrf_token']);

    enforceUserPermission('module_support', 2);

    $ticket_id = intval($_GET['resolve_ticket']);

    $sql = mysqli_query($mysqli, "SELECT * FROM tickets WHERE ticket_id = $ticket_id");
    $row = mysqli_fetch_assoc($sql);
    $ticket_prefix = escapeSql($row['ticket_prefix']);
    $ticket_number = intval($row['ticket_number']);
    $ticket_first_response_at = escapeSql($row['ticket_first_response_at']);
    $client_id = intval($row['ticket_client_id']);

    // Don't Enforce Client Access if Ticket doesn't have an assigned client
    if ($client_id) {
        enforceClientAccess();
    }

    // Mark FR
    if (empty($ticket_first_response_at)) {
        mysqli_query($mysqli, "UPDATE tickets SET ticket_first_response_at = NOW() WHERE ticket_id = $ticket_id");
    }

    // Resolve
    mysqli_query($mysqli, "UPDATE tickets SET ticket_status = 4, ticket_resolved_at = NOW() WHERE ticket_id = $ticket_id");

    logAudit("Ticket", "Resolved", "$session_name resolved ticket $ticket_prefix$ticket_number (ID: $ticket_id)", $client_id, $ticket_id);

    triggerCustomAction('ticket_resolve', $ticket_id);

    // Client notification email
    if ((!empty($config_smtp_provider)) && $config_ticket_client_general_notifications == 1) {

        // Get details
        $ticket_sql = mysqli_query($mysqli, "SELECT contact_name, contact_email, ticket_prefix, ticket_number, ticket_subject, ticket_status_name, ticket_assigned_to, ticket_url_key FROM tickets
            LEFT JOIN clients ON ticket_client_id = client_id
            LEFT JOIN contacts ON ticket_contact_id = contact_id
            LEFT JOIN ticket_statuses ON ticket_status = ticket_status_id
            WHERE ticket_id = $ticket_id
        ");
        $row = mysqli_fetch_assoc($ticket_sql);

        $contact_name = escapeSql($row['contact_name']);
        $contact_email = escapeSql($row['contact_email']);
        $ticket_prefix = escapeSql($row['ticket_prefix']);
        $ticket_number = intval($row['ticket_number']);
        $ticket_subject = escapeSql($row['ticket_subject']);
        $ticket_assigned_to = intval($row['ticket_assigned_to']);
        $ticket_status = escapeSql($row['ticket_status_name']);
        $url_key = escapeSql($row['ticket_url_key']);

        // Sanitize Config vars from get_settings.php
        $config_ticket_from_name = escapeSql($config_ticket_from_name);
        $config_ticket_from_email = escapeSql($config_ticket_from_email);
        $config_base_url = escapeSql($config_base_url);

        // Get Company Info
        $sql = mysqli_query($mysqli, "SELECT company_name, company_phone, company_phone_country_code FROM companies WHERE company_id = 1");
        $row = mysqli_fetch_assoc($sql);
        $company_name = escapeSql($row['company_name']);
        $company_phone = escapeSql(formatPhoneNumber($row['company_phone'], $row['company_phone_country_code']));

        // EMAIL
        $subject = "Ticket resolved - [$ticket_prefix$ticket_number] - $ticket_subject | (pending closure)";
        $body = "<i style=\'color: #808080\'>##- Please type your reply above this line -##</i><br><br>Hello $contact_name,<br><br>Your ticket regarding $ticket_subject has been marked as solved and is pending closure.<br><br>If your request/issue is resolved, you can simply ignore this email. If you need further assistance, please reply or <a href=\'https://$config_base_url/guest/guest_view_ticket.php?ticket_id=$ticket_id&url_key=$url_key\'>re-open</a> to let us know! <br><br>Ticket: $ticket_prefix$ticket_number<br>Subject: $ticket_subject<br>Status: $ticket_status<br>Portal: <a href=\'https://$config_base_url/guest/guest_view_ticket.php?ticket_id=$ticket_id&url_key=$url_key\'>View ticket</a><br><br>--<br>$company_name - Support<br>$config_ticket_from_email<br>$company_phone";

        // Check email valid
        if (filter_var($contact_email, FILTER_VALIDATE_EMAIL)) {

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
        }

        // Also Email all the watchers
        $sql_watchers = mysqli_query($mysqli, "SELECT watcher_email FROM ticket_watchers WHERE watcher_ticket_id = $ticket_id");
        $body .= "<br><br>----------------------------------------<br>YOU ARE A COLLABORATOR ON THIS TICKET";
        while ($row = mysqli_fetch_assoc($sql_watchers)) {
            $watcher_email = escapeSql($row['watcher_email']);

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
        addToMailQueue($data);
    }
    //End Mail IF

    flashAlert("Ticket resolved");

    redirect();

}

if (isset($_GET['close_ticket'])) {

    validateCSRFToken($_GET['csrf_token']);

    enforceUserPermission('module_support', 2);

    $ticket_id = intval($_GET['close_ticket']);
    $client_id = intval(getFieldById('tickets', $ticket_id, 'ticket_client_id'));

    // Don't Enforce Client Access if Ticket doesn't have an assigned client
    if ($client_id) {
        enforceClientAccess();
    }

    mysqli_query($mysqli, "UPDATE tickets SET ticket_status = 5, ticket_closed_at = NOW(), ticket_closed_by = $session_user_id WHERE ticket_id = $ticket_id") or die(mysqli_error($mysqli));

    mysqli_query($mysqli, "INSERT INTO ticket_replies SET ticket_reply = 'Ticket closed.', ticket_reply_type = 'Internal', ticket_reply_time_worked = '00:01:00', ticket_reply_by = $session_user_id, ticket_reply_ticket_id = $ticket_id");

    logAudit("Ticket", "Closed", "$session_name closed ticket ID $ticket_id", $client_id, $ticket_id);

    triggerCustomAction('ticket_close', $ticket_id);

    // Client notification email
    if ((!empty($config_smtp_provider)) && $config_ticket_client_general_notifications == 1) {

        // Get details
        $ticket_sql = mysqli_query($mysqli, "SELECT contact_name, contact_email, ticket_prefix, ticket_number, ticket_subject, ticket_url_key FROM tickets
            LEFT JOIN clients ON ticket_client_id = client_id
            LEFT JOIN contacts ON ticket_contact_id = contact_id
            WHERE ticket_id = $ticket_id
        ");
        $row = mysqli_fetch_assoc($ticket_sql);

        $contact_name = escapeSql($row['contact_name']);
        $contact_email = escapeSql($row['contact_email']);
        $ticket_prefix = escapeSql($row['ticket_prefix']);
        $ticket_number = intval($row['ticket_number']);
        $ticket_subject = escapeSql($row['ticket_subject']);
        $url_key = escapeSql($row['ticket_url_key']);

        // Sanitize Config vars from get_settings.php
        $config_ticket_from_name = escapeSql($config_ticket_from_name);
        $config_ticket_from_email = escapeSql($config_ticket_from_email);
        $config_base_url = escapeSql($config_base_url);

        // Get Company Info
        $sql = mysqli_query($mysqli, "SELECT company_name, company_phone, company_phone_country_code FROM companies WHERE company_id = 1");
        $row = mysqli_fetch_assoc($sql);
        $company_name = escapeSql($row['company_name']);
        $company_phone = escapeSql(formatPhoneNumber($row['company_phone'], $row['company_phone_country_code']));

        // EMAIL
        $subject = "Ticket closed - [$ticket_prefix$ticket_number] - $ticket_subject | (do not reply)";
        $body = "Hello $contact_name,<br><br>Your ticket regarding \"$ticket_subject\" has been closed. <br><br> We hope the request/issue was resolved to your satisfaction, please provide your feedback <a href=\'https://$config_base_url/guest/guest_view_ticket.php?ticket_id=$ticket_id&url_key=$url_key\'>here</a>. <br>If you need further assistance, please raise a new ticket using the below details. Please do not reply to this email. <br><br>Ticket: $ticket_prefix$ticket_number<br>Subject: $ticket_subject<br>Portal: https://$config_base_url/client/ticket.php?id=$ticket_id<br><br>--<br>$company_name - Support<br>$config_ticket_from_email<br>$company_phone";

        // Check email valid
        if (filter_var($contact_email, FILTER_VALIDATE_EMAIL)) {

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
        }

        // Also Email all the watchers
        $sql_watchers = mysqli_query($mysqli, "SELECT watcher_email FROM ticket_watchers WHERE watcher_ticket_id = $ticket_id");
        $body .= "<br><br>----------------------------------------<br>YOU ARE A COLLABORATOR ON THIS TICKET";
        while ($row = mysqli_fetch_assoc($sql_watchers)) {
            $watcher_email = escapeSql($row['watcher_email']);

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
        addToMailQueue($data);
    }
    //End Mail IF

    flashAlert("Ticket Closed, this cannot not be reopened but you may start another one");

    redirect();

}

if (isset($_GET['reopen_ticket'])) {

    validateCSRFToken($_GET['csrf_token']);

    enforceUserPermission('module_support', 2);

    $ticket_id = intval($_GET['reopen_ticket']);

    $client_id = intval(getFieldById('tickets', $ticket_id, 'ticket_client_id'));

    // Don't Enforce Client Access if Ticket doesn't have an assigned client
    if ($client_id) {
        enforceClientAccess();
    }

    mysqli_query($mysqli, "UPDATE tickets SET ticket_status = 2, ticket_resolved_at = NULL WHERE ticket_id = $ticket_id");

    logAudit("Ticket", "Reopened", "$session_name reopened ticket ID $ticket_id", $client_id, $ticket_id);

    triggerCustomAction('ticket_update', $ticket_id);

    flashAlert("Ticket re-opened");

    redirect();

}

if (isset($_POST['add_invoice_from_ticket'])) {

    validateCSRFToken($_POST['csrf_token']);

    enforceUserPermission('module_support', 2);
    enforceUserPermission('module_sales', 2);

    $invoice_id = intval($_POST['invoice_id']);
    $ticket_id = intval($_POST['ticket_id']);
    $date = escapeSql($_POST['date']);
    $category = intval($_POST['category']);
    $scope = escapeSql($_POST['scope']);

    $sql = mysqli_query(
        $mysqli,
        "SELECT * FROM tickets
        LEFT JOIN clients ON ticket_client_id = client_id
        LEFT JOIN contacts ON ticket_contact_id = contact_id
        LEFT JOIN assets ON ticket_asset_id = asset_id
        LEFT JOIN locations ON ticket_location_id = location_id
        WHERE ticket_id = $ticket_id"
    );

    $row = mysqli_fetch_assoc($sql);
    $client_id = intval($row['client_id']);
    $client_net_terms = intval($row['client_net_terms']);
    if ($client_net_terms == 0) {
        $client_net_terms = $config_default_net_terms;
    }

    $ticket_prefix = escapeSql($row['ticket_prefix']);
    $ticket_number = intval($row['ticket_number']);
    $ticket_category = escapeSql($row['ticket_category']);
    $ticket_subject = escapeSql($row['ticket_subject']);
    $ticket_created_at = escapeSql($row['ticket_created_at']);
    $ticket_updated_at = escapeSql($row['ticket_updated_at']);
    $ticket_closed_at = escapeSql($row['ticket_closed_at']);

    $contact_id = intval($row['contact_id']);
    $contact_name = escapeSql($row['contact_name']);
    $contact_email = escapeSql($row['contact_email']);

    $asset_id = intval($row['asset_id']);

    $location_name = escapeSql($row['location_name']);

    enforceClientAccess();

    if ($invoice_id == 0) {

        $invoice_prefix = escapeSql($config_invoice_prefix);

        // Atomically increment and get the new invoice number
        mysqli_query($mysqli, "
            UPDATE settings
            SET
                config_invoice_next_number = LAST_INSERT_ID(config_invoice_next_number),
                config_invoice_next_number = config_invoice_next_number + 1
            WHERE company_id = 1
        ");

        $invoice_number = mysqli_insert_id($mysqli);

        //Generate a unique URL key for clients to access
        $url_key = randomString(32);

        mysqli_query($mysqli, "INSERT INTO invoices SET invoice_prefix = '$config_invoice_prefix', invoice_number = $invoice_number, invoice_scope = '$scope', invoice_date = '$date', invoice_due = DATE_ADD('$date', INTERVAL $client_net_terms day), invoice_currency_code = '$session_company_currency', invoice_category_id = $category, invoice_status = 'Draft', invoice_url_key = '$url_key', invoice_client_id = $client_id");
        $invoice_id = mysqli_insert_id($mysqli);
    } else {
        $sql_invoice = mysqli_query($mysqli, "SELECT invoice_prefix, invoice_number FROM invoices WHERE invoice_id = $invoice_id");
        $row = mysqli_fetch_assoc($sql_invoice);
        $invoice_prefix = escapeSql($row['invoice_prefix']);
        $invoice_number = intval($row['invoice_number']);
    }

    //Add Item
    $item_name = escapeSql($_POST['item_name']);
    $item_description = escapeSql($_POST['item_description']);
    $qty = floatval($_POST['qty']);
    $price = floatval($_POST['price']);
    $tax_id = intval($_POST['tax_id']);

    $subtotal = $price * $qty;

    if ($tax_id > 0) {
        $sql = mysqli_query($mysqli, "SELECT * FROM taxes WHERE tax_id = $tax_id");
        $row = mysqli_fetch_assoc($sql);
        $tax_percent = floatval($row['tax_percent']);
        $tax_amount = $subtotal * $tax_percent / 100;
    } else {
        $tax_amount = 0;
    }

    $total = $subtotal + $tax_amount;

    mysqli_query($mysqli, "INSERT INTO invoice_items SET item_name = '$item_name', item_description = '$item_description', item_quantity = $qty, item_price = $price, item_subtotal = $subtotal, item_tax = $tax_amount, item_total = $total, item_order = 1, item_tax_id = $tax_id, item_invoice_id = $invoice_id");

    //Update Invoice Balances

    $sql = mysqli_query($mysqli, "SELECT * FROM invoices WHERE invoice_id = $invoice_id");
    $row = mysqli_fetch_assoc($sql);

    $new_invoice_amount = floatval($row['invoice_amount']) + $total;

    mysqli_query($mysqli, "UPDATE invoices SET invoice_amount = $new_invoice_amount WHERE invoice_id = $invoice_id");

    mysqli_query($mysqli, "INSERT INTO history SET history_status = 'Draft', history_description = 'Invoice created from Ticket $ticket_prefix$ticket_number', history_invoice_id = $invoice_id");

    // Add internal note to ticket, and link to invoice in database
    mysqli_query($mysqli, "INSERT INTO ticket_replies SET ticket_reply = 'Created invoice <a href=\"invoice.php?invoice_id=$invoice_id\">$config_invoice_prefix$invoice_number</a> for this ticket.', ticket_reply_type = 'Internal', ticket_reply_time_worked = '00:01:00', ticket_reply_by = $session_user_id, ticket_reply_ticket_id = $ticket_id");

    mysqli_query($mysqli, "UPDATE tickets SET ticket_invoice_id = $invoice_id WHERE ticket_id = $ticket_id");

    logAudit("Invoice", "Create", "$session_name created invoice $invoice_prefix$invoice_number from Ticket $ticket_prefix$ticket_number", $client_id, $invoice_id);

    flashAlert("Invoice $invoice_prefix$invoice_number created from ticket");

    redirect("invoice.php?invoice_id=$invoice_id");

}

if (isset($_POST['add_quote_from_ticket'])) {

    validateCSRFToken($_POST['csrf_token']);

    enforceUserPermission('module_support', 2);
    enforceUserPermission('module_sales', 2);

    require_once 'quote_model.php';

    $ticket_id = intval($_POST['ticket_id']);
    $item_name = escapeSql($_POST['item_name']);
    $item_description = escapeSql($_POST['item_description']);
    $qty = floatval($_POST['qty']);
    $price = floatval($_POST['price']);
    $tax_id = intval($_POST['tax_id']);

    // Totals
    $subtotal = $price * $qty;
    $tax_amount = 0;
    if ($tax_id > 0) {
        $sql = mysqli_query($mysqli, "SELECT * FROM taxes WHERE tax_id = $tax_id");
        $row = mysqli_fetch_assoc($sql);
        $tax_percent = floatval($row['tax_percent']);
        $tax_amount = $subtotal * $tax_percent / 100;
    }
    $total = floatval($subtotal + $tax_amount);

    // Ticket info
    $sql = mysqli_query(
        $mysqli,
        "SELECT ticket_prefix, ticket_number, ticket_client_id FROM tickets WHERE ticket_id = $ticket_id LIMIT 1"
    );
    $row = mysqli_fetch_assoc($sql);
    $ticket_prefix = escapeSql($row['ticket_prefix']);
    $ticket_number = intval($row['ticket_number']);
    $client_id = intval($row['ticket_client_id']);

    enforceClientAccess();

    // Atomically increment and get the new quote number
    mysqli_query($mysqli, "
        UPDATE settings
        SET
            config_quote_next_number = LAST_INSERT_ID(config_quote_next_number),
            config_quote_next_number = config_quote_next_number + 1
        WHERE company_id = 1
    ");

    $quote_number = mysqli_insert_id($mysqli);

    //Generate a unique URL key for clients to access
    $quote_url_key = randomString(32);

    mysqli_query($mysqli,"INSERT INTO quotes SET quote_prefix = '$config_quote_prefix', quote_number = $quote_number, quote_scope = '$scope', quote_date = '$date', quote_expire = '$expire', quote_amount = $total, quote_currency_code = '$session_company_currency', quote_category_id = $category, quote_status = 'Draft', quote_url_key = '$quote_url_key', quote_client_id = $client_id");

    $quote_id = mysqli_insert_id($mysqli);

    // Add line item
    mysqli_query($mysqli, "INSERT INTO quote_items SET item_name = '$item_name', item_description = '$item_description', item_quantity = $qty, item_price = $price, item_subtotal = $subtotal, item_tax = $tax_amount, item_total = $total, item_order = 1, item_tax_id = $tax_id, item_quote_id = $quote_id");

    // Add internal note to ticket, and link to invoice in database
    mysqli_query($mysqli, "INSERT INTO ticket_replies SET ticket_reply = 'Created quote <a href=\"quote.php?quote_id=$quote_id\">$config_quote_prefix$quote_number</a> for this ticket.', ticket_reply_type = 'Internal', ticket_reply_time_worked = '00:01:00', ticket_reply_by = $session_user_id, ticket_reply_ticket_id = $ticket_id");
    mysqli_query($mysqli, "UPDATE tickets SET ticket_quote_id = $quote_id WHERE ticket_id = $ticket_id LIMIT 1");

    // Logging + redirects
    mysqli_query($mysqli,"INSERT INTO history SET history_status = 'Draft', history_description = 'Quote created from Ticket $ticket_prefix$ticket_number!', history_quote_id = $quote_id");
    logAudit("Quote", "Create", "$session_name created quote $config_quote_prefix$quote_number from ticket $ticket_prefix$ticket_number", $client_id, $quote_id);

    triggerCustomAction('quote_create', $quote_id);

    flashAlert("Quote <strong>$config_quote_prefix$quote_number</strong> created");
    redirect("quote.php?quote_id=$quote_id");

}

if (isset($_POST['export_tickets_csv'])) {

    validateCSRFToken($_POST['csrf_token']);

    enforceUserPermission('module_support', 2);

    if ($_POST['client_id']) {
        $client_id = intval($_POST['client_id']);
        $client_query = "WHERE ticket_client_id = $client_id";
        $client_name = getFieldById('clients', $client_id, 'client_name');
        $file_name_prepend = "$client_name-";
    } else {
        $client_query = '';
        $client_name = '';
        $file_name_prepend = "$session_company_name-";
    }

    $sql = mysqli_query(
        $mysqli,
        "SELECT * FROM tickets
        LEFT JOIN ticket_statuses ON ticket_status = ticket_status_id
        $client_query ORDER BY ticket_number ASC"
    );

    if ($sql->num_rows > 0) {
        $delimiter = ",";
        $enclosure = '"';
        $escape    = '\\';   // backslash
        $filename = sanitizeFilename($file_name_prepend . "Tickets-" . date('Y-m-d_H-i-s') . ".csv");

        //create a file pointer
        $f = fopen('php://memory', 'w');

        //set column headers
        $fields = array('Ticket Number', 'Priority', 'Status', 'Subject', 'Date Opened', 'Date Resolved', 'Date Closed');
        fputcsv($f, $fields, $delimiter, $enclosure, $escape);

        //output each row of the data, format line as csv and write to file pointer
        while ($row = $sql->fetch_assoc()) {
            $lineData = array($config_ticket_prefix . $row['ticket_number'], $row['ticket_priority'], $row['ticket_status_name'], $row['ticket_subject'], $row['ticket_created_at'], $row['ticket_resolved_at'], $row['ticket_closed_at']);
            fputcsv($f, $lineData, $delimiter, $enclosure, $escape);
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

if (isset($_POST['edit_ticket_billable_status'])) {

    validateCSRFToken($_POST['csrf_token']);

    enforceUserPermission('module_support', 2);
    enforceUserPermission('module_sales', 2);

    $ticket_id = intval($_POST['ticket_id']);
    $billable_status = intval($_POST['billable_status']);
    if ($billable_status == 0 ) {
        $billable_wording = "Not";
    }

    // Get ticket details for logging
    $sql = mysqli_query($mysqli, "SELECT ticket_prefix, ticket_number, ticket_client_id FROM tickets WHERE ticket_id = $ticket_id");
    $row = mysqli_fetch_assoc($sql);
    $ticket_prefix = escapeSql($row['ticket_prefix']);
    $ticket_number = intval($row['ticket_number']);
    $client_id = intval($row['ticket_client_id']);

    // Don't Enforce Client Access if Ticket doesn't have an assigned client
    if ($client_id) {
        enforceClientAccess();
    }

    mysqli_query($mysqli,"UPDATE tickets SET ticket_billable = $billable_status WHERE ticket_id = $ticket_id");

    logAudit("Ticket", "Edit", "$session_name marked ticket $ticket_prefix$ticket_number as $billable_wording Billable", $client_id, $ticket_id);

    flashAlert("Ticket marked <strong>$billable_wording Billable</strong>");

    redirect();

}

if (isset($_POST['edit_ticket_schedule'])) {

    validateCSRFToken($_POST['csrf_token']);

    enforceUserPermission('module_support', 2);

    $ticket_id = intval($_POST['ticket_id']);
    $onsite = intval($_POST['onsite']);
    $schedule = escapeSql($_POST['scheduled_date_time']);
    $ticket_link = "client/ticket.php?id=$ticket_id";
    $full_ticket_url = "https://$config_base_url/client/ticket.php?id=$ticket_id";
    $ticket_link_html = "<a href=\"$full_ticket_url\">$ticket_link</a>";

    $client_id = intval(getFieldById('tickets', $ticket_id, 'ticket_client_id'));
    // Don't Enforce Client Access if Ticket doesn't have an assigned client
    if ($client_id) {
        enforceClientAccess();
    }

    mysqli_query($mysqli,"UPDATE tickets
        SET ticket_schedule = '$schedule', ticket_onsite = $onsite
        WHERE ticket_id = $ticket_id"
    );

    // Check for other conflicting scheduled items based on 2 hr window
    //TODO make this configurable
    $start = date('Y-m-d H:i:s', strtotime($schedule) - 7200);
    $end = date('Y-m-d H:i:s', strtotime($schedule) + 7200);
    $sql = mysqli_query($mysqli, "SELECT * FROM tickets WHERE ticket_schedule BETWEEN '$start' AND '$end' AND ticket_id != $ticket_id");
    if (mysqli_num_rows($sql) > 0) {
        $conflicting_tickets = [];
        while ($row = mysqli_fetch_assoc($sql)) {
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

    $row = mysqli_fetch_assoc($sql);

    $client_name = escapeSql($row['client_name']);
    $ticket_details = escapeSql($row['ticket_details']);
    $contact_name = escapeSql($row['contact_name']);
    $contact_email = escapeSql($row['contact_email']);
    $ticket_prefix = escapeSql($row['ticket_prefix']);
    $ticket_number = intval($row['ticket_number']);
    $ticket_subject = escapeSql($row['ticket_subject']);
    $user_name = escapeSql($row['user_name']);
    $user_email = escapeSql($row['user_email']);
    $cal_subject = $ticket_number . ": " . $client_name . " - " . $ticket_subject;
    $ticket_details_truncated = substr($ticket_details, 0, 100);
    $cal_description = $ticket_details_truncated . " - " . $full_ticket_url;
    $cal_location = escapeSql($row["location_address"]);
    $email_datetime = date('l, F j, Y \a\t g:ia', strtotime($schedule));

    if ($client_id) {
        $client_uri = "&client_id=$client_id";
    } else {
        $client_uri = '';
    }

    // Sanitize Config Vars
    $config_ticket_from_email = escapeSql($config_ticket_from_email);
    $config_ticket_from_name = escapeSql($config_ticket_from_name);
    $session_company_name = escapeSql($session_company_name);


    /// Create iCal event
    $cal_str = createiCalStr($schedule, $cal_subject, $cal_description, $cal_location);

    // Notify the agent of the scheduled work
    $data[] = [
            'from' => $config_ticket_from_email,
            'from_name' => $config_ticket_from_name,
            'recipient' => $user_email,
            'recipient_name' => $user_name,
            'subject' => "Ticket Scheduled - [$ticket_prefix$ticket_number] - $ticket_subject",
            'body' => "Hello, " . $user_name . "<br><br>The ticket regarding $ticket_subject has been scheduled for $email_datetime.<br><br>--------------------------------<br><a href=\"https://$config_base_url/agent/ticket.php?ticket_id=$ticket_id$client_uri\">$ticket_link</a><br>--------------------------------<br><br>Please do not reply to this email. <br><br>Ticket: $ticket_prefix$ticket_number<br>Subject: $ticket_subject<br>Portal: https://$config_base_url/agent/ticket.php?ticket_id=$ticket_id$client_uri<br><br>~<br>$session_company_name<br>Support Department<br>$config_ticket_from_email",
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
                            <a href='https://$config_base_url/client/ticket.php?id=$ticket_id' class='link-button'>Access your ticket here</a>
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

        while ($row = mysqli_fetch_assoc($sql_watchers)) {
            $watcher_email = escapeSql($row['watcher_email']);
            $data[] = [
                'from' => $config_ticket_from_email,
                'from_name' => $config_ticket_from_name,
                'recipient' => $watcher_email,
                'recipient_name' => $watcher_email,
                'subject' => "Ticket Scheduled - [$ticket_prefix$ticket_number] - $ticket_subject",
                'body' => mysqli_escape_string($mysqli, escapeHtml("<div class='header'>
            Hello,
        </div>
        The ticket regarding $ticket_subject has been scheduled for $email_datetime.
        <br><br>
        <a href='https://$config_base_url/client/ticket.php?id=$ticket_id' class='link-button'>$ticket_link</a>
        <br><br>
        Please do not reply to this email.
        <br><br>
        <strong>Ticket:</strong> $ticket_prefix$ticket_number<br>
        <strong>Subject:</strong> $ticket_subject<br>
        <strong>Portal:</strong> <a href='https://$config_base_url/client/ticket.php?id=$ticket_id'>Access the ticket here</a>
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
    $response = addToMailQueue($data);

    // Update ticket reply
    $ticket_reply_note = "Ticket scheduled for $email_datetime " . (boolval($onsite) ? '(onsite).' : '(remote).');
    mysqli_query($mysqli, "INSERT INTO ticket_replies SET ticket_reply = '$ticket_reply_note', ticket_reply_type = 'Internal', ticket_reply_time_worked = '00:01:00', ticket_reply_by = $session_user_id, ticket_reply_ticket_id = $ticket_id");

    logAudit("Ticket", "Edit", "$session_name edited ticket schedule", $client_id, $ticket_id);

    triggerCustomAction('ticket_schedule', $ticket_id);

    if (empty($conflicting_tickets)) {
        flashAlert("Ticket scheduled for $email_datetime");
        redirect();
    } else {
        $_SESSION['alert_type'] = "error";
        flashAlert("Ticket scheduled for $email_datetime. Yet there are conflicting tickets scheduled for the same time: <br>" . implode(", <br>", $conflicting_tickets), 'error');
        redirect("calendar.php");
    }

}

if (isset($_GET['cancel_ticket_schedule'])) {

    validateCSRFToken($_GET['csrf_token']);

    enforceUserPermission('module_support', 2);

    $ticket_id = intval($_GET['cancel_ticket_schedule']);

    $sql = mysqli_query($mysqli, "SELECT * FROM tickets WHERE ticket_id = $ticket_id");
    $row = mysqli_fetch_assoc($sql);

    $client_id = intval($row['ticket_client_id']);
    $ticket_prefix = escapeSql($row['ticket_prefix']);
    $ticket_number = intval($row['ticket_number']);
    $ticket_subject = escapeSql($row['ticket_subject']);
    $ticket_schedule = escapeSql($row['ticket_schedule']);
    $ticket_cal_str = escapeSql($row['ticket_cal_str']);

    // Don't Enforce Client Access if Ticket doesn't have an assigned client
    if ($client_id) {
        enforceClientAccess();
    }

    if ($client_id) {
        $client_uri = "&client_id=$client_id";
    } else {
        $client_uri = '';
    }

    mysqli_query($mysqli, "UPDATE tickets SET ticket_schedule = NULL WHERE ticket_id = $ticket_id");

    // Sanitize Config Vars
    $config_ticket_from_email = escapeSql($config_ticket_from_email);
    $config_ticket_from_name = escapeSql($config_ticket_from_name);
    $session_company_name = escapeSql($session_company_name);

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
    $row = mysqli_fetch_assoc($sql);

    $client_id = intval($row['ticket_client_id']);
    $client_name = escapeSql($row['client_name']);
    $ticket_details = escapeSql($row['ticket_details']);
    $contact_name = escapeSql($row['contact_name']);
    $contact_email = escapeSql($row['contact_email']);
    $ticket_prefix = escapeSql($row['ticket_prefix']);
    $ticket_number = intval($row['ticket_number']);
    $ticket_subject = escapeSql($row['ticket_subject']);
    $user_name = escapeSql($row['user_name']);
    $user_email = escapeSql($row['user_email']);

    // Notify the agent of the cancellation
    $data[] = [
            // User Email
            'from' => $config_ticket_from_email,
            'from_name' => $config_ticket_from_name,
            'recipient' => $user_email,
            'recipient_name' => $user_name,
            'subject' => "Ticket Schedule Cancelled - [$ticket_prefix$ticket_number] - $ticket_subject",
            'body' => "Hello, " . $user_name . "<br><br>Scheduled work for the ticket regarding $ticket_subject has been cancelled.<br><br>--------------------------------<br><a href=\"https://$config_base_url/agent/ticket.php?ticket_id=$ticket_id$client_uri\">$ticket_link</a><br>--------------------------------<br><br>Please do not reply to this email. <br><br>Ticket: $ticket_prefix$ticket_number<br>Subject: $ticket_subject<br>Portal: https://$config_base_url/agent/ticket.php?id=$ticket_id&client_id=$client_id<br><br>~<br>$session_company_name<br>Support Department<br>$config_ticket_from_email",
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
                            <a href='https://$config_base_url/client/ticket.php?id=$ticket_id' class='link-button'>Access your ticket here</a>
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
            $watcher_email = escapeSql($row['watcher_email']);
            $data[] = [
                'from' => $config_ticket_from_email,
                'from_name' => $config_ticket_from_name,
                'recipient' => $watcher_email,
                'recipient_name' => $watcher_email,
                'subject' => "Ticket Schedule Cancelled - [$ticket_prefix$ticket_number] - $ticket_subject",
                'body' => mysqli_escape_string($mysqli, escapeHtml("<div class='header'>
            Hello,
        </div>
        Scheduled work for the ticket regarding $ticket_subject has been cancelled.
        <br><br>
        <a href='https://$config_base_url/client/ticket.php?id=$ticket_id' class='link-button'>$ticket_link</a>
        <br><br>
        Please do not reply to this email.
        <br><br>
        <strong>Ticket:</strong> $ticket_prefix$ticket_number<br>
        <strong>Subject:</strong> $ticket_subject<br>
        <strong>Portal:</strong> <a href='https://$config_base_url/client/ticket.php?id=$ticket_id'>Access the ticket here</a>
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
    addToMailQueue($data);

    // Update ticket reply
    $ticket_reply_note = "Ticket schedule cancelled.";
    mysqli_query($mysqli, "INSERT INTO ticket_replies SET ticket_reply = '$ticket_reply_note', ticket_reply_type = 'Internal', ticket_reply_time_worked = '00:01:00', ticket_reply_by = $session_user_id, ticket_reply_ticket_id = $ticket_id");

    logAudit("Ticket", "Edit", "$session_name cancelled ticket schedule", $client_id, $ticket_id);

    triggerCustomAction('ticket_unschedule', $ticket_id);

    flashAlert("Ticket schedule cancelled", 'error');

    redirect();

}
