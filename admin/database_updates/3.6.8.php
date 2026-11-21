<?php

/*
 * ITFlow - Database update to version 3.6.8 (from 3.6.7)
 * Included by admin/database_updates.php - do not access directly
 *
 * RMM alerts open tickets (functions/rmm.php):
 *   rmm_providers.rmm_provider_alert_level - which alerts open tickets: '' none, error,
 *     warning (and errors) or info (every one); rmm_provider_alert_category_id - the ticket
 *     category they get; rmm_provider_alerts_since - when it started watching (NULL until the
 *     first look, which takes the alerts already open as known); rmm_provider_alerts_at and
 *     rmm_provider_alerts_note - when it last looked, and what went wrong if it didn't work
 *   rmm_alerts - one an alert ITFlow has seen: the device it is on, what it is about there (a
 *     check, a task, the device not checking in), its severity and message, when it was raised
 *     and cleared, and the ticket it is on - 0 when it was already open before ITFlow watched
 *
 * Re-runnable: the table and each column are made only where they aren't there yet.
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    $itflow_rmm_columns = [
        'rmm_provider_alert_level' => "varchar(20) NOT NULL DEFAULT '' AFTER `rmm_provider_sync_note`",
        'rmm_provider_alert_category_id' => "int(11) NOT NULL DEFAULT 0 AFTER `rmm_provider_alert_level`",
        'rmm_provider_alerts_since' => "datetime DEFAULT NULL AFTER `rmm_provider_alert_category_id`",
        'rmm_provider_alerts_at' => "datetime DEFAULT NULL AFTER `rmm_provider_alerts_since`",
        'rmm_provider_alerts_note' => "varchar(500) DEFAULT NULL AFTER `rmm_provider_alerts_at`",
    ];
    foreach ($itflow_rmm_columns as $itflow_rmm_column => $itflow_rmm_definition) {
        $itflow_column_exists = mysqli_query($mysqli, "SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rmm_providers' AND COLUMN_NAME = '$itflow_rmm_column' LIMIT 1");
        if (!$itflow_column_exists || mysqli_num_rows($itflow_column_exists) === 0) {
            mysqli_query($mysqli, "ALTER TABLE `rmm_providers` ADD `$itflow_rmm_column` $itflow_rmm_definition");
        }
    }

    mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `rmm_alerts` (
        `rmm_alert_id` int(11) NOT NULL AUTO_INCREMENT,
        `rmm_alert_provider_id` int(11) NOT NULL,
        `rmm_alert_remote_id` varchar(100) NOT NULL,
        `rmm_alert_device_id` int(11) NOT NULL DEFAULT 0,
        `rmm_alert_asset_id` int(11) NOT NULL DEFAULT 0,
        `rmm_alert_client_id` int(11) NOT NULL DEFAULT 0,
        `rmm_alert_about` varchar(150) NOT NULL DEFAULT '',
        `rmm_alert_type` varchar(20) NOT NULL DEFAULT '',
        `rmm_alert_severity` varchar(20) NOT NULL DEFAULT '',
        `rmm_alert_message` text DEFAULT NULL,
        `rmm_alert_raised_at` datetime DEFAULT NULL,
        `rmm_alert_cleared_at` datetime DEFAULT NULL,
        `rmm_alert_ticket_id` int(11) NOT NULL DEFAULT 0,
        `rmm_alert_created_at` datetime NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`rmm_alert_id`),
        UNIQUE KEY `rmm_alert_remote` (`rmm_alert_provider_id`,`rmm_alert_remote_id`),
        KEY `rmm_alert_device_id` (`rmm_alert_device_id`),
        KEY `rmm_alert_ticket_id` (`rmm_alert_ticket_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
