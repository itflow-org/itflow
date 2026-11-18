<?php

/*
 * ITFlow - Database update to version 3.8.6 (from 3.8.5)
 * Included by admin/database_updates.php - do not access directly
 *
 * Each agent's own time zone (functions/user_timezone.php):
 *   user_settings.user_config_timezone - the zone they work in, when it isn't the
 *     company's; NULL is the company's. Stored times stay in company time.
 *
 * Re-runnable: the column is added only where it isn't there yet.
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    $itflow_column = function ($table, $column) use ($mysqli) {
        $result = mysqli_query($mysqli, "SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$table' AND COLUMN_NAME = '$column' LIMIT 1");
        return $result && mysqli_num_rows($result) > 0;
    };

    if (!$itflow_column('user_settings', 'user_config_timezone')) {
        mysqli_query($mysqli, "ALTER TABLE `user_settings` ADD `user_config_timezone` varchar(64) DEFAULT NULL AFTER `user_config_calendar_state`");
    }
