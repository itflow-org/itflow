<?php

require_once("config.php");
require_once("functions.php");

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
if ( $argv[1] !== $config_cron_key ) {
    exit("Cron Key invalid  -- Quitting..");
}

/*
 * ###############################################################################################################
 *  STARTUP ACTIONS
 * ###############################################################################################################
 */

//Logging
mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Cron', log_action = 'Started', log_description = 'Cron started processing emails from the queue'");

// Process Mail Queue

// Get date for search
$today = new DateTime();
$today_text = $today->format('Y-m-d');

// Get Mail Queue that hasnt been sent yet
$sql_queue = mysqli_query($mysqli, "SELECT * FROM email_queue WHERE email_sent_at IS NULL");

if (mysqli_num_rows($sql_queue) > 0) {
    while ($row = mysqli_fetch_array($sql_queue)) {
        $email_id = intval($row['email_id']);
        $email_recipient = nullable_htmlentities($row['email_recipient']);
        $email_recipient_logging = sanitizeInput($row['email_recipient']);
        $email_recipient_name = "";
        $email_from = nullable_htmlentities($row['email_from']);
        $email_from_name = nullable_htmlentities($row['email_from_name']);
        $email_subject = nullable_htmlentities($row['email_subject']);
        $email_subject_logging = sanitizeInput($row['email_subject']);
        $email_content = nullable_htmlentities($row['email_content']);
        $email_queued_at = nullable_htmlentities($row['email_queued_at']);
        $email_sent_at = nullable_htmlentities($row['email_sent_at']);

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
                $email_content
            );

            if ($mail !== true) {
                mysqli_query($mysqli, "INSERT INTO notifications SET notification_type = 'Mail', notification = 'Failed to send email to $email_recipient_logging'");
                mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Mail', log_action = 'Error', log_description = 'Failed to send email to $email_recipient_logging regarding $email_subject_logging. $mail'");
            }

        }

        // Update Message
        mysqli_query($mysqli, "UPDATE email_queue SET email_sent_at = NOW() WHERE email_id = $email_id");
    }

}

/*
 * ###############################################################################################################
 *  FINISH UP
 * ###############################################################################################################
 */

// Logging
mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Cron', log_action = 'Ended', log_description = 'Cron finished processing the mail queue'");
