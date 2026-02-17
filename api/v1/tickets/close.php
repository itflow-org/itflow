<?php

require_once '../validate_api_key.php';
require_once '../require_post_method.php';

function getTicketIdFromAnyInput(): int
{
    if (isset($_POST['ticket_id'])) {
        return intval($_POST['ticket_id']);
    }

    if (isset($_POST['ticket_ids'])) {
        $v = $_POST['ticket_ids'];
        if (is_array($v) && isset($v[0])) {
            return intval($v[0]);
        }
        if (is_string($v)) {
            $parts = preg_split('/[,\s]+/', trim($v));
            if (isset($parts[0]) && $parts[0] !== '') {
                return intval($parts[0]);
            }
        }
    }

    $raw = file_get_contents('php://input');
    if (is_string($raw) && trim($raw) !== '') {
        $j = json_decode($raw, true);
        if (is_array($j)) {
            if (isset($j['ticket_id'])) {
                return intval($j['ticket_id']);
            }
            if (isset($j['ticket_ids'])) {
                $v = $j['ticket_ids'];
                if (is_array($v) && isset($v[0])) {
                    return intval($v[0]);
                }
                if (is_string($v)) {
                    $parts = preg_split('/[,\s]+/', trim($v));
                    if (isset($parts[0]) && $parts[0] !== '') {
                        return intval($parts[0]);
                    }
                }
            }
            if (isset($j['parameters']) && is_array($j['parameters']) && isset($j['parameters'][0]) && is_array($j['parameters'][0])) {
                $p0 = $j['parameters'][0];
                if (isset($p0['ticket_id'])) {
                    return intval($p0['ticket_id']);
                }
                if (isset($p0['ticket_ids']) && is_array($p0['ticket_ids']) && isset($p0['ticket_ids'][0])) {
                    return intval($p0['ticket_ids'][0]);
                }
            }
        }
    }

    return 0;
}

function getClosedByFromAnyInput(): int
{
    if (isset($_POST['closed_by'])) {
        return intval($_POST['closed_by']);
    }
    if (isset($_POST['user_id'])) {
        return intval($_POST['user_id']);
    }

    $raw = file_get_contents('php://input');
    if (is_string($raw) && trim($raw) !== '') {
        $j = json_decode($raw, true);
        if (is_array($j)) {
            if (isset($j['closed_by'])) {
                return intval($j['closed_by']);
            }
            if (isset($j['user_id'])) {
                return intval($j['user_id']);
            }
            if (isset($j['parameters']) && is_array($j['parameters']) && isset($j['parameters'][0]) && is_array($j['parameters'][0])) {
                $p0 = $j['parameters'][0];
                if (isset($p0['closed_by'])) {
                    return intval($p0['closed_by']);
                }
                if (isset($p0['user_id'])) {
                    return intval($p0['user_id']);
                }
            }
        }
    }

    return 0;
}

$ticket_id = getTicketIdFromAnyInput();
$closed_by = getClosedByFromAnyInput();

$update_count = false;

if ($ticket_id > 0) {

    $client_filter = '';
    if ((int) $client_id > 0) {
        $client_filter = " AND ticket_client_id = " . intval($client_id);
    }

    $ticket_sql = mysqli_query($mysqli, "SELECT ticket_id, ticket_client_id, ticket_prefix, ticket_number, ticket_first_response_at, ticket_resolved_at, ticket_closed_at, ticket_status FROM tickets WHERE ticket_id = " . intval($ticket_id) . " $client_filter LIMIT 1");

    if ($ticket_sql && mysqli_num_rows($ticket_sql) === 1) {

        $ticket_row = mysqli_fetch_assoc($ticket_sql);

        $ticket_id = intval($ticket_row['ticket_id']);
        $ticket_client_id = intval($ticket_row['ticket_client_id']);
        $ticket_prefix = sanitizeInput($ticket_row['ticket_prefix']);
        $ticket_number = intval($ticket_row['ticket_number']);
        $ticket_first_response_at = sanitizeInput($ticket_row['ticket_first_response_at']);
        $ticket_resolved_at = sanitizeInput($ticket_row['ticket_resolved_at']);
        $ticket_closed_at = sanitizeInput($ticket_row['ticket_closed_at']);

        if (empty($ticket_first_response_at)) {
            mysqli_query($mysqli, "UPDATE tickets SET ticket_first_response_at = NOW() WHERE ticket_id = $ticket_id $client_filter LIMIT 1");
        }

        if (empty($ticket_resolved_at)) {
            mysqli_query($mysqli, "UPDATE tickets SET ticket_resolved_at = NOW() WHERE ticket_id = $ticket_id $client_filter LIMIT 1");
        }

        if (empty($ticket_closed_at)) {
            $update_sql = mysqli_query($mysqli, "UPDATE tickets SET ticket_status = 5, ticket_closed_at = NOW(), ticket_closed_by = " . intval($closed_by) . " WHERE ticket_id = $ticket_id $client_filter LIMIT 1");
        } else {
            $update_sql = mysqli_query($mysqli, "UPDATE tickets SET ticket_status = 5, ticket_closed_by = " . intval($closed_by) . " WHERE ticket_id = $ticket_id $client_filter LIMIT 1");
        }

        if ($update_sql) {
            $update_count = mysqli_affected_rows($mysqli);
            logAction("Ticket", "Closed", "$ticket_prefix$ticket_number ticket via API ($api_key_name)", $ticket_client_id, $ticket_id);
            logAction("API", "Success", "Closed ticket $ticket_prefix$ticket_number via API ($api_key_name)", $ticket_client_id);
            customAction('ticket_close', $ticket_id);
        }
    }
}

require_once '../update_output.php';
