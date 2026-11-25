<?php

/*
 * ITFlow - Database update to version 4.1.8 (from 4.1.7)
 * Included by admin/database_updates.php - do not access directly
 *
 * A managed instance's problems open tickets (functions/managed.php):
 *   settings.config_manager_ticket_level - which problems open tickets: '' none, danger (it has
 *     stopped checking in, cron, updates) or warning (backups and errors too);
 *     settings.config_manager_ticket_category_id - the ticket category they get
 *   managed_instance_issues - one problem an instance had: what it was (its key - offline, cron,
 *     backup...), how bad and what it said, when it was first seen and when it cleared, and the
 *     ticket it is on - 0 until it has lasted long enough for one
 *
 * Re-runnable: the table and each column are made only where they aren't there yet.
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    $itflow_manager_columns = [
        'config_manager_ticket_level' => "varchar(20) NOT NULL DEFAULT '' AFTER `config_managed`",
        'config_manager_ticket_category_id' => "int(11) NOT NULL DEFAULT 0 AFTER `config_manager_ticket_level`",
    ];
    foreach ($itflow_manager_columns as $itflow_manager_column => $itflow_manager_definition) {
        $itflow_column_exists = mysqli_query($mysqli, "SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'settings' AND COLUMN_NAME = '$itflow_manager_column' LIMIT 1");
        if (!$itflow_column_exists || mysqli_num_rows($itflow_column_exists) === 0) {
            mysqli_query($mysqli, "ALTER TABLE `settings` ADD `$itflow_manager_column` $itflow_manager_definition");
        }
    }

    mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `managed_instance_issues` (
        `managed_issue_id` int(11) NOT NULL AUTO_INCREMENT,
        `managed_issue_instance_id` int(11) NOT NULL,
        `managed_issue_key` varchar(30) NOT NULL,
        `managed_issue_tone` varchar(20) NOT NULL DEFAULT '',
        `managed_issue_text` varchar(255) NOT NULL DEFAULT '',
        `managed_issue_ticket_id` int(11) NOT NULL DEFAULT 0,
        `managed_issue_created_at` datetime NOT NULL DEFAULT current_timestamp(),
        `managed_issue_cleared_at` datetime DEFAULT NULL,
        PRIMARY KEY (`managed_issue_id`),
        KEY `managed_issue_instance` (`managed_issue_instance_id`, `managed_issue_cleared_at`),
        KEY `managed_issue_ticket_id` (`managed_issue_ticket_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
