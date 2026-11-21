<?php

require_once '../validate_api_key.php';
require_once '../require_post_method.php';

/*
 * Record an expense - the same rules as the New Expense form (expenseSave()):
 * a date, an amount, one of your vendors and expense categories, and the
 * account it was paid from - or "personal" with expense_paid_by, the agent
 * who paid it. client_id files it against a client (0 or none: overhead).
 * expense_refund_of_id records money back on an earlier expense.
 */

require_once "../../../includes/load_global_settings.php";
// The company's currency, for an expense paid personally or from an account with none
require_once "../../../includes/load_company_settings.php";

$insert_id = false;

$expense_row = null;
require_once 'expense_model.php';

$expense_in['client_id'] = $client_id;
if (isset($_POST['expense_refund_of_id'])) {
    $expense_in['refund_of'] = intval($_POST['expense_refund_of_id']);
}

// A refund is of an expense the user can see
if (!empty($expense_in['refund_of']) && !mysqli_num_rows(mysqli_query($mysqli, "SELECT 1 FROM expenses WHERE expense_id = " . intval($expense_in['refund_of']) . " " . apiClientScopeSql('expense_client_id')))) {
    $expense_in['refund_of'] = -1;
}

$error = '';
$expense_id = expenseSave($expense_in, 0, $error);
if (!$expense_id) {
    http_response_code(400);
    $return_arr['success'] = "False";
    $return_arr['message'] = $error . '.';
    echo json_encode($return_arr);
    exit();
}

$insert_id = $expense_id;

$row = expenseRow($expense_id, false);
$label = escapeSql(expenseLabel($row));
$refund = intval($row['expense_refund_of_id']) > 0;
logAudit("Expense", $refund ? "Refund" : "Create", ($refund ? 'Recorded a refund from ' : 'Added the expense ') . "$label via API ($api_key_name)", $client_id, $expense_id);
logAudit("API", "Success", ($refund ? 'Recorded a refund from ' : 'Added the expense ') . "$label via API ($api_key_name)", $client_id);

// Output
require_once '../create_output.php';
