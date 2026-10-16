<?php

/*
 * ITFlow - Database update to version 3.0.0 (from 2.9.9)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    // Recurring expenses on the recurring invoice schedule (functions/expenses.php):
    // month, quarter or year, on the day of the month it started on, with an
    // optional end date for leases and financing. 1 was monthly, 2 yearly.
    mysqli_query($mysqli, "ALTER TABLE `recurring_expenses`
        MODIFY `recurring_expense_frequency` varchar(10) NOT NULL DEFAULT 'month',
        ADD COLUMN `recurring_expense_anchor_day` tinyint(2) NOT NULL DEFAULT 0 AFTER `recurring_expense_frequency`,
        ADD COLUMN `recurring_expense_end_date` date DEFAULT NULL AFTER `recurring_expense_next_date`");
    mysqli_query($mysqli, "UPDATE recurring_expenses SET recurring_expense_frequency = IF(recurring_expense_frequency = '2', 'year', 'month')");

    // Each keeps the day of the month its next date is on - the nightly run
    // moved it a month on from the day it ran, so that is the day it really
    // runs on. The old day and month fields go.
    mysqli_query($mysqli, "UPDATE recurring_expenses SET recurring_expense_anchor_day = DAY(recurring_expense_next_date)");
    mysqli_query($mysqli, "ALTER TABLE `recurring_expenses` DROP COLUMN `recurring_expense_day`, DROP COLUMN `recurring_expense_month`");

    // The expenses a recurring expense made link back to it
    mysqli_query($mysqli, "ALTER TABLE `expenses` ADD COLUMN `expense_recurring_expense_id` int(11) DEFAULT NULL AFTER `expense_refund_of_id`");

    // The ones the nightly run already made carried its vendor, category,
    // client and description - linked from the day the recurring expense was set up
    mysqli_query($mysqli, "UPDATE expenses
        JOIN recurring_expenses ON recurring_expense_vendor_id = expense_vendor_id AND recurring_expense_category_id = expense_category_id
            AND recurring_expense_client_id = expense_client_id AND recurring_expense_description = expense_description
        SET expense_recurring_expense_id = recurring_expense_id
        WHERE expense_recurring_expense_id IS NULL AND expense_vendor_id > 0 AND expense_amount > 0
            AND COALESCE(recurring_expense_description, '') <> '' AND expense_date >= DATE(recurring_expense_created_at)");
