<?php

/*
 * ITFlow - Database update to version 4.0.9 (from 4.0.8)
 * Included by admin/database_updates.php - do not access directly
 *
 * settings.config_ticket_reply_assigns - a reply the client sees on a ticket nobody
 * is assigned to makes it the replying agent's (agent/post/ticket.php, add_ticket_reply).
 * On to start with; Admin > Settings > Tickets switches it off.
 *
 * Re-runnable: the column is only added where it isn't there yet.
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    if (!mysqli_num_rows(mysqli_query($mysqli, "SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'settings' AND COLUMN_NAME = 'config_ticket_reply_assigns'"))) {
        mysqli_query($mysqli, "ALTER TABLE `settings` ADD `config_ticket_reply_assigns` tinyint(1) NOT NULL DEFAULT 1 AFTER `config_ticket_moving_columns`");
    }
