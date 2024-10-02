<?php

require_once "config.php";

// Set Timezone
require_once "inc_set_timezone.php";

require_once "functions.php";


session_start();

if (isset($_GET['accept_quote'], $_GET['url_key'])) {

    $quote_id = intval($_GET['accept_quote']);
    $url_key = sanitizeInput($_GET['url_key']);

    $sql = mysqli_query($mysqli, "SELECT * FROM quotes WHERE quote_id = $quote_id AND quote_url_key = '$url_key'");

    if (mysqli_num_rows($sql) == 1) {

        mysqli_query($mysqli, "UPDATE quotes SET quote_status = 'Accepted' WHERE quote_id = $quote_id");

        mysqli_query($mysqli, "INSERT INTO history SET history_status = 'Accepted', history_description = 'Client accepted Quote!', history_quote_id = $quote_id");

        customAction('quote_accept', $quote_id);

        $_SESSION['alert_message'] = "Quote Accepted";

        header("Location: " . $_SERVER["HTTP_REFERER"]);
    } else {
        echo "Invalid!!";
    }

}

if (isset($_GET['decline_quote'], $_GET['url_key'])) {

    $quote_id = intval($_GET['decline_quote']);
    $url_key = sanitizeInput($_GET['url_key']);

    $sql = mysqli_query($mysqli, "SELECT * FROM quotes WHERE quote_id = $quote_id AND quote_url_key = '$url_key'");

    if (mysqli_num_rows($sql) == 1) {

        mysqli_query($mysqli, "UPDATE quotes SET quote_status = 'Declined' WHERE quote_id = $quote_id");

        mysqli_query($mysqli, "INSERT INTO history SET history_status = 'Declined', history_description = 'Client declined Quote!', history_quote_id = $quote_id");

        customAction('quote_decline', $quote_id);

        $_SESSION['alert_type'] = "danger";
        $_SESSION['alert_message'] = "Quote Declined";

        header("Location: " . $_SERVER["HTTP_REFERER"]);
    } else {
        echo "Invalid!!";
    }

}

if (isset($_GET['reopen_ticket'], $_GET['url_key'])) {

    $ticket_id = intval($_GET['ticket_id']);
    $url_key = sanitizeInput($_GET['url_key']);

    $sql = mysqli_query($mysqli, "SELECT * FROM tickets WHERE ticket_id = $ticket_id AND ticket_url_key = '$url_key' AND ticket_resolved_at IS NOT NULL and ticket_closed_at IS NULL");

    if (mysqli_num_rows($sql) == 1) {

        // Update the ticket
        mysqli_query($mysqli, "UPDATE tickets SET ticket_status = 2, ticket_resolved_at = NULL WHERE ticket_id = $ticket_id AND ticket_url_key = '$url_key'");

        // Add reply
        mysqli_query($mysqli, "INSERT INTO ticket_replies SET ticket_reply = 'Ticket reopened by client (guest URL).', ticket_reply_type = 'Internal', ticket_reply_by = 0, ticket_reply_ticket_id = $ticket_id");

        //Logging
        mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Ticket', log_action = 'Replied', log_description = '$ticket_id reopened by client (guest)', log_ip = '$session_ip', log_user_agent = '$session_user_agent'");

        customAction('ticket_update', $ticket_id);

        $_SESSION['alert_message'] = "Ticket reopened";
        header("Location: " . $_SERVER["HTTP_REFERER"]);

    } else {
        echo "Invalid!!";
    }

}

if (isset($_GET['close_ticket'], $_GET['url_key'])) {

    $ticket_id = intval($_GET['ticket_id']);
    $url_key = sanitizeInput($_GET['url_key']);

    $sql = mysqli_query($mysqli, "SELECT * FROM tickets WHERE ticket_id = $ticket_id AND ticket_url_key = '$url_key' AND ticket_resolved_at IS NOT NULL and ticket_closed_at IS NULL");

    if (mysqli_num_rows($sql) == 1) {

        // Update the ticket
        mysqli_query($mysqli, "UPDATE tickets SET ticket_status = 5, ticket_closed_at = NOW() WHERE ticket_id = $ticket_id AND ticket_url_key = '$url_key'");

        // Add reply
        mysqli_query($mysqli, "INSERT INTO ticket_replies SET ticket_reply = 'Ticket closed by client (guest URL).', ticket_reply_type = 'Internal', ticket_reply_by = 0, ticket_reply_ticket_id = $ticket_id");

        //Logging
        mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Ticket', log_action = 'Replied', log_description = '$ticket_id closed by client (guest)', log_ip = '$session_ip', log_user_agent = '$session_user_agent'");

        customAction('ticket_close', $ticket_id);

        $_SESSION['alert_message'] = "Ticket closed";
        header("Location: " . $_SERVER["HTTP_REFERER"]);

    } else {
        echo "Invalid!!";
    }

}

if (isset($_GET['add_ticket_feedback'], $_GET['url_key'])) {

    $ticket_id = intval($_GET['ticket_id']);
    $url_key = sanitizeInput($_GET['url_key']);
    $feedback = sanitizeInput($_GET['feedback']);

    $sql = mysqli_query($mysqli, "SELECT * FROM tickets WHERE ticket_id = $ticket_id AND ticket_url_key = '$url_key' AND ticket_closed_at IS NOT NULL");

    if (mysqli_num_rows($sql) == 1) {

        // Add feedback
        mysqli_query($mysqli, "UPDATE tickets SET ticket_feedback = '$feedback' WHERE ticket_id = $ticket_id AND ticket_url_key = '$url_key'");

        // Notify on bad feedback
        if ($feedback == "Bad") {
            mysqli_query($mysqli, "INSERT INTO notifications SET notification_type = 'Feedback', notification = 'Guest rated ticket ID $ticket_id as bad'");
        }

        $_SESSION['alert_message'] = "Feedback recorded - thank you";
        header("Location: " . $_SERVER["HTTP_REFERER"]);

        customAction('ticket_feedback', $ticket_id);

    } else {
        echo "Invalid!!";
    }

}
