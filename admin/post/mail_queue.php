<?php

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_GET['send_failed_mail'])) {

    $email_id = intval($_GET['send_failed_mail']);

    mysqli_query($mysqli,"UPDATE email_queue SET email_status = 0, email_attempts = 3 WHERE email_id = $email_id");

    logAction("Email", "Send", "$session_name attempted to force send email id: $email_id in the mail queue", 0, $email_id);

    flash_alert("Email Force Sent, give it a minute to resend");

    redirect();

}

if (isset($_GET['cancel_mail'])) {

    $email_id = intval($_GET['cancel_mail']);

    mysqli_query($mysqli,"UPDATE email_queue SET email_status = 2, email_attempts = 99, email_failed_at = NOW() WHERE email_id = $email_id");

    logAction("Email", "Send", "$session_name canceled send email id: $email_id in the mail queue", 0, $email_id);

    flash_alert("Email cancelled and marked as failed.", 'error');

    redirect();

}

if (isset($_POST['bulk_cancel_emails'])) {

    validateCSRFToken($_POST['csrf_token']);

    if (isset($_POST['email_ids'])) {

        $count = count($_POST['email_ids']);

        // Cycle through array and mark each email as failed
        foreach ($_POST['email_ids'] as $email_id) {

            $email_id = intval($email_id);
            mysqli_query($mysqli,"UPDATE email_queue SET email_status = 2, email_attempts = 99, email_failed_at = NOW() WHERE email_id = $email_id");

            logAction("Email", "Cancel", "$session_name cancelled email id: $email_id in the mail queue", 0, $email_id);

        }

        logAction("Email", "Bulk Cancel", "$session_name cancelled $count email(s) in the mail queue");

        flash_alert("Cancelled <strong>$count</strong> email(s)", 'error');

    }

    redirect();

}

if (isset($_POST['bulk_delete_emails'])) {

    validateCSRFToken($_POST['csrf_token']);

    if (isset($_POST['email_ids'])) {

        $count = count($_POST['email_ids']);

        // Cycle through array and delete each email
        foreach ($_POST['email_ids'] as $email_id) {

            $email_id = intval($email_id);
            mysqli_query($mysqli,"DELETE FROM email_queue WHERE email_id = $email_id");

            logAction("Email", "Delete", "$session_name deleted email id: $email_id from the mail queue");

        }

        logAction("Email", "Bulk Delete", "$session_name deleted $count email(s) from the mail queue");

        flash_alert("Deleted <strong>$count</strong> email(s)", 'error');

    }

    redirect();

}
