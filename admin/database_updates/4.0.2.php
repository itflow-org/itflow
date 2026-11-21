<?php

/*
 * ITFlow - Database update to version 4.0.2 (from 4.0.1)
 * Included by admin/database_updates.php - do not access directly
 *
 * ticket_reads - when each agent last opened each ticket, so the tickets list can show in bold
 * the ones the client has written on since (functions/ticket_read.php). The row with ticket_id 0
 * is an agent's starting point: anything older counts as seen. Rows for closed tickets go nightly.
 *
 * Re-runnable: the table is only made where it isn't there yet.
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `ticket_reads` (
        `ticket_id` int(11) NOT NULL,
        `user_id` int(11) NOT NULL,
        `read_at` datetime NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`ticket_id`, `user_id`),
        KEY `user_id` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
