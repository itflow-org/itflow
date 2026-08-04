<?php

require_once '../validate_api_key.php';

require_once '../require_get_method.php';


// Expenses aren't client-scoped; access is gated by module_financial in enforce_api_rbac.php

if (isset($_GET['expense_id'])) {
    // Expense via ID (single)

    $id = intval($_GET['expense_id']);
    $sql = mysqli_query($mysqli, "SELECT * FROM expenses WHERE expense_id = '$id'");

} else {
    // All expenses

    $sql = mysqli_query($mysqli, "SELECT * FROM expenses ORDER BY expense_id LIMIT $limit OFFSET $offset");
}

// Output
require_once "../read_output.php";

