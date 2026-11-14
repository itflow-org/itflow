<?php
/*
 * API - Transfers - Read
 * GET /api/v1/transfers/read.php
 *
 * Money moved between your own accounts, newest first - paying off a card,
 * sweeping to savings, a processor's payout - with both accounts.
 *
 *   transfer_id    optional - Just this one
 *   account_id     optional - Just those out of or into this account
 *   limit, offset  optional - Paging
 */

require_once '../validate_api_key.php';
require_once '../require_get_method.php';

$where = 'transfer_archived_at IS NULL';
if (isset($_GET['transfer_id'])) {
    $where .= ' AND transfer_id = ' . intval($_GET['transfer_id']);
}
if (isset($_GET['account_id'])) {
    $account_id = intval($_GET['account_id']);
    $where .= " AND (expense_account_id = $account_id OR revenue_account_id = $account_id)";
}

$sql = mysqli_query($mysqli, "SELECT transfer_id, expense_date AS transfer_date, expense_amount AS transfer_amount, expense_currency_code AS transfer_currency_code,
        expense_account_id AS transfer_from_account_id, from_account.account_name AS transfer_from_account_name,
        revenue_account_id AS transfer_to_account_id, to_account.account_name AS transfer_to_account_name,
        transfer_method, transfer_notes, transfer_created_at, transfer_updated_at
    FROM transfers
    JOIN expenses ON expense_id = transfer_expense_id
    JOIN revenues ON revenue_id = transfer_revenue_id
    LEFT JOIN accounts AS from_account ON from_account.account_id = expense_account_id
    LEFT JOIN accounts AS to_account ON to_account.account_id = revenue_account_id
    WHERE $where ORDER BY expense_date DESC, transfer_id DESC LIMIT $limit OFFSET $offset");

// Output
require_once "../read_output.php";
