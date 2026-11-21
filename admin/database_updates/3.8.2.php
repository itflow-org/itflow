<?php

/*
 * ITFlow - Database update to version 3.8.2 (from 3.8.1)
 * Included by admin/database_updates.php - do not access directly
 *
 * Booked work has an end (functions/ticket.php, ticketSetSchedule()):
 *   tickets.ticket_schedule_end - when the booked work ends, in company time;
 *     NULL is the usual length after the start (CALENDAR_TICKET_MINUTES)
 *   recurring_tickets.recurring_ticket_schedule_minutes - how long each ticket a
 *     recurring ticket books is booked for
 *
 * Re-runnable: each column is added only where it isn't there yet.
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    $itflow_column = function ($table, $column) use ($mysqli) {
        $result = mysqli_query($mysqli, "SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$table' AND COLUMN_NAME = '$column' LIMIT 1");
        return $result && mysqli_num_rows($result) > 0;
    };

    if (!$itflow_column('tickets', 'ticket_schedule_end')) {
        mysqli_query($mysqli, "ALTER TABLE `tickets` ADD `ticket_schedule_end` datetime DEFAULT NULL AFTER `ticket_schedule`");
    }

    if (!$itflow_column('recurring_tickets', 'recurring_ticket_schedule_minutes')) {
        mysqli_query($mysqli, "ALTER TABLE `recurring_tickets` ADD `recurring_ticket_schedule_minutes` int(11) NOT NULL DEFAULT 60 AFTER `recurring_ticket_schedule_onsite`");
    }
