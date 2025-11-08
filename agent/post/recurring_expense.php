<?php

/*
 * ITFlow - GET/POST request handler for recurring expenses
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['create_recurring_expense'])) {

    $frequency = intval($_POST['frequency']);
    $day = intval($_POST['day']);
    $month = intval($_POST['month']);
    $amount =  floatval(str_replace(',', '', $_POST['amount']));
    $account = intval($_POST['account']);
    $vendor = intval($_POST['vendor']);
    $client_id = intval($_POST['client']);
    $category = intval($_POST['category']);
    $description = sanitizeInput($_POST['description']);
    $reference = sanitizeInput($_POST['reference']);

    $year = date('Y');
    if (strtotime("$year-$month-$day") < time()) {
        $year++;
    }
    $start_date = "$year-$month-$day";

    mysqli_query($mysqli,"INSERT INTO recurring_expenses SET recurring_expense_frequency = $frequency, recurring_expense_day = $day, recurring_expense_month = $month, recurring_expense_next_date = '$start_date', recurring_expense_description = '$description', recurring_expense_reference = '$reference', recurring_expense_amount = $amount, recurring_expense_currency_code = '$session_company_currency', recurring_expense_vendor_id = $vendor, recurring_expense_client_id = $client_id, recurring_expense_category_id = $category, recurring_expense_account_id = $account");

    $recurring_expense_id = mysqli_insert_id($mysqli);

    logAction("Recurring Expense", "Create", "$session_name created recurring expense $description", $client_id, $recurring_expense_id);

    flash_alert("Recurring Expense created");

    redirect();

}

if (isset($_POST['edit_recurring_expense'])) {

    $recurring_expense_id = intval($_POST['recurring_expense_id']);
    $frequency = intval($_POST['frequency']);
    $day = intval($_POST['day']);
    $month = intval($_POST['month']);
    $amount =  floatval(str_replace(',', '', $_POST['amount']));
    $account = intval($_POST['account']);
    $vendor = intval($_POST['vendor']);
    $client_id = intval($_POST['client']);
    $category = intval($_POST['category']);
    $description = sanitizeInput($_POST['description']);
    $reference = sanitizeInput($_POST['reference']);

    $year = date('Y');
    if (strtotime("$year-$month-$day") < time()) {
        $year++;
    }
    $start_date = "$year-$month-$day";

    mysqli_query($mysqli,"UPDATE recurring_expenses SET recurring_expense_frequency = $frequency, recurring_expense_day = $day, recurring_expense_month = $month, recurring_expense_next_date = '$start_date', recurring_expense_description = '$description', recurring_expense_reference = '$reference', recurring_expense_amount = $amount, recurring_expense_currency_code = '$session_company_currency', recurring_expense_vendor_id = $vendor, recurring_expense_client_id = $client_id, recurring_expense_category_id = $category, recurring_expense_account_id = $account WHERE recurring_expense_id = $recurring_expense_id");

    logAction("Recurring Expense", "Edit", "$session_name edited recurring expense $description", $client_id, $recurring_expense_id);

    flash_alert("Recurring Expense edited");

    redirect();

}

if (isset($_GET['delete_recurring_expense'])) {
    $recurring_expense_id = intval($_GET['delete_recurring_expense']);

    // Get Recurring Expense Details for Logging
    $sql = mysqli_query($mysqli,"SELECT recurring_expense_description, recurring_expense_client_id FROM recurring_expenses WHERE recurring_expense_id = $recurring_expense_id");
    $row = mysqli_fetch_array($sql);
    $recurring_expense_description = sanitizeInput($row['recurring_expense_description']);
    $client_id = intval($row['recurring_expense_client_id']);

    mysqli_query($mysqli,"DELETE FROM recurring_expenses WHERE recurring_expense_id = $recurring_expense_id");

    logAction("Recurring Expense", "Delete", "$session_name deleted recurring expense $recurring_expense_description", $client_id);

    flash_alert("Recurring Expense deleted", 'error');

    redirect();

}
