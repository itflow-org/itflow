<?php

/*
 * ITFlow - Database update to version 3.8.3 (from 3.8.2)
 * Included by admin/database_updates.php - do not access directly
 *
 * Repeats by weekday - "the third Wednesday", "the last Friday" (functions/by_day.php):
 *   recurring_tickets.recurring_ticket_by_day - the rule for monthly to yearly recurring tickets
 *   calendar_events.event_repeat_by_day       - the rule for monthly and yearly events
 * Both hold an RFC 5545 BYDAY value ("3WE", "-1FR"); NULL is the same date each time, as before.
 *
 * Re-runnable: each column is added only where it isn't there yet.
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    $itflow_column = function ($table, $column) use ($mysqli) {
        $result = mysqli_query($mysqli, "SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$table' AND COLUMN_NAME = '$column' LIMIT 1");
        return $result && mysqli_num_rows($result) > 0;
    };

    if (!$itflow_column('recurring_tickets', 'recurring_ticket_by_day')) {
        mysqli_query($mysqli, "ALTER TABLE `recurring_tickets` ADD `recurring_ticket_by_day` varchar(5) DEFAULT NULL AFTER `recurring_ticket_frequency`");
    }

    if (!$itflow_column('calendar_events', 'event_repeat_by_day')) {
        mysqli_query($mysqli, "ALTER TABLE `calendar_events` ADD `event_repeat_by_day` varchar(5) DEFAULT NULL AFTER `event_repeat_interval`");
    }
