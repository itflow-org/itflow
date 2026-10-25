<?php

/*
 * ITFlow - Database update to version 3.4.4 (from 3.4.3)
 * Included by admin/database_updates.php - do not access directly
 *
 * Client contracts, stage 3: prepaid hours. A contract's blocks of hours -
 * bought, or included each month - are contract_blocks; the time drawn from
 * them, ticket by ticket, is contract_block_uses (functions/contract_hours.php).
 * A contract (and a template) can include hours each month, and say how
 * few hours left is low enough to tell its owner.
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    $itflow_column = function ($table, $column) use ($mysqli) {
        $result = mysqli_query($mysqli, "SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$table' AND COLUMN_NAME = '$column' LIMIT 1");
        return $result && mysqli_num_rows($result) > 0;
    };

    foreach (['contract_templates' => 'contract_template', 'contracts' => 'contract'] as $itflow_table => $itflow_prefix) {
        if (!$itflow_column($itflow_table, "{$itflow_prefix}_monthly_hours")) {
            mysqli_query($mysqli, "ALTER TABLE `$itflow_table` ADD `{$itflow_prefix}_monthly_hours` decimal(8,2) NOT NULL DEFAULT 0.00");
        }
        if (!$itflow_column($itflow_table, "{$itflow_prefix}_hours_alert")) {
            mysqli_query($mysqli, "ALTER TABLE `$itflow_table` ADD `{$itflow_prefix}_hours_alert` decimal(8,2) NOT NULL DEFAULT 0.00");
        }
    }
    if (!$itflow_column('contracts', 'contract_hours_alerted_at')) {
        mysqli_query($mysqli, "ALTER TABLE `contracts` ADD `contract_hours_alerted_at` datetime DEFAULT NULL");
    }

    mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `contract_blocks` (
        `contract_block_id` int(11) NOT NULL AUTO_INCREMENT,
        `contract_block_contract_id` int(11) NOT NULL,
        `contract_block_hours` decimal(8,2) NOT NULL,
        `contract_block_price` decimal(15,2) DEFAULT NULL,
        `contract_block_bought` date NOT NULL,
        `contract_block_expires` date DEFAULT NULL,
        `contract_block_month` char(7) DEFAULT NULL,
        `contract_block_invoice_id` int(11) NOT NULL DEFAULT 0,
        `contract_block_note` varchar(255) DEFAULT NULL,
        `contract_block_created_by` int(11) NOT NULL DEFAULT 0,
        `contract_block_created_at` datetime NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`contract_block_id`),
        UNIQUE KEY `contract_block_month` (`contract_block_contract_id`, `contract_block_month`),
        CONSTRAINT `contract_blocks_ibfk_1` FOREIGN KEY (`contract_block_contract_id`) REFERENCES `contracts` (`contract_id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `contract_block_uses` (
        `contract_block_use_id` int(11) NOT NULL AUTO_INCREMENT,
        `contract_block_use_block_id` int(11) NOT NULL,
        `contract_block_use_ticket_id` int(11) NOT NULL,
        `contract_block_use_hours` decimal(8,2) NOT NULL,
        `contract_block_use_full` tinyint(1) NOT NULL DEFAULT 0,
        `contract_block_use_created_by` int(11) NOT NULL DEFAULT 0,
        `contract_block_use_created_at` datetime NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`contract_block_use_id`),
        KEY `contract_block_use_block_id` (`contract_block_use_block_id`),
        KEY `contract_block_use_ticket_id` (`contract_block_use_ticket_id`),
        CONSTRAINT `contract_block_uses_ibfk_1` FOREIGN KEY (`contract_block_use_block_id`) REFERENCES `contract_blocks` (`contract_block_id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    unset($itflow_column, $itflow_table, $itflow_prefix);
