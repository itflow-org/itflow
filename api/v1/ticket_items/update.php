<?php

require_once('../validate_api_key.php');
require_once('../require_post_method.php');

$ticket_item_id = intval($_POST['ticket_item_id'] ?? 0);
$invoiced = intval($_POST['invoiced'] ?? 0);
$invoiced_ref = sanitizeInput($_POST['invoiced_ref'] ?? '');

if ($ticket_item_id === 0) {
    echo json_encode(['error' => 'ticket_item_id is required']);
    exit;
}

$client_filter = "";
if (isset($client_id) && intval($client_id) > 0) {
    $client_filter = "AND tickets.ticket_client_id = " . intval($client_id);
}

$sql_check = mysqli_query(
    $mysqli,
    "SELECT ticket_items.ticket_item_ticket_id
    FROM ticket_items
    INNER JOIN tickets ON ticket_items.ticket_item_ticket_id = tickets.ticket_id
    WHERE ticket_items.ticket_item_id = $ticket_item_id
    $client_filter
    LIMIT 1"
);

if (mysqli_num_rows($sql_check) !== 1) {
    echo json_encode(['error' => 'not found or not permitted']);
    exit;
}

$invoiced_ref_sql = $invoiced_ref !== '' ? "'" . $invoiced_ref . "'" : "NULL";

if ($invoiced === 1) {
    mysqli_query(
        $mysqli,
        "UPDATE ticket_items SET
            ticket_item_invoiced_at = NOW(),
            ticket_item_invoiced_ref = $invoiced_ref_sql,
            ticket_item_updated_at = NOW()
        WHERE ticket_item_id = $ticket_item_id"
    );
} else {
    mysqli_query(
        $mysqli,
        "UPDATE ticket_items SET
            ticket_item_invoiced_at = NULL,
            ticket_item_invoiced_ref = $invoiced_ref_sql,
            ticket_item_updated_at = NOW()
        WHERE ticket_item_id = $ticket_item_id"
    );
}

echo json_encode(['success' => true]);
