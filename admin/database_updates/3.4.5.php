<?php

/*
 * ITFlow - Database update to version 3.4.5 (from 3.4.4)
 * Included by admin/database_updates.php - do not access directly
 *
 * Client contracts, stage 4: a contract can be linked to one of the client's
 * recurring invoices, which starts and stops with it
 * (functions/contract_billing.php). contract_recurring_stopped_at notes when
 * the contract stopped it, so it is stopped once - an agent who starts it
 * again by hand is left alone.
 *
 * A contract's rates now price the client's time, so a rate of 0.00 - what
 * the old form saved when left empty - becomes empty: the client's own.
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    $itflow_column = function ($column) use ($mysqli) {
        $result = mysqli_query($mysqli, "SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'contracts' AND COLUMN_NAME = '$column' LIMIT 1");
        return $result && mysqli_num_rows($result) > 0;
    };

    if (!$itflow_column('contract_recurring_invoice_id')) {
        mysqli_query($mysqli, "ALTER TABLE `contracts` ADD `contract_recurring_invoice_id` int(11) NOT NULL DEFAULT 0, ADD KEY `contract_recurring_invoice_id` (`contract_recurring_invoice_id`)");
    }
    if (!$itflow_column('contract_recurring_stopped_at')) {
        mysqli_query($mysqli, "ALTER TABLE `contracts` ADD `contract_recurring_stopped_at` datetime DEFAULT NULL");
    }

    foreach (['contract_templates' => 'contract_template', 'contracts' => 'contract'] as $itflow_table => $itflow_prefix) {
        mysqli_query($mysqli, "UPDATE `$itflow_table` SET `{$itflow_prefix}_rate_standard` = NULL WHERE `{$itflow_prefix}_rate_standard` = 0");
        mysqli_query($mysqli, "UPDATE `$itflow_table` SET `{$itflow_prefix}_rate_after_hours` = NULL WHERE `{$itflow_prefix}_rate_after_hours` = 0");
    }

    unset($itflow_column, $itflow_table, $itflow_prefix);
