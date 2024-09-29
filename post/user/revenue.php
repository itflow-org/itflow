<?php

/*
 * ITFlow - GET/POST request handler for revenue
 */

if (isset($_POST['add_revenue'])) {

    $date = sanitizeInput($_POST['date']);
    $amount = floatval($_POST['amount']);
    $currency_code = sanitizeInput($_POST['currency_code']);
    $account = intval($_POST['account']);
    $category = intval($_POST['category']);
    $payment_method = sanitizeInput($_POST['payment_method']);
    $description = sanitizeInput($_POST['description']);
    $reference = sanitizeInput($_POST['reference']);

    mysqli_query($mysqli,"INSERT INTO revenues SET revenue_date = '$date', revenue_amount = $amount, revenue_currency_code = '$currency_code', revenue_payment_method = '$payment_method', revenue_reference = '$reference', revenue_description = '$description', revenue_category_id = $category, revenue_account_id = $account");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Revenue', log_action = 'Create', log_description = '$date - $amount', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Revenue added!";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['edit_revenue'])) {

    $revenue_id = intval($_POST['revenue_id']);
    $date = sanitizeInput($_POST['date']);
    $amount = floatval($_POST['amount']);
    $currency_code = sanitizeInput($_POST['currency_code']);
    $account = intval($_POST['account']);
    $category = intval($_POST['category']);
    $payment_method = sanitizeInput($_POST['payment_method']);
    $description = sanitizeInput($_POST['description']);
    $reference = sanitizeInput($_POST['reference']);

    mysqli_query($mysqli,"UPDATE revenues SET revenue_date = '$date', revenue_amount = $amount, revenue_currency_code = '$currency_code', revenue_payment_method = '$payment_method', revenue_reference = '$reference', revenue_description = '$description', revenue_category_id = $category, revenue_account_id = $account WHERE revenue_id = $revenue_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Revenue', log_action = 'Modify', log_description = '$revenue_id', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Revenue modified!";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_GET['delete_revenue'])) {
    $revenue_id = intval($_GET['delete_revenue']);

    mysqli_query($mysqli,"DELETE FROM revenues WHERE revenue_id = $revenue_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Revenue', log_action = 'Delete', log_description = '$revenue_id', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Revenue deleted";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

