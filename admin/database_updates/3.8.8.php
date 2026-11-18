<?php

/*
 * ITFlow - Database update to version 3.8.8 (from 3.8.7)
 * Included by admin/database_updates.php - do not access directly
 *
 * Pictures on quote lines and products (functions/quote_images.php) - the same
 * columns, with the same meanings, as MountaineerIT's build:
 *   quote_items.item_image - the line's picture, a path under uploads/; NULL for none.
 *   products.product_image - the product's, which a quote line added from it copies.
 *   settings.config_quote_show_images - quotes show their lines' pictures (1, the default) or not.
 *
 * Re-runnable: each column is added only where it isn't there yet.
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    $itflow_column = function ($table, $column) use ($mysqli) {
        $result = mysqli_query($mysqli, "SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$table' AND COLUMN_NAME = '$column' LIMIT 1");
        return $result && mysqli_num_rows($result) > 0;
    };

    if (!$itflow_column('quote_items', 'item_image')) {
        mysqli_query($mysqli, "ALTER TABLE `quote_items` ADD `item_image` varchar(255) DEFAULT NULL AFTER `item_price_percent`");
    }
    if (!$itflow_column('products', 'product_image')) {
        mysqli_query($mysqli, "ALTER TABLE `products` ADD `product_image` varchar(255) DEFAULT NULL AFTER `product_cost`");
    }
    if (!$itflow_column('settings', 'config_quote_show_images')) {
        mysqli_query($mysqli, "ALTER TABLE `settings` ADD `config_quote_show_images` tinyint(1) NOT NULL DEFAULT 1 AFTER `config_quote_price_presets`");
    }
