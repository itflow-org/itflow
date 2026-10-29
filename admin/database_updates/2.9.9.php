<?php

/*
 * ITFlow - Database update to version 2.9.9 (from 2.9.8)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    // Who entered an expense (0 for ones made by cron or a payment provider),
    // and the expense a refund gives back (functions/expenses.php)
    mysqli_query($mysqli, "ALTER TABLE `expenses`
        ADD COLUMN `expense_created_by` int(11) NOT NULL DEFAULT 0 AFTER `expense_archived_at`,
        ADD COLUMN `expense_refund_of_id` int(11) DEFAULT NULL AFTER `expense_account_id`");

    // Expenses already entered take their creator from the audit log, where
    // one was written
    mysqli_query($mysqli, "UPDATE expenses
        JOIN (SELECT log_entity_id, MIN(log_user_id) AS user_id FROM logs
            WHERE log_type = 'Expense' AND log_action = 'Create' AND log_entity_id > 0 AND log_user_id > 0
            GROUP BY log_entity_id) AS created ON created.log_entity_id = expense_id
        SET expense_created_by = created.user_id");
