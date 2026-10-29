<?php

/*
 * ITFlow - Database update to version 3.0.2 (from 3.0.1)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    // Expenses from a statement import keep the key of the line they came
    // from, so the same statement can't bring them in twice. One paid
    // personally by an agent (expense_paid_by) is on no account until it is
    // paid back, by a reimbursement
    mysqli_query($mysqli, "ALTER TABLE `expenses`
        ADD COLUMN `expense_import_key` varchar(64) DEFAULT NULL AFTER `expense_invoice_item_id`,
        ADD COLUMN `expense_paid_by` int(11) NOT NULL DEFAULT 0 AFTER `expense_import_key`,
        ADD COLUMN `expense_reimbursement_id` int(11) DEFAULT NULL AFTER `expense_paid_by`,
        ADD KEY `expense_import_key` (`expense_import_key`),
        ADD KEY `expense_reimbursement_id` (`expense_reimbursement_id`)");

    // What a statement line's merchant is - the vendor, category and client
    // picked for it last time, matched on the start of its cleaned-up text
    mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `expense_import_rules` (
        `import_rule_id` int(11) NOT NULL AUTO_INCREMENT,
        `import_rule_match` varchar(200) NOT NULL,
        `import_rule_vendor_id` int(11) NOT NULL,
        `import_rule_category_id` int(11) NOT NULL DEFAULT 0,
        `import_rule_client_id` int(11) NOT NULL DEFAULT 0,
        `import_rule_billable` tinyint(1) NOT NULL DEFAULT 0,
        `import_rule_used_count` int(11) NOT NULL DEFAULT 0,
        `import_rule_created_at` datetime NOT NULL DEFAULT current_timestamp(),
        `import_rule_updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
        PRIMARY KEY (`import_rule_id`),
        UNIQUE KEY `import_rule_match` (`import_rule_match`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    // Paying an agent back for what they paid personally - the money out of
    // the account is an expense with no vendor (reimbursement_expense_id), the
    // way a transfer is
    mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `reimbursements` (
        `reimbursement_id` int(11) NOT NULL AUTO_INCREMENT,
        `reimbursement_user_id` int(11) NOT NULL,
        `reimbursement_date` date NOT NULL,
        `reimbursement_amount` decimal(15,2) NOT NULL,
        `reimbursement_currency_code` varchar(200) NOT NULL,
        `reimbursement_account_id` int(11) NOT NULL,
        `reimbursement_payment_method` varchar(200) DEFAULT NULL,
        `reimbursement_reference` varchar(200) DEFAULT NULL,
        `reimbursement_expense_id` int(11) NOT NULL,
        `reimbursement_created_by` int(11) NOT NULL DEFAULT 0,
        `reimbursement_created_at` datetime NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`reimbursement_id`),
        KEY `reimbursement_user_id` (`reimbursement_user_id`),
        KEY `reimbursement_expense_id` (`reimbursement_expense_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    // The columns, date format and sign an account's statements were last
    // imported with, to start the next one from
    mysqli_query($mysqli, "ALTER TABLE `accounts` ADD COLUMN `account_import_map` text DEFAULT NULL AFTER `account_notes`");
