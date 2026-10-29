<?php

/*
 * ITFlow - Database update to version 3.1.0 (from 3.0.9)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    // Notices for the client portal's home page - to every client (client 0)
    // or one - shown between their start and end, from Admin > Client Portal
    mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `portal_announcements` (
        `announcement_id` int(11) NOT NULL AUTO_INCREMENT,
        `announcement_title` varchar(200) NOT NULL,
        `announcement_body` text DEFAULT NULL,
        `announcement_tone` varchar(20) NOT NULL DEFAULT 'info',
        `announcement_client_id` int(11) NOT NULL DEFAULT 0,
        `announcement_starts_at` datetime DEFAULT NULL,
        `announcement_ends_at` datetime DEFAULT NULL,
        `announcement_created_by` int(11) NOT NULL DEFAULT 0,
        `announcement_created_at` datetime NOT NULL DEFAULT current_timestamp(),
        `announcement_updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
        `announcement_archived_at` datetime DEFAULT NULL,
        PRIMARY KEY (`announcement_id`),
        KEY `announcement_client_id` (`announcement_client_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    // Which ticket emails a contact wants, set from Your account in the portal:
    // their own tickets - every email (all) or not the created and closed
    // notices (replies) - and, for primary and technical contacts, their
    // company's other tickets: none, new ones, or new ones and every update (all)
    mysqli_query($mysqli, "ALTER TABLE `contacts`
        ADD COLUMN `contact_ticket_emails` varchar(10) NOT NULL DEFAULT 'all' AFTER `contact_technical`,
        ADD COLUMN `contact_company_ticket_emails` varchar(10) NOT NULL DEFAULT 'none' AFTER `contact_ticket_emails`");

    // Ask everyone signing in to the portal with a password for a code from an authenticator app
    mysqli_query($mysqli, "ALTER TABLE `settings`
        ADD COLUMN `config_client_portal_require_mfa` tinyint(1) NOT NULL DEFAULT 0 AFTER `config_client_portal_enable`");

    // Portal password links are kept as a SHA-256 of the token from now on, and
    // run out (user_password_reset_token_expire, unused until now). Links sent
    // before this keep working for a day.
    mysqli_query($mysqli, "UPDATE `users` SET
        `user_password_reset_token` = SHA2(`user_password_reset_token`, 256),
        `user_password_reset_token_expire` = NOW() + INTERVAL 1 DAY
        WHERE `user_password_reset_token` IS NOT NULL AND `user_password_reset_token` <> ''");
