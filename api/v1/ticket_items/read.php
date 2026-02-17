<?php

require_once('../validate_api_key.php');
require_once('../require_get_method.php');

$ticket_id = intval($_GET['ticket_id'] ?? 0);

if ($ticket_id === 0) {
    echo json_encode(['error' => 'ticket_id is required']);
    exit;
}

$client_filter = "";
if (isset($client_id) && intval($client_id) > 0) {
    $client_filter = "AND tickets.ticket_client_id = " . intval($client_id);
}

$sql = mysqli_query(
    $mysqli,
    "SELECT
        ticket_items.ticket_item_id,
        ticket_items.ticket_item_ticket_id,
        ticket_items.ticket_item_product_id,
        ticket_items.ticket_item_name,
        ticket_items.ticket_item_description,
        ticket_items.ticket_item_quantity,
        ticket_items.ticket_item_unit_price,
        ticket_items.ticket_item_tax_id,
        ticket_items.ticket_item_billable,
        ticket_items.ticket_item_invoiced_at,
        ticket_items.ticket_item_invoiced_ref,
        ticket_items.ticket_item_created_at,
        ticket_items.ticket_item_updated_at
    FROM ticket_items
    INNER JOIN tickets ON ticket_items.ticket_item_ticket_id = tickets.ticket_id
    WHERE ticket_items.ticket_item_ticket_id = $ticket_id
    $client_filter
    ORDER BY ticket_items.ticket_item_id DESC"
);

require_once('../read_output.php');
