<?php

require_once "config.php";

// Set Timezone
require_once "inc_set_timezone.php";

require_once "functions.php";

$sql_settings = mysqli_query($mysqli, "SELECT * FROM settings WHERE company_id = 1");

$row = mysqli_fetch_array($sql_settings);

// Company Settings
$config_enable_cron = intval($row['config_enable_cron']);
$config_cron_key = $row['config_cron_key'];
$config_smtp_host = $row['config_smtp_host'];
$config_smtp_username = $row['config_smtp_username'];
$config_smtp_password = $row['config_smtp_password'];
$config_smtp_port = intval($row['config_smtp_port']);
$config_smtp_encryption = $row['config_smtp_encryption'];

$argv = $_SERVER['argv'];

// Check cron is enabled
if ($config_enable_cron == 0) {
    exit("Cron: is not enabled -- Quitting..");
}

// Check Cron Key
if ($argv[1] !== $config_cron_key) {
    exit("Cron Key invalid  -- Quitting..");
}

// Get system temp directory
$temp_dir = sys_get_temp_dir();

// Create the path for the lock file using the temp directory
$lock_file_path = "{$temp_dir}/itflow_mail_queue_{$installation_id}.lock";

// Check for lock file to prevent concurrent script runs
if (file_exists($lock_file_path)) {
    $file_age = time() - filemtime($lock_file_path);

    // If file is older than 10 minutes (600 seconds), delete and continue
    if ($file_age > 600) {
        unlink($lock_file_path);
        mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Cron-Mail-Queue', log_action = 'Delete', log_description = 'Cron Mail Queuer detected a lock file was present but was over 10 minutes old so it removed it.'");
    } else {
        mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Cron-Mail-Queue', log_action = 'Locked', log_description = 'Cron Mail Queuer attempted to execute but was already executing so instead it terminated.'");
        exit("Script is already running. Exiting.");
    }
}

// Create a lock file
file_put_contents($lock_file_path, "Locked");

// Process Mail Queue

// Email Status:
// 0 Queued
// 1 Sending
// 2 Failed
// 3 Sent

// Get Mail Queue that has status of Queued and send it to the function sendSingleEmail() located in functions.php

$sql_queue = mysqli_query($mysqli, "SELECT * FROM email_queue WHERE email_status = 0 AND email_queued_at <= NOW()");

if (mysqli_num_rows($sql_queue) > 0) {
    while ($row = mysqli_fetch_array($sql_queue)) {
        $email_id = intval($row['email_id']);
        $email_from = $row['email_from'];
        $email_from_name = $row['email_from_name'];
        $email_recipient = $row['email_recipient'];
        $email_recipient_name = $row['email_recipient_name'];
        $email_subject = $row['email_subject'];
        $email_content = $row['email_content'];
        $email_queued_at = $row['email_queued_at'];
        $email_sent_at = $row['email_sent_at'];
        $email_ics_str = $row['email_cal_str'];

        // Sanitized Input
        $email_recipient_logging = sanitizeInput($row['email_recipient']);
        $email_subject_logging = sanitizeInput($row['email_subject']);

        // Update the status to sending
        mysqli_query($mysqli, "UPDATE email_queue SET email_status = 1 WHERE email_id = $email_id");

        // Verify contact email is valid
        if (filter_var($email_recipient, FILTER_VALIDATE_EMAIL)) {

            $mail = sendSingleEmail(
                $config_smtp_host,
                $config_smtp_username,
                $config_smtp_password,
                $config_smtp_encryption,
                $config_smtp_port,
                $email_from,
                $email_from_name,
                $email_recipient,
                $email_recipient_name,
                $email_subject,
                $email_content,
                $email_ics_str
            );

            if ($mail !== true) {
                // Update Message - Failure
                mysqli_query($mysqli, "UPDATE email_queue SET email_status = 2, email_failed_at = NOW(), email_attempts = 1 WHERE email_id = $email_id");

                mysqli_query($mysqli, "INSERT INTO notifications SET notification_type = 'Mail', notification = 'Failed to send email to $email_recipient_logging'");
                mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Mail', log_action = 'Error', log_description = 'Failed to send email to $email_recipient_logging regarding $email_subject_logging. $mail'");
            } else {
                // Update Message - Success
                mysqli_query($mysqli, "UPDATE email_queue SET email_status = 3, email_sent_at = NOW(), email_attempts = 1 WHERE email_id = $email_id");
            }
        }
    }
}

//

// Get Mail that failed to send and attempt to send Failed Mail up to 4 times every 30 mins
$sql_failed_queue = mysqli_query($mysqli, "SELECT * FROM email_queue WHERE email_status = 2 AND email_attempts < 4 AND email_failed_at < NOW() + INTERVAL 30 MINUTE");

if (mysqli_num_rows($sql_failed_queue) > 0) {
    while ($row = mysqli_fetch_array($sql_failed_queue)) {
        $email_id = intval($row['email_id']);
        $email_from = $row['email_from'];
        $email_from_name = $row['email_from_name'];
        $email_recipient = $row['email_recipient'];
        $email_recipient_name = $row['email_recipient_name'];
        $email_subject = $row['email_subject'];
        $email_content = $row['email_content'];
        $email_queued_at = $row['email_queued_at'];
        $email_sent_at = $row['email_sent_at'];
        $email_ics_str = $row['email_cal_str'];
        // Increment the attempts
        $email_attempts = intval($row['email_attempts']) + 1;

        // Sanitized Input
        $email_recipient_logging = sanitizeInput($row['email_recipient']);
        $email_subject_logging = sanitizeInput($row['email_subject']);

        // Update the status to sending before actually sending
        mysqli_query($mysqli, "UPDATE email_queue SET email_status = 1 WHERE email_id = $email_id");

        // Verify contact email is valid
        if (filter_var($email_recipient, FILTER_VALIDATE_EMAIL)) {

            $mail = sendSingleEmail(
                $config_smtp_host,
                $config_smtp_username,
                $config_smtp_password,
                $config_smtp_encryption,
                $config_smtp_port,
                $email_from,
                $email_from_name,
                $email_recipient,
                $email_recipient_name,
                $email_subject,
                $email_content,
                $email_ics_str
            );

            if ($mail !== true) {
                // Update Message
                mysqli_query($mysqli, "UPDATE email_queue SET email_status = 2, email_failed_at = NOW(), email_attempts = $email_attempts WHERE email_id = $email_id");

                mysqli_query($mysqli, "INSERT INTO notifications SET notification_type = 'Mail', notification = 'Failed to send email to $email_recipient_logging'");
                mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Mail', log_action = 'Error', log_description = 'Failed to send email to $email_recipient_logging regarding $email_subject_logging. $mail'");
            } else {
                // Update Message
                mysqli_query($mysqli, "UPDATE email_queue SET email_status = 3, email_sent_at = NOW(), email_attempts = $email_attempts WHERE email_id = $email_id");
            }
        }
    }
}

// Remove the lock file once mail has finished processing so it doesnt get overun causing possible duplicates
unlink($lock_file_path);
