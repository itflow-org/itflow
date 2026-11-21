<?php

/*
 * ITFlow - Database update to version 4.0.8 (from 4.0.7)
 * Included by admin/database_updates.php - do not access directly
 *
 * Related tickets (functions/ticket_link.php): ticket_links puts a ticket in a group
 * of related ones - the same outage at several clients, say. Each ticket is in one
 * group at most (link_group, the lowest ticket id in it), with who linked it and when.
 * A deleted ticket's row goes with it.
 *
 * Re-runnable: the table is made only where it isn't there yet.
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `ticket_links` (
        `ticket_id` int(11) NOT NULL,
        `link_group` int(11) NOT NULL,
        `linked_by` int(11) NOT NULL DEFAULT 0,
        `linked_at` datetime NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`ticket_id`),
        KEY `ticket_links_group` (`link_group`),
        CONSTRAINT `ticket_links_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`ticket_id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
