<?php

/*
 * ITFlow - Database update to version 2.4.2 (from 2.4.1)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    // Migrate Items
    mysqli_query($mysqli, "
        INSERT INTO `recurring_invoice_items` (
          `item_name`,
          `item_description`,
          `item_quantity`,
          `item_price`,
          `item_subtotal`,
          `item_tax`,
          `item_total`,
          `item_order`,
          `item_created_at`,
          `item_updated_at`,
          `item_archived_at`,
          `item_tax_id`,
          `item_product_id`,
          `item_recurring_invoice_id`
        )
        SELECT
          `item_name`,
          `item_description`,
          `item_quantity`,
          `item_price`,
          `item_subtotal`,
          `item_tax`,
          `item_total`,
          `item_order`,
          `item_created_at`,
          `item_updated_at`,
          `item_archived_at`,
          `item_tax_id`,
          `item_product_id`,
          `item_recurring_invoice_id`
        FROM `invoice_items`
        WHERE `item_recurring_invoice_id` != 0
    ");

    mysqli_query($mysqli, "
        INSERT INTO `quote_items` (
          `item_name`,
          `item_description`,
          `item_quantity`,
          `item_price`,
          `item_subtotal`,
          `item_tax`,
          `item_total`,
          `item_order`,
          `item_created_at`,
          `item_updated_at`,
          `item_archived_at`,
          `item_tax_id`,
          `item_product_id`,
          `item_quote_id`
        )
        SELECT
          `item_name`,
          `item_description`,
          `item_quantity`,
          `item_price`,
          `item_subtotal`,
          `item_tax`,
          `item_total`,
          `item_order`,
          `item_created_at`,
          `item_updated_at`,
          `item_archived_at`,
          `item_tax_id`,
          `item_product_id`,
          `item_quote_id`
        FROM `invoice_items`
        WHERE `item_quote_id` != 0
    ");

    mysqli_query($mysqli, "
        DELETE FROM `invoice_items`
        WHERE `item_recurring_invoice_id` != 0
    ");

    mysqli_query($mysqli, "
        DELETE FROM `invoice_items`
        WHERE `item_quote_id` != 0
    ");

    mysqli_query($mysqli, "
        ALTER TABLE `invoice_items`
        DROP COLUMN `item_quote_id`,
        DROP COLUMN `item_recurring_invoice_id`
    ");
