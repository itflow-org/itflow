<?php

/*
 * ITFlow - Database update to version 4.0.3 (from 4.0.2)
 * Included by admin/database_updates.php - do not access directly
 *
 * contacts.contact_email_bounced_at and contact_email_bounce - when mail to a contact last bounced,
 * and what the mail server said, set by the ticket mail parser when a bounce comes back
 * (functions/contact_bounce.php). Cleared when mail comes in from the address, when the address is
 * changed, or by hand.
 *
 * Re-runnable: each column is only added where it isn't there yet.
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    $itflow_column = function ($table, $column) use ($mysqli) {
        $table = mysqli_real_escape_string($mysqli, $table);
        $column = mysqli_real_escape_string($mysqli, $column);
        return mysqli_num_rows(mysqli_query($mysqli, "SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$table' AND COLUMN_NAME = '$column'")) > 0;
    };

    if (!$itflow_column('contacts', 'contact_email_bounced_at')) {
        mysqli_query($mysqli, "ALTER TABLE `contacts` ADD `contact_email_bounced_at` datetime DEFAULT NULL AFTER `contact_email`");
    }
    if (!$itflow_column('contacts', 'contact_email_bounce')) {
        mysqli_query($mysqli, "ALTER TABLE `contacts` ADD `contact_email_bounce` varchar(255) DEFAULT NULL AFTER `contact_email_bounced_at`");
    }
