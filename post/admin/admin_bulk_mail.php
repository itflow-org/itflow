<?php

/*
 * ITFlow - GET/POST request handler for bulk email
 */

if (isset($_POST['send_bulk_mail_now'])) {
    
    if (isset($_POST['contact_ids'])) {

        $count = count($_POST['contact_ids']);

        $mail_from = sanitizeInput($_POST['mail_from']);
        $mail_from_name = sanitizeInput($_POST['mail_from_name']);
        $subject = sanitizeInput($_POST['subject']);
        $body = mysqli_escape_string($mysqli, $_POST['body']);
        $queued_at = sanitizeInput($_POST['queued_at']);

        // Add Emails
        foreach($_POST['contact_ids'] as $contact_id) {
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
                'body' => $body,
                'queued_at' => $queued_at
            ];
        }
        addToMailQueue($mysqli, $data);

        // Logging
        logAction("Bulk Mail", "Send", "$session_name sent $count messages via bulk mail");

        $_SESSION['alert_message'] = "<strong>$count</strong> messages queued";
    
    }

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}