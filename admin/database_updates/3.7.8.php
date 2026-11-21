<?php

/*
 * ITFlow - Database update to version 3.7.8 (from 3.7.7)
 * Included by admin/database_updates.php - do not access directly
 *
 * When the time on a ticket note was worked (functions/ticket.php, "When a
 * note's time was worked"): the start and end of the work, in company time.
 * The reply box fills them from the timer, or the agent types them; typed
 * times set the time worked. NULL on a note from before, the API, a bulk
 * reply or email - it ended when it was posted.
 *   ticket_replies.ticket_reply_work_start
 *   ticket_replies.ticket_reply_work_end
 *
 * Re-runnable: each column is added only where it isn't there yet.
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    $itflow_column = function ($table, $column) use ($mysqli) {
        $result = mysqli_query($mysqli, "SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$table' AND COLUMN_NAME = '$column' LIMIT 1");
        return $result && mysqli_num_rows($result) > 0;
    };

    if (!$itflow_column('ticket_replies', 'ticket_reply_work_start')) {
        mysqli_query($mysqli, "ALTER TABLE `ticket_replies` ADD `ticket_reply_work_start` datetime DEFAULT NULL AFTER `ticket_reply_time_worked`");
    }

    if (!$itflow_column('ticket_replies', 'ticket_reply_work_end')) {
        mysqli_query($mysqli, "ALTER TABLE `ticket_replies` ADD `ticket_reply_work_end` datetime DEFAULT NULL AFTER `ticket_reply_work_start`");
    }
