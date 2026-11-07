<?php

/*
 * ITFlow - Database update to version 3.6.1 (from 3.6.0)
 * Included by admin/database_updates.php - do not access directly
 *
 * Texts to clients (functions/sms.php):
 *   contacts.contact_sms_opt_in, contact_sms_opt_in_at, contact_sms_opt_in_by - whether a contact
 *     agreed to texts about their tickets, visits and invoices, when, and how (an agent, the
 *     client portal, or replying YES when asked by text). Off for everyone to start with.
 *   sms_messages.sms_message_send_after - a text that waits for the morning
 *   settings.config_sms_client_tickets, config_sms_client_visits, config_sms_visit_reminder_hours,
 *     config_sms_client_invoices - Settings > Notifications > Texts to clients
 *
 * Re-runnable: each column is made only where it isn't there yet.
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    $itflow_sms_columns = [
        ['contacts', 'contact_sms_opt_in', "tinyint(1) NOT NULL DEFAULT 0 AFTER `contact_company_ticket_emails`"],
        ['contacts', 'contact_sms_opt_in_at', "datetime DEFAULT NULL AFTER `contact_sms_opt_in`"],
        ['contacts', 'contact_sms_opt_in_by', "varchar(200) DEFAULT NULL AFTER `contact_sms_opt_in_at`"],
        ['sms_messages', 'sms_message_send_after', "datetime DEFAULT NULL AFTER `sms_message_created_at`"],
        ['settings', 'config_sms_client_tickets', "tinyint(1) NOT NULL DEFAULT 1 AFTER `config_ticket_sms_acknowledge`"],
        ['settings', 'config_sms_client_visits', "tinyint(1) NOT NULL DEFAULT 1 AFTER `config_sms_client_tickets`"],
        ['settings', 'config_sms_visit_reminder_hours', "int(11) NOT NULL DEFAULT 24 AFTER `config_sms_client_visits`"],
        ['settings', 'config_sms_client_invoices', "tinyint(1) NOT NULL DEFAULT 1 AFTER `config_sms_visit_reminder_hours`"],
    ];
    foreach ($itflow_sms_columns as [$itflow_sms_table, $itflow_sms_column, $itflow_sms_definition]) {
        $itflow_column_exists = mysqli_query($mysqli, "SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$itflow_sms_table' AND COLUMN_NAME = '$itflow_sms_column' LIMIT 1");
        if (!$itflow_column_exists || mysqli_num_rows($itflow_column_exists) === 0) {
            mysqli_query($mysqli, "ALTER TABLE `$itflow_sms_table` ADD `$itflow_sms_column` $itflow_sms_definition");
        }
    }

    // Whether a reminder or a booking went for a ticket is looked up by what it was for and the ticket
    $itflow_index_exists = mysqli_query($mysqli, "SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sms_messages' AND INDEX_NAME = 'sms_message_purpose' LIMIT 1");
    if (!$itflow_index_exists || mysqli_num_rows($itflow_index_exists) === 0) {
        mysqli_query($mysqli, "ALTER TABLE `sms_messages` ADD KEY `sms_message_purpose` (`sms_message_purpose`,`sms_message_ticket_id`,`sms_message_created_at`)");
    }

    unset($itflow_sms_columns, $itflow_sms_table, $itflow_sms_column, $itflow_sms_definition, $itflow_column_exists, $itflow_index_exists);
