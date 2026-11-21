<?php

/*
 * ITFlow - Database update to version 3.9.0 (from 3.8.9)
 * Included by admin/database_updates.php - do not access directly
 *
 * Offices (functions/offices.php) - the other places the company works from,
 * beside the main one in Admin > Settings > Company Details:
 *   offices - one row an office: its name, address, phone and email. Removed
 *     ones are kept (office_archived_at) for the record.
 *   users.user_office_id - the office an agent works from; 0 (the default) is
 *     the main one.
 *   user_invites.invite_office_id - the office an invited agent will work from,
 *     given to them when they accept.
 *
 * Re-runnable: the table is made and each column added only where it isn't there yet.
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    $itflow_column = function ($table, $column) use ($mysqli) {
        $result = mysqli_query($mysqli, "SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$table' AND COLUMN_NAME = '$column' LIMIT 1");
        return $result && mysqli_num_rows($result) > 0;
    };

    mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `offices` (
        `office_id` int(11) NOT NULL AUTO_INCREMENT,
        `office_name` varchar(100) NOT NULL,
        `office_address` varchar(200) NOT NULL DEFAULT '',
        `office_city` varchar(200) NOT NULL DEFAULT '',
        `office_state` varchar(200) NOT NULL DEFAULT '',
        `office_zip` varchar(200) NOT NULL DEFAULT '',
        `office_country` varchar(200) NOT NULL DEFAULT '',
        `office_phone_country_code` varchar(10) NOT NULL DEFAULT '',
        `office_phone` varchar(200) NOT NULL DEFAULT '',
        `office_email` varchar(200) NOT NULL DEFAULT '',
        `office_created_at` datetime NOT NULL DEFAULT current_timestamp(),
        `office_updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
        `office_archived_at` datetime DEFAULT NULL,
        PRIMARY KEY (`office_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    if (!$itflow_column('users', 'user_office_id')) {
        mysqli_query($mysqli, "ALTER TABLE `users` ADD `user_office_id` int(11) NOT NULL DEFAULT 0 AFTER `user_role_id`");
    }
    if (!$itflow_column('user_invites', 'invite_office_id')) {
        mysqli_query($mysqli, "ALTER TABLE `user_invites` ADD `invite_office_id` int(11) NOT NULL DEFAULT 0 AFTER `invite_role_id`");
    }
