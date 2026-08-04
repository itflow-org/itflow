<?php

/*
 * ITFlow - Database update to version 2.5.7 (from 2.5.6)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    // Queued mail can now carry file attachments. The column holds a JSON manifest
    // of app-root-relative paths and display names rather than file contents, so
    // the queue table stays small and the files stay in uploads/ where the ticket
    // attachment endpoints already serve them from. It is scrubbed on delivery
    // alongside the body, like email_cal_str.
    mysqli_query($mysqli, "ALTER TABLE `email_queue`
        ADD COLUMN `email_attachments` text DEFAULT NULL AFTER `email_cal_str`");
