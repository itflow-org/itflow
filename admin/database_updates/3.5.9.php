<?php

/*
 * ITFlow - Database update to version 3.5.9 (from 3.5.8)
 * Included by admin/database_updates.php - do not access directly
 *
 * settings.config_invoice_paid_lead_to_client - a lead that pays an invoice in full
 * becomes a client (invoicePaidFollowUp(), functions/billing.php). On by default:
 * a lead that pays is a client.
 *
 * Re-runnable: the column is made only where it isn't there yet.
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    $itflow_column_exists = mysqli_query($mysqli, "SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'settings' AND COLUMN_NAME = 'config_invoice_paid_lead_to_client' LIMIT 1");
    if (!$itflow_column_exists || mysqli_num_rows($itflow_column_exists) === 0) {
        mysqli_query($mysqli, "ALTER TABLE `settings` ADD `config_invoice_paid_lead_to_client` tinyint(1) NOT NULL DEFAULT 1 AFTER `config_invoice_paid_notification_email`");
    }

    unset($itflow_column_exists);
