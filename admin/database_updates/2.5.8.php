<?php

/*
 * ITFlow - Database update to version 2.5.8 (from 2.5.7)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    // Calendars can now be published as a read-only ICS feed for Google Calendar,
    // Nextcloud and any other subscription client. The key is stored in cleartext,
    // matching invoice_url_key and shared_items.item_key - hashing it would buy
    // nothing here (anyone holding the database already holds the events the key
    // grants access to) and would make the URL impossible to re-copy for a second
    // device without breaking every existing subscriber.
    //
    // The UNIQUE key is on a nullable column, so unshared calendars all keep NULL.
    // The column is explicitly utf8mb4_bin: the table default is utf8mb4_general_ci,
    // which compares case-insensitively, and the key alphabet is mixed-case
    // base64url - a _ci column would throw away entropy on lookup and make the
    // UNIQUE index blind to case.
    mysqli_query($mysqli, "ALTER TABLE `calendars`
        ADD COLUMN `calendar_feed_key` varchar(64) COLLATE utf8mb4_bin DEFAULT NULL AFTER `calendar_color`,
        ADD COLUMN `calendar_feed_busy_only` tinyint(1) NOT NULL DEFAULT 0 AFTER `calendar_feed_key`,
        ADD COLUMN `calendar_feed_created_at` datetime DEFAULT NULL AFTER `calendar_feed_busy_only`,
        ADD COLUMN `calendar_feed_accessed_at` datetime DEFAULT NULL AFTER `calendar_feed_created_at`,
        ADD UNIQUE KEY `calendar_feed_key` (`calendar_feed_key`)");
