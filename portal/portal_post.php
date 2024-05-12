<?php
/*
 * Client Portal
 * Process GET/POST requests
 */

require_once "inc_portal.php";


if (isset($_POST['add_ticket'])) {

    $client_id = intval($session_client_id);
    $contact = intval($session_contact_id);
    $subject = sanitizeInput($_POST['subject']);
    $details = mysqli_real_escape_string($mysqli,($_POST['details']));

    // Get settings from get_settings.php
    $config_ticket_prefix = sanitizeInput($config_ticket_prefix);
    $config_ticket_from_name = sanitizeInput($config_ticket_from_name);
    $config_ticket_from_email = sanitizeInput($config_ticket_from_email);
    $config_base_url = sanitizeInput($config_base_url);
    $config_ticket_new_ticket_notification_email = filter_var($config_ticket_new_ticket_notification_email, FILTER_VALIDATE_EMAIL);

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

    mysqli_query($mysqli, "INSERT INTO tickets SET ticket_prefix = '$config_ticket_prefix', ticket_number = $ticket_number, ticket_subject = '$subject', ticket_details = '$details', ticket_priority = '$priority', ticket_status = 1, ticket_created_by = 0, ticket_contact_id = $contact, ticket_client_id = $client_id");
    $id = mysqli_insert_id($mysqli);

    // Notify agent DL of the new ticket, if populated with a valid email
    if ($config_ticket_new_ticket_notification_email) {

        $client_name = sanitizeInput($session_client_name);
        $details = removeEmoji($details);

        $email_subject = "ITFlow - New Ticket - $client_name: $subject";
        $email_body = "Hello, <br><br>This is a notification that a new ticket has been raised in ITFlow. <br>Client: $client_name<br>Priority: $priority<br>Link: https://$config_base_url/ticket.php?ticket_id=$id <br><br><b>$subject</b><br>$details";

        // Queue Mail
        $data = [
            [
                'from' => $config_ticket_from_email,
                'from_name' => $config_ticket_from_name,
                'recipient' => $config_ticket_new_ticket_notification_email,
                'recipient_name' => $config_ticket_from_name,
                'subject' => $email_subject,
                'body' => $email_body,
            ]
        ];
        addToMailQueue($mysqli, $data);
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

        $ticket_reply_id = mysqli_insert_id($mysqli);

        // Update Ticket Last Response Field & set ticket to open as client has replied
        mysqli_query($mysqli, "UPDATE tickets SET ticket_status = 2 WHERE ticket_id = $ticket_id AND ticket_client_id = $session_client_id LIMIT 1");


        // Get ticket details &  Notify the assigned tech (if any)
        $ticket_details = mysqli_fetch_array(mysqli_query($mysqli, "SELECT * FROM tickets LEFT JOIN clients ON ticket_client_id = client_id WHERE ticket_id = $ticket_id LIMIT 1"));

        $ticket_number = intval($ticket_details['ticket_number']);
        $ticket_assigned_to = intval($ticket_details['ticket_assigned_to']);
        $ticket_subject = sanitizeInput($ticket_details['ticket_subject']);
        $client_name = sanitizeInput($ticket_details['client_name']);

        if ($ticket_details && $ticket_assigned_to !== 0) {

            // Get tech details
            $tech_details = mysqli_fetch_array(mysqli_query($mysqli, "SELECT user_email, user_name FROM users WHERE user_id = $ticket_assigned_to LIMIT 1"));
            $tech_email = sanitizeInput($tech_details['user_email']);
            $tech_name = sanitizeInput($tech_details['user_name']);

            $subject = "$config_app_name Ticket updated - [$config_ticket_prefix$ticket_number] $ticket_subject";
            $body    = "Hello $tech_name,<br><br>A new reply has been added to the below ticket, check ITFlow for full details.<br><br>Client: $client_name<br>Ticket: $config_ticket_prefix$ticket_number<br>Subject: $ticket_subject<br><br>https://$config_base_url/ticket.php?ticket_id=$ticket_id";

            $data = [
                [
                    'from' => $config_ticket_from_email,
                    'from_name' => $config_ticket_from_name,
                    'recipient' => $tech_email,
                    'recipient_name' => $tech_name,
                    'subject' => $subject,
                    'body' => $body
                ]
            ];

            addToMailQueue($mysqli, $data);

        }

        // Store any attached any files
        if (!empty($_FILES)) {

            // Define & create directories, as required
            mkdirMissing('../uploads/tickets/');
            $upload_file_dir = "../uploads/tickets/" . $ticket_id . "/";
            mkdirMissing($upload_file_dir);

            for ($i = 0; $i < count($_FILES['file']['name']); $i++) {
                // Extract file details for this iteration
                $single_file = [
                    'name' => $_FILES['file']['name'][$i],
                    'type' => $_FILES['file']['type'][$i],
                    'tmp_name' => $_FILES['file']['tmp_name'][$i],
                    'error' => $_FILES['file']['error'][$i],
                    'size' => $_FILES['file']['size'][$i]
                ];

                if ($ticket_attachment_ref_name = checkFileUpload($single_file, array('jpg', 'jpeg', 'gif', 'png', 'webp', 'pdf', 'txt', 'md', 'doc', 'docx', 'odt', 'csv', 'xls', 'xlsx', 'ods', 'pptx', 'odp', 'zip', 'tar', 'gz', 'xml', 'msg', 'json', 'wav', 'mp3', 'ogg', 'mov', 'mp4', 'av1', 'ovpn'))) {

                    $file_tmp_path = $_FILES['file']['tmp_name'][$i];

                    $file_name = sanitizeInput($_FILES['file']['name'][$i]);
                    $extarr = explode('.', $_FILES['file']['name'][$i]);
                    $file_extension = sanitizeInput(strtolower(end($extarr)));

                    // Define destination file path
                    $dest_path = $upload_file_dir . $ticket_attachment_ref_name;

                    move_uploaded_file($file_tmp_path, $dest_path);

                    mysqli_query($mysqli, "INSERT INTO ticket_attachments SET ticket_attachment_name = '$file_name', ticket_attachment_reference_name = '$ticket_attachment_ref_name', ticket_attachment_reply_id = $ticket_reply_id, ticket_attachment_ticket_id = $ticket_id");
                }

            }
        }

        // Redirect back to original page
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
        mysqli_query($mysqli, "UPDATE tickets SET ticket_status = 5, ticket_closed_at = NOW() WHERE ticket_id = $ticket_id AND ticket_client_id = $session_client_id");

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
