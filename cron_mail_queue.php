<?php

// Set working directory to the directory this cron script lives at.
chdir(dirname(__FILE__));

// Ensure we're running from command line
if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line.\n");
}

require_once "config.php";
// Set Timezone
require_once "inc_set_timezone.php";
require_once "functions.php";

$sql_settings = mysqli_query($mysqli, "SELECT * FROM settings WHERE company_id = 1");

$row = mysqli_fetch_array($sql_settings);

// Company Settings
$config_enable_cron = intval($row['config_enable_cron']);
$config_smtp_host = $row['config_smtp_host'];
$config_smtp_username = $row['config_smtp_username'];
$config_smtp_password = $row['config_smtp_password'];
$config_smtp_port = intval($row['config_smtp_port']);
$config_smtp_encryption = $row['config_smtp_encryption'];

// Check cron is enabled
if ($config_enable_cron == 0) {
    error_log("Mail queue error - Cron is not enabled");
    exit("Cron: is not enabled -- Quitting..");
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
        // Logging
        logAction("Cron-Mail-Queue", "Delete", "Cron Mail Queuer detected a lock file was present but was over 10 minutes old so it removed it.");
    
    } else {
        
        // Logging
        logAction("Cron-Mail-Queue", "Locked", "Cron Mail Queuer attempted to execute but was already executing so instead it terminated.");
        
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

/*
 * ###############################################################################################################
 *  Initial email send
 * ###############################################################################################################
 */
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

        // First, validate the sender email address
        if (filter_var($email_from, FILTER_VALIDATE_EMAIL)) {

            // Sanitized Input
            $email_recipient_logging = sanitizeInput($row['email_recipient']);
            $email_subject_logging = sanitizeInput($row['email_subject']);

            // Update the status to sending
            mysqli_query($mysqli, "UPDATE email_queue SET email_status = 1 WHERE email_id = $email_id");

            // Next, verify recipient email is valid
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

                    appNotify("Cron-Mail-Queue", "Failed to send email #$email_id to $email_recipient_logging");

                    // Logging
                    logAction("Cron-Mail-Queue", "Error", "Failed to send email: $email_id to $email_recipient_logging regarding $email_subject_logging. $mail");
                } else {
                    // Update Message - Success
                    mysqli_query($mysqli, "UPDATE email_queue SET email_status = 3, email_sent_at = NOW(), email_attempts = 1 WHERE email_id = $email_id");
                }
            
            } else {
                
                // Recipient email isn't valid, mark as failed and log the error
                mysqli_query($mysqli, "UPDATE email_queue SET email_status = 2, email_attempts = 99 WHERE email_id = $email_id");

                // Logging
                logAction("Cron-Mail-Queue", "Error", "Failed to send email: $email_id due to invalid recipient address. Email subject was: $email_subject_logging");
            }

        } else {
            error_log("Failed to send email due to invalid sender address (' $email_from ') - check configuration in settings.");

            $email_from_logging = sanitizeInput($row['email_from']);
            mysqli_query($mysqli, "UPDATE email_queue SET email_status = 2, email_attempts = 99 WHERE email_id = $email_id");

            // Logging
            logAction("Cron-Mail-Queue", "Error", "Failed to send email #$email_id due to invalid sender address: $email_from_logging - check configuration in settings.");
            
            appNotify("Mail", "Failed to send email #$email_id due to invalid sender address");

        }

    }
}


/*
 * ###############################################################################################################
 *  Retries
 * ###############################################################################################################
 */
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

        // Verify recipient email is valid
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

                // Logging
                logAction("Cron-Mail-Queue", "Error", "Failed to re-send email #$email_id to $email_recipient_logging regarding $email_subject_logging. $mail");
            
            } else {
                
                // Update Message
                mysqli_query($mysqli, "UPDATE email_queue SET email_status = 3, email_sent_at = NOW(), email_attempts = $email_attempts WHERE email_id = $email_id");
            
            }
        }
    }
}

// Remove the lock file once mail has finished processing
unlink($lock_file_path);
