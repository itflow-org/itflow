<?php

require_once '../validate_api_key.php';
require_once '../require_post_method.php';

/*
 * Update an expense - only the fields you send change (expenseSave(), as the
 * Edit form). client_id is the expense's client now; expense_client_id moves it
 * to another (0: overhead). Its tax is worked out again when its amount changes.
 */

require_once "../../../includes/load_global_settings.php";
require_once "../../../includes/load_company_settings.php";

// Parse ID
$expense_id = intval($_POST['expense_id'] ?? 0);

// Default
$update_count = false;

if (!empty($expense_id)) {

    $expense_row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT * FROM expenses WHERE expense_id = $expense_id AND expense_client_id = $client_id LIMIT 1"));

    if ($expense_row) {

        require_once 'expense_model.php';

        // Its client - another one must be one the user can write for
        $expense_in['client_id'] = $client_id;
        if (isset($_POST['expense_client_id'])) {
            $move_to = intval($_POST['expense_client_id']);
            if (!apiUserCanAccessClient($move_to)) {
                apiDeny("The user linked to this API key does not have access to the client the expense would move to.");
            }
            $expense_in['client_id'] = $move_to;
        }

        $error = '';
        if (!expenseSave($expense_in, $expense_id, $error)) {
            http_response_code(400);
            $return_arr['success'] = "False";
            $return_arr['message'] = $error . '.';
            echo json_encode($return_arr);
            exit();
        }

        // An expense saved as it was still counts as updated
        $update_count = 1;

        $row = expenseRow($expense_id, false);
        $label = escapeSql(expenseLabel($row));
        logAudit("Expense", "Edit", "Edited the expense $label via API ($api_key_name)", intval($row['expense_client_id']), $expense_id);
        logAudit("API", "Success", "Edited the expense $label via API ($api_key_name)", intval($row['expense_client_id']));
    }
}

// Output
require_once '../update_output.php';
