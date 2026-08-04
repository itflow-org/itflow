<?php

/*
 * ITFlow - Database update to version 2.0.4 (from 2.0.3)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    // Clean up orphaned history
    mysqli_query($mysqli, "
        DELETE FROM `certificate_history`
        WHERE `certificate_history_certificate_id` NOT IN (SELECT `certificate_id` FROM `certificates`);
    ");

    // Add foreign key certificate history
    mysqli_query($mysqli, "
        ALTER TABLE `certificate_history`
        ADD FOREIGN KEY (`certificate_history_certificate_id`) REFERENCES `certificates`(`certificate_id`) ON DELETE CASCADE
    ");
