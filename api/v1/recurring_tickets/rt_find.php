<?php

// The recurring ticket a request names (recurring_ticket_id) - of a client the key's
// user can reach, and client_id's when it is sent - as $rt_row, or null.

if (!function_exists('apiDeny')) {
    http_response_code(404);
    exit();
}

$recurring_ticket_id = intval($_POST['recurring_ticket_id'] ?? 0);
$rt_row = $recurring_ticket_id ? recurringTicketRow($recurring_ticket_id) : null;
if ($rt_row && (!apiUserCanAccessClient($rt_row['recurring_ticket_client_id']) || ($client_id && intval($rt_row['recurring_ticket_client_id']) !== $client_id))) {
    $rt_row = null;
}
if ($rt_row) {
    $client_id = intval($rt_row['recurring_ticket_client_id']);
}
