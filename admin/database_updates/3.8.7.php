<?php

/*
 * ITFlow - Database update to version 3.8.7 (from 3.8.6)
 * Included by admin/database_updates.php - do not access directly
 *
 * Quote line pricing (functions/quote_pricing.php) - the same columns, with the
 * same meanings, as MountaineerIT's build, so their quotes carry straight over:
 *   quote_items.item_cost - what the line costs you, each; NULL is not entered.
 *   quote_items.item_price_method - how its price came from the cost: markup,
 *     margin or price (set by hand); NULL with no cost.
 *   quote_items.item_price_percent - the markup or margin %; NULL for a set price.
 *   settings.config_quote_price_method - what a new line is priced by (markup or margin).
 *   settings.config_quote_price_percent - its % to start with.
 *   settings.config_quote_price_presets - the % buttons offered, comma separated.
 *
 * Re-runnable: each column is added only where it isn't there yet.
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    $itflow_column = function ($table, $column) use ($mysqli) {
        $result = mysqli_query($mysqli, "SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$table' AND COLUMN_NAME = '$column' LIMIT 1");
        return $result && mysqli_num_rows($result) > 0;
    };

    if (!$itflow_column('quote_items', 'item_cost')) {
        mysqli_query($mysqli, "ALTER TABLE `quote_items` ADD `item_cost` decimal(15,2) DEFAULT NULL AFTER `item_price`");
    }
    if (!$itflow_column('quote_items', 'item_price_method')) {
        mysqli_query($mysqli, "ALTER TABLE `quote_items` ADD `item_price_method` varchar(10) DEFAULT NULL AFTER `item_cost`");
    }
    if (!$itflow_column('quote_items', 'item_price_percent')) {
        mysqli_query($mysqli, "ALTER TABLE `quote_items` ADD `item_price_percent` decimal(7,2) DEFAULT NULL AFTER `item_price_method`");
    }

    if (!$itflow_column('settings', 'config_quote_price_method')) {
        mysqli_query($mysqli, "ALTER TABLE `settings` ADD `config_quote_price_method` varchar(10) NOT NULL DEFAULT 'markup' AFTER `config_quote_notification_email`");
    }
    if (!$itflow_column('settings', 'config_quote_price_percent')) {
        mysqli_query($mysqli, "ALTER TABLE `settings` ADD `config_quote_price_percent` decimal(7,2) NOT NULL DEFAULT 20.00 AFTER `config_quote_price_method`");
    }
    if (!$itflow_column('settings', 'config_quote_price_presets')) {
        mysqli_query($mysqli, "ALTER TABLE `settings` ADD `config_quote_price_presets` varchar(100) NOT NULL DEFAULT '15,20,25,35' AFTER `config_quote_price_percent`");
    }
