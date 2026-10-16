<?php

/*
 * ITFlow - Database update to version 2.9.3 (from 2.9.2)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    // Quotes with monthly lines (functions/billing.php, Quotes). A quote line is
    // billed once (NULL) or each month, quarter or year - those go on a
    // recurring invoice when the quote is invoiced. quote_amount stays what is
    // billed once; quote_monthly_amount is what the recurring lines come to a
    // month (a quarterly line a third, a yearly one a twelfth).
    mysqli_query($mysqli, "ALTER TABLE `quote_items` ADD COLUMN `item_frequency` varchar(10) DEFAULT NULL AFTER `item_order`");
    mysqli_query($mysqli, "ALTER TABLE `quotes` ADD COLUMN `quote_monthly_amount` decimal(15,2) NOT NULL DEFAULT 0.00 AFTER `quote_amount`");

    // What a quote became - the invoice and recurring invoices made from it
    mysqli_query($mysqli, "ALTER TABLE `invoices` ADD COLUMN `invoice_quote_id` int(11) NOT NULL DEFAULT 0 AFTER `invoice_recurring_invoice_id`");
    mysqli_query($mysqli, "ALTER TABLE `recurring_invoices` ADD COLUMN `recurring_invoice_quote_id` int(11) NOT NULL DEFAULT 0 AFTER `recurring_invoice_category_id`");

    // Invoices made from a quote before now say so only in their history - link them
    mysqli_query($mysqli, "UPDATE `invoices`
        JOIN `history` ON `history_invoice_id` = `invoice_id`
        JOIN `quotes` ON `history_description` = CONCAT('Invoice created from quote ', `quote_prefix`, `quote_number`) AND `quote_client_id` = `invoice_client_id`
        SET `invoice_quote_id` = `quote_id`
        WHERE `invoice_quote_id` = 0");
