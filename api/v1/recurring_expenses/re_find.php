<?php

// The recurring expense a request names - of a client the key's user can reach, and
// client_id's when it is sent - as $re_row, or null.

if (!function_exists('apiDeny')) {
    http_response_code(404);
    exit();
}

$recurring_expense_id = intval($_POST['recurring_expense_id'] ?? 0);
$re_row = $recurring_expense_id ? recurringExpenseRow($recurring_expense_id) : null;
if ($re_row && $client_id && intval($re_row['recurring_expense_client_id']) !== $client_id) {
    $re_row = null;
}
