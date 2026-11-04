<?php

/*
 * ITFlow - Database update to version 3.5.5 (from 3.5.4)
 * Included by admin/database_updates.php - do not access directly
 *
 * Index expenses.expense_refund_of_id - the expense a refund gives back.
 * What is left to bill of an expense is its amount less its refunds, and
 * each expense waiting to be billed looked its refunds up with nothing to
 * find them by, so every one was checked against every expense. The side
 * menu counts them on every agent page (expenseUnbilledCount()), and the
 * invoice page, Add Expenses and an expense's quick look do the same.
 *
 * Re-runnable: skipped where the index is already there.
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    $itflow_index_exists = mysqli_query($mysqli, "SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'expenses' AND INDEX_NAME = 'expense_refund_of_id' LIMIT 1");

    if (!$itflow_index_exists || mysqli_num_rows($itflow_index_exists) === 0) {
        mysqli_query($mysqli, "ALTER TABLE `expenses` ADD KEY `expense_refund_of_id` (`expense_refund_of_id`)");
    }

    unset($itflow_index_exists);
