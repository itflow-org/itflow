<?php

/*
 * ITFlow - Database update to version 2.8.8 (from 2.8.7)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    // Events: a repeat can now be every N days/weeks/months/years and stop on a date,
    // and single occurrences can be skipped (the dates they would have fallen on).
    // The sequence goes up each time an invited event changes, so the attendees'
    // calendars take the update rather than ignoring it. Who added it puts it on
    // their own schedule.
    mysqli_query($mysqli, "ALTER TABLE `calendar_events` ADD COLUMN `event_repeat_interval` int(11) NOT NULL DEFAULT 1 AFTER `event_repeat`");
    mysqli_query($mysqli, "ALTER TABLE `calendar_events` ADD COLUMN `event_repeat_until` date DEFAULT NULL AFTER `event_repeat_interval`");
    mysqli_query($mysqli, "ALTER TABLE `calendar_events` ADD COLUMN `event_repeat_skip` text DEFAULT NULL AFTER `event_repeat_until`");
    mysqli_query($mysqli, "ALTER TABLE `calendar_events` ADD COLUMN `event_sequence` int(11) NOT NULL DEFAULT 0 AFTER `event_repeat_skip`");
    mysqli_query($mysqli, "ALTER TABLE `calendar_events` ADD COLUMN `event_created_by` int(11) NOT NULL DEFAULT 0 AFTER `event_archived_at`");

    // The calendar asks for a date range at a time now, rather than everything at once
    mysqli_query($mysqli, "ALTER TABLE `calendar_events` ADD INDEX `event_start` (`event_start`)");

    // Agents on an event as well as client contacts, and when each was last sent the invitation
    mysqli_query($mysqli, "ALTER TABLE `calendar_event_attendees` ADD COLUMN `attendee_user_id` int(11) NOT NULL DEFAULT 0 AFTER `attendee_contact_id`");
    mysqli_query($mysqli, "ALTER TABLE `calendar_event_attendees` ADD COLUMN `attendee_invited_at` datetime DEFAULT NULL AFTER `attendee_invitation_status`");
    mysqli_query($mysqli, "ALTER TABLE `calendar_event_attendees` ADD INDEX `attendee_user_id` (`attendee_user_id`)");

    // Each agent's own schedule as a subscription link - their scheduled tickets,
    // the events they are on and the projects they manage - for the phone or
    // Nextcloud calendar. The key is the only thing the link carries, so like the
    // calendar feed keys it is case-sensitive.
    mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `user_calendar_feeds` (
        `feed_user_id` int(11) NOT NULL,
        `feed_key` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
        `feed_scope` varchar(20) NOT NULL DEFAULT 'mine',
        `feed_tickets` tinyint(1) NOT NULL DEFAULT 1,
        `feed_events` tinyint(1) NOT NULL DEFAULT 1,
        `feed_projects` tinyint(1) NOT NULL DEFAULT 1,
        `feed_busy_only` tinyint(1) NOT NULL DEFAULT 0,
        `feed_created_at` datetime NOT NULL DEFAULT current_timestamp(),
        `feed_accessed_at` datetime DEFAULT NULL,
        PRIMARY KEY (`feed_user_id`),
        UNIQUE KEY `feed_key` (`feed_key`),
        CONSTRAINT `user_calendar_feeds_ibfk_1` FOREIGN KEY (`feed_user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    // A calendar invitation - with the timezone it is in, so a repeating meeting
    // keeps its time across a clock change - is longer than the 1024 characters
    // the mail queue kept for it
    mysqli_query($mysqli, "ALTER TABLE `email_queue` MODIFY `email_cal_str` text DEFAULT NULL");
