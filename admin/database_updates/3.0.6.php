<?php

/*
 * ITFlow - Database update to version 3.0.6 (from 3.0.5)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    // Deposits brought in from a bank statement (functions/income_import.php)
    // keep the key of the line they came from, so the same statement can't
    // bring them in twice - as payments on invoices, or as other income and
    // the money coming in from a transfer
    mysqli_query($mysqli, "ALTER TABLE `payments`
        ADD COLUMN `payment_import_key` varchar(64) DEFAULT NULL AFTER `payment_invoice_id`,
        ADD KEY `payment_import_key` (`payment_import_key`)");
    mysqli_query($mysqli, "ALTER TABLE `revenues`
        ADD COLUMN `revenue_import_key` varchar(64) DEFAULT NULL AFTER `revenue_created_by`,
        ADD KEY `revenue_import_key` (`revenue_import_key`)");

    // Who a deposit is from - what was picked for a payer last time (a
    // payment from a client, other income in a category, or a transfer from
    // one of our accounts), matched on the start of its cleaned-up text
    mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `income_import_rules` (
        `import_rule_id` int(11) NOT NULL AUTO_INCREMENT,
        `import_rule_match` varchar(200) NOT NULL,
        `import_rule_action` varchar(10) NOT NULL DEFAULT 'income',
        `import_rule_client_id` int(11) NOT NULL DEFAULT 0,
        `import_rule_category_id` int(11) NOT NULL DEFAULT 0,
        `import_rule_account_id` int(11) NOT NULL DEFAULT 0,
        `import_rule_used_count` int(11) NOT NULL DEFAULT 0,
        `import_rule_created_at` datetime NOT NULL DEFAULT current_timestamp(),
        `import_rule_updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
        PRIMARY KEY (`import_rule_id`),
        UNIQUE KEY `import_rule_match` (`import_rule_match`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
