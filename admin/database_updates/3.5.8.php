<?php

/*
 * ITFlow - Database update to version 3.5.8 (from 3.5.7)
 * Included by admin/database_updates.php - do not access directly
 *
 * Managed mode - one ITFlow managing others (functions/managed.php).
 *
 * As a manager: managed_instances, the instances it manages and what it sets for each;
 * managed_instance_commands, what it has asked of them; settings.config_manager_keys, the
 * key pair it signs support sign-ins with.
 *
 * As a managed instance: settings.config_managed, its manager and what the manager set.
 *
 * Re-runnable: each table and column is made only where it isn't there yet.
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `managed_instances` (
        `managed_instance_id` int(11) NOT NULL AUTO_INCREMENT,
        `managed_instance_uuid` varchar(36) NOT NULL,
        `managed_instance_name` varchar(200) NOT NULL,
        `managed_instance_client_id` int(11) NOT NULL DEFAULT 0,
        `managed_instance_notes` text DEFAULT NULL,
        `managed_instance_enroll_hash` varchar(64) DEFAULT NULL,
        `managed_instance_enroll_expires_at` datetime DEFAULT NULL,
        `managed_instance_secret_hash` varchar(64) DEFAULT NULL,
        `managed_instance_enrolled_at` datetime DEFAULT NULL,
        `managed_instance_url` varchar(255) DEFAULT NULL,
        `managed_instance_mode` varchar(20) NOT NULL DEFAULT 'active',
        `managed_instance_mode_message` varchar(500) DEFAULT NULL,
        `managed_instance_seat_limit` int(11) NOT NULL DEFAULT 0,
        `managed_instance_white_label_key` text DEFAULT NULL,
        `managed_instance_update_policy` varchar(20) NOT NULL DEFAULT 'manual',
        `managed_instance_update_delay_days` int(11) NOT NULL DEFAULT 7,
        `managed_instance_version` varchar(20) DEFAULT NULL,
        `managed_instance_db_version` varchar(20) DEFAULT NULL,
        `managed_instance_update_version` varchar(40) DEFAULT NULL,
        `managed_instance_update_seen_at` datetime DEFAULT NULL,
        `managed_instance_report` mediumtext DEFAULT NULL,
        `managed_instance_last_checkin_at` datetime DEFAULT NULL,
        `managed_instance_last_ip` varchar(45) DEFAULT NULL,
        `managed_instance_offline_notified_at` datetime DEFAULT NULL,
        `managed_instance_created_at` datetime NOT NULL DEFAULT current_timestamp(),
        `managed_instance_updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
        `managed_instance_archived_at` datetime DEFAULT NULL,
        PRIMARY KEY (`managed_instance_id`),
        UNIQUE KEY `managed_instance_uuid` (`managed_instance_uuid`),
        KEY `managed_instance_client_id` (`managed_instance_client_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `managed_instance_commands` (
        `managed_command_id` int(11) NOT NULL AUTO_INCREMENT,
        `managed_command_instance_id` int(11) NOT NULL,
        `managed_command` varchar(40) NOT NULL,
        `managed_command_status` varchar(20) NOT NULL DEFAULT 'queued',
        `managed_command_source` varchar(20) NOT NULL DEFAULT 'manual',
        `managed_command_requested_by` varchar(200) DEFAULT NULL,
        `managed_command_result` varchar(1000) DEFAULT NULL,
        `managed_command_created_at` datetime NOT NULL DEFAULT current_timestamp(),
        `managed_command_sent_at` datetime DEFAULT NULL,
        `managed_command_done_at` datetime DEFAULT NULL,
        PRIMARY KEY (`managed_command_id`),
        KEY `managed_command_instance_id` (`managed_command_instance_id`, `managed_command_status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    // In db.sql's order: config_update_details, config_manager_keys, config_managed
    foreach ([
        'config_manager_keys' => "TEXT DEFAULT NULL AFTER `config_update_details`",
        'config_managed' => "MEDIUMTEXT DEFAULT NULL AFTER `config_manager_keys`",
    ] as $itflow_column => $itflow_definition) {
        $itflow_column_exists = mysqli_query($mysqli, "SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'settings' AND COLUMN_NAME = '$itflow_column' LIMIT 1");
        if (!$itflow_column_exists || mysqli_num_rows($itflow_column_exists) === 0) {
            mysqli_query($mysqli, "ALTER TABLE `settings` ADD `$itflow_column` $itflow_definition");
        }
    }

    unset($itflow_column, $itflow_definition, $itflow_column_exists);
