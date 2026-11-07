<?php

/*
 * ITFlow - Database update to version 3.6.0 (from 3.5.9)
 * Included by admin/database_updates.php - do not access directly
 *
 * Text messaging (functions/sms.php):
 *   sms_providers - the texting service ITFlow sends and receives through (Skyetel), its
 *     keys, the number it texts from and the key in its inbound address
 *   sms_messages - every text sent and received, and what came of it: queued, sent or failed
 *     going out; on a ticket, in the Texts inbox (unmatched, a code, spam) or a keyword coming in
 *   sms_numbers - what is known about a number: a name for it, whether it replied STOP, and
 *     whether it is blocked as spam
 *   settings.config_ticket_sms_parse, config_ticket_sms_unknown_senders, config_ticket_sms_acknowledge -
 *     Settings > Tickets > Text to ticket
 *
 * Re-runnable: each table and column is made only where it isn't there yet.
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `sms_providers` (
        `sms_provider_id` int(11) NOT NULL AUTO_INCREMENT,
        `sms_provider_name` varchar(100) NOT NULL,
        `sms_provider_description` varchar(255) DEFAULT NULL,
        `sms_provider_username` varchar(255) NOT NULL DEFAULT '',
        `sms_provider_secret` varchar(500) NOT NULL DEFAULT '',
        `sms_provider_number` varchar(20) NOT NULL DEFAULT '',
        `sms_provider_inbound_key` varchar(64) NOT NULL DEFAULT '',
        `sms_provider_active` tinyint(1) NOT NULL DEFAULT 1,
        `sms_provider_last_inbound_at` datetime DEFAULT NULL,
        `sms_provider_created_at` datetime NOT NULL DEFAULT current_timestamp(),
        `sms_provider_updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
        PRIMARY KEY (`sms_provider_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `sms_messages` (
        `sms_message_id` int(11) NOT NULL AUTO_INCREMENT,
        `sms_message_direction` varchar(3) NOT NULL,
        `sms_message_status` varchar(20) NOT NULL,
        `sms_message_number` varchar(20) NOT NULL,
        `sms_message_our_number` varchar(20) NOT NULL DEFAULT '',
        `sms_message_body` text DEFAULT NULL,
        `sms_message_media` text DEFAULT NULL,
        `sms_message_code` varchar(20) DEFAULT NULL,
        `sms_message_purpose` varchar(30) NOT NULL DEFAULT '',
        `sms_message_attempts` tinyint(4) NOT NULL DEFAULT 0,
        `sms_message_error` varchar(500) DEFAULT NULL,
        `sms_message_provider_ref` varchar(100) DEFAULT NULL,
        `sms_message_contact_id` int(11) NOT NULL DEFAULT 0,
        `sms_message_client_id` int(11) NOT NULL DEFAULT 0,
        `sms_message_ticket_id` int(11) NOT NULL DEFAULT 0,
        `sms_message_ticket_reply_id` int(11) NOT NULL DEFAULT 0,
        `sms_message_user_id` int(11) NOT NULL DEFAULT 0,
        `sms_message_created_at` datetime NOT NULL DEFAULT current_timestamp(),
        `sms_message_attempted_at` datetime DEFAULT NULL,
        `sms_message_sent_at` datetime DEFAULT NULL,
        `sms_message_handled_at` datetime DEFAULT NULL,
        `sms_message_handled_by` int(11) NOT NULL DEFAULT 0,
        PRIMARY KEY (`sms_message_id`),
        KEY `sms_message_status` (`sms_message_status`,`sms_message_id`),
        KEY `sms_message_number` (`sms_message_number`,`sms_message_created_at`),
        KEY `sms_message_ticket_id` (`sms_message_ticket_id`),
        KEY `sms_message_created_at` (`sms_message_created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `sms_numbers` (
        `sms_number_phone` varchar(20) NOT NULL,
        `sms_number_label` varchar(100) DEFAULT NULL,
        `sms_number_opted_out_at` datetime DEFAULT NULL,
        `sms_number_blocked_at` datetime DEFAULT NULL,
        `sms_number_blocked_by` int(11) NOT NULL DEFAULT 0,
        `sms_number_updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
        PRIMARY KEY (`sms_number_phone`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    // Settings > Tickets > Text to ticket - after the email-to-ticket switches, in the order the page shows them
    $itflow_sms_settings = [
        'config_ticket_sms_parse' => "tinyint(1) NOT NULL DEFAULT 1 AFTER `config_ticket_email_parse_unknown_senders`",
        'config_ticket_sms_unknown_senders' => "tinyint(1) NOT NULL DEFAULT 0 AFTER `config_ticket_sms_parse`",
        'config_ticket_sms_acknowledge' => "tinyint(1) NOT NULL DEFAULT 1 AFTER `config_ticket_sms_unknown_senders`",
    ];
    foreach ($itflow_sms_settings as $itflow_sms_column => $itflow_sms_definition) {
        $itflow_column_exists = mysqli_query($mysqli, "SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'settings' AND COLUMN_NAME = '$itflow_sms_column' LIMIT 1");
        if (!$itflow_column_exists || mysqli_num_rows($itflow_column_exists) === 0) {
            mysqli_query($mysqli, "ALTER TABLE `settings` ADD `$itflow_sms_column` $itflow_sms_definition");
        }
    }

    unset($itflow_sms_settings, $itflow_sms_column, $itflow_sms_definition, $itflow_column_exists);
