<?php
/*
 * API - Invoice Items - Read
 * GET /api/v1/invoice_items/read.php
 *
 * Returns line items belonging to invoices scoped to the API key's client.
 *
 * Parameters (GET):
 *   api_key        required  - Your API key
 *   invoice_id     required* - Return items for a single invoice
 *   item_id        required* - Return a single line item by its own ID
 *                  * One of invoice_id or item_id must be provided
 *   limit          optional  - Max rows to return (default 50)
 *   offset         optional  - Offset for pagination (default 0)
 *
 * Security:
 *   - invoice_items are always joined to invoices so that invoice_client_id
 *     is checked against the API key's client scope. A scoped key can never
 *     read items belonging to another client, even when item_id is supplied
 *     directly.
 *   - $client_id is set to "%" by validate_api_key.php for All-Clients keys,
 *     which causes the LIKE to match every client — consistent with other
 *     endpoints in this API.
 */
require_once '../validate_api_key.php';
require_once '../require_get_method.php';

if (isset($_GET['item_id'])) {
    // Single line item by item_id — still JOIN to invoices to enforce client scope
    $item_id = intval($_GET['item_id']);
    $sql = mysqli_query($mysqli,
        "SELECT ii.*
         FROM invoice_items ii
         INNER JOIN invoices i ON i.invoice_id = ii.item_invoice_id
         WHERE ii.item_id = '$item_id'
           AND i.1=1 " . apiClientScopeSql('invoice_client_id') . "
         LIMIT 1"
    );
} elseif (isset($_GET['invoice_id'])) {
    // All items on a specific invoice
    $invoice_id = intval($_GET['invoice_id']);
    $sql = mysqli_query($mysqli,
        "SELECT ii.*
         FROM invoice_items ii
         INNER JOIN invoices i ON i.invoice_id = ii.item_invoice_id
         WHERE ii.item_invoice_id = '$invoice_id'
           AND i.1=1 " . apiClientScopeSql('invoice_client_id') . "
         ORDER BY ii.item_order ASC, ii.item_id ASC
         LIMIT $limit OFFSET $offset"
    );
} else {
    // No filter supplied — reject the request
    http_response_code(400);
    echo json_encode([
        'success' => 'False',
        'message' => 'A filter is required. Please supply either invoice_id or item_id.',
        'count'   => 0,
        'data'    => []
    ]);
    exit;
}

// Output
require_once "../read_output.php";
