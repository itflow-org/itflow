<?php

/*
 * ITFlow - Database update to version 3.3.7 (from 3.3.6)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    // Why an email in the mail queue last failed, or who cancelled it - kept
    // with the email, where Maintenance > Mail Queue shows it, rather than only
    // in the app logs. Checked first, so the step can run again.
    $itflow_exists = mysqli_query($mysqli, "SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'email_queue' AND COLUMN_NAME = 'email_error' LIMIT 1");
    if (!$itflow_exists || mysqli_num_rows($itflow_exists) === 0) {
        mysqli_query($mysqli, "ALTER TABLE `email_queue` ADD `email_error` varchar(500) DEFAULT NULL AFTER `email_failed_at`");
    }

    unset($itflow_exists);
