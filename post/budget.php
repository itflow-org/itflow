<?php

/*
 * ITFlow - GET/POST request handler for budget
 */

if (isset($_POST['create_budget'])) {

    $month = intval($_POST['month']);
    $year = intval($_POST['year']);
    $amount = floatval($_POST['amount']);
    $description = sanitizeInput($_POST['description']);
    $category = intval($_POST['category']);
    
    mysqli_query($mysqli,"INSERT INTO budget SET budget_month = $month, budget_year = $year, budget_amount = $amount, budget_description = '$description', budget_category_id = $category");

    $budget_id = mysqli_insert_id($mysqli);

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Budget', log_action = 'Create', log_description = '$description', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Budget created";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['edit_budget'])) {

    $budget_id = intval($_POST['budget_id']);
    $month = intval($_POST['month']);
    $year = intval($_POST['year']);
    $amount = floatval($_POST['amount']);
    $description = sanitizeInput($_POST['description']);
    $category = intval($_POST['category']);

    mysqli_query($mysqli,"UPDATE budget SET budget_month = $month, budget_year = $year, budget_amount = $amount, budget_description = '$description', budget_category_id = $category WHERE budget_id = $budget_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Budget', log_action = 'Edit', log_description = '$description', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Budget edited";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_GET['delete_budget'])) {
    $budget_id = intval($_GET['delete_budget']);

    mysqli_query($mysqli,"DELETE FROM budget WHERE budget_id = $budget_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Budget', log_action = 'Delete', log_description = '$budget_id', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "Budget deleted";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}
