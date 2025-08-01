<?php

/*
 * ITFlow - GET/POST request handler for transfers (accounting)
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['add_transfer'])) {

    enforceUserPermission('module_financial', 2);

    require_once 'transfer_model.php';

    // Get Source Account Name for logging
    $sql = mysqli_query($mysqli,"SELECT account_name, account_currency_code FROM accounts WHERE account_id = $account_from");
    $row = mysqli_fetch_array($sql);
    $source_account_name = sanitizeInput($row['account_name']);
    $account_currency_code = sanitizeInput($row['account_currency_code']);

    // Get Destination Account Name for logging
    $sql = mysqli_query($mysqli,"SELECT account_name FROM accounts WHERE account_id = $account_to");
    $row = mysqli_fetch_array($sql);
    $destination_account_name = sanitizeInput($row['account_name']);

    mysqli_query($mysqli,"INSERT INTO expenses SET expense_date = '$date', expense_amount = $amount, expense_currency_code = '$session_company_currency', expense_vendor_id = 0, expense_category_id = 0, expense_account_id = $account_from");
    $expense_id = mysqli_insert_id($mysqli);

    mysqli_query($mysqli,"INSERT INTO revenues SET revenue_date = '$date', revenue_amount = $amount, revenue_currency_code = '$session_company_currency', revenue_account_id = $account_to, revenue_category_id = 0");
    $revenue_id = mysqli_insert_id($mysqli);

    mysqli_query($mysqli,"INSERT INTO transfers SET transfer_expense_id = $expense_id, transfer_revenue_id = $revenue_id, transfer_method = '$transfer_method', transfer_notes = '$notes'");

    $transfer_id = mysqli_insert_id($mysqli);

    // Logging
    logAction("Account Transfer", "Create", "$session_name transferred " . numfmt_format_currency($currency_format, $amount, $account_currency_code) . " from account $source_account_name to $destination_account_name", 0, $transfer_id);

    $_SESSION['alert_message'] = "Transferred <strong>" . numfmt_format_currency($currency_format, $amount, $account_currency_code) . "</strong> from <strong>$source_account_name</strong> to <strong>$destination_account_name</strong>";

    redirect();

}

if (isset($_POST['edit_transfer'])) {

    enforceUserPermission('module_financial', 2);

    require_once 'transfer_model.php';


    $transfer_id = intval($_POST['transfer_id']);
    $expense_id = intval($_POST['expense_id']);
    $revenue_id = intval($_POST['revenue_id']);

    mysqli_query($mysqli,"UPDATE expenses SET expense_date = '$date', expense_amount = $amount, expense_account_id = $account_from WHERE expense_id = $expense_id");

    mysqli_query($mysqli,"UPDATE revenues SET revenue_date = '$date', revenue_amount = $amount, revenue_account_id = $account_to WHERE revenue_id = $revenue_id");

    mysqli_query($mysqli,"UPDATE transfers SET transfer_method = '$transfer_method', transfer_notes = '$notes' WHERE transfer_id = $transfer_id");

    // Logging
    logAction("Account Transfer", "Edit", "$session_name edited transfer", 0, $transfer_id);

    $_SESSION['alert_message'] = "Transfer edited";

    redirect();

}

if (isset($_GET['delete_transfer'])) {

    enforceUserPermission('module_financial', 3);

    $transfer_id = intval($_GET['delete_transfer']);

    // Query the transfer ID to get the Payment and Expense IDs, so we can delete those as well
    $row = mysqli_fetch_array(mysqli_query($mysqli,"SELECT * FROM transfers WHERE transfer_id = $transfer_id"));
    $expense_id = intval($row['transfer_expense_id']);
    $revenue_id = intval($row['transfer_revenue_id']);

    mysqli_query($mysqli,"DELETE FROM expenses WHERE expense_id = $expense_id");

    mysqli_query($mysqli,"DELETE FROM revenues WHERE revenue_id = $revenue_id");

    mysqli_query($mysqli,"DELETE FROM transfers WHERE transfer_id = $transfer_id");

    // Logging
    logAction("Account Transfer", "Delete", "$session_name deleted transfer");

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "Transfer deleted";

    redirect();

}
