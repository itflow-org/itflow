<?php

/*
 * ITFlow - GET/POST request handler for budget
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");


if (isset($_POST['save_budget'])) {

    enforceUserPermission('module_financial', 2);

    validateCSRFToken($_POST['csrf_token']);

    $budgets = $_POST['budget'];
    $year = intval($_POST['year']);

    foreach ($budgets as $category_id => $months) {
        foreach ($months as $month => $amount) {
            $amount = (int)$amount;

            // Check if budget exists
            $query = "SELECT * FROM budget WHERE budget_category_id = $category_id AND budget_month = $month AND budget_year = $year";
            $result = mysqli_query($mysqli, $query);
            if (mysqli_num_rows($result) > 0) {
                // Update existing budget
                $query = "UPDATE budget SET budget_amount = $amount WHERE budget_category_id = $category_id AND budget_month = $month AND budget_year = $year";
            } else {
                // Insert new budget
                $query = "INSERT INTO budget SET budget_category_id = $category_id, budget_month = $month, budget_year = $year, budget_amount = $amount";
            }
            mysqli_query($mysqli, $query);
        }
    }

    // Logging
    logAction("Budget", "Edit", "$session_name edited the budget for $year");

    $_SESSION['alert_message'] = "Budget Updated for $year";

    header("Location: " . $_SERVER["HTTP_REFERER"]);
    
    exit;
}

if (isset($_POST['delete_budget'])) {

    enforceUserPermission('module_financial', 3);

    validateCSRFToken($_POST['csrf_token']);

    $year = intval($_POST['year']);

    mysqli_query($mysqli,"DELETE FROM budget WHERE budget_year = $year");

    // Logging
    logAction("Budget", "Delete", "$session_name deleted the budget for $year");

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "Budget deleted for $year";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}
