<?php

/*
 * ITFlow - Database update to version 3.4.1 (from 3.4.0)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    $itflow_column = function ($table, $column) use ($mysqli) {
        $result = mysqli_query($mysqli, "SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$table' AND COLUMN_NAME = '$column' LIMIT 1");
        return $result && mysqli_num_rows($result) > 0;
    };

    // What a payment method is for - 0 money in and out (every method so far), 1 money in (payments and income), 2 money out (expenses)
    if (!$itflow_column('payment_methods', 'payment_method_use')) {
        mysqli_query($mysqli, "ALTER TABLE `payment_methods` ADD `payment_method_use` tinyint(1) NOT NULL DEFAULT 0 AFTER `payment_method_description`");
    }

    // An archived payment method leaves the pickers - the records that used it keep it
    if (!$itflow_column('payment_methods', 'payment_method_archived_at')) {
        mysqli_query($mysqli, "ALTER TABLE `payment_methods` ADD `payment_method_archived_at` datetime DEFAULT NULL AFTER `payment_method_updated_at`");
    }

    unset($itflow_column);
