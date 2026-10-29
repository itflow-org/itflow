<?php

/*
 * ITFlow - Database update to version 3.3.9 (from 3.3.8)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    $itflow_column = function ($table, $column) use ($mysqli) {
        $result = mysqli_query($mysqli, "SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$table' AND COLUMN_NAME = '$column' LIMIT 1");
        return $result && mysqli_num_rows($result) > 0;
    };

    // Whether new tickets are triaged by AI as they come in - 0 not, 1 suggested on the ticket, 2 filled in. Off until it is turned on
    if (!$itflow_column('settings', 'config_ticket_ai_triage')) {
        mysqli_query($mysqli, "ALTER TABLE `settings` ADD `config_ticket_ai_triage` tinyint(1) NOT NULL DEFAULT 0 AFTER `config_ticket_new_ticket_notification_email`");
    }

    // What AI triage suggested for a ticket - its category, priority and a line on what the client needs - and what became of it
    mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `ai_ticket_triage` (
        `ai_triage_ticket_id` int(11) NOT NULL,
        `ai_triage_category_id` int(11) NOT NULL DEFAULT 0,
        `ai_triage_priority` varchar(20) DEFAULT NULL,
        `ai_triage_summary` varchar(300) DEFAULT NULL,
        `ai_triage_reason` varchar(200) DEFAULT NULL,
        `ai_triage_state` varchar(20) NOT NULL DEFAULT 'suggested',
        `ai_triage_by` int(11) NOT NULL DEFAULT 0,
        `ai_triage_created_at` datetime NOT NULL DEFAULT current_timestamp(),
        `ai_triage_updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
        PRIMARY KEY (`ai_triage_ticket_id`),
        KEY `ai_triage_state` (`ai_triage_state`),
        CONSTRAINT `ai_ticket_triage_ticket` FOREIGN KEY (`ai_triage_ticket_id`) REFERENCES `tickets` (`ticket_id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    unset($itflow_column);
