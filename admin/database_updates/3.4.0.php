<?php

/*
 * ITFlow - Database update to version 3.4.0 (from 3.3.9)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    $itflow_column = function ($table, $column) use ($mysqli) {
        $result = mysqli_query($mysqli, "SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$table' AND COLUMN_NAME = '$column' LIMIT 1");
        return $result && mysqli_num_rows($result) > 0;
    };

    // What a ticket status is for - shown when it is hovered, in the pickers and on the board
    if (!$itflow_column('ticket_statuses', 'ticket_status_description')) {
        mysqli_query($mysqli, "ALTER TABLE `ticket_statuses` ADD `ticket_status_description` varchar(255) DEFAULT NULL AFTER `ticket_status_color`");
    }

    // What a client's reply does to a ticket in the status - 1 moves it back to Open (as every reply always has), 0 leaves it there
    if (!$itflow_column('ticket_statuses', 'ticket_status_client_reply')) {
        mysqli_query($mysqli, "ALTER TABLE `ticket_statuses` ADD `ticket_status_client_reply` tinyint(1) NOT NULL DEFAULT 1 AFTER `ticket_status_pauses_sla`");
    }

    // The five built-in statuses say what they are for - only where nothing is written yet
    $itflow_descriptions = [
        1 => 'Just come in - nobody has picked it up yet.',
        2 => 'Being worked on.',
        3 => 'Waiting on someone else - the client, a vendor or a part.',
        4 => 'Fixed, and waiting to be closed - a reply from the client reopens it.',
        5 => 'Finished - it can\'t be replied to.',
    ];
    foreach ($itflow_descriptions as $itflow_status_id => $itflow_description) {
        mysqli_query($mysqli, "UPDATE ticket_statuses SET ticket_status_description = '" . mysqli_real_escape_string($mysqli, $itflow_description) . "'
            WHERE ticket_status_id = $itflow_status_id AND (ticket_status_description IS NULL OR ticket_status_description = '')");
    }

    // Number the statuses 1, 2, 3... in the order they already show in (most are all 0, and ties go by id),
    // so they can be dragged into another
    $itflow_position = 0;
    $itflow_statuses = mysqli_query($mysqli, "SELECT ticket_status_id FROM ticket_statuses ORDER BY ticket_status_order ASC, ticket_status_id ASC");
    while ($itflow_status = mysqli_fetch_row($itflow_statuses)) {
        $itflow_position++;
        mysqli_query($mysqli, "UPDATE ticket_statuses SET ticket_status_order = $itflow_position WHERE ticket_status_id = " . intval($itflow_status[0]));
    }

    unset($itflow_column, $itflow_descriptions, $itflow_status_id, $itflow_description, $itflow_position, $itflow_statuses, $itflow_status);
