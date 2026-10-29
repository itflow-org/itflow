<?php

/*
 * ITFlow - Database update to version 3.4.6 (from 3.4.5)
 * Included by admin/database_updates.php - do not access directly
 *
 * Client portal invites and sign-ups (functions/portal_invite.php).
 * portal_invites holds both: an invite an agent or a primary contact sends
 * to an address (invite_kind invite), and the link someone asks for on the
 * sign-in page to finish signing up (signup). Only the SHA-256 of the link's
 * token is kept. Nobody is a contact until the link is used.
 *
 * clients.client_portal_signup lets people with an address at one of the
 * client's Domains sign up, and settings.config_client_portal_signup turns
 * signing up on for the portal as a whole. Both start off.
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    $itflow_column = function ($table, $column) use ($mysqli) {
        $result = mysqli_query($mysqli, "SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$table' AND COLUMN_NAME = '$column' LIMIT 1");
        return $result && mysqli_num_rows($result) > 0;
    };

    mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `portal_invites` (
        `invite_id` int(11) NOT NULL AUTO_INCREMENT,
        `invite_kind` varchar(10) NOT NULL DEFAULT 'invite',
        `invite_email` varchar(200) NOT NULL,
        `invite_name` varchar(200) DEFAULT NULL,
        `invite_billing` tinyint(1) NOT NULL DEFAULT 0,
        `invite_technical` tinyint(1) NOT NULL DEFAULT 0,
        `invite_token_hash` char(64) NOT NULL DEFAULT '',
        `invite_expires_at` datetime NOT NULL,
        `invite_sent_at` datetime DEFAULT NULL,
        `invite_sent_count` int(11) NOT NULL DEFAULT 0,
        `invite_created_by` int(11) NOT NULL DEFAULT 0,
        `invite_created_by_contact_id` int(11) NOT NULL DEFAULT 0,
        `invite_created_at` datetime NOT NULL DEFAULT current_timestamp(),
        `invite_accepted_at` datetime DEFAULT NULL,
        `invite_contact_id` int(11) NOT NULL DEFAULT 0,
        `invite_cancelled_at` datetime DEFAULT NULL,
        `invite_client_id` int(11) NOT NULL,
        PRIMARY KEY (`invite_id`),
        KEY `invite_client_id` (`invite_client_id`, `invite_email`),
        KEY `invite_contact_id` (`invite_contact_id`),
        CONSTRAINT `portal_invites_ibfk_1` FOREIGN KEY (`invite_client_id`) REFERENCES `clients` (`client_id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    if (!$itflow_column('clients', 'client_portal_signup')) {
        mysqli_query($mysqli, "ALTER TABLE `clients` ADD `client_portal_signup` tinyint(1) NOT NULL DEFAULT 0 AFTER `client_reminders`");
    }
    if (!$itflow_column('settings', 'config_client_portal_signup')) {
        mysqli_query($mysqli, "ALTER TABLE `settings` ADD `config_client_portal_signup` tinyint(1) NOT NULL DEFAULT 0 AFTER `config_client_portal_require_mfa`");
    }

    unset($itflow_column);
