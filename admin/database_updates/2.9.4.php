<?php

/*
 * ITFlow - Database update to version 2.9.4 (from 2.9.3)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    // Products (functions/products.php): what one costs us and who we buy it
    // from, internal notes, how it is billed by default - once, or each month,
    // quarter or year, counted per user or per device - and whether its stock
    // is tracked. Only tracked products move stock when invoiced.
    mysqli_query($mysqli, "ALTER TABLE `products`
        ADD COLUMN `product_notes` text DEFAULT NULL AFTER `product_description`,
        ADD COLUMN `product_vendor_part` varchar(200) DEFAULT NULL AFTER `product_code`,
        ADD COLUMN `product_cost` decimal(15,2) NOT NULL DEFAULT 0.00 AFTER `product_price`,
        ADD COLUMN `product_frequency` varchar(10) DEFAULT NULL AFTER `product_currency_code`,
        ADD COLUMN `product_seat_source` varchar(20) DEFAULT NULL AFTER `product_frequency`,
        ADD COLUMN `product_track_stock` tinyint(1) NOT NULL DEFAULT 0 AFTER `product_seat_source`,
        ADD COLUMN `product_vendor_id` int(11) NOT NULL DEFAULT 0 AFTER `product_category_id`");

    // Stock was tracked for every product. Keep it for those that ever had stock
    // added; one that never did could not be invoiced at all, so it becomes
    // ordered as needed.
    mysqli_query($mysqli, "UPDATE `products` SET `product_track_stock` = 1
        WHERE `product_type` = 'product' AND EXISTS (SELECT 1 FROM `product_stock` WHERE `stock_product_id` = `product_id`)");

    // A product's page lists the lines it is on and its stock
    mysqli_query($mysqli, "ALTER TABLE `invoice_items` ADD KEY `item_product_id` (`item_product_id`)");
    mysqli_query($mysqli, "ALTER TABLE `quote_items` ADD KEY `item_product_id` (`item_product_id`)");
    mysqli_query($mysqli, "ALTER TABLE `recurring_invoice_items` ADD KEY `item_product_id` (`item_product_id`)");
    mysqli_query($mysqli, "ALTER TABLE `product_stock` ADD KEY `stock_product_id` (`stock_product_id`)");
