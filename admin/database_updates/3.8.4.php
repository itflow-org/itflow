<?php

/*
 * ITFlow - Database update to version 3.8.4 (from 3.8.3)
 * Included by admin/database_updates.php - do not access directly
 *
 * The calendar, per agent (functions/calendar.php):
 *   user_settings.user_config_calendar_color - the colour their booked work and events are drawn
 *     in, '#rrggbb'; NULL is one worked out from their user id (calendarAgentColor())
 *   user_settings.user_config_calendar_state - what they last had shown on the calendar page
 *     (calendars, sources, whose, view), as JSON, so every device opens the same way
 *
 * Re-runnable: each column is added only where it isn't there yet.
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    $itflow_column = function ($table, $column) use ($mysqli) {
        $result = mysqli_query($mysqli, "SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$table' AND COLUMN_NAME = '$column' LIMIT 1");
        return $result && mysqli_num_rows($result) > 0;
    };

    if (!$itflow_column('user_settings', 'user_config_calendar_color')) {
        mysqli_query($mysqli, "ALTER TABLE `user_settings` ADD `user_config_calendar_color` varchar(7) DEFAULT NULL AFTER `user_config_calendar_first_day`");
    }

    if (!$itflow_column('user_settings', 'user_config_calendar_state')) {
        mysqli_query($mysqli, "ALTER TABLE `user_settings` ADD `user_config_calendar_state` text DEFAULT NULL AFTER `user_config_calendar_color`");
    }
