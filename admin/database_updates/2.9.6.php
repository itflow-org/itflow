<?php

/*
 * ITFlow - Database update to version 2.9.6 (from 2.9.5)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    // Hardware that becomes a client asset when it is sold - the asset type it
    // becomes, its make and model, and how long its warranty runs from the
    // invoice date (functions/products.php, productSave())
    mysqli_query($mysqli, "ALTER TABLE `products`
        ADD COLUMN `product_asset_type` varchar(200) DEFAULT NULL AFTER `product_reorder_qty`,
        ADD COLUMN `product_make` varchar(200) DEFAULT NULL AFTER `product_asset_type`,
        ADD COLUMN `product_model` varchar(200) DEFAULT NULL AFTER `product_make`,
        ADD COLUMN `product_warranty_months` int(11) NOT NULL DEFAULT 0 AFTER `product_model`");

    // An asset made from a sale - the product, the invoice and the line it was
    // sold on, so each line knows how many of its units are on the client's
    // assets (productInvoiceHardware())
    mysqli_query($mysqli, "ALTER TABLE `assets`
        ADD COLUMN `asset_product_id` int(11) NOT NULL DEFAULT 0 AFTER `asset_client_id`,
        ADD COLUMN `asset_invoice_id` int(11) NOT NULL DEFAULT 0 AFTER `asset_product_id`,
        ADD COLUMN `asset_invoice_item_id` int(11) NOT NULL DEFAULT 0 AFTER `asset_invoice_id`,
        ADD KEY `asset_product_id` (`asset_product_id`),
        ADD KEY `asset_invoice_id` (`asset_invoice_id`)");
