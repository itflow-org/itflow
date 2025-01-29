<?php

/*
 * ITFlow - GET/POST request handler for client tickets
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['update_kanban_status_position'])) {
    // Update multiple ticket status kanban orders
    enforceUserPermission('module_support', 2);

    $positions = $_POST['positions'];

    foreach ($positions as $position) {
        $status_id = intval($position['status_id']);
        $kanban = intval($position['status_kanban']);

        mysqli_query($mysqli, "UPDATE ticket_statuses SET ticket_status_kanban = $kanban WHERE ticket_status_id = $status_id");
    }

    // return a response
    echo json_encode(['status' => 'success']);
    exit;
}

if (isset($_POST['update_kanban_ticket'])) {
    // Update ticket kanban order and status
    enforceUserPermission('module_support', 2);

    $positions = $_POST['positions'];

    foreach ($positions as $position) {
        $ticket_id = intval($position['ticket_id']);
        $kanban = intval($position['ticket_kanban']); // ticket kanban position
        $status = intval($position['ticket_status']); // ticket statuses

        // Continue if status is null
        if ($status === null) {
            continue;
        }

        mysqli_query($mysqli, "UPDATE tickets SET ticket_kanban = $kanban, ticket_status = $status WHERE ticket_id = $ticket_id");
        customAction('ticket_update', $ticket_id);
    }

    // return a response
    echo json_encode(['status' => 'success','payload' => $positions]);
    exit;
}
