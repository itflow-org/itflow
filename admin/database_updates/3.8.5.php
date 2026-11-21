<?php

/*
 * ITFlow - Database update to version 3.8.5 (from 3.8.4)
 * Included by admin/database_updates.php - do not access directly
 *
 * Additional agents on a ticket (functions/ticket_helpers.php): ticket_helpers, the
 * agents helping the assigned (lead) agent - each booked at the same time as the
 * lead (follows_lead), at a time of their own (schedule_start / schedule_end, in
 * company time), or not booked. The lead stays tickets.ticket_assigned_to.
 *
 * Re-runnable: the table is made only where it isn't there yet.
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `ticket_helpers` (
        `ticket_id` int(11) NOT NULL,
        `user_id` int(11) NOT NULL,
        `follows_lead` tinyint(1) NOT NULL DEFAULT 1,
        `schedule_start` datetime DEFAULT NULL,
        `schedule_end` datetime DEFAULT NULL,
        `added_by` int(11) NOT NULL DEFAULT 0,
        `added_at` datetime NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`ticket_id`, `user_id`),
        KEY `ticket_helpers_user` (`user_id`),
        CONSTRAINT `ticket_helpers_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`ticket_id`) ON DELETE CASCADE,
        CONSTRAINT `ticket_helpers_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
