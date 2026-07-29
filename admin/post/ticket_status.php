<?php

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['add_ticket_status'])) {

    validateCSRFToken();

    $name = escapeSql($_POST['name']);
    $color = escapeSql($_POST['color']);

    $pauses_sla = intval($_POST['pauses_sla'] ?? 0);

    mysqli_query($mysqli, "INSERT INTO ticket_statuses SET ticket_status_name = '$name', ticket_status_color = '$color', ticket_status_pauses_sla = $pauses_sla");

    $ticket_status_id = mysqli_insert_id($mysqli);

    logAudit("Ticket Status", "Create", "$session_name created custom ticket status $name", 0, $ticket_status_id);

    flashAlert("Custom Ticket Status <strong>$name</strong> created");

    redirect();

}

if (isset($_POST['edit_ticket_status'])) {

    validateCSRFToken();

    $ticket_status_id = intval($_POST['ticket_status_id']);
    $name = escapeSql($_POST['name']);
    $color = escapeSql($_POST['color']);
    $order = intval($_POST['order']);
    $status = intval($_POST['status']);

    $pauses_sla = intval($_POST['pauses_sla'] ?? 0);

    mysqli_query($mysqli, "UPDATE ticket_statuses SET ticket_status_name = '$name', ticket_status_color = '$color', ticket_status_order = $order, ticket_status_active = $status, ticket_status_pauses_sla = $pauses_sla WHERE ticket_status_id = $ticket_status_id");

    // Tickets already sitting in this status need their clock reconciled
    $sql_status_tickets = mysqli_query($mysqli, "SELECT ticket_id FROM tickets WHERE ticket_status = $ticket_status_id AND ticket_closed_at IS NULL AND ticket_archived_at IS NULL");
    while ($status_ticket_row = mysqli_fetch_assoc($sql_status_tickets)) {
        syncTicketSlaClock($status_ticket_row['ticket_id']);
    }

    logAudit("Ticket Status", "Edit", "$session_name edited custom ticket status $name", 0, $ticket_status_id);

    flashAlert("Custom Ticket Status <strong>$name</strong> edited");

    redirect();

}

if (isset($_GET['delete_ticket_status'])) {

    validateCSRFToken();

    $ticket_status_id = intval($_GET['delete_ticket_status']);

    if ($ticket_status_id <= 5) {
        exit("Can't delete built-in statuses");
    }

    $ticlet_status_name = escapeSql(getFieldById('ticket_statuses', $ticket_status_id, 'ticket_status_name'));

    mysqli_query($mysqli, "DELETE FROM ticket_statuses WHERE ticket_status_id = $ticket_status_id");

    logAudit("Ticket Status", "Delete", "$session_name deleted custom ticket status $ticket_status_name");

    flashAlert("Custom Ticket Status <strong>$ticket_status_name</strong> Deleted", 'error');

    redirect();

}
