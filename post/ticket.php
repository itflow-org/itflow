<?php

/*
 * ITFlow - GET/POST request handler for client tickets
 */

if (isset($_POST['add_ticket'])) {

    validateTechRole();

    $client_id = intval($_POST['client']);
    $assigned_to = intval($_POST['assigned_to']);
    if($assigned_to == 0){
        $ticket_status = 'Pending-Assignment';
    }else{
        $ticket_status = 'Assigned';
    }
    $contact = intval($_POST['contact']);
    $subject = sanitizeInput($_POST['subject']);
    $priority = sanitizeInput($_POST['priority']);
    $details = mysqli_real_escape_string($mysqli,$_POST['details']);
    $vendor_ticket_number = sanitizeInput($_POST['vendor_ticket_number']);
    $vendor_id = intval($_POST['vendor']);
    $asset_id = intval($_POST['asset']);

    // If no contact is selected automatically choose the primary contact for the client
    if ($client_id > 0 && $contact == 0) {
        $sql = mysqli_query($mysqli,"SELECT contact_id FROM contacts WHERE contact_client_id = $client_id AND contact_primary = 1");
        $row = mysqli_fetch_array($sql);
        $contact = intval($row['contact_id']);
    }

    //Get the next Ticket Number and add 1 for the new ticket number
    $ticket_number = $config_ticket_next_number;
    $new_config_ticket_next_number = $config_ticket_next_number + 1;
    mysqli_query($mysqli,"UPDATE settings SET config_ticket_next_number = $new_config_ticket_next_number WHERE company_id = 1");

    mysqli_query($mysqli,"INSERT INTO tickets SET ticket_prefix = '$config_ticket_prefix', ticket_number = $ticket_number, ticket_subject = '$subject', ticket_details = '$details', ticket_priority = '$priority', ticket_status = '$ticket_status', ticket_vendor_ticket_number = '$vendor_ticket_number', ticket_vendor_id = $vendor_id, ticket_asset_id = $asset_id, ticket_created_by = $session_user_id, ticket_assigned_to = $assigned_to, ticket_contact_id = $contact, ticket_client_id = $client_id");

    $ticket_id = mysqli_insert_id($mysqli);

    // Add Watchers
    if (!empty($_POST['watchers'])) {
        foreach($_POST['watchers'] as $watcher) {
            $watcher_email = sanitizeInput($watcher);
            mysqli_query($mysqli,"INSERT INTO ticket_watchers SET watcher_email = '$watcher_email', watcher_ticket_id = $ticket_id");
        }
    }

    // E-mail client
    if (!empty($config_smtp_host) && $config_ticket_client_general_notifications == 1) {

        // Get contact/ticket details
        $sql = mysqli_query($mysqli,"SELECT contact_name, contact_email, ticket_prefix, ticket_number, ticket_subject, ticket_details, ticket_client_id FROM tickets 
              LEFT JOIN clients ON ticket_client_id = client_id 
              LEFT JOIN contacts ON ticket_contact_id = contact_id
              WHERE ticket_id = $ticket_id");
        $row = mysqli_fetch_array($sql);

        // Unescaped Content used for email body and subject because it will get escaped as a whole
        $contact_name = $row['contact_name'];
        $ticket_prefix = $row['ticket_prefix'];
        $ticket_number = intval($row['ticket_number']);
        $ticket_subject = $row['ticket_subject'];
        $ticket_details = $row['ticket_details'];
        $client_id = intval($row['ticket_client_id']);
        $ticket_created_by = intval($row['ticket_created_by']);
        $ticket_assigned_to = intval($row['ticket_assigned_to']);

        // Escaped content used for everything else except email subject and body
        $contact_name_escaped = sanitizeInput($row['contact_name']);
        $contact_email_escaped = sanitizeInput($row['contact_email']);
        $ticket_prefix_escaped = sanitizeInput($row['ticket_prefix']);
        $ticket_subject_escaped = sanitizeInput($row['ticket_subject']);

        // Sanitize Config vars from get_settings.php
        $config_ticket_from_name_escaped = sanitizeInput($config_ticket_from_name);
        $config_ticket_from_email_escaped = sanitizeInput($config_ticket_from_email);

        $sql = mysqli_query($mysqli,"SELECT company_phone FROM companies WHERE company_id = 1");

        $company_phone = formatPhoneNumber($row['company_phone']);

        // Verify contact email is valid
        if (filter_var($contact_email_escaped, FILTER_VALIDATE_EMAIL)) {

            $subject_escaped = mysqli_escape_string($mysqli, "Ticket created - [$ticket_prefix$ticket_number] - $ticket_subject");
            $body_escaped    = mysqli_escape_string($mysqli, "<i style='color: #808080'>##- Please type your reply above this line -##</i><br><br>Hello, $contact_name<br><br>A ticket regarding \"$ticket_subject\" has been created for you.<br><br>--------------------------------<br>$ticket_details--------------------------------<br><br>Ticket: $ticket_prefix$ticket_number<br>Subject: $ticket_subject<br>Status: Open<br>Portal: https://$config_base_url/portal/ticket.php?id=$ticket_id<br><br>~<br>$session_company_name<br>Support Department<br>$config_ticket_from_email<br>$company_phone");

           // Email Ticket Contact
            // Queue Mail
            mysqli_query($mysqli, "INSERT INTO email_queue SET email_recipient = '$contact_email_escaped', email_recipient_name = '$contact_name_escaped', email_from = '$config_ticket_from_email_escaped', email_from_name = '$config_ticket_from_name_escaped', email_subject = '$subject_escaped', email_content = '$body_escaped'");

            // Get Email ID for reference
            $email_id = mysqli_insert_id($mysqli);

            // Also Email all the watchers
            $sql_watchers = mysqli_query($mysqli, "SELECT watcher_email FROM ticket_watchers WHERE watcher_ticket_id = $ticket_id");
            $body_escaped    .= "<br><br>----------------------------------------<br>DO NOT REPLY - YOU ARE RECEIVING THIS EMAIL BECAUSE YOU ARE A WATCHER";
            while ($row = mysqli_fetch_array($sql_watchers)) {
                $watcher_email_escaped = sanitizeInput($row['watcher_email']);

                // Queue Mail
                mysqli_query($mysqli, "INSERT INTO email_queue SET email_recipient = '$watcher_email_escaped', email_recipient_name = '$contact_name_escaped', email_from = '$config_ticket_from_email_escaped', email_from_name = '$config_ticket_from_name_escaped', email_subject = '$subject_escaped', email_content = '$body_escaped'");
            }
        }
    }

    // Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Ticket', log_action = 'Create', log_description = '$session_name created ticket $config_ticket_prefix_escaped$ticket_number - $ticket_subject_escaped', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $ticket_id");

    $_SESSION['alert_message'] = "Ticket <strong>$config_ticket_prefix$ticket_number</strong> created";

    header("Location: ticket.php?ticket_id=" . $ticket_id);

}

if (isset($_POST['edit_ticket'])) {

    validateTechRole();

    $ticket_id = intval($_POST['ticket_id']);
    $contact_id = intval($_POST['contact']);
    $subject = sanitizeInput($_POST['subject']);
    $priority = sanitizeInput($_POST['priority']);
    $details = mysqli_real_escape_string($mysqli,$_POST['details']);
    $vendor_ticket_number = sanitizeInput($_POST['vendor_ticket_number']);
    $vendor_id = intval($_POST['vendor']);
    $asset_id = intval($_POST['asset']);
    $client_id = intval($_POST['client_id']);
    $ticket_number = intval($_POST['ticket_number']);

    mysqli_query($mysqli,"UPDATE tickets SET ticket_subject = '$subject', ticket_priority = '$priority', ticket_details = '$details', ticket_vendor_ticket_number = '$vendor_ticket_number', ticket_contact_id = $contact_id, ticket_vendor_id = $vendor_id, ticket_asset_id = $asset_id WHERE ticket_id = $ticket_id");

    // Add Watchers
    if (!empty($_POST['watchers'])) {

        // Remove all watchers first
        mysqli_query($mysqli,"DELETE FROM ticket_watchers WHERE watcher_ticket_id = $ticket_id");

        //Add the Watchers
        foreach($_POST['watchers'] as $watcher) {
            $watcher_email = sanitizeInput($watcher);
            mysqli_query($mysqli,"INSERT INTO ticket_watchers SET watcher_email = '$watcher_email', watcher_ticket_id = $ticket_id");
        }
    }

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Ticket', log_action = 'Modify', log_description = '$session_name modified ticket $ticket_number - $subject', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $ticket_id");

    $_SESSION['alert_message'] = "Ticket <strong>$ticket_number</strong> updated";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['edit_ticket_priority'])) {

    validateTechRole();

    $ticket_id = intval($_POST['ticket_id']);
    $priority = sanitizeInput($_POST['priority']);
    $client_id = intval($_POST['client_id']);

    mysqli_query($mysqli,"UPDATE tickets SET ticket_priority = '$priority' WHERE ticket_id = $ticket_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Ticket', log_action = 'Modify', log_description = '$session_name edited ticket priority', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $ticket_id");

    $_SESSION['alert_message'] = "Ticket priority updated";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['edit_ticket_contact'])) {

    validateTechRole();

    $ticket_id = intval($_POST['ticket_id']);
    $contact_id = intval($_POST['contact']);
    $client_id = intval($_POST['client_id']);
    $ticket_number = sanitizeInput($_POST['ticket_number']);

    mysqli_query($mysqli,"UPDATE tickets SET ticket_contact_id = $contact_id WHERE ticket_id = $ticket_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Ticket', log_action = 'Modify', log_description = '$session_name changed contact for ticket $ticket_number', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $ticket_id");

    $_SESSION['alert_message'] = "Ticket <strong>$ticket_number</strong> contact updated";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['assign_ticket'])) {

    // Role check
    validateTechRole();

    // POST variables
    $ticket_id = intval($_POST['ticket_id']);
    $assigned_to = intval($_POST['assigned_to']);
    $ticket_status = sanitizeInput($_POST['ticket_status']);
    if($ticket_status == 'Pending-Assignment' && $assigned_to > 0){
        $ticket_status = 'Assigned';
    }

    // Allow for un-assigning tickets
    if ($assigned_to == 0) {
        $ticket_reply = "Ticket unassigned, pending re-assignment.";
        $agent_name = "No One";
        $ticket_status = "Pending-Assignment";
    } else {
        // Get & verify assigned agent details
        $agent_details_sql = mysqli_query($mysqli, "SELECT user_name, user_email FROM users LEFT JOIN user_settings ON users.user_id = user_settings.user_id WHERE users.user_id = $assigned_to AND user_settings.user_role > 1");
        $agent_details = mysqli_fetch_array($agent_details_sql);

        //Unescaped
        $agent_name = $agent_details['user_name'];
        $agent_email = $agent_details['user_email'];
        $ticket_reply = "Ticket re-assigned to $agent_name.";

        // Escaped
        $agent_name_escaped = sanitizeInput($agent_details['user_name']);
        $agent_email_escaped = sanitizeInput($agent_details['user_email']);
        $ticket_reply_escaped = mysqli_real_escape_string($mysqli, "Ticket re-assigned to $agent_name.");

        if (!$agent_name_escaped) {
            $_SESSION['alert_type'] = "error";
            $_SESSION['alert_message'] = "Invalid agent!";
            header("Location: " . $_SERVER["HTTP_REFERER"]);
            exit();
        }
    }

    // Get & verify ticket details
    $ticket_details_sql = mysqli_query($mysqli, "SELECT ticket_prefix, ticket_number, ticket_subject, ticket_client_id FROM tickets WHERE ticket_id = '$ticket_id' AND ticket_status != 'Closed'");
    $ticket_details = mysqli_fetch_array($ticket_details_sql);

    //Unescaped
    $ticket_prefix = $ticket_details['ticket_prefix'];
    $ticket_subject = $ticket_details['ticket_subject'];

    //Escaped
    $ticket_prefix_escaped = sanitizeInput($ticket_details['ticket_prefix']);
    $ticket_number = intval($ticket_details['ticket_number']);
    $ticket_subject_escaped = sanitizeInput($ticket_details['ticket_subject']);
    $client_id = intval($ticket_details['ticket_client_id']);

    if (!$ticket_subject_escaped) {
        $_SESSION['alert_type'] = "error";
        $_SESSION['alert_message'] = "Invalid ticket!";
        header("Location: " . $_SERVER["HTTP_REFERER"]);
        exit();
    }

    // Update ticket & insert reply
    mysqli_query($mysqli,"UPDATE tickets SET ticket_assigned_to = $assigned_to, ticket_status = '$ticket_status' WHERE ticket_id = $ticket_id");

    mysqli_query($mysqli,"INSERT INTO ticket_replies SET ticket_reply = '$ticket_reply_escaped', ticket_reply_type = 'Internal', ticket_reply_time_worked = '00:01:00', ticket_reply_by = $session_user_id, ticket_reply_ticket_id = $ticket_id");

    // Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Ticket', log_action = 'Edit', log_description = '$session_name reassigned ticket $ticket_prefix_escaped$ticket_number - $ticket_subject_escaped to $agent_name_escaped', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $ticket_id");


    // Notification
    if ($session_user_id != $assigned_to && $assigned_to != 0) {

        // App Notification
        mysqli_query($mysqli,"INSERT INTO notifications SET notification_type = 'Ticket', notification = 'Ticket $ticket_prefix_escaped$ticket_number - Subject: $ticket_subject_escaped has been assigned to you by $session_name', notification_action = 'ticket.php?ticket_id=$ticket_id', notification_client_id = $client_id, notification_user_id = $assigned_to");

        // Email Notification
        if (!empty($config_smtp_host)) {

            // Sanitize Config vars from get_settings.php
            $config_ticket_from_name_escaped = sanitizeInput($config_ticket_from_name);
            $config_ticket_from_email_escaped = sanitizeInput($config_ticket_from_email);

            $subject_escaped = mysqli_escape_string($mysqli, "$config_app_name ticket $ticket_prefix$ticket_number assigned to you");
            $body_escaped = mysqli_escape_string($mysqli, "Hi $agent_name, <br><br>A ticket has been assigned to you!<br><br>Ticket Number: $ticket_prefix$ticket_number<br> Subject: $ticket_subject <br><br>Thanks, <br>$session_name<br>$session_company_name");

            // Email Ticket Agent
            // Queue Mail
            mysqli_query($mysqli, "INSERT INTO email_queue SET email_recipient = '$agent_email_escaped', email_recipient_name = '$agent_name_escaped', email_from = '$config_ticket_from_email_escaped', email_from_name = '$config_ticket_from_name_escaped', email_subject = '$subject_escaped', email_content = '$body_escaped'");

            // Get Email ID for reference
            $email_id = mysqli_insert_id($mysqli);
        }

    }

    $_SESSION['alert_message'] = "Ticket <strong>$ticket_prefix$ticket_number</strong> assigned to <strong>$agent_name</strong>";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_GET['delete_ticket'])) {

    validateAdminRole();

    $ticket_id = intval($_GET['delete_ticket']);

    // Get Ticket and Client ID for logging and alert message
    $sql = mysqli_query($mysqli,"SELECT ticket_prefix, ticket_number, ticket_subject, ticket_status, ticket_client_id FROM tickets WHERE ticket_id = $ticket_id");
    $row = mysqli_fetch_array($sql);
    $ticket_prefix = sanitizeInput($row['ticket_prefix']);
    $ticket_number = sanitizeInput($row['ticket_number']);
    $ticket_subject = sanitizeInput($row['ticket_subject']);
    $ticket_status = sanitizeInput($row['ticket_status']);
    $client_id = intval($row['ticket_client_id']);

    if ($ticket_status !== 'Closed') {
        mysqli_query($mysqli,"DELETE FROM tickets WHERE ticket_id = $ticket_id");

        // Delete all ticket replies
        mysqli_query($mysqli,"DELETE FROM ticket_replies WHERE ticket_reply_ticket_id = $ticket_id");

        // Delete all ticket views
        mysqli_query($mysqli,"DELETE FROM ticket_views WHERE view_ticket_id = $ticket_id");

        // Logging
        mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Ticket', log_action = 'Delete', log_description = '$session_name deleted ticket $ticket_prefix$ticket_number - $ticket_subject along with all replies', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $ticket_id");

        $_SESSION['alert_type'] = "error";
        $_SESSION['alert_message'] = "Ticket <strong>$ticket_prefix$ticket_number</strong> along with all replies deleted";

        header("Location: " . $_SERVER["HTTP_REFERER"]);
    }

}

if (isset($_POST['add_ticket_reply'])) {

    validateTechRole();

    $ticket_id = intval($_POST['ticket_id']);
    $ticket_reply_escaped = mysqli_real_escape_string($mysqli,$_POST['ticket_reply']);
    $ticket_reply = $_POST['ticket_reply'];
    $ticket_status_escaped = sanitizeInput($_POST['status']);
    $ticket_status = $_POST['status'];
    $ticket_reply_time_worked_escaped = sanitizeInput($_POST['time']);
    $ticket_reply_time_worked = $_POST['time'];

    $client_id = intval($_POST['client_id']);

    if (isset($_POST['public_reply_type'])) {
        $ticket_reply_type = 'Public';
    } else {
        $ticket_reply_type = 'Internal';
    }

    // Add reply
    mysqli_query($mysqli,"INSERT INTO ticket_replies SET ticket_reply = '$ticket_reply_escaped', ticket_reply_time_worked = '$ticket_reply_time_worked_escaped', ticket_reply_type = '$ticket_reply_type', ticket_reply_by = $session_user_id, ticket_reply_ticket_id = $ticket_id") or die(mysqli_error($mysqli));

    $ticket_reply_id = mysqli_insert_id($mysqli);

    // Update Ticket Last Response Field
    mysqli_query($mysqli,"UPDATE tickets SET ticket_status = '$ticket_status_escaped' WHERE ticket_id = $ticket_id") or die(mysqli_error($mysqli));

    if ($ticket_status == 'Closed') {
        mysqli_query($mysqli,"UPDATE tickets SET ticket_closed_at = NOW() WHERE ticket_id = $ticket_id");
    }

    // Get Ticket Details
    $ticket_sql = mysqli_query($mysqli,"SELECT contact_name, contact_email, ticket_prefix, ticket_number, ticket_subject, ticket_client_id, ticket_created_by, ticket_assigned_to 
        FROM tickets 
        LEFT JOIN clients ON ticket_client_id = client_id 
        LEFT JOIN contacts ON ticket_contact_id = contact_id
        WHERE ticket_id = $ticket_id
    ");

    $row = mysqli_fetch_array($ticket_sql);

    // Unescaped Content used for email body and subject because it will get escaped as a whole
    $contact_name = $row['contact_name'];
    $ticket_prefix = $row['ticket_prefix'];
    $ticket_number = intval($row['ticket_number']);
    $ticket_subject = $row['ticket_subject'];
    $client_id = intval($row['ticket_client_id']);
    $ticket_created_by = intval($row['ticket_created_by']);
    $ticket_assigned_to = intval($row['ticket_assigned_to']);

    // Escaped content used for everything else except email subject and body
    $contact_name_escaped = sanitizeInput($row['contact_name']);
    $contact_email_escaped = sanitizeInput($row['contact_email']);
    $ticket_prefix_escaped = sanitizeInput($row['ticket_prefix']);
    $ticket_subject_escaped = sanitizeInput($row['ticket_subject']);

    // Sanitize Config vars from get_settings.php
    $config_ticket_from_name_escaped = sanitizeInput($config_ticket_from_name);
    $config_ticket_from_email_escaped = sanitizeInput($config_ticket_from_email);

    $sql = mysqli_query($mysqli,"SELECT company_phone FROM companies WHERE company_id = 1");

    $company_phone = formatPhoneNumber($row['company_phone']);

    // Send e-mail to client if public update & email is set up
    if ($ticket_reply_type == 'Public' && !empty($config_smtp_host)) {

        if (filter_var($contact_email_escaped, FILTER_VALIDATE_EMAIL)) {

            // Slightly different email subject/text depending on if this update closed the ticket or not

            if ($ticket_status == 'Closed') {
                $subject_escaped = mysqli_escape_string($mysqli, "Ticket closed - [$ticket_prefix$ticket_number] - $ticket_subject | (do not reply)");
                $body_escaped    = mysqli_escape_string($mysqli, "Hello, $contact_name<br><br>Your ticket regarding $ticket_subject has been closed.<br><br>--------------------------------<br>$ticket_reply<br>--------------------------------<br><br>We hope the issue was resolved to your satisfaction. If you need further assistance, please raise a new ticket using the below details. Please do not reply to this email. <br><br>Ticket: $ticket_prefix$ticket_number<br>Subject: $ticket_subject<br>Portal: https://$config_base_url/portal/ticket.php?id=$ticket_id<br><br>~<br>$session_company_name<br>Support Department<br>$config_ticket_from_email<br>$company_phone");

            } elseif ($ticket_status == 'Auto Close') {
                $subject_escaped = mysqli_escape_string($mysqli, "Re: [$ticket_prefix$ticket_number] - $ticket_subject | (pending closure)");
                $body_escaped    = mysqli_escape_string($mysqli, "<i style='color: #808080'>##- Please type your reply above this line -##</i><br><br>Hello, $contact_name<br><br>Your ticket regarding $ticket_subject has been updated and is pending closure.<br><br>--------------------------------<br>$ticket_reply<br>--------------------------------<br><br>If your issue is resolved, you can ignore this email. If you need further assistance, please respond!  <br><br>Ticket: $ticket_prefix$ticket_number<br>Subject: $ticket_subject<br>Status: $ticket_status<br>Portal: https://$config_base_url/portal/ticket.php?id=$ticket_id<br><br>~<br>$session_company_name<br>Support Department<br>$config_ticket_from_email<br>$company_phone");

            } else {
                $subject_escaped = mysqli_escape_string($mysqli, "Re: [$ticket_prefix$ticket_number] - $ticket_subject");
                $body_escaped    = mysqli_escape_string($mysqli, "<i style='color: #808080'>##- Please type your reply above this line -##</i><br><br>Hello, $contact_name<br><br>Your ticket regarding $ticket_subject has been updated.<br><br>--------------------------------<br>$ticket_reply<br>--------------------------------<br><br>Ticket: $ticket_prefix$ticket_number<br>Subject: $ticket_subject<br>Status: $ticket_status<br>Portal: https://$config_base_url/portal/ticket.php?id=$ticket_id<br><br>~<br>$session_company_name<br>Support Department<br>$config_ticket_from_email<br>$company_phone");

            }

            // Email Ticket Contact
            // Queue Mail
            mysqli_query($mysqli, "INSERT INTO email_queue SET email_recipient = '$contact_email_escaped', email_recipient_name = '$contact_name_escaped', email_from = '$config_ticket_from_email_escaped', email_from_name = '$config_ticket_from_name_escaped', email_subject = '$subject_escaped', email_content = '$body_escaped'");

            // Get Email ID for reference
            $email_id = mysqli_insert_id($mysqli);

            // Also Email all the watchers
            $sql_watchers = mysqli_query($mysqli, "SELECT watcher_email FROM ticket_watchers WHERE watcher_ticket_id = $ticket_id");
            $body_escaped   .= "<br><br>----------------------------------------<br>DO NOT REPLY - YOU ARE RECEIVING THIS EMAIL BECAUSE YOU ARE A WATCHER";
            while ($row = mysqli_fetch_array($sql_watchers)) {
                $watcher_email_escaped = sanitizeInput($row['watcher_email']);

                // Queue Mail
                mysqli_query($mysqli, "INSERT INTO email_queue SET email_recipient = '$watcher_email_escaped', email_recipient_name = '$contact_name_escaped', email_from = '$config_ticket_from_email_escaped', email_from_name = '$config_ticket_from_name_escaped', email_subject = '$subject_escaped', email_content = '$body_escaped'");
            }

        }
    }
    //End Mail IF

    // Notification for assigned ticket user
    if ($session_user_id != $ticket_assigned_to && $ticket_assigned_to != 0) {

        mysqli_query($mysqli,"INSERT INTO notifications SET notification_type = 'Ticket', notification = '$session_name updated Ticket $ticket_prefix_escaped$ticket_number - Subject: $ticket_subject_escaped that is assigned to you', notification_action = 'ticket.php?ticket_id=$ticket_id', notification_client_id = $client_id, notification_user_id = $ticket_assigned_to");
    }

    // Notification for user that opened the ticket
    if ($session_user_id != $ticket_created_by && $ticket_created_by != 0) {

        mysqli_query($mysqli,"INSERT INTO notifications SET notification_type = 'Ticket', notification = '$session_name updated Ticket $ticket_prefix_escaped$ticket_number - Subject: $ticket_subject_escaped that you opened', notification_action = 'ticket.php?ticket_id=$ticket_id', notification_client_id = $client_id, notification_user_id = $ticket_created_by");
    }

    // Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Ticket Reply', log_action = 'Create', log_description = '$session_name replied to ticket $ticket_prefix_escaped$ticket_number - $ticket_subject_escaped and was a $ticket_reply_type reply', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $ticket_reply_id");

    $_SESSION['alert_message'] = "Ticket <strong>$ticket_prefix$ticket_number</strong> has been updated with your reply and was <strong>$ticket_reply_type</strong>";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['edit_ticket_reply'])) {

    validateTechRole();

    $ticket_reply_id = intval($_POST['ticket_reply_id']);
    $ticket_reply = mysqli_real_escape_string($mysqli,$_POST['ticket_reply']);
    $ticket_reply_time_worked = sanitizeInput($_POST['time']);

    $client_id = intval($_POST['client_id']);

    mysqli_query($mysqli,"UPDATE ticket_replies SET ticket_reply = '$ticket_reply', ticket_reply_time_worked = '$ticket_reply_time_worked' WHERE ticket_reply_id = $ticket_reply_id AND ticket_reply_type != 'Client'") or die(mysqli_error($mysqli));

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Ticket Reply', log_action = 'Modify', log_description = '$session_name modified ticket reply', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $ticket_reply_id");

    $_SESSION['alert_message'] = "Ticket reply updated";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_GET['archive_ticket_reply'])) {

    validateAdminRole();

    $ticket_reply_id = intval($_GET['archive_ticket_reply']);

    mysqli_query($mysqli,"UPDATE ticket_replies SET ticket_reply_archived_at = NOW() WHERE ticket_reply_id = $ticket_reply_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Ticket Reply', log_action = 'Archive', log_description = '$session_name arhived ticket reply', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $ticket_reply_id");

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "Ticket reply archived";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['merge_ticket'])) {

    validateTechRole();

    $ticket_id = intval($_POST['ticket_id']);
    $merge_into_ticket_number = intval($_POST['merge_into_ticket_number']);
    $merge_comment = sanitizeInput($_POST['merge_comment']);
    $ticket_reply_type = 'Internal';

    //Get current ticket details
    $sql = mysqli_query($mysqli, "SELECT ticket_prefix, ticket_number, ticket_subject, ticket_details FROM tickets WHERE ticket_id = $ticket_id");
    if (mysqli_num_rows($sql) == 0) {
        $_SESSION['alert_message'] = "No ticket with that ID found.";
        header("Location: " . $_SERVER["HTTP_REFERER"]);
        exit();
    }
    $row = mysqli_fetch_array($sql);
    $ticket_prefix = sanitizeInput($row['ticket_prefix']);
    $ticket_number = intval($row['ticket_number']);
    $ticket_subject = sanitizeInput($row['ticket_subject']);
    $ticket_details = sanitizeInput($row['ticket_details']);

    //Get merge into ticket id (as it may differ from the number)
    $sql = mysqli_query($mysqli, "SELECT ticket_id FROM tickets WHERE ticket_number = $merge_into_ticket_number");
    if (mysqli_num_rows($sql) == 0) {
        $_SESSION['alert_message'] = "Cannot merge into that ticket.";
        header("Location: " . $_SERVER["HTTP_REFERER"]);
        exit();
    }
    $merge_row = mysqli_fetch_array($sql);
    $merge_into_ticket_id = intval($merge_row['ticket_id']);

    if ($ticket_number == $merge_into_ticket_number) {
        $_SESSION['alert_message'] = "Cannot merge into the same ticket.";
        header("Location: " . $_SERVER["HTTP_REFERER"]);
        exit();
    }

    //Update current ticket
    mysqli_query($mysqli,"INSERT INTO ticket_replies SET ticket_reply = 'Ticket $ticket_prefix$ticket_number merged into $ticket_prefix$merge_into_ticket_number. Comment: $merge_comment', ticket_reply_time_worked = '00:01:00', ticket_reply_type = '$ticket_reply_type', ticket_reply_by = $session_user_id, ticket_reply_ticket_id = $ticket_id") or die(mysqli_error($mysqli));
    mysqli_query($mysqli,"UPDATE tickets SET ticket_status = 'Closed', ticket_closed_at = NOW() WHERE ticket_id = $ticket_id") or die(mysqli_error($mysqli));

    //Update new ticket
    mysqli_query($mysqli,"INSERT INTO ticket_replies SET ticket_reply = 'Ticket $ticket_prefix$ticket_number was merged into this ticket with comment: $merge_comment.<br><b>$ticket_subject</b><br>$ticket_details', ticket_reply_time_worked = '00:01:00', ticket_reply_type = '$ticket_reply_type', ticket_reply_by = $session_user_id, ticket_reply_ticket_id = $merge_into_ticket_id") or die(mysqli_error($mysqli));

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Ticket', log_action = 'Merged', log_description = 'Merged ticket $ticket_prefix$ticket_number into $ticket_prefix$merge_into_ticket_number', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Ticket merged into $ticket_prefix$merge_into_ticket_number";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['change_client_ticket'])) {

    validateTechRole();

    $ticket_id = intval($_POST['ticket_id']);
    $client_id = intval($_POST['new_client_id']);
    $contact_id = intval($_POST['new_contact_id']);

    // Set any/all existing replies to internal
    mysqli_query($mysqli, "UPDATE ticket_replies SET ticket_reply_type = 'Internal' WHERE ticket_reply_ticket_id = $ticket_id");

    // Update ticket client & contact
    mysqli_query($mysqli, "UPDATE tickets SET ticket_client_id = $client_id, ticket_contact_id = $contact_id WHERE ticket_id = $ticket_id LIMIT 1");

    //Logging
    mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Ticket Reply', log_action = 'Modify', log_description = '$session_name modified ticket - client changed', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $ticket_id");

    $_SESSION['alert_message'] = "Ticket client updated";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_GET['close_ticket'])) {

    validateTechRole();

    $ticket_id = intval($_GET['close_ticket']);

    mysqli_query($mysqli,"UPDATE tickets SET ticket_status = 'Closed', ticket_closed_at = NOW(), ticket_closed_by = $session_user_id WHERE ticket_id = $ticket_id") or die(mysqli_error($mysqli));

    mysqli_query($mysqli,"INSERT INTO ticket_replies SET ticket_reply = 'Ticket closed.', ticket_reply_type = 'Internal', ticket_reply_time_worked = '00:01:00', ticket_reply_by = $session_user_id, ticket_reply_ticket_id = $ticket_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Ticket', log_action = 'Closed', log_description = 'Ticket ID $ticket_id Closed', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id, log_entity_id = $ticket_id");

    // Client notification email
    if (!empty($config_smtp_host) && $config_ticket_client_general_notifications == 1) {

        // Get details
        $ticket_sql = mysqli_query($mysqli,"SELECT contact_name, contact_email, ticket_prefix, ticket_number, ticket_subject FROM tickets 
            LEFT JOIN clients ON ticket_client_id = client_id 
            LEFT JOIN contacts ON ticket_contact_id = contact_id
            WHERE ticket_id = $ticket_id
        ");
        $row = mysqli_fetch_array($ticket_sql);

        // Unescaped Content used for email body and subject because it will get escaped as a whole
        $contact_name = $row['contact_name'];
        $ticket_prefix = $row['ticket_prefix'];
        $ticket_number = intval($row['ticket_number']);
        $ticket_subject = $row['ticket_subject'];
        $ticket_details = $row['ticket_details'];
        $client_id = intval($row['ticket_client_id']);
        $ticket_created_by = intval($row['ticket_created_by']);
        $ticket_assigned_to = intval($row['ticket_assigned_to']);

        // Escaped content used for everything else except email subject and body
        $contact_name_escaped = sanitizeInput($row['contact_name']);
        $contact_email_escaped = sanitizeInput($row['contact_email']);
        $ticket_prefix_escaped = sanitizeInput($row['ticket_prefix']);
        $ticket_subject_escaped = sanitizeInput($row['ticket_subject']);

        // Sanitize Config vars from get_settings.php
        $config_ticket_from_name_escaped = sanitizeInput($config_ticket_from_name);
        $config_ticket_from_email_escaped = sanitizeInput($config_ticket_from_email);

        $sql = mysqli_query($mysqli,"SELECT company_phone FROM companies WHERE company_id = 1");

        $company_phone = formatPhoneNumber($row['company_phone']);

        // Check email valid
        if (filter_var($contact_email_escaped, FILTER_VALIDATE_EMAIL)) {

            $subject_escaped = mysqli_escape_string($mysqli, "Ticket closed - [$ticket_prefix$ticket_number] - $ticket_subject | (do not reply)");
            $body_escaped    = mysqli_escape_string($mysqli, "Hello, $contact_name<br><br>Your ticket regarding \"$ticket_subject\" has been closed. <br><br> We hope the issue was resolved to your satisfaction. If you need further assistance, please raise a new ticket using the below details. Please do not reply to this email. <br><br>Ticket: $ticket_prefix$ticket_number<br>Subject: $ticket_subject<br>Portal: https://$config_base_url/portal/ticket.php?id=$ticket_id<br><br>~<br>$session_company_name<br>Support Department<br>$config_ticket_from_email<br>$company_phone");

            // Email Ticket Contact
            // Queue Mail
            mysqli_query($mysqli, "INSERT INTO email_queue SET email_recipient = '$contact_email_escaped', email_recipient_name = '$contact_name_escaped', email_from = '$config_ticket_from_email_escaped', email_from_name = '$config_ticket_from_name_escaped', email_subject = '$subject_escaped', email_content = '$body_escaped'");

            // Get Email ID for reference
            $email_queue_id = mysqli_insert_id($mysqli);

            // Also Email all the watchers
            $sql_watchers = mysqli_query($mysqli, "SELECT watcher_email FROM ticket_watchers WHERE watcher_ticket_id = $ticket_id");
            $body_escaped    .= "<br><br>----------------------------------------<br>DO NOT REPLY - YOU ARE RECEIVING THIS EMAIL BECAUSE YOU ARE A WATCHER";
            while ($row = mysqli_fetch_array($sql_watchers)) {
                $watcher_email_escaped = sanitizeInput($row['watcher_email']);

                // Queue Mail
                mysqli_query($mysqli, "INSERT INTO email_queue SET email_recipient = '$watcher_email_escaped', email_recipient_name = '$contact_name_escaped', email_from = '$config_ticket_from_email_escaped', email_from_name = '$config_ticket_from_name_escaped', email_subject = '$subject_escaped', email_content = '$body_escaped'");
            }

        }

    }
    //End Mail IF

    $_SESSION['alert_message'] = "Ticket Closed, this cannot not be reopened but you may start another one";
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['add_invoice_from_ticket'])) {

    $invoice_id = intval($_POST['invoice_id']);
    $ticket_id = intval($_POST['ticket_id']);
    $date = sanitizeInput($_POST['date']);
    $category = intval($_POST['category']);
    $scope = sanitizeInput($_POST['scope']);

    $sql = mysqli_query($mysqli, "SELECT * FROM tickets 
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
        mysqli_query($mysqli,"UPDATE settings SET config_invoice_next_number = $new_config_invoice_next_number WHERE company_id = 1");

        //Generate a unique URL key for clients to access
        $url_key = randomString(156);

        mysqli_query($mysqli,"INSERT INTO invoices SET invoice_prefix = '$config_invoice_prefix', invoice_number = $invoice_number, invoice_scope = '$scope', invoice_date = '$date', invoice_due = DATE_ADD('$date', INTERVAL $client_net_terms day), invoice_currency_code = '$session_company_currency', invoice_category_id = $category, invoice_status = 'Draft', invoice_url_key = '$url_key', invoice_client_id = $client_id");
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
        $sql = mysqli_query($mysqli,"SELECT * FROM taxes WHERE tax_id = $tax_id");
        $row = mysqli_fetch_array($sql);
        $tax_percent = floatval($row['tax_percent']);
        $tax_amount = $subtotal * $tax_percent / 100;
    }else{
        $tax_amount = 0;
    }

    $total = $subtotal + $tax_amount;

    mysqli_query($mysqli,"INSERT INTO invoice_items SET item_name = '$item_name', item_description = '$item_description', item_quantity = $qty, item_price = $price, item_subtotal = $subtotal, item_tax = $tax_amount, item_total = $total, item_tax_id = $tax_id, item_invoice_id = $invoice_id");

    //Update Invoice Balances

    $sql = mysqli_query($mysqli,"SELECT * FROM invoices WHERE invoice_id = $invoice_id");
    $row = mysqli_fetch_array($sql);

    $new_invoice_amount = floatval($row['invoice_amount']) + $total;

    mysqli_query($mysqli,"UPDATE invoices SET invoice_amount = $new_invoice_amount WHERE invoice_id = $invoice_id");

    mysqli_query($mysqli,"INSERT INTO history SET history_status = 'Draft', history_description = 'Invoice created from Ticket $ticket_prefix$ticket_number', history_invoice_id = $invoice_id");

    // Add internal note to ticket
    mysqli_query($mysqli, "INSERT INTO ticket_replies SET ticket_reply = 'Created invoice <a href=\"invoice.php?invoice_id=$invoice_id\">$config_invoice_prefix$invoice_number</a> for this ticket.', ticket_reply_type = 'Internal', ticket_reply_time_worked = '00:01:00', ticket_reply_by = $session_user_id, ticket_reply_ticket_id = $ticket_id");

    // Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Invoice', log_action = 'Create', log_description = '$config_invoice_prefix$invoice_number created from Ticket $ticket_prefix$ticket_number', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Invoice created from ticket";

    header("Location: invoice.php?invoice_id=$invoice_id");
}

if (isset($_POST['export_client_tickets_csv'])) {

    validateTechRole();

    $client_id = intval($_POST['client_id']);

    //get records from database
    $sql = mysqli_query($mysqli,"SELECT * FROM clients WHERE client_id = $client_id");
    $row = mysqli_fetch_array($sql);

    $client_name = $row['client_name'];

    $sql = mysqli_query($mysqli,"SELECT * FROM tickets WHERE ticket_client_id = $client_id ORDER BY ticket_number ASC");
    if ($sql->num_rows > 0) {
        $delimiter = ",";
        $filename = $client_name . "-Tickets-" . date('Y-m-d') . ".csv";

        //create a file pointer
        $f = fopen('php://memory', 'w');

        //set column headers
        $fields = array('Ticket Number', 'Priority', 'Status', 'Subject', 'Date Opened', 'Date Closed');
        fputcsv($f, $fields, $delimiter);

        //output each row of the data, format line as csv and write to file pointer
        while($row = $sql->fetch_assoc()) {
            $lineData = array($row['ticket_number'], $row['ticket_priority'], $row['ticket_status'], $row['ticket_subject'], $row['ticket_created_at'], $row['ticket_closed_at']);
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

if (isset($_POST['add_scheduled_ticket'])) {

    validateTechRole();

    require_once('post/scheduled_ticket_model.php');
    $start_date = sanitizeInput($_POST['start_date']);

    // If no contact is selected automatically choose the primary contact for the client
    if ($client_id > 0 && $contact_id == 0) {
        $sql = mysqli_query($mysqli,"SELECT contact_id FROM contacts WHERE contact_client_id = $client_id AND contact_primary = 1");
        $row = mysqli_fetch_array($sql);
        $contact_id = intval($row['contact_id']);
    }

    // Add scheduled ticket
    mysqli_query($mysqli, "INSERT INTO scheduled_tickets SET scheduled_ticket_subject = '$subject', scheduled_ticket_details = '$details', scheduled_ticket_priority = '$priority', scheduled_ticket_frequency = '$frequency', scheduled_ticket_start_date = '$start_date', scheduled_ticket_next_run = '$start_date', scheduled_ticket_created_by = $session_user_id, scheduled_ticket_client_id = $client_id, scheduled_ticket_contact_id = $contact_id, scheduled_ticket_asset_id = $asset_id");

    $scheduled_ticket_id = mysqli_insert_id($mysqli);

    // Logging
    mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Scheduled Ticket', log_action = 'Create', log_description = '$session_name created scheduled ticket for $subject - $frequency', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $scheduled_ticket_id");

    $_SESSION['alert_message'] = "Scheduled ticket <strong>$subject - $frequency</strong> created";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['edit_scheduled_ticket'])) {

    validateTechRole();

    require_once('post/scheduled_ticket_model.php');
    $scheduled_ticket_id = intval($_POST['scheduled_ticket_id']);
    $next_run_date = sanitizeInput($_POST['next_date']);

    // Edit scheduled ticket
    mysqli_query($mysqli, "UPDATE scheduled_tickets SET scheduled_ticket_subject = '$subject', scheduled_ticket_details = '$details', scheduled_ticket_priority = '$priority', scheduled_ticket_frequency = '$frequency', scheduled_ticket_next_run = '$next_run_date', scheduled_ticket_asset_id = $asset_id WHERE scheduled_ticket_id = $scheduled_ticket_id");

    // Logging
    mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Scheduled Ticket', log_action = 'Modify', log_description = '$session_name modified scheduled ticket for $subject - $frequency', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $scheduled_ticket_id");

    $_SESSION['alert_message'] = "Scheduled ticket <strong>$subject - $frequency</strong> updated";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_GET['delete_scheduled_ticket'])) {

    validateAdminRole();

    $scheduled_ticket_id = intval($_GET['delete_scheduled_ticket']);

    // Get Scheduled Ticket Subject Ticket Prefix, Number and Client ID for logging and alert message
    $sql = mysqli_query($mysqli, "SELECT * FROM scheduled_tickets WHERE scheduled_ticket_id = $scheduled_ticket_id");
    $row = mysqli_fetch_array($sql);
    $scheduled_ticket_subject = sanitizeInput($row['scheduled_ticket_subject']);
    $scheduled_ticket_frequency = sanitizeInput($row['scheduled_ticket_frequency']);

    $client_id = intval($row['scheduled_ticket_client_id']);

    // Delete
    mysqli_query($mysqli, "DELETE FROM scheduled_tickets WHERE scheduled_ticket_id = $scheduled_ticket_id");

    //Logging
    mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Scheduled Ticket', log_action = 'Delete', log_description = '$session_name deleted scheduled ticket for $subject - $frequency', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $scheduled_ticket_id");

    $_SESSION['alert_message'] = "Scheduled ticket <strong>$subject - $frequency</strong> deleted";

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if (isset($_POST['bulk_delete_scheduled_tickets'])) {
    validateAdminRole();
    validateCSRFToken($_POST['csrf_token']);

    $count = 0; // Default 0
    $scheduled_ticket_ids = $_POST['scheduled_ticket_ids']; // Get array of scheduled tickets IDs to be deleted

    if (!empty($scheduled_ticket_ids)) {

        // Cycle through array and delete each scheduled ticket
        foreach ($scheduled_ticket_ids as $scheduled_ticket_id) {

            $scheduled_ticket_id = intval($scheduled_ticket_id);
            mysqli_query($mysqli, "DELETE FROM scheduled_tickets WHERE scheduled_ticket_id = $scheduled_ticket_id");
            mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Scheduled Ticket', log_action = 'Delete', log_description = '$session_name deleted scheduled ticket (bulk)', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id, log_entity_id = $scheduled_ticket_id");

            $count++;
        }

        // Logging
        mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Scheduled Ticket', log_action = 'Delete', log_description = '$session_name bulk deleted $count scheduled tickets', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");

        $_SESSION['alert_message'] = "Deleted $count scheduled ticket(s)";

    }

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}
