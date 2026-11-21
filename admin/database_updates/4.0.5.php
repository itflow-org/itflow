<?php

/*
 * ITFlow - Database update to version 4.0.5 (from 4.0.4)
 * Included by admin/database_updates.php - do not access directly
 *
 * recurring_invoices.recurring_invoice_paused_until - the day a paused recurring invoice starts
 * running again by itself (the nightly run resumes it, skipping the runs it would have made while
 * paused). NULL while it is running, or paused until someone resumes it.
 *
 * Re-runnable: the column is only added where it isn't there yet.
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    $has_paused_until = mysqli_num_rows(mysqli_query($mysqli, "SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'recurring_invoices' AND COLUMN_NAME = 'recurring_invoice_paused_until'")) > 0;

    if (!$has_paused_until) {
        mysqli_query($mysqli, "ALTER TABLE `recurring_invoices` ADD `recurring_invoice_paused_until` date DEFAULT NULL AFTER `recurring_invoice_status`");
    }
