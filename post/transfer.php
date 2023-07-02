<?php

/*
 * ITFlow - GET/POST request handler for transfers (accounting)
 */

if (isset($_POST['add_transfer'])) {

    require_once('post/transfer_model.php');

    mysqli_query($mysqli,"INSERT INTO expenses SET expense_date = '$date', expense_amount = $amount, expense_currency_code = '$session_company_currency', expense_vendor_id = 0, expense_category_id = 0, expense_account_id = $account_from");
    $expense_id = mysqli_insert_id($mysqli);

    mysqli_query($mysqli,"INSERT INTO revenues SET revenue_date = '$date', revenue_amount = $amount, revenue_currency_code = '$session_company_currency', revenue_account_id = $account_to, revenue_category_id = 0");
    $revenue_id = mysqli_insert_id($mysqli);

    mysqli_query($mysqli,"INSERT INTO transfers SET transfer_expense_id = $expense_id, transfer_revenue_id = $revenue_id, transfer_notes = '$notes'");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Transfer', log_action = 'Create', log_description = '$date - $amount', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Transfer complete";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['edit_transfer'])) {

    require_once('post/transfer_model.php');

    $transfer_id = intval($_POST['transfer_id']);
    $expense_id = intval($_POST['expense_id']);
    $revenue_id = intval($_POST['revenue_id']);

    mysqli_query($mysqli,"UPDATE expenses SET expense_date = '$date', expense_amount = $amount, expense_account_id = $account_from WHERE expense_id = $expense_id");

    mysqli_query($mysqli,"UPDATE revenues SET revenue_date = '$date', revenue_amount = $amount, revenue_account_id = $account_to WHERE revenue_id = $revenue_id");

    mysqli_query($mysqli,"UPDATE transfers SET transfer_notes = '$notes' WHERE transfer_id = $transfer_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Transfer', log_action = 'Modifed', log_description = '$date - $amount', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Transfer modified";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_GET['delete_transfer'])) {
    $transfer_id = intval($_GET['delete_transfer']);

    //Query the transfer ID to get the Payment and Expense IDs, so we can delete those as well
    $row = mysqli_fetch_array(mysqli_query($mysqli,"SELECT * FROM transfers WHERE transfer_id = $transfer_id"));
    $expense_id = intval($row['transfer_expense_id']);
    $revenue_id = intval($row['transfer_revenue_id']);

    mysqli_query($mysqli,"DELETE FROM expenses WHERE expense_id = $expense_id");

    mysqli_query($mysqli,"DELETE FROM revenues WHERE revenue_id = $revenue_id");

    mysqli_query($mysqli,"DELETE FROM transfers WHERE transfer_id = $transfer_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Transfer', log_action = 'Delete', log_description = '$transfer_id', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Transfer deleted";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

