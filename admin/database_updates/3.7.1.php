<?php

/*
 * ITFlow - Database update to version 3.7.1 (from 3.7.0)
 * Included by admin/database_updates.php - do not access directly
 *
 * The mail queue keeps what a sent email said (functions/mail_queue.php):
 *   email_queue.email_sensitive - an email with a link or code that opens something (a shared
 *     login, an invite, a password link, a signing or approval link), cleared once it has gone.
 *     Every other email keeps what it said until it is deleted after 90 days. Each one waiting
 *     or going now is marked, as it would have been cleared once sent before this update.
 *
 * Re-runnable: the column is made, and those marked, only where it isn't there yet.
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    $itflow_column_exists = mysqli_query($mysqli, "SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'email_queue' AND COLUMN_NAME = 'email_sensitive' LIMIT 1");
    if (!$itflow_column_exists || mysqli_num_rows($itflow_column_exists) === 0) {
        mysqli_query($mysqli, "ALTER TABLE `email_queue` ADD `email_sensitive` tinyint(1) NOT NULL DEFAULT 0 AFTER `email_ticket_reply_id`");
        mysqli_query($mysqli, "UPDATE `email_queue` SET `email_sensitive` = 1 WHERE `email_status` IN (0, 1)");
    }
