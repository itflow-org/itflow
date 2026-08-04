<?php

/*
 * ITFlow - Database update to version 2.5.6 (from 2.5.5)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    // Recurring tickets now own their task list rather than reading the linked
    // ticket template at every run. The template still fills the list in when it
    // is picked, but the list can then be edited per schedule - which is only
    // meaningful if the edits are what the run actually reads.
    mysqli_query($mysqli, "CREATE TABLE `recurring_ticket_tasks` (
        `recurring_ticket_task_id` int(11) NOT NULL AUTO_INCREMENT,
        `recurring_ticket_task_name` varchar(255) NOT NULL,
        `recurring_ticket_task_order` int(11) NOT NULL DEFAULT 0,
        `recurring_ticket_task_completion_estimate` int(11) NOT NULL DEFAULT 0,
        `recurring_ticket_task_recurring_ticket_id` int(11) NOT NULL,
        PRIMARY KEY (`recurring_ticket_task_id`),
        KEY `recurring_ticket_task_recurring_ticket_id` (`recurring_ticket_task_recurring_ticket_id`),
        CONSTRAINT `recurring_ticket_tasks_ibfk_1` FOREIGN KEY (`recurring_ticket_task_recurring_ticket_id`) REFERENCES `recurring_tickets` (`recurring_ticket_id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    // Copy each linked template's current tasks onto the schedule that links it, so
    // every existing recurring ticket keeps raising exactly the tasks it raises
    // today. Without this the switch to an owned list would silently produce
    // taskless tickets on the next run.
    mysqli_query($mysqli, "INSERT INTO recurring_ticket_tasks
        (recurring_ticket_task_name, recurring_ticket_task_order, recurring_ticket_task_completion_estimate, recurring_ticket_task_recurring_ticket_id)
        SELECT task_template_name, task_template_order, task_template_completion_estimate, recurring_ticket_id
        FROM recurring_tickets
        INNER JOIN task_templates ON task_template_ticket_template_id = recurring_ticket_ticket_template_id
        WHERE recurring_ticket_ticket_template_id > 0");
