<?php

/*
 * ITFlow - Database update to version 2.7.9 (from 2.7.8)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    // Add auto-send option to recurring invoices (default to enabled) or whether they should be generated as drafts for manual review & sending

    mysqli_query($mysqli, "ALTER TABLE `recurring_invoices`
        ADD COLUMN `recurring_invoice_auto_send` tinyint(1) NOT NULL DEFAULT 1 AFTER `recurring_invoice_note`
    ");
