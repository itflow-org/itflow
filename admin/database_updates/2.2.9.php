<?php

/*
 * ITFlow - Database update to version 2.2.9 (from 2.2.8)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    mysqli_query($mysqli, "ALTER TABLE `products` ADD `product_type` ENUM('service', 'product') NOT NULL DEFAULT 'service' AFTER `product_name`");
    mysqli_query($mysqli, "ALTER TABLE `products` ADD `product_code` VARCHAR(200) DEFAULT NULL AFTER `product_description`");
    mysqli_query($mysqli, "ALTER TABLE `products` ADD `product_location` VARCHAR(250) DEFAULT NULL AFTER `product_code`");

    mysqli_query($mysqli, "CREATE TABLE `product_stock` (
        `stock_id` INT(11) NOT NULL AUTO_INCREMENT,
        `stock_qty` INT(11) NOT NULL,
        `stock_note` TEXT DEFAULT NULL,
        `stock_created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP(),
        `stock_expense_id` INT(11) DEFAULT NULL,
        `stock_item_id` INT(11) DEFAULT NULL,
        `stock_product_id` INT(11) NOT NULL,
        PRIMARY KEY (`stock_id`)
    )");
