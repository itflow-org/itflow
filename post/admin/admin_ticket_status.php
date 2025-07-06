<?php

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['add_ticket_status'])) {

    $name = sanitizeInput($_POST['name']);
    $color = sanitizeInput($_POST['color']);

    mysqli_query($mysqli, "INSERT INTO ticket_statuses SET ticket_status_name = '$name', ticket_status_color = '$color'");

    $ticket_status_id = mysqli_insert_id($mysqli);

    // Logging
    logAction("Ticket Status", "Create", "$session_name created custom ticket status $name", 0, $ticket_status_id);

    $_SESSION['alert_message'] = "Custom Ticket Status <strong>$name</strong> created";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['edit_ticket_status'])) {

    $ticket_status_id = intval($_POST['ticket_status_id']);
    $name = sanitizeInput($_POST['name']);
    $color = sanitizeInput($_POST['color']);
    $order = intval($_POST['order']);
    $status = intval($_POST['status']);

    mysqli_query($mysqli, "UPDATE ticket_statuses SET ticket_status_name = '$name', ticket_status_color = '$color', ticket_status_order = $order, ticket_status_active = $status WHERE ticket_status_id = $ticket_status_id");

    // Logging
    logAction("Ticket Status", "Edit", "$session_name edited custom ticket status $name", 0, $ticket_status_id);

    $_SESSION['alert_message'] = "Custom Ticket Status <strong>$name</strong> edited";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_GET['delete_ticket_status'])) {

    validateCSRFToken($_GET['csrf_token']);

    $ticket_status_id = intval($_GET['delete_ticket_status']);

    if ($ticket_status_id <= 5) {
        exit("Can't delete built-in statuses");
    }

    // Get ticket status name for logging and notification
    $sql = mysqli_query($mysqli, "SELECT * FROM ticket_statuses WHERE ticket_status_id = $ticket_status_id");
    $row = mysqli_fetch_array($sql);
    $ticket_status_name = sanitizeInput($row['ticket_status_name']);

    mysqli_query($mysqli, "DELETE FROM ticket_statuses WHERE ticket_status_id = $ticket_status_id");

    // Logging
    logAction("Ticket Status", "Delete", "$session_name deleted custom ticket status $ticket_status_name");

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "Custom Ticket Status <strong>$ticket_status_name</strong> Deleted";

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}
