<?php

/*
 * ITFlow - Database update to version 3.7.3 (from 3.7.2)
 * Included by admin/database_updates.php - do not access directly
 *
 * Ticket statuses (functions/ticket_status.php):
 *   ticket_statuses.ticket_status_reply_status_id - the status a client's reply moves a ticket in
 *     this status to (a "Client responded" status, say); 0 does what ticket_status_client_reply says.
 *   ticket_statuses.ticket_status_first_response - an agent moving a ticket into this status before
 *     anyone has answered counts as its first response: 0 no, 1 yes, 2 yes once the client has been
 *     emailed the "ticket assigned" message.
 *   settings.config_ticket_assigned_message - that message, as typed in Admin > Settings > Tickets;
 *     NULL is the built-in wording.
 *
 * Re-runnable: each column is added only where it isn't there yet.
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    $itflow_column = function ($table, $column) use ($mysqli) {
        $result = mysqli_query($mysqli, "SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$table' AND COLUMN_NAME = '$column' LIMIT 1");
        return $result && mysqli_num_rows($result) > 0;
    };

    if (!$itflow_column('ticket_statuses', 'ticket_status_reply_status_id')) {
        mysqli_query($mysqli, "ALTER TABLE `ticket_statuses` ADD `ticket_status_reply_status_id` int(11) NOT NULL DEFAULT 0 AFTER `ticket_status_client_reply`");
    }

    if (!$itflow_column('ticket_statuses', 'ticket_status_first_response')) {
        mysqli_query($mysqli, "ALTER TABLE `ticket_statuses` ADD `ticket_status_first_response` tinyint(1) NOT NULL DEFAULT 0 AFTER `ticket_status_reply_status_id`");
    }

    if (!$itflow_column('settings', 'config_ticket_assigned_message')) {
        mysqli_query($mysqli, "ALTER TABLE `settings` ADD `config_ticket_assigned_message` text DEFAULT NULL AFTER `config_ticket_timer_autostart`");
    }
