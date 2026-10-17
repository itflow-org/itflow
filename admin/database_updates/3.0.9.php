<?php

/*
 * ITFlow - Database update to version 3.0.9 (from 3.0.8)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    // An account's first reconciled statement can start from the bank's
    // balance rather than the opening balance: everything before it is
    // brought forward as reconciled, in a reconciliation of its own
    // (functions/reconcile.php). The adjustment is what its opening balance
    // was corrected by so ITFlow matched the bank there, so undoing it can
    // put it back.
    mysqli_query($mysqli, "ALTER TABLE `account_reconciliations`
        ADD COLUMN `reconciliation_opening` tinyint(1) NOT NULL DEFAULT 0 AFTER `reconciliation_notes`,
        ADD COLUMN `reconciliation_adjustment` decimal(15,2) NOT NULL DEFAULT 0.00 AFTER `reconciliation_opening`");

    // The deposit a payment or other income went to the bank in - the
    // transfer out of a cash account it was ticked on (functions/accounts.php)
    mysqli_query($mysqli, "ALTER TABLE `payments`
        ADD COLUMN `payment_deposit_transfer_id` int(11) DEFAULT NULL AFTER `payment_reconciliation_id`,
        ADD KEY `payment_deposit_transfer_id` (`payment_deposit_transfer_id`)");
    mysqli_query($mysqli, "ALTER TABLE `revenues`
        ADD COLUMN `revenue_deposit_transfer_id` int(11) DEFAULT NULL AFTER `revenue_reconciliation_id`,
        ADD KEY `revenue_deposit_transfer_id` (`revenue_deposit_transfer_id`)");
