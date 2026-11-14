<?php
/*
 * API - Revenues - Read
 * GET /api/v1/revenues/read.php
 *
 * Other income - money in that isn't a payment on an invoice, such as a referral
 * fee or a refund from a supplier - newest first, with its category's and
 * account's names. Not the in side of transfers between your accounts.
 *
 *   revenue_id     optional - Just this one
 *   client_id      optional - Just this client's; 0 for those of no client
 *   limit, offset  optional - Paging
 */

require_once '../validate_api_key.php';
require_once '../require_get_method.php';

$where = 'revenue_archived_at IS NULL AND ' . incomeRevenueSql('revenue_id') . (isset($_GET['revenue_id']) ? ' AND revenue_id = ' . intval($_GET['revenue_id']) : '');

$sql = mysqli_query($mysqli, "SELECT revenues.*, category_name, account_name, client_name FROM revenues
    LEFT JOIN categories ON category_id = revenue_category_id
    LEFT JOIN accounts ON account_id = revenue_account_id
    LEFT JOIN clients ON client_id = revenue_client_id
    WHERE $where " . apiClientScopeSql('revenue_client_id') . " ORDER BY revenue_date DESC, revenue_id DESC LIMIT $limit OFFSET $offset");

// Output
require_once "../read_output.php";
