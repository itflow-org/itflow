<?php

/*
 * ITFlow - Database update to version 2.4.1 (from 2.4.0)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    mysqli_query($mysqli, "
        CREATE TABLE `quote_items` (
          `item_id` int(11) NOT NULL AUTO_INCREMENT,
          `item_name` varchar(200) NOT NULL,
          `item_description` text DEFAULT NULL,
          `item_quantity` decimal(15,2) NOT NULL DEFAULT 0.00,
          `item_price` decimal(15,2) NOT NULL DEFAULT 0.00,
          `item_subtotal` decimal(15,2) NOT NULL DEFAULT 0.00,
          `item_tax` decimal(15,2) NOT NULL DEFAULT 0.00,
          `item_total` decimal(15,2) NOT NULL DEFAULT 0.00,
          `item_order` int(11) NOT NULL DEFAULT 0,
          `item_created_at` datetime NOT NULL DEFAULT current_timestamp(),
          `item_updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
          `item_archived_at` datetime DEFAULT NULL,
          `item_tax_id` int(11) NOT NULL DEFAULT 0,
          `item_product_id` int(11) NOT NULL DEFAULT 0,
          `item_quote_id` int(11) NOT NULL,
          PRIMARY KEY (`item_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    mysqli_query($mysqli, "
        CREATE TABLE `recurring_invoice_items` (
          `item_id` int(11) NOT NULL AUTO_INCREMENT,
          `item_name` varchar(200) NOT NULL,
          `item_description` text DEFAULT NULL,
          `item_quantity` decimal(15,2) NOT NULL DEFAULT 0.00,
          `item_price` decimal(15,2) NOT NULL DEFAULT 0.00,
          `item_subtotal` decimal(15,2) NOT NULL DEFAULT 0.00,
          `item_tax` decimal(15,2) NOT NULL DEFAULT 0.00,
          `item_total` decimal(15,2) NOT NULL DEFAULT 0.00,
          `item_order` int(11) NOT NULL DEFAULT 0,
          `item_created_at` datetime NOT NULL DEFAULT current_timestamp(),
          `item_updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
          `item_archived_at` datetime DEFAULT NULL,
          `item_tax_id` int(11) NOT NULL DEFAULT 0,
          `item_product_id` int(11) NOT NULL DEFAULT 0,
          `item_recurring_invoice_id` int(11) NOT NULL,
          PRIMARY KEY (`item_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
