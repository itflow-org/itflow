<?php

if (isset($_POST['add_ticket_status'])) {

    $name = sanitizeInput($_POST['name']);
    $color = sanitizeInput($_POST['color']);

    mysqli_query($mysqli, "INSERT INTO ticket_statuses SET ticket_status_name = '$name', ticket_status_color = '$color'");

    $ticket_status_id = mysqli_insert_id($mysqli);

    // Logging
    mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Ticket Status', log_action = 'Create', log_description = '$session_name created ticket status $name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id, log_entity_id = $ticket_status_id");

    $_SESSION['alert_message'] = "You created Ticket Status <strong>$name</strong>";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['edit_ticket_status'])) {

    $ticket_status_id = intval($_POST['ticket_status_id']);
    $name = sanitizeInput($_POST['name']);
    $color = sanitizeInput($_POST['color']);
    $status = intval($_POST['status']);

    mysqli_query($mysqli, "UPDATE ticket_statuses SET ticket_status_name = '$name', ticket_status_color = '$color', ticket_status_active = $status WHERE ticket_status_id = $ticket_status_id");

    // Logging
    mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Ticket Status', log_action = 'Edit', log_description = '$session_name edited ticket status $name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id, log_entity_id = $ticket_status_id");

    $_SESSION['alert_message'] = "You edited Ticket Status <strong>$name</strong>";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_GET['delete_ticket_status'])) {

    $ticket_status_id = intval($_GET['delete_ticket_status']);

    // Get ticket status name for logging and notification
    $sql = mysqli_query($mysqli, "SELECT * FROM ticket_statuses WHERE ticket_status_id = $ticket_status_id");
    $row = mysqli_fetch_array($sql);
    $ticket_status_name = sanitizeInput($row['ticket_status_name']);

    mysqli_query($mysqli, "DELETE FROM ticket_statuses WHERE ticket_status_id = $ticket_status_id");

    // Logging
    mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Ticket Status', log_action = 'Delete', log_description = '$session_name deleted ticket_status $ticket_status_name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id, log_entity_id = $ticket_status_id");

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "You Deleted Ticket Status <strong>$ticket_status_name</strong>";

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}
