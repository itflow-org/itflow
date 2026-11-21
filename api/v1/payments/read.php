<?php
/*
 * API - Payments - Read
 * GET /api/v1/payments/read.php
 *
 * Payments on invoices - each with its invoice's reference and client, and the
 * name of the account it went into. Payments need Financial, with Sales at Read
 * to see the invoices.
 *
 *   payment_id     optional - Just this payment
 *   invoice_id     optional - Just this invoice's payments
 *   client_id      optional - Just this client's
 *   limit, offset  optional - Paging
 */

require_once '../validate_api_key.php';
require_once '../require_get_method.php';

if (intval(lookupUserPermission('module_sales')) < 1) {
    apiDeny(apiPermissionText('module_sales', 1));
}

$where = '1 = 1';
if (isset($_GET['payment_id'])) {
    $where .= ' AND payment_id = ' . intval($_GET['payment_id']);
}
if (isset($_GET['invoice_id'])) {
    $where .= ' AND payment_invoice_id = ' . intval($_GET['invoice_id']);
}

$sql = mysqli_query($mysqli, "SELECT payments.*, invoice_prefix, invoice_number, invoice_client_id, account_name
    FROM payments
    JOIN invoices ON invoice_id = payment_invoice_id
    LEFT JOIN accounts ON account_id = payment_account_id
    WHERE $where " . apiClientScopeSql('invoice_client_id') . " ORDER BY payment_id LIMIT $limit OFFSET $offset");

// Output
require_once "../read_output.php";
