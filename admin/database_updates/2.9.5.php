<?php

/*
 * ITFlow - Database update to version 2.9.5 (from 2.9.4)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    // The stock ledger (functions/products.php, productStockMove()). Every move
    // says what it was - received, sold, returned, adjusted (a count) or
    // written off - with its unit cost, the invoice and line it went out on,
    // and who made it. Quantities follow invoice lines, which can be decimal.
    mysqli_query($mysqli, "ALTER TABLE `product_stock`
        MODIFY `stock_qty` decimal(15,2) NOT NULL,
        ADD COLUMN `stock_type` varchar(20) NOT NULL DEFAULT 'adjusted' AFTER `stock_qty`,
        ADD COLUMN `stock_unit_cost` decimal(15,2) DEFAULT NULL AFTER `stock_type`,
        ADD COLUMN `stock_invoice_id` int(11) NOT NULL DEFAULT 0 AFTER `stock_expense_id`,
        ADD COLUMN `stock_user_id` int(11) NOT NULL DEFAULT 0 AFTER `stock_item_id`,
        ADD KEY `stock_invoice_id` (`stock_invoice_id`)");

    // What the moves on file were, from the notes ITFlow wrote with them:
    // "QTY 2 - Invoice 12", "QTY 2 to Invoice 12", "Returned QTY 2 ... Invoice 12"
    mysqli_query($mysqli, "UPDATE `product_stock` SET `stock_invoice_id` = CAST(SUBSTRING_INDEX(`stock_note`, 'Invoice ', -1) AS UNSIGNED)
        WHERE `stock_note` REGEXP 'Invoice [0-9]+$'");
    mysqli_query($mysqli, "UPDATE `product_stock` SET `stock_type` = CASE
            WHEN `stock_qty` < 0 AND `stock_invoice_id` > 0 THEN 'sold'
            WHEN `stock_qty` > 0 AND `stock_invoice_id` > 0 AND `stock_note` LIKE 'Returned%' THEN 'returned'
            WHEN `stock_qty` > 0 THEN 'received'
            ELSE 'adjusted' END");

    // When to buy more, and how many - a product at or under its reorder level is
    // listed to reorder, and agents are told as it gets there
    mysqli_query($mysqli, "ALTER TABLE `products`
        ADD COLUMN `product_reorder_level` decimal(15,2) NOT NULL DEFAULT 0.00 AFTER `product_track_stock`,
        ADD COLUMN `product_reorder_qty` decimal(15,2) NOT NULL DEFAULT 0.00 AFTER `product_reorder_level`");
