<?php

/*
 * ITFlow - Database update to version 2.8.5 (from 2.8.4)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    // A recurring ticket can be paused - kept, but raising nothing until resumed
    mysqli_query($mysqli, "ALTER TABLE `recurring_tickets` ADD COLUMN `recurring_ticket_paused_at` datetime DEFAULT NULL AFTER `recurring_ticket_next_run`");

    // Deleting a ticket used to leave its tasks, their approvals, its history
    // and its SLA history behind - clear out what belongs to no ticket
    mysqli_query($mysqli, "DELETE `task_approvals` FROM `task_approvals` JOIN `tasks` ON `task_id` = `approval_task_id`
        LEFT JOIN `tickets` ON `ticket_id` = `task_ticket_id` WHERE `task_ticket_id` > 0 AND `ticket_id` IS NULL");
    mysqli_query($mysqli, "DELETE `tasks` FROM `tasks` LEFT JOIN `tickets` ON `ticket_id` = `task_ticket_id` WHERE `task_ticket_id` > 0 AND `ticket_id` IS NULL");
    mysqli_query($mysqli, "DELETE `ticket_history` FROM `ticket_history` LEFT JOIN `tickets` ON `ticket_id` = `ticket_history_ticket_id` WHERE `ticket_id` IS NULL");
    mysqli_query($mysqli, "DELETE `sla_history` FROM `sla_history` LEFT JOIN `tickets` ON `ticket_id` = `sla_history_ticket_id` WHERE `ticket_id` IS NULL");
