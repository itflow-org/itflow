<?php

/*
 * ITFlow - Database update to version 2.0.3 (from 2.0.2)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    // Clean up orphans
    mysqli_query($mysqli, "
        DELETE FROM `calendar_event_attendees`
        WHERE `attendee_event_id` NOT IN (SELECT `event_id` FROM `calendar_events`);
    ");

    mysqli_query($mysqli, "
        DELETE FROM `calendar_events`
        WHERE `event_calendar_id` NOT IN (SELECT `calendar_id` FROM `calendars`);
    ");

    // Add foreign key to calendar_event_attendees
    mysqli_query($mysqli, "
        ALTER TABLE `calendar_event_attendees`
        ADD FOREIGN KEY (`attendee_event_id`) REFERENCES `calendar_events`(`event_id`) ON DELETE CASCADE
    ");

    // Add foreign key to calendar_events
    mysqli_query($mysqli, "
        ALTER TABLE `calendar_events`
        ADD FOREIGN KEY (`event_calendar_id`) REFERENCES `calendars`(`calendar_id`) ON DELETE CASCADE
    ");
