<?php

/*
 * ITFlow - GET/POST request handler for revenue
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['add_revenue'])) {

    enforceUserPermission('module_sales', 2);

    $date = sanitizeInput($_POST['date']);
    $amount = floatval($_POST['amount']);
    $account = intval($_POST['account']);
    $category = intval($_POST['category']);
    $payment_method = sanitizeInput($_POST['payment_method']);
    $description = sanitizeInput($_POST['description']);
    $reference = sanitizeInput($_POST['reference']);

    mysqli_query($mysqli,"INSERT INTO revenues SET revenue_date = '$date', revenue_amount = $amount, revenue_currency_code = '$session_company_currency', revenue_payment_method = '$payment_method', revenue_reference = '$reference', revenue_description = '$description', revenue_category_id = $category, revenue_account_id = $account");

    $revenue_id = mysqli_insert_id($mysqli);

    // Logging
    logAction("Revenue", "Create", "$session_name added revenue $description", 0, $revenue_id);

    $_SESSION['alert_message'] = "Revenue added";

    redirect();

}

if (isset($_POST['edit_revenue'])) {

    enforceUserPermission('module_sales', 2);

    $revenue_id = intval($_POST['revenue_id']);
    $date = sanitizeInput($_POST['date']);
    $amount = floatval($_POST['amount']);
    $account = intval($_POST['account']);
    $category = intval($_POST['category']);
    $payment_method = sanitizeInput($_POST['payment_method']);
    $description = sanitizeInput($_POST['description']);
    $reference = sanitizeInput($_POST['reference']);

    mysqli_query($mysqli,"UPDATE revenues SET revenue_date = '$date', revenue_amount = $amount, revenue_payment_method = '$payment_method', revenue_reference = '$reference', revenue_description = '$description', revenue_category_id = $category, revenue_account_id = $account WHERE revenue_id = $revenue_id");

    // Logging
    logAction("Revenue", "Edit", "$session_name edited revenue $description", 0, $revenue_id);

    $_SESSION['alert_message'] = "Revenue edited";

    redirect();

}

if (isset($_GET['delete_revenue'])) {

    enforceUserPermission('module_sales', 3);

    $revenue_id = intval($_GET['delete_revenue']);

    // Get Revenue Details
    $sql = mysqli_query($mysqli,"SELECT revenue_description FROM revenues WHERE revenue_id = $revenue_id");
    $row = mysqli_fetch_array($sql);
    $revenue_description = sanitizeInput($row['revenue_description']);

    mysqli_query($mysqli,"DELETE FROM revenues WHERE revenue_id = $revenue_id");

    // Logging
    logAction("Revenue", "Delete", "$session_name deleted revenue $revenue_description");

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "Revenue removed";

    redirect();

}
