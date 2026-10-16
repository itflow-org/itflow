<?php

/*
 * ITFlow - Database update to version 2.9.1 (from 2.9.0)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    // Seat-based lines on recurring invoices (functions/billing.php). A line
    // can take its quantity from the client - their contacts, their devices
    // (of some types), or a license's seats - counted again each time an
    // invoice is made, never less than its minimum. item_seat_filter holds
    // what to count, as JSON.
    mysqli_query($mysqli, "ALTER TABLE `recurring_invoice_items`
        ADD COLUMN `item_seat_source` varchar(20) DEFAULT NULL AFTER `item_product_id`,
        ADD COLUMN `item_seat_filter` varchar(1000) DEFAULT NULL AFTER `item_seat_source`,
        ADD COLUMN `item_seat_minimum` decimal(15,2) NOT NULL DEFAULT 0.00 AFTER `item_seat_filter`");

    // The day of the month a recurring invoice bills on, so a schedule on the
    // 31st comes back to the 31st after a shorter month instead of drifting
    // to the 28th for good. Existing ones keep the day of their next date.
    mysqli_query($mysqli, "ALTER TABLE `recurring_invoices` ADD COLUMN `recurring_invoice_anchor_day` tinyint(2) NOT NULL DEFAULT 0 AFTER `recurring_invoice_next_date`");
    mysqli_query($mysqli, "UPDATE `recurring_invoices` SET `recurring_invoice_anchor_day` = DAY(`recurring_invoice_next_date`) WHERE `recurring_invoice_next_date` IS NOT NULL");
