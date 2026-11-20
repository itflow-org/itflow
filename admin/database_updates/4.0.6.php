<?php

/*
 * ITFlow - Database update to version 4.0.6 (from 4.0.5)
 * Included by admin/database_updates.php - do not access directly
 *
 * Reminders before what is coming up (functions/calendar_reminders.php, cron/calendar_reminders.php):
 *   calendar_events.event_reminder - minutes before an event its agents are reminded, NULL for none
 *   user_settings.user_config_booking_reminder - minutes before work booked on a ticket assigned to
 *     the agent they are reminded, NULL for none; user_config_reminder_email - email them as well
 *   calendar_reminders - each reminder sent, so it goes once: what for (an event or a ticket), which
 *     time it was for (an occurrence of a repeating event, a ticket booked again), and to whom
 *
 * Re-runnable: each column and the table are only added where they aren't there yet.
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    $itflow_column = function ($table, $column) use ($mysqli) {
        $table = mysqli_real_escape_string($mysqli, $table);
        $column = mysqli_real_escape_string($mysqli, $column);
        return mysqli_num_rows(mysqli_query($mysqli, "SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$table' AND COLUMN_NAME = '$column'")) > 0;
    };

    if (!$itflow_column('calendar_events', 'event_reminder')) {
        mysqli_query($mysqli, "ALTER TABLE `calendar_events` ADD `event_reminder` int(11) DEFAULT NULL AFTER `event_sequence`");
    }
    if (!$itflow_column('user_settings', 'user_config_booking_reminder')) {
        mysqli_query($mysqli, "ALTER TABLE `user_settings` ADD `user_config_booking_reminder` int(11) DEFAULT NULL AFTER `user_config_compact_rows`");
    }
    if (!$itflow_column('user_settings', 'user_config_reminder_email')) {
        mysqli_query($mysqli, "ALTER TABLE `user_settings` ADD `user_config_reminder_email` tinyint(1) NOT NULL DEFAULT 0 AFTER `user_config_booking_reminder`");
    }

    mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `calendar_reminders` (
        `reminder_kind` varchar(10) NOT NULL,
        `reminder_item_id` int(11) NOT NULL,
        `reminder_for` datetime NOT NULL,
        `reminder_user_id` int(11) NOT NULL,
        `reminder_sent_at` datetime NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`reminder_kind`, `reminder_item_id`, `reminder_for`, `reminder_user_id`),
        KEY `reminder_sent_at` (`reminder_sent_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
