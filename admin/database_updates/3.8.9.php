<?php

/*
 * ITFlow - Database update to version 3.8.9 (from 3.8.8)
 * Included by admin/database_updates.php - do not access directly
 *
 * Project tickets kept apart from the service desk (functions/project_tickets.php):
 *   settings.config_project_tickets_apart - 1 keeps a project's tickets off the
 *     Tickets list's usual views, the dashboard's counts and the Dispatch queue,
 *     with no SLA; 0 (the default) as before.
 *   settings.config_project_ticket_color - their bookings' colour on the calendar;
 *     NULL is purple.
 *   tickets.ticket_sla_from - when the ticket's SLA targets count from, when it
 *     isn't when it was made: the moment it left a project. NULL from creation.
 *
 * Re-runnable: each column is added only where it isn't there yet.
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    $itflow_column = function ($table, $column) use ($mysqli) {
        $result = mysqli_query($mysqli, "SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$table' AND COLUMN_NAME = '$column' LIMIT 1");
        return $result && mysqli_num_rows($result) > 0;
    };

    if (!$itflow_column('settings', 'config_project_tickets_apart')) {
        mysqli_query($mysqli, "ALTER TABLE `settings` ADD `config_project_tickets_apart` tinyint(1) NOT NULL DEFAULT 0 AFTER `config_project_next_number`");
    }
    if (!$itflow_column('settings', 'config_project_ticket_color')) {
        mysqli_query($mysqli, "ALTER TABLE `settings` ADD `config_project_ticket_color` varchar(20) DEFAULT NULL AFTER `config_project_tickets_apart`");
    }
    if (!$itflow_column('tickets', 'ticket_sla_from')) {
        mysqli_query($mysqli, "ALTER TABLE `tickets` ADD `ticket_sla_from` datetime DEFAULT NULL AFTER `ticket_sla_id`");
    }
