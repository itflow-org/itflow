<?php

/*
 * ITFlow - Database update to version 3.2.3 (from 3.2.2)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    // What picking a canned response sets up on the reply as well: who sees it
    // (0 internal note, 1 public note, 2 email reply - NULL leaves it), the
    // ticket's status after it (NULL leaves it) and minutes added to the time
    // worked
    mysqli_query($mysqli, "ALTER TABLE `canned_responses`
        ADD `canned_response_reply_type` tinyint(1) DEFAULT NULL AFTER `canned_response_body`,
        ADD `canned_response_ticket_status` int(11) DEFAULT NULL AFTER `canned_response_reply_type`,
        ADD `canned_response_minutes` int(11) NOT NULL DEFAULT 0 AFTER `canned_response_ticket_status`");
