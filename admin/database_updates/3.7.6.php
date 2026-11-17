<?php

/*
 * ITFlow - Database update to version 3.7.6 (from 3.7.5)
 * Included by admin/database_updates.php - do not access directly
 *
 * Quick ticket (functions/quick_ticket.php) - what a ticket raised from the top
 * bar's + starts with, set in Admin > Settings > Tickets, Quick tickets:
 *   config_quick_ticket_on            the button shows, for those who can make tickets
 *   config_quick_ticket_assign        'me' (the agent making it), 'nobody', or an agent's id
 *   config_quick_ticket_status        0 (New, or Open once someone has it), or a status an agent can pick
 *   config_quick_ticket_priority      Low, Medium, High or Urgent
 *   config_quick_ticket_category      0, or a ticket category
 *   config_quick_ticket_billable      -1 as a new ticket starts (config_ticket_default_billable), else 1 or 0
 *   config_quick_ticket_timer         make, open or off - when its timer counts from
 *   config_quick_ticket_primary       the contact starts on the client's primary contact
 *   config_quick_ticket_open_tickets  the window shows the contact's open tickets
 *   config_quick_ticket_email_client  the contact gets the "ticket created" email (with client emails on)
 *
 * Re-runnable: each column is added only where it isn't there yet.
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    $itflow_column = function ($table, $column) use ($mysqli) {
        $result = mysqli_query($mysqli, "SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$table' AND COLUMN_NAME = '$column' LIMIT 1");
        return $result && mysqli_num_rows($result) > 0;
    };

    $itflow_after = 'config_ticket_no_response_message';
    foreach ([
        'config_quick_ticket_on' => "tinyint(1) NOT NULL DEFAULT 1",
        'config_quick_ticket_assign' => "varchar(10) NOT NULL DEFAULT 'me'",
        'config_quick_ticket_status' => "int(11) NOT NULL DEFAULT 0",
        'config_quick_ticket_priority' => "varchar(10) NOT NULL DEFAULT 'Medium'",
        'config_quick_ticket_category' => "int(11) NOT NULL DEFAULT 0",
        'config_quick_ticket_billable' => "tinyint(1) NOT NULL DEFAULT -1",
        'config_quick_ticket_timer' => "varchar(10) NOT NULL DEFAULT 'make'",
        'config_quick_ticket_primary' => "tinyint(1) NOT NULL DEFAULT 1",
        'config_quick_ticket_open_tickets' => "tinyint(1) NOT NULL DEFAULT 1",
        'config_quick_ticket_email_client' => "tinyint(1) NOT NULL DEFAULT 1",
    ] as $itflow_name => $itflow_type) {
        if (!$itflow_column('settings', $itflow_name)) {
            mysqli_query($mysqli, "ALTER TABLE `settings` ADD `$itflow_name` $itflow_type AFTER `$itflow_after`");
        }
        $itflow_after = $itflow_name;
    }
