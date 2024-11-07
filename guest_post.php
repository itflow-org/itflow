<?php

require_once "config.php";
require_once "functions.php";

session_start();

require_once "inc_set_timezone.php"; // Must be included after session_start to work

if (isset($_GET['accept_quote'], $_GET['url_key'])) {
    $quote_id = intval($_GET['accept_quote']);
    $url_key = sanitizeInput($_GET['url_key']);

    // Select only the necessary fields
    $sql = mysqli_query($mysqli, "SELECT quote_prefix, quote_number, client_name, client_id FROM quotes LEFT JOIN clients ON quote_client_id = client_id WHERE quote_id = $quote_id AND quote_url_key = '$url_key'");

    if (mysqli_num_rows($sql) == 1) {
        $row = mysqli_fetch_array($sql);
        $quote_prefix = sanitizeInput($row['quote_prefix']);
        $quote_number = intval($row['quote_number']);
        $client_name = sanitizeInput($row['client_name']);
        $client_id = intval($row['client_id']);

        mysqli_query($mysqli, "UPDATE quotes SET quote_status = 'Accepted' WHERE quote_id = $quote_id");
        mysqli_query($mysqli, "INSERT INTO history SET history_status = 'Accepted', history_description = 'Client accepted Quote!', history_quote_id = $quote_id");
        // Notification
        appNotify("Quote Accepted", "Quote $quote_prefix$quote_number has been accepted by $client_name", "quote.php?quote_id=$quote_id", $client_id);


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

    // Select only the necessary fields
    $sql = mysqli_query($mysqli, "SELECT quote_prefix, quote_number, client_name, client_id FROM quotes LEFT JOIN clients ON quote_client_id = client_id WHERE quote_id = $quote_id AND quote_url_key = '$url_key'");

    if (mysqli_num_rows($sql) == 1) {
        $row = mysqli_fetch_array($sql);
        $quote_prefix = sanitizeInput($row['quote_prefix']);
        $quote_number = intval($row['quote_number']);
        $client_name = sanitizeInput($row['client_name']);
        $client_id = intval($row['client_id']);

        mysqli_query($mysqli, "UPDATE quotes SET quote_status = 'Declined' WHERE quote_id = $quote_id");
        mysqli_query($mysqli, "INSERT INTO history SET history_status = 'Declined', history_description = 'Client declined Quote!', history_quote_id = $quote_id");
        // Notification
        appNotify("Quote Declined", "Quote $quote_prefix$quote_number has been declined by $client_name", "quote.php?quote_id=$quote_id", $client_id);

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

    // Select only the necessary fields
    $sql = mysqli_query($mysqli, "SELECT ticket_id FROM tickets WHERE ticket_id = $ticket_id AND ticket_url_key = '$url_key' AND ticket_resolved_at IS NOT NULL AND ticket_closed_at IS NULL");

    if (mysqli_num_rows($sql) == 1) {
        // Update the ticket
        mysqli_query($mysqli, "UPDATE tickets SET ticket_status = 2, ticket_resolved_at = NULL WHERE ticket_id = $ticket_id AND ticket_url_key = '$url_key'");
        // Add reply
        mysqli_query($mysqli, "INSERT INTO ticket_replies SET ticket_reply = 'Ticket reopened by client (guest URL).', ticket_reply_type = 'Internal', ticket_reply_by = 0, ticket_reply_ticket_id = $ticket_id");
        // Logging
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

    // Select only the necessary fields
    $sql = mysqli_query($mysqli, "SELECT ticket_id FROM tickets WHERE ticket_id = $ticket_id AND ticket_url_key = '$url_key' AND ticket_resolved_at IS NOT NULL AND ticket_closed_at IS NULL");

    if (mysqli_num_rows($sql) == 1) {
        // Update the ticket
        mysqli_query($mysqli, "UPDATE tickets SET ticket_status = 5, ticket_closed_at = NOW() WHERE ticket_id = $ticket_id AND ticket_url_key = '$url_key'");
        // Add reply
        mysqli_query($mysqli, "INSERT INTO ticket_replies SET ticket_reply = 'Ticket closed by client (guest URL).', ticket_reply_type = 'Internal', ticket_reply_by = 0, ticket_reply_ticket_id = $ticket_id");
        // Logging
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

    // Select only the necessary fields
    $sql = mysqli_query($mysqli, "SELECT ticket_id FROM tickets WHERE ticket_id = $ticket_id AND ticket_url_key = '$url_key' AND ticket_closed_at IS NOT NULL");

    if (mysqli_num_rows($sql) == 1) {
        // Add feedback
        mysqli_query($mysqli, "UPDATE tickets SET ticket_feedback = '$feedback' WHERE ticket_id = $ticket_id AND ticket_url_key = '$url_key'");
        // Notify on bad feedback
        if ($feedback == "Bad") {
            appNotify("Feedback", "Guest rated ticket ID $ticket_id as bad");
        }

        $_SESSION['alert_message'] = "Feedback recorded - thank you";
        header("Location: " . $_SERVER["HTTP_REFERER"]);
        customAction('ticket_feedback', $ticket_id);
    } else {
        echo "Invalid!!";
    }
}
?>
