<?php

/*
 * ITFlow - Database update to version 3.5.4 (from 3.5.3)
 * Included by admin/database_updates.php - do not access directly
 *
 * Index transfers.transfer_expense_id - the out side of a transfer. The
 * account ledger (accountLedgerSql(), behind Transactions, its Transfers
 * pill and export, and Reconcile) joins every expense to the transfer it
 * may belong to. With nothing to look it up by, each expense was checked
 * against every transfer - expenses times transfers - which is what made
 * those pages crawl as the books grew. transfer_revenue_id, the in side,
 * has had its index since 2.7.5.
 *
 * Re-runnable: skipped where the index is already there.
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    $itflow_index_exists = mysqli_query($mysqli, "SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'transfers' AND INDEX_NAME = 'transfer_expense_id' LIMIT 1");

    if (!$itflow_index_exists || mysqli_num_rows($itflow_index_exists) === 0) {
        mysqli_query($mysqli, "ALTER TABLE `transfers` ADD KEY `transfer_expense_id` (`transfer_expense_id`)");
    }

    unset($itflow_index_exists);
