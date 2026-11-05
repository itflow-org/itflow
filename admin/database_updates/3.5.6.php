<?php

/*
 * ITFlow - Database update to version 3.5.6 (from 3.5.5)
 * Included by admin/database_updates.php - do not access directly
 *
 * Clients can pay the card fee. On the Stripe payment provider:
 * payment_provider_card_fee turns it on, and payment_provider_card_fee_percent
 * and payment_provider_card_fee_fixed are what Stripe charges on a card
 * payment. On a client, client_card_fee_exempt lets them off it. All start
 * off, so nothing changes until it is turned on.
 *
 * Re-runnable: each column is added only where it isn't there yet.
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    $itflow_new_columns = [
        ['payment_providers', 'payment_provider_card_fee', "TINYINT(1) NOT NULL DEFAULT 0 AFTER `payment_provider_expense_category`"],
        ['payment_providers', 'payment_provider_card_fee_percent', "DECIMAL(5,2) NOT NULL DEFAULT 0.00 AFTER `payment_provider_card_fee`"],
        ['payment_providers', 'payment_provider_card_fee_fixed', "DECIMAL(15,2) NOT NULL DEFAULT 0.00 AFTER `payment_provider_card_fee_percent`"],
        ['clients', 'client_card_fee_exempt', "TINYINT(1) NOT NULL DEFAULT 0 AFTER `client_tax_exempt_expires`"],
    ];

    foreach ($itflow_new_columns as [$itflow_table, $itflow_column, $itflow_definition]) {
        $itflow_column_exists = mysqli_query($mysqli, "SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$itflow_table' AND COLUMN_NAME = '$itflow_column' LIMIT 1");
        if (!$itflow_column_exists || mysqli_num_rows($itflow_column_exists) === 0) {
            mysqli_query($mysqli, "ALTER TABLE `$itflow_table` ADD `$itflow_column` $itflow_definition");
        }
    }

    unset($itflow_new_columns, $itflow_table, $itflow_column, $itflow_definition, $itflow_column_exists);
