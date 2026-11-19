<?php

/*
 * ITFlow - Database update to version 4.0.1 (from 4.0.0)
 * Included by admin/database_updates.php - do not access directly
 *
 * ticket_reply_drafts - the reply an agent is writing on a ticket, kept as they type so a page
 * left, a browser closed or a sign-in timing out doesn't lose it (functions/ticket_reply_draft.php).
 * One per agent per ticket; it goes when the reply is sent, and nightly once a month old or once
 * the ticket is closed.
 *
 * Re-runnable: the table is only made where it isn't there yet.
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `ticket_reply_drafts` (
        `ticket_id` int(11) NOT NULL,
        `user_id` int(11) NOT NULL,
        `draft_body` longtext NOT NULL,
        `draft_updated_at` datetime NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`ticket_id`, `user_id`),
        KEY `draft_updated_at` (`draft_updated_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
