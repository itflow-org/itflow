<?php

/*
 * ITFlow - Database update to version 3.5.3 (from 3.5.2)
 * Included by admin/database_updates.php - do not access directly
 *
 * Mentions in a ticket's internal notes (functions/ticket_mention.php): each
 * agent an internal note @mentions - told in ITFlow and by email when it is
 * written, and found again with the tickets list's Mentioned me. One row per
 * agent a note mentions; it goes with the note, and an archived note's
 * mentions no longer count. The ticket is the note's.
 *
 *   mention_reply_id    the internal note (ticket_replies)
 *   mention_user_id     the agent mentioned
 *   mention_by          who mentioned them
 *   mention_created_at  when - an edit adding someone tells them then
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `ticket_reply_mentions` (
        `mention_reply_id` int(11) NOT NULL,
        `mention_user_id` int(11) NOT NULL,
        `mention_by` int(11) NOT NULL DEFAULT 0,
        `mention_created_at` datetime NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`mention_reply_id`,`mention_user_id`),
        KEY `mention_user_id` (`mention_user_id`),
        CONSTRAINT `ticket_reply_mentions_ibfk_1` FOREIGN KEY (`mention_reply_id`) REFERENCES `ticket_replies` (`ticket_reply_id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
