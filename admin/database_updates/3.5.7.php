<?php

/*
 * ITFlow - Database update to version 3.5.7 (from 3.5.6)
 * Included by admin/database_updates.php - do not access directly
 *
 * settings.config_update_details - what the update check found besides the commits, as JSON:
 * the version the update brings, the changelog between this install and it, and the files
 * this install has changed that updating would put back. Written by cron/update_check.php,
 * read by Maintenance > Update.
 *
 * Re-runnable: skipped where the column is already there.
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    $itflow_column_exists = mysqli_query($mysqli, "SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'settings' AND COLUMN_NAME = 'config_update_details' LIMIT 1");

    if (!$itflow_column_exists || mysqli_num_rows($itflow_column_exists) === 0) {
        mysqli_query($mysqli, "ALTER TABLE `settings` ADD `config_update_details` MEDIUMTEXT DEFAULT NULL AFTER `config_update_checked_at`");
    }

    unset($itflow_column_exists);
