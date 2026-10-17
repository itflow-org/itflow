<?php

/*
 * ITFlow - Database update to version 3.1.2 (from 3.1.1)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    // The tax included in what an expense cost - its rate (0: none, or a tax
    // with no rate of ours) and the amount, part of expense_amount, for the
    // tax paid on purchases in the Tax Collected report
    mysqli_query($mysqli, "ALTER TABLE `expenses`
        ADD COLUMN `expense_tax_id` int(11) NOT NULL DEFAULT 0 AFTER `expense_amount`,
        ADD COLUMN `expense_tax_amount` decimal(15,2) NOT NULL DEFAULT 0.00 AFTER `expense_tax_id`");

    // The same on a recurring expense, copied to each expense it records
    mysqli_query($mysqli, "ALTER TABLE `recurring_expenses`
        ADD COLUMN `recurring_expense_tax_id` int(11) NOT NULL DEFAULT 0 AFTER `recurring_expense_amount`,
        ADD COLUMN `recurring_expense_tax_amount` decimal(15,2) NOT NULL DEFAULT 0.00 AFTER `recurring_expense_tax_id`");
