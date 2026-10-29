<?php

/*
 * ITFlow - Database update to version 3.0.1 (from 3.0.0)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    // Expenses billed on to the client (functions/expenses.php): billable with
    // a markup, and the invoice and line they went on once billed
    mysqli_query($mysqli, "ALTER TABLE `expenses`
        ADD COLUMN `expense_billable` tinyint(1) NOT NULL DEFAULT 0 AFTER `expense_recurring_expense_id`,
        ADD COLUMN `expense_markup` decimal(7,2) NOT NULL DEFAULT 0.00 AFTER `expense_billable`,
        ADD COLUMN `expense_invoice_id` int(11) DEFAULT NULL AFTER `expense_markup`,
        ADD COLUMN `expense_invoice_item_id` int(11) DEFAULT NULL AFTER `expense_invoice_id`,
        ADD KEY `expense_invoice_id` (`expense_invoice_id`)");

    // A client's recurring expense is either billed on as each one is
    // recorded, or covered by one of their recurring invoices - its cost
    // shown against what that bills
    mysqli_query($mysqli, "ALTER TABLE `recurring_expenses`
        ADD COLUMN `recurring_expense_billable` tinyint(1) NOT NULL DEFAULT 0 AFTER `recurring_expense_account_id`,
        ADD COLUMN `recurring_expense_markup` decimal(7,2) NOT NULL DEFAULT 0.00 AFTER `recurring_expense_billable`,
        ADD COLUMN `recurring_expense_recurring_invoice_id` int(11) DEFAULT NULL AFTER `recurring_expense_markup`");

    // The markup a billable expense starts with
    mysqli_query($mysqli, "ALTER TABLE `settings` ADD COLUMN `config_expense_markup` decimal(7,2) NOT NULL DEFAULT 0.00 AFTER `config_time_item_name`");
