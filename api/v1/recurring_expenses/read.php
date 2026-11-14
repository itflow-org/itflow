<?php
/*
 * API - Recurring Expenses - Read
 * GET /api/v1/recurring_expenses/read.php
 *
 * Bills that come round - a monthly hosting bill, a yearly domain - each with
 * its vendor's, category's and account's names, when it is next due, and whether
 * it runs (recurring_expense_status 1) or is paused or ended (0).
 *
 *   recurring_expense_id  optional - Just this one
 *   client_id             optional - Just this client's; 0 for the business's own
 *   limit, offset         optional - Paging
 */

require_once '../validate_api_key.php';
require_once '../require_get_method.php';

$where = 'recurring_expense_archived_at IS NULL' . (isset($_GET['recurring_expense_id']) ? ' AND recurring_expense_id = ' . intval($_GET['recurring_expense_id']) : '');

$sql = mysqli_query($mysqli, "SELECT recurring_expenses.*, vendor_name, category_name, account_name, client_name FROM recurring_expenses
    LEFT JOIN vendors ON vendor_id = recurring_expense_vendor_id
    LEFT JOIN categories ON category_id = recurring_expense_category_id
    LEFT JOIN accounts ON account_id = recurring_expense_account_id
    LEFT JOIN clients ON client_id = recurring_expense_client_id
    WHERE $where " . apiClientScopeSql('recurring_expense_client_id') . " ORDER BY recurring_expense_next_date, recurring_expense_id LIMIT $limit OFFSET $offset");

// Output
require_once "../read_output.php";
