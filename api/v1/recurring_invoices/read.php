<?php

/*
 * API - Recurring Invoices - Read
 * GET /api/v1/recurring_invoices/read.php
 *
 *   recurring_invoice_id  optional - Just this one, with its lines as items
 *   client_id             optional - Just this client's
 *   limit, offset         optional - Paging
 */

require_once '../validate_api_key.php';

require_once '../require_get_method.php';

if (isset($_GET['recurring_invoice_id'])) {
    $id = intval($_GET['recurring_invoice_id']);
    $sql = mysqli_query($mysqli, "SELECT * FROM recurring_invoices WHERE recurring_invoice_id = $id " . apiClientScopeSql('recurring_invoice_client_id'));
    if ($sql && ($row = mysqli_fetch_assoc($sql))) {
        $row['items'] = billingItems('recurring', $id);
        echo json_encode(['success' => 'True', 'count' => 1, 'data' => [$row]]);
        exit();
    }
} else {
    $sql = mysqli_query($mysqli, "SELECT * FROM recurring_invoices WHERE 1=1 " . apiClientScopeSql('recurring_invoice_client_id') . " ORDER BY recurring_invoice_id LIMIT $limit OFFSET $offset");
}

// Output
require_once "../read_output.php";
