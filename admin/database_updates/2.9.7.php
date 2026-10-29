<?php

/*
 * ITFlow - Database update to version 2.9.7 (from 2.9.6)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    // A product's price and cost over time - each change, where it came from
    // (edit, import, received stock) and who made it. Lines on recurring
    // invoices, drafts and quotes still at one of its old prices are listed
    // to update (functions/products.php, productRepriceLines())
    mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `product_prices` (
        `price_id` int(11) NOT NULL AUTO_INCREMENT,
        `price_old` decimal(15,2) NOT NULL,
        `price_new` decimal(15,2) NOT NULL,
        `price_cost_old` decimal(15,2) NOT NULL DEFAULT 0.00,
        `price_cost_new` decimal(15,2) NOT NULL DEFAULT 0.00,
        `price_source` varchar(20) NOT NULL DEFAULT 'edit',
        `price_created_at` datetime NOT NULL DEFAULT current_timestamp(),
        `price_user_id` int(11) NOT NULL DEFAULT 0,
        `price_product_id` int(11) NOT NULL,
        PRIMARY KEY (`price_id`),
        KEY `price_product_id` (`price_product_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
