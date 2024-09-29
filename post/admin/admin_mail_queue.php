<?php

if (isset($_GET['send_failed_mail'])) {

    $email_id = intval($_GET['send_failed_mail']);

    mysqli_query($mysqli,"UPDATE email_queue SET email_status = 0, email_attempts = 3 WHERE email_id = $email_id");

    // Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Email', log_action = 'Send', log_description = '$session_name attempted to force send email queue id: $email_id', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id, log_entity_id = $email_id");

    $_SESSION['alert_message'] = "Email Force Sent, give it a minute to resend";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_GET['cancel_mail'])) {

    $email_id = intval($_GET['cancel_mail']);

    mysqli_query($mysqli,"UPDATE email_queue SET email_status = 2, email_attempts = 99, email_failed_at = NOW() WHERE email_id = $email_id");

    // Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Email', log_action = 'Cancel', log_description = '$session_name canceled send email queue id: $email_id', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id, log_entity_id = $email_id");

    $_SESSION['alert_message'] = "Email cancelled and marked as failed.";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['bulk_cancel_emails'])) {

    validateCSRFToken($_POST['csrf_token']);

    $count = 0; // Default 0
    $email_ids = $_POST['email_ids']; // Get array of email IDs to be cancelled

    if (!empty($email_ids)) {

        // Cycle through array and mark each email as failed
        foreach ($email_ids as $email_id) {

            $email_id = intval($email_id);
            mysqli_query($mysqli,"UPDATE email_queue SET email_status = 2, email_attempts = 99, email_failed_at = NOW() WHERE email_id = $email_id");

            $count++;
        }

        // Logging
        mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Email', log_action = 'Cancel', log_description = '$session_name bulk cancelled $count emails from the mail Queue', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");

        $_SESSION['alert_message'] = "Cancelled $count email(s)";

    }

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if (isset($_POST['bulk_delete_emails'])) {

    validateCSRFToken($_POST['csrf_token']);

    $count = 0; // Default 0
    $email_ids = $_POST['email_ids']; // Get array of email IDs to be deleted

    if (!empty($email_ids)) {

        // Cycle through array and delete each email
        foreach ($email_ids as $email_id) {

            $email_id = intval($email_id);
            mysqli_query($mysqli,"DELETE FROM email_queue WHERE email_id = $email_id");

            $count++;
        }

        // Logging
        mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Email', log_action = 'Delete', log_description = '$session_name bulk deleted $count emails from the mail Queue', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");

        $_SESSION['alert_type'] = "danger";
        $_SESSION['alert_message'] = "Deleted $count email(s)";

    }

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}
