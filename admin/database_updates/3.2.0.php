<?php

/*
 * ITFlow - Database update to version 3.2.0 (from 3.1.9)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    // Fill-ins: what a ticket template asks for when a ticket is raised from
    // it - the [Employee Name]s in its text, and any others added. Only the
    // ones set up (a type, required, choices, help, or dragged into order)
    // have a row; the rest of the bracketed words in its text are asked for
    // as plain optional text.
    mysqli_query($mysqli, "CREATE TABLE `ticket_template_fill_ins` (
        `fill_in_id` int(11) NOT NULL AUTO_INCREMENT,
        `fill_in_ticket_template_id` int(11) NOT NULL,
        `fill_in_name` varchar(50) NOT NULL,
        `fill_in_type` varchar(10) NOT NULL DEFAULT 'text',
        `fill_in_options` text DEFAULT NULL,
        `fill_in_required` tinyint(1) NOT NULL DEFAULT 0,
        `fill_in_help` varchar(255) DEFAULT NULL,
        `fill_in_order` int(11) NOT NULL DEFAULT 0,
        PRIMARY KEY (`fill_in_id`),
        UNIQUE KEY `fill_in_template_name` (`fill_in_ticket_template_id`,`fill_in_name`),
        CONSTRAINT `ticket_template_fill_ins_ibfk_1` FOREIGN KEY (`fill_in_ticket_template_id`) REFERENCES `ticket_templates` (`ticket_template_id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    // The answers a ticket was raised with - each with its question as it was
    // then, so the ticket's summary outlives changes to the template
    mysqli_query($mysqli, "CREATE TABLE `ticket_fill_ins` (
        `ticket_fill_in_id` int(11) NOT NULL AUTO_INCREMENT,
        `ticket_fill_in_ticket_id` int(11) NOT NULL,
        `ticket_fill_in_name` varchar(50) NOT NULL,
        `ticket_fill_in_type` varchar(10) NOT NULL DEFAULT 'text',
        `ticket_fill_in_options` text DEFAULT NULL,
        `ticket_fill_in_required` tinyint(1) NOT NULL DEFAULT 0,
        `ticket_fill_in_value` text DEFAULT NULL,
        `ticket_fill_in_order` int(11) NOT NULL DEFAULT 0,
        PRIMARY KEY (`ticket_fill_in_id`),
        KEY `ticket_fill_in_ticket_id` (`ticket_fill_in_ticket_id`),
        CONSTRAINT `ticket_fill_ins_ibfk_1` FOREIGN KEY (`ticket_fill_in_ticket_id`) REFERENCES `tickets` (`ticket_id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    // The answers a recurring ticket raises its tickets with, every time
    mysqli_query($mysqli, "CREATE TABLE `recurring_ticket_fill_ins` (
        `recurring_ticket_fill_in_id` int(11) NOT NULL AUTO_INCREMENT,
        `recurring_ticket_fill_in_recurring_ticket_id` int(11) NOT NULL,
        `recurring_ticket_fill_in_name` varchar(50) NOT NULL,
        `recurring_ticket_fill_in_type` varchar(10) NOT NULL DEFAULT 'text',
        `recurring_ticket_fill_in_options` text DEFAULT NULL,
        `recurring_ticket_fill_in_required` tinyint(1) NOT NULL DEFAULT 0,
        `recurring_ticket_fill_in_value` text DEFAULT NULL,
        `recurring_ticket_fill_in_order` int(11) NOT NULL DEFAULT 0,
        PRIMARY KEY (`recurring_ticket_fill_in_id`),
        KEY `recurring_ticket_fill_in_recurring_ticket_id` (`recurring_ticket_fill_in_recurring_ticket_id`),
        CONSTRAINT `recurring_ticket_fill_ins_ibfk_1` FOREIGN KEY (`recurring_ticket_fill_in_recurring_ticket_id`) REFERENCES `recurring_tickets` (`recurring_ticket_id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    // A template's task can be for some answers only - "Order a laptop" when
    // Needs a laptop is Yes. NULL: always.
    mysqli_query($mysqli, "ALTER TABLE `task_templates`
        ADD `task_template_when_fill_in` varchar(50) DEFAULT NULL AFTER `task_template_completion_estimate`,
        ADD `task_template_when_value` varchar(255) DEFAULT NULL AFTER `task_template_when_fill_in`");
