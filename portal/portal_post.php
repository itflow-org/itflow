<?php
/*
 * Client Portal
 * Process GET/POST requests
 */

require_once "inc_portal.php";


if (isset($_POST['add_ticket'])) {

    // Get ticket prefix/number
    $sql_settings = mysqli_query($mysqli, "SELECT * FROM settings WHERE company_id = 1");
    $row = mysqli_fetch_array($sql_settings);
    $config_ticket_prefix = sanitizeInput($row['config_ticket_prefix']);
    $config_ticket_next_number = intval($row['config_ticket_next_number']);

    // Get email settings
    $config_ticket_from_name = $row['config_ticket_from_name'];
    $config_ticket_from_email = $row['config_ticket_from_email'];
    $config_ticket_new_ticket_notification_email = filter_var($row['config_ticket_new_ticket_notification_email'], FILTER_VALIDATE_EMAIL);


    $client_id = intval($session_client_id);
    $contact = intval($session_contact_id);
    $subject = sanitizeInput($_POST['subject']);
    $details = mysqli_real_escape_string($mysqli,($_POST['details']));

    // Ensure priority is low/med/high (as can be user defined)
    if ($_POST['priority'] !== "Low" && $_POST['priority'] !== "Medium" && $_POST['priority'] !== "High") {
        $priority = "Low";
    } else {
        $priority = sanitizeInput($_POST['priority']);
    }

    // Get the next Ticket Number and add 1 for the new ticket number
    $ticket_number = $config_ticket_next_number;
    $new_config_ticket_next_number = $config_ticket_next_number + 1;
    mysqli_query($mysqli, "UPDATE settings SET config_ticket_next_number = $new_config_ticket_next_number WHERE company_id = 1");

    mysqli_query($mysqli, "INSERT INTO tickets SET ticket_prefix = '$config_ticket_prefix', ticket_number = $ticket_number, ticket_subject = '$subject', ticket_details = '$details', ticket_priority = '$priority', ticket_status = 'Open', ticket_created_by = 0, ticket_contact_id = $contact, ticket_client_id = $client_id");
    $id = mysqli_insert_id($mysqli);

    // Notify agent DL of the new ticket, if populated with a valid email
    if ($config_ticket_new_ticket_notification_email) {

        $client_name = sanitizeInput($session_client_name);
        $details = removeEmoji($details);

        $email_subject = "ITFlow - New Ticket - $client_name: $subject";
        $email_body = "Hello, <br><br>This is a notification that a new ticket has been raised in ITFlow. <br>Client: $client_name<br>Priority: $priority<br>Link: https://$config_base_url/ticket.php?ticket_id=$id <br><br><b>$subject</b><br>$details";

        mysqli_query($mysqli, "INSERT INTO email_queue SET email_recipient = '$config_ticket_new_ticket_notification_email', email_recipient_name = 'ITFlow Agents', email_from = '$config_ticket_from_email', email_from_name = '$config_ticket_from_name', email_subject = '$email_subject', email_content = '$email_body'");
    }

    // Logging
    mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Ticket', log_action = 'Create', log_description = 'Client contact $session_contact_name created ticket $subject', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id");

    header("Location: ticket.php?id=" . $id);

}

if (isset($_POST['add_ticket_comment'])) {

    $ticket_id = intval($_POST['ticket_id']);
    $comment = mysqli_real_escape_string($mysqli, $_POST['comment']);

    // After stripping bad HTML, check the comment isn't just empty
    if (empty($comment)) {
        header("Location: " . $_SERVER["HTTP_REFERER"]);
        exit;
    }

    // Verify the contact has access to the provided ticket ID
    if (verifyContactTicketAccess($ticket_id, "Open")) {

        // Add the comment
        mysqli_query($mysqli, "INSERT INTO ticket_replies SET ticket_reply = '$comment', ticket_reply_type = 'Client', ticket_reply_by = $session_contact_id, ticket_reply_ticket_id = $ticket_id");

        // Update Ticket Last Response Field & set ticket to open as client has replied
        mysqli_query($mysqli, "UPDATE tickets SET ticket_status = 'Open' WHERE ticket_id = $ticket_id AND ticket_client_id = $session_client_id LIMIT 1");

        // Redirect
        header("Location: " . $_SERVER["HTTP_REFERER"]);
    } else {
        // The client does not have access to this ticket
        header("Location: portal_post.php?logout");
        exit();
    }
}

if (isset($_POST['add_ticket_feedback'])) {
    $ticket_id = intval($_POST['ticket_id']);
    $feedback = sanitizeInput($_POST['add_ticket_feedback']);

    // Verify the contact has access to the provided ticket ID
    if (verifyContactTicketAccess($ticket_id, "Closed")) {

        // Add feedback
        mysqli_query($mysqli, "UPDATE tickets SET ticket_feedback = '$feedback' WHERE ticket_id = $ticket_id AND ticket_client_id = $session_client_id LIMIT 1");

        // Notify on bad feedback
        if ($feedback == "Bad") {
            mysqli_query($mysqli, "INSERT INTO notifications SET notification_type = 'Feedback', notification = '$session_contact_name rated ticket ID $ticket_id as bad', notification_client_id = $session_client_id");
        }

        // Redirect
        header("Location: " . $_SERVER["HTTP_REFERER"]);
    } else {
        // The client does not have access to this ticket
        header("Location: portal_post.php?logout");
        exit();
    }

}

if (isset($_GET['close_ticket'])) {
    $ticket_id = intval($_GET['close_ticket']);

    // Verify the contact has access to the provided ticket ID
    if (verifyContactTicketAccess($ticket_id, "Open")) {

        // Close ticket
        mysqli_query($mysqli, "UPDATE tickets SET ticket_status = 'Closed', ticket_closed_at = NOW() WHERE ticket_id = $ticket_id AND ticket_client_id = $session_client_id");

        // Add reply
        mysqli_query($mysqli, "INSERT INTO ticket_replies SET ticket_reply = 'Ticket closed by $session_contact_name.', ticket_reply_type = 'Client', ticket_reply_by = $session_contact_id, ticket_reply_ticket_id = $ticket_id");

        //Logging
        mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Ticket', log_action = 'Closed', log_description = '$ticket_id Closed by client', log_ip = '$session_ip', log_user_agent = '$session_user_agent'");

        header("Location: ticket.php?id=" . $ticket_id);
    } else {
        // The client does not have access to this ticket - send them home
        header("Location: index.php");
        exit();
    }
}

if (isset($_GET['logout'])) {
    setcookie("PHPSESSID", '', time() - 3600, "/");
    unset($_COOKIE['PHPSESSID']);

    session_unset();
    session_destroy();

    header('Location: login.php');
}

if (isset($_POST['edit_profile'])) {
    $new_password = $_POST['new_password'];
    if (!empty($new_password)) {
        $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
        mysqli_query($mysqli, "UPDATE contacts SET contact_password_hash = '$password_hash' WHERE contact_id = $session_contact_id AND contact_client_id = $session_client_id");

        // Logging
        mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Contact', log_action = 'Modify', log_description = 'Client contact $session_contact_name modified their profile/password.', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $session_client_id");
    }
    header('Location: index.php');
}
