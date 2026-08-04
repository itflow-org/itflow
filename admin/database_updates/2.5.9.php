<?php

/*
 * ITFlow - Database update to version 2.5.9 (from 2.5.8)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    // Events can now be marked all-day explicitly. Until now calendar_events had
    // no all-day column at all - FullCalendar inferred it from a start value with
    // no time component, which meant a genuine midnight appointment was
    // indistinguishable from an all-day event.
    mysqli_query($mysqli, "ALTER TABLE `calendar_events`
        ADD COLUMN `event_all_day` tinyint(1) NOT NULL DEFAULT 0 AFTER `event_end`");

    // Backfill using the same rule the calendar already rendered by, so existing
    // events keep displaying exactly as they do today: a midnight start, and
    // either no end or a midnight end.
    mysqli_query($mysqli, "UPDATE `calendar_events`
        SET `event_all_day` = 1
        WHERE TIME(`event_start`) = '00:00:00'
        AND (`event_end` IS NULL OR TIME(`event_end`) = '00:00:00')");

    // Corrects the collation on installs that already applied 2.5.8 before the
    // column was pinned to utf8mb4_bin. Harmless to re-run.
    mysqli_query($mysqli, "ALTER TABLE `calendars`
        MODIFY COLUMN `calendar_feed_key` varchar(64) COLLATE utf8mb4_bin DEFAULT NULL");
