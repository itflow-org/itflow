<?php

/*
 * ITFlow - Database update to version 3.7.2 (from 3.7.1)
 * Included by admin/database_updates.php - do not access directly
 *
 * Banners for agents (functions/agent_banner.php) - kept with the client portal's announcements,
 * as they have the same fields:
 *   portal_announcements.announcement_audience - portal (the portal's home page, as every
 *     announcement so far) or agents (a banner along the top of agents' pages).
 *   announcement_dismissals - who has hidden which banner, and when. A banner they hid comes
 *     back once it is changed after that; Urgent ones can't be hidden.
 *
 * Re-runnable: the column and the table are made only where they aren't there yet.
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    $itflow_column_exists = mysqli_query($mysqli, "SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'portal_announcements' AND COLUMN_NAME = 'announcement_audience' LIMIT 1");
    if (!$itflow_column_exists || mysqli_num_rows($itflow_column_exists) === 0) {
        mysqli_query($mysqli, "ALTER TABLE `portal_announcements` ADD `announcement_audience` varchar(10) NOT NULL DEFAULT 'portal' AFTER `announcement_client_id`");
    }

    mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `announcement_dismissals` (
        `announcement_dismissal_announcement_id` int(11) NOT NULL,
        `announcement_dismissal_user_id` int(11) NOT NULL,
        `announcement_dismissal_at` datetime NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`announcement_dismissal_announcement_id`, `announcement_dismissal_user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
