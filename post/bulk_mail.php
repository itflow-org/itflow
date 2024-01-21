<?php

/*
 * ITFlow - GET/POST request handler for bulk email
 */

if (isset($_POST['send_bulk_mail_now'])) {
    
    if ($_POST['contact']) {

        $mail_from = sanitizeInput($_POST['mail_from']);
        $mail_from_name = sanitizeInput($_POST['mail_from_name']);
        $subject = sanitizeInput($_POST['subject']);
        $body = mysqli_escape_string($mysqli, $_POST['body']);

        // Add Emails
        foreach($_POST['contact'] as $contact_id) {
            $contact_id = intval($contact_id);

            $sql = mysqli_query($mysqli,"SELECT * FROM contacts WHERE contact_id = $contact_id");
            $row = mysqli_fetch_array($sql);
            $contact_name = sanitizeInput($row['contact_name']);
            $contact_email = sanitizeInput($row['contact_email']);
            $client_id = intval($row['contact_client_id']);

            // Queue Mail
            $data[] = [    
                'from' => $mail_from,
                'from_name' => $mail_from_name,
                'recipient' => $contact_email,
                'recipient_name' => $contact_name,
                'subject' => $subject,
                'body' => $body
            ];
        }
        addToMailQueue($mysqli, $data);
        
        // Logging
        mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Bulk Mail', log_action = 'Send', log_description = '$session_name sent bulk email', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id");

        $_SESSION['alert_message'] = "You Sent Bulk Mail";
    
    } else {
    
        $_SESSION['alert_message'] = "NO Bulk Mail SENT";
    
    }

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}