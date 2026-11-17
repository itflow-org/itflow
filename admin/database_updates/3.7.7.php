<?php

/*
 * ITFlow - Database update to version 3.7.7 (from 3.7.6)
 * Included by admin/database_updates.php - do not access directly
 *
 * Recurring tickets booked at a time, and recurring tickets and invoices made
 * ahead of their date (functions/ticket.php, functions/billing.php):
 *   recurring_tickets.recurring_ticket_schedule_time    NULL books nothing; else each ticket raised is
 *     booked for its run day at this time, as the ticket's Schedule window books it
 *   recurring_tickets.recurring_ticket_schedule_onsite  that booking is onsite (1) or remote (0)
 *   recurring_tickets.recurring_ticket_lead_days        raised this many days before its run - the next
 *     run stays the day the work is for
 *   recurring_tickets.recurring_ticket_lead_mode        days (before the run day) or month (before the 1st
 *     of the run's month - every run in a month made on one day; month-based repeats only)
 *   recurring_invoices.recurring_invoice_lead_days      made this many days before its next invoice date,
 *     dated the day it is made
 *   recurring_invoices.recurring_invoice_due_mode       one made ahead is due on the next invoice date
 *     plus the client's terms (terms), or on that date (next)
 *
 * Re-runnable: each column is added only where it isn't there yet.
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    $itflow_column = function ($table, $column) use ($mysqli) {
        $result = mysqli_query($mysqli, "SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$table' AND COLUMN_NAME = '$column' LIMIT 1");
        return $result && mysqli_num_rows($result) > 0;
    };

    foreach ([
        ['recurring_tickets', 'recurring_ticket_schedule_time', "time DEFAULT NULL", 'recurring_ticket_next_run'],
        ['recurring_tickets', 'recurring_ticket_schedule_onsite', "tinyint(1) NOT NULL DEFAULT 0", 'recurring_ticket_schedule_time'],
        ['recurring_tickets', 'recurring_ticket_lead_days', "int(11) NOT NULL DEFAULT 0", 'recurring_ticket_schedule_onsite'],
        ['recurring_tickets', 'recurring_ticket_lead_mode', "varchar(10) NOT NULL DEFAULT 'days'", 'recurring_ticket_lead_days'],
        ['recurring_invoices', 'recurring_invoice_lead_days', "int(11) NOT NULL DEFAULT 0", 'recurring_invoice_anchor_day'],
        ['recurring_invoices', 'recurring_invoice_due_mode', "varchar(10) NOT NULL DEFAULT 'terms'", 'recurring_invoice_lead_days'],
    ] as [$itflow_table, $itflow_name, $itflow_type, $itflow_after]) {
        if (!$itflow_column($itflow_table, $itflow_name)) {
            mysqli_query($mysqli, "ALTER TABLE `$itflow_table` ADD `$itflow_name` $itflow_type AFTER `$itflow_after`");
        }
    }
