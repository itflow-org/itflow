<?php

require_once '../validate_api_key.php';
require_once '../require_post_method.php';

// Delete an expense, as Delete does in the app - its receipt goes with it.
// The out side of a transfer, money paid back to an agent, and an expense
// cleared on a reconciled statement can't be deleted here.

// Parse ID
$expense_id = intval($_POST['expense_id'] ?? 0);

// Default
$delete_count = false;

if (!empty($expense_id) && mysqli_num_rows(mysqli_query($mysqli, "SELECT 1 FROM expenses WHERE expense_id = $expense_id AND expense_client_id = $client_id"))) {

    $error = '';
    $row = expenseDelete($expense_id, $error);
    if (!$row) {
        http_response_code(400);
        $return_arr['success'] = "False";
        $return_arr['message'] = $error . '.';
        echo json_encode($return_arr);
        exit();
    }

    $delete_count = 1;

    $label = escapeSql(expenseLabel($row));
    logAudit("Expense", "Delete", "Deleted the expense $label via API ($api_key_name)", $client_id);
    logAudit("API", "Success", "Deleted the expense $label via API ($api_key_name)", $client_id);
}

// Output
require_once '../delete_output.php';
