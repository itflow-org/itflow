<?php

/*
 * ITFlow - Database update to version 3.9.3 (from 3.9.2)
 * Included by admin/database_updates.php - do not access directly
 *
 * Purchase orders (functions/purchase_orders.php):
 *   order_items - what is waiting to be ordered, added by hand or from a
 *     product that is low on stock: for a client or for our own stock, with
 *     who it comes from and what it costs. waiting, then ordered once it is on
 *     a purchase order, received once that line is all in.
 *   purchase_orders - a purchase order to a vendor: numbered like invoices
 *     (config_po_prefix / config_po_next_number), draft -> pending (waiting
 *     for approval) -> approved -> ordered -> partial -> received, or
 *     cancelled. Where it ships to (one of our offices, or an address typed
 *     in), shipping, the total, who approved and ordered it, and the expense
 *     its vendor bill was recorded as.
 *   po_items - its lines, each from an item on the list or added on the PO.
 *   po_receipts - what came in against a line: how many, the serials, and
 *     what it did - the assets it made for a client, the stock move it made.
 *   po_tracking - the tracking numbers the vendor sent.
 * Settings: config_po_prefix, config_po_next_number, config_po_approval (0 -
 * no approval) and config_po_approval_over (approval for a PO over this
 * amount - 0 for every one). A Procurement permission for Admin > Roles
 * (module_procurement): no role has it until it is given - admins always do.
 *
 * Re-runnable: each table, column and module is added only where it isn't there yet.
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    $itflow_column = function ($table, $column) use ($mysqli) {
        $result = mysqli_query($mysqli, "SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$table' AND COLUMN_NAME = '$column' LIMIT 1");
        return $result && mysqli_num_rows($result) > 0;
    };

    mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `order_items` (
        `order_item_id` int(11) NOT NULL AUTO_INCREMENT,
        `order_item_name` varchar(255) NOT NULL,
        `order_item_description` text DEFAULT NULL,
        `order_item_quantity` decimal(15,2) NOT NULL DEFAULT 1.00,
        `order_item_cost` decimal(15,2) DEFAULT NULL,
        `order_item_status` varchar(20) NOT NULL DEFAULT 'waiting',
        `order_item_source` varchar(20) NOT NULL DEFAULT 'manual',
        `order_item_note` varchar(255) DEFAULT NULL,
        `order_item_product_id` int(11) NOT NULL DEFAULT 0,
        `order_item_vendor_id` int(11) NOT NULL DEFAULT 0,
        `order_item_client_id` int(11) NOT NULL DEFAULT 0,
        `order_item_created_by` int(11) NOT NULL DEFAULT 0,
        `order_item_created_at` datetime NOT NULL DEFAULT current_timestamp(),
        `order_item_updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
        PRIMARY KEY (`order_item_id`),
        KEY `order_item_status` (`order_item_status`,`order_item_vendor_id`),
        KEY `order_item_product_id` (`order_item_product_id`),
        KEY `order_item_client_id` (`order_item_client_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `purchase_orders` (
        `po_id` int(11) NOT NULL AUTO_INCREMENT,
        `po_prefix` varchar(200) NOT NULL DEFAULT 'PO-',
        `po_number` int(11) NOT NULL,
        `po_status` varchar(20) NOT NULL DEFAULT 'draft',
        `po_vendor_id` int(11) NOT NULL DEFAULT 0,
        `po_currency_code` varchar(200) NOT NULL DEFAULT '',
        `po_ship_to_office_id` int(11) DEFAULT NULL,
        `po_ship_to` text DEFAULT NULL,
        `po_note` text DEFAULT NULL,
        `po_shipping` decimal(15,2) NOT NULL DEFAULT 0.00,
        `po_total` decimal(15,2) NOT NULL DEFAULT 0.00,
        `po_vendor_reference` varchar(200) DEFAULT NULL,
        `po_order_date` date DEFAULT NULL,
        `po_created_by` int(11) NOT NULL DEFAULT 0,
        `po_submitted_at` datetime DEFAULT NULL,
        `po_approved_by` int(11) NOT NULL DEFAULT 0,
        `po_approved_at` datetime DEFAULT NULL,
        `po_approval_note` varchar(255) DEFAULT NULL,
        `po_ordered_by` int(11) NOT NULL DEFAULT 0,
        `po_ordered_at` datetime DEFAULT NULL,
        `po_received_at` datetime DEFAULT NULL,
        `po_cancelled_at` datetime DEFAULT NULL,
        `po_expense_id` int(11) NOT NULL DEFAULT 0,
        `po_created_at` datetime NOT NULL DEFAULT current_timestamp(),
        `po_updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
        PRIMARY KEY (`po_id`),
        UNIQUE KEY `po_number` (`po_prefix`,`po_number`),
        KEY `po_status` (`po_status`),
        KEY `po_vendor_id` (`po_vendor_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `po_items` (
        `po_item_id` int(11) NOT NULL AUTO_INCREMENT,
        `po_item_po_id` int(11) NOT NULL,
        `po_item_order_item_id` int(11) NOT NULL DEFAULT 0,
        `po_item_product_id` int(11) NOT NULL DEFAULT 0,
        `po_item_client_id` int(11) NOT NULL DEFAULT 0,
        `po_item_name` varchar(255) NOT NULL,
        `po_item_description` text DEFAULT NULL,
        `po_item_vendor_part` varchar(200) DEFAULT NULL,
        `po_item_quantity` decimal(15,2) NOT NULL DEFAULT 1.00,
        `po_item_cost` decimal(15,2) NOT NULL DEFAULT 0.00,
        `po_item_received` decimal(15,2) NOT NULL DEFAULT 0.00,
        `po_item_order` int(11) NOT NULL DEFAULT 0,
        PRIMARY KEY (`po_item_id`),
        KEY `po_item_po_id` (`po_item_po_id`),
        KEY `po_item_order_item_id` (`po_item_order_item_id`),
        KEY `po_item_client_id` (`po_item_client_id`),
        CONSTRAINT `po_items_po` FOREIGN KEY (`po_item_po_id`) REFERENCES `purchase_orders` (`po_id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `po_receipts` (
        `po_receipt_id` int(11) NOT NULL AUTO_INCREMENT,
        `po_receipt_po_id` int(11) NOT NULL,
        `po_receipt_item_id` int(11) NOT NULL,
        `po_receipt_quantity` decimal(15,2) NOT NULL,
        `po_receipt_serials` text DEFAULT NULL,
        `po_receipt_asset_ids` varchar(1000) DEFAULT NULL,
        `po_receipt_stock_id` int(11) NOT NULL DEFAULT 0,
        `po_receipt_by` int(11) NOT NULL DEFAULT 0,
        `po_receipt_at` datetime NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`po_receipt_id`),
        KEY `po_receipt_item_id` (`po_receipt_item_id`),
        KEY `po_receipt_po_id` (`po_receipt_po_id`),
        CONSTRAINT `po_receipts_po` FOREIGN KEY (`po_receipt_po_id`) REFERENCES `purchase_orders` (`po_id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `po_tracking` (
        `po_tracking_id` int(11) NOT NULL AUTO_INCREMENT,
        `po_tracking_po_id` int(11) NOT NULL,
        `po_tracking_carrier` varchar(30) NOT NULL DEFAULT '',
        `po_tracking_number` varchar(100) NOT NULL DEFAULT '',
        `po_tracking_eta` date DEFAULT NULL,
        `po_tracking_created_at` datetime NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`po_tracking_id`),
        KEY `po_tracking_po_id` (`po_tracking_po_id`),
        CONSTRAINT `po_tracking_po` FOREIGN KEY (`po_tracking_po_id`) REFERENCES `purchase_orders` (`po_id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    if (!$itflow_column('settings', 'config_po_prefix')) {
        mysqli_query($mysqli, "ALTER TABLE `settings` ADD `config_po_prefix` varchar(200) NOT NULL DEFAULT 'PO-' AFTER `config_recurring_invoice_next_number`");
    }
    if (!$itflow_column('settings', 'config_po_next_number')) {
        mysqli_query($mysqli, "ALTER TABLE `settings` ADD `config_po_next_number` int(11) NOT NULL DEFAULT 1 AFTER `config_po_prefix`");
    }
    if (!$itflow_column('settings', 'config_po_approval')) {
        mysqli_query($mysqli, "ALTER TABLE `settings` ADD `config_po_approval` tinyint(1) NOT NULL DEFAULT 0 AFTER `config_po_next_number`");
    }
    if (!$itflow_column('settings', 'config_po_approval_over')) {
        mysqli_query($mysqli, "ALTER TABLE `settings` ADD `config_po_approval_over` decimal(15,2) NOT NULL DEFAULT 0.00 AFTER `config_po_approval`");
    }

    // The Procurement permission - Mountaineer IT's build names it the same
    if (!mysqli_num_rows(mysqli_query($mysqli, "SELECT 1 FROM modules WHERE module_name = 'module_procurement'"))) {
        mysqli_query($mysqli, "INSERT INTO modules SET module_name = 'module_procurement', module_description = 'Access to purchasing - items to order, purchase orders and receiving'");
    }
