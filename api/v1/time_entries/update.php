<?php

require_once '../validate_api_key.php';
require_once '../require_post_method.php';

$raw_input = file_get_contents('php://input');
if (!empty($raw_input)) {
    $decoded_input = json_decode($raw_input, true);
    if (is_array($decoded_input)) {
        $_POST = array_merge($_POST, $decoded_input);
    }
}

$ticket_id     = isset($_POST['ticket_id']) ? intval($_POST['ticket_id']) : 0;
$ticket_status = array_key_exists('ticket_status', $_POST) ? intval($_POST['ticket_status']) : null;
$time_entry_id = isset($_POST['time_entry_id']) ? intval($_POST['time_entry_id']) : 0;

$update_count = false;

if ($time_entry_id && !$ticket_id) {
    $time_row = mysqli_fetch_assoc(
        mysqli_query(
            $mysqli,
            "SELECT ticket_reply_ticket_id FROM ticket_replies WHERE ticket_reply_id = '$time_entry_id' LIMIT 1"
        )
    );
    if ($time_row) {
        $ticket_id = (int) $time_row['ticket_reply_ticket_id'];
    }
}

if ($ticket_id && $ticket_status !== null) {
    $normalized_client_id = (string) $client_id;
    $client_filter = '';
    if ($normalized_client_id !== '%' && $normalized_client_id !== '' && $normalized_client_id !== '0') {
        $client_id_escaped = mysqli_real_escape_string($mysqli, $normalized_client_id);
        $client_filter = " AND ticket_client_id LIKE '$client_id_escaped'";
    }

    $ticket_sql = mysqli_query(
        $mysqli,
        "SELECT ticket_id, ticket_client_id, ticket_status
         FROM tickets
         WHERE ticket_id = '$ticket_id' $client_filter
         LIMIT 1"
    );

    if ($ticket_sql && mysqli_num_rows($ticket_sql) === 1) {
        $ticket           = mysqli_fetch_assoc($ticket_sql);
        $ticket_client_id = (int) $ticket['ticket_client_id'];
        $old_status       = (int) $ticket['ticket_status'];

        $set = [];
        $set[] = "ticket_status = '$ticket_status'";
        $set[] = "ticket_updated_at = NOW()";

        if ($ticket_status === 5 && $old_status !== 5) {
            $set[] = "ticket_closed_at = NOW()";
            $set[] = "ticket_resolved_at = NOW()";
        }

        $set_clause = implode(', ', $set);

        mysqli_query(
            $mysqli,
            "UPDATE tickets
             SET $set_clause
             WHERE ticket_id = '$ticket_id'
             LIMIT 1"
        );
        $update_count = mysqli_affected_rows($mysqli);

        if ($update_count > 0) {
            logAction(
                "Ticket",
                "Edit",
                "Ticket $ticket_id status changed to $ticket_status via time_entries API ($api_key_name)",
                $ticket_client_id
            );
            logAction(
                "API",
                "Success",
                "Updated ticket $ticket_id via time_entries API ($api_key_name)",
                $ticket_client_id
            );
        } else {
            logAction(
                "API",
                "Failure",
                "Update query touched 0 rows for ticket $ticket_id via time_entries API ($api_key_name)",
                $ticket_client_id
            );
        }
    } else {
        logAction(
            "API",
            "Failure",
            "Ticket lookup failed for ticket $ticket_id via time_entries API ($api_key_name)",
            0
        );
    }
}

require_once '../update_output.php';
