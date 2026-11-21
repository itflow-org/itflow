<?php

/*
 * ITFlow - Database update to version 3.7.4 (from 3.7.3)
 * Included by admin/database_updates.php - do not access directly
 *
 * Resolve - no response (functions/ticket.php, ticketResolveNoResponse()):
 *   tickets.ticket_no_response_at - the ticket_resolved_at of the resolution that was "no response";
 *     while the two match, closing it asks the client nothing (ticketMailClosed()). A reopen and a
 *     later resolve leave it behind.
 *   settings.config_ticket_no_response_message - the message, as typed in Admin > Settings >
 *     Tickets; NULL is the built-in wording.
 *
 * Re-runnable: each column is added only where it isn't there yet.
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    $itflow_column = function ($table, $column) use ($mysqli) {
        $result = mysqli_query($mysqli, "SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$table' AND COLUMN_NAME = '$column' LIMIT 1");
        return $result && mysqli_num_rows($result) > 0;
    };

    if (!$itflow_column('tickets', 'ticket_no_response_at')) {
        mysqli_query($mysqli, "ALTER TABLE `tickets` ADD `ticket_no_response_at` datetime DEFAULT NULL AFTER `ticket_resolved_at`");
    }

    if (!$itflow_column('settings', 'config_ticket_no_response_message')) {
        mysqli_query($mysqli, "ALTER TABLE `settings` ADD `config_ticket_no_response_message` text DEFAULT NULL AFTER `config_ticket_assigned_message`");
    }
