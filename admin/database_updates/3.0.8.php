<?php

/*
 * ITFlow - Database update to version 3.0.8 (from 3.0.7)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    // A bank or card statement reconciled against an account
    // (functions/reconcile.php): the statement's date and ending balance, the
    // balance it started from, and who finished it. One still in progress has
    // no finished_at; the lines ticked on it point at it either way.
    mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `account_reconciliations` (
        `reconciliation_id` int(11) NOT NULL AUTO_INCREMENT,
        `reconciliation_account_id` int(11) NOT NULL,
        `reconciliation_statement_date` date NOT NULL,
        `reconciliation_starting_balance` decimal(15,2) NOT NULL DEFAULT 0.00,
        `reconciliation_ending_balance` decimal(15,2) NOT NULL DEFAULT 0.00,
        `reconciliation_notes` varchar(250) DEFAULT NULL,
        `reconciliation_created_by` int(11) NOT NULL DEFAULT 0,
        `reconciliation_created_at` datetime NOT NULL DEFAULT current_timestamp(),
        `reconciliation_finished_by` int(11) NOT NULL DEFAULT 0,
        `reconciliation_finished_at` datetime DEFAULT NULL,
        PRIMARY KEY (`reconciliation_id`),
        KEY `reconciliation_account_id` (`reconciliation_account_id`,`reconciliation_statement_date`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    // The statement each payment, income and expense cleared on
    mysqli_query($mysqli, "ALTER TABLE `payments`
        ADD COLUMN `payment_reconciliation_id` int(11) DEFAULT NULL AFTER `payment_import_key`,
        ADD KEY `payment_reconciliation_id` (`payment_reconciliation_id`)");
    mysqli_query($mysqli, "ALTER TABLE `revenues`
        ADD COLUMN `revenue_reconciliation_id` int(11) DEFAULT NULL AFTER `revenue_import_key`,
        ADD KEY `revenue_reconciliation_id` (`revenue_reconciliation_id`)");
    mysqli_query($mysqli, "ALTER TABLE `expenses`
        ADD COLUMN `expense_reconciliation_id` int(11) DEFAULT NULL AFTER `expense_reimbursement_id`,
        ADD KEY `expense_reconciliation_id` (`expense_reconciliation_id`)");
