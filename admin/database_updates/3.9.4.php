<?php

/*
 * ITFlow - Database update to version 3.9.4 (from 3.9.3)
 * Included by admin/database_updates.php - do not access directly
 *
 * Five more parts of ITFlow an admin can switch off in Admin > Settings >
 * Modules (moduleOn() in functions/settings.php) - nothing in one is deleted:
 *   config_module_enable_purchasing - To Order, purchase orders and
 *     receiving. Off to start with - on where purchasing is already in use.
 *   config_module_enable_bookkeeping - accounts, transactions and transfers,
 *     reconciling and statement imports. With it off, money goes into and out
 *     of the default accounts without asking.
 *   config_module_enable_dispatch - the Dispatch board.
 *   config_module_enable_trips - trips and mileage.
 *   config_module_enable_calendar - the calendar, its events and feeds.
 * Purchasing, Bookkeeping and Trips come with invoicing, Dispatch with
 * ticketing. All but Purchasing are on, as they were.
 *
 * Re-runnable: each column is added only where it isn't there yet.
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    $itflow_column = function ($table, $column) use ($mysqli) {
        $result = mysqli_query($mysqli, "SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$table' AND COLUMN_NAME = '$column' LIMIT 1");
        return $result && mysqli_num_rows($result) > 0;
    };

    $after = 'config_module_enable_ticketing';
    foreach (['purchasing' => 0, 'bookkeeping' => 1, 'dispatch' => 1, 'trips' => 1, 'calendar' => 1] as $module => $default) {
        $column = "config_module_enable_$module";
        if (!$itflow_column('settings', $column)) {
            mysqli_query($mysqli, "ALTER TABLE `settings` ADD `$column` tinyint(1) NOT NULL DEFAULT $default AFTER `$after`");
            if ($module === 'purchasing') {
                // Purchasing already in use stays on
                mysqli_query($mysqli, "UPDATE settings SET config_module_enable_purchasing = 1
                    WHERE EXISTS (SELECT 1 FROM order_items) OR EXISTS (SELECT 1 FROM purchase_orders)");
            }
        }
        $after = $column;
    }
