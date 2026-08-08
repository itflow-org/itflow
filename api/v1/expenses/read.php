<?php

require_once '../validate_api_key.php';

require_once '../require_get_method.php';


if (isset($_GET['expense_id'])) {
    // Expense via ID (single)

    $id = intval($_GET['expense_id']);
    $sql = mysqli_query($mysqli, "SELECT * FROM expenses WHERE expense_id = '$id' AND 1=1 " . apiClientScopeSql('expense_client_id') . "");

} else {
    // All expenses

    $sql = mysqli_query($mysqli, "SELECT * FROM expenses WHERE 1=1 " . apiClientScopeSql('expense_client_id') . " ORDER BY expense_id LIMIT $limit OFFSET $offset");
}

// Output
require_once "../read_output.php";

