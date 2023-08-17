<?php

/*
 * ITFlow - GET/POST request handler for expenses
 */

if (isset($_POST['add_expense'])) {

    require_once('post/expense_model.php');

    mysqli_query($mysqli,"INSERT INTO expenses SET expense_date = '$date', expense_amount = $amount, expense_currency_code = '$session_company_currency', expense_account_id = $account, expense_vendor_id = $vendor, expense_client_id = $client, expense_category_id = $category, expense_description = '$description', expense_reference = '$reference'");

    $expense_id = mysqli_insert_id($mysqli);

    // Check for and process attachment
    $extended_alert_description = '';
    if ($_FILES['file']['tmp_name'] != '') {
        if ($new_file_name = checkFileUpload($_FILES['file'], array('jpg', 'jpeg', 'gif', 'png', 'pdf'))) {

            $file_tmp_path = $_FILES['file']['tmp_name'];

            // directory in which the uploaded file will be moved
            $upload_file_dir = "uploads/expenses/";
            $dest_path = $upload_file_dir . $new_file_name;
            move_uploaded_file($file_tmp_path, $dest_path);

            mysqli_query($mysqli,"UPDATE expenses SET expense_receipt = '$new_file_name' WHERE expense_id = $expense_id");
            $extended_alert_description = '. File successfully uploaded.';
        } else {
            $_SESSION['alert_type'] = "error";
            $extended_alert_description = '. Error uploading file. Check upload directory is writable/correct file type/size';
        }
    }

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Expense', log_action = 'Create', log_description = '$description', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Expense added" . $extended_alert_description;

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['edit_expense'])) {

    require_once('post/expense_model.php');

    $expense_id = intval($_POST['expense_id']);

    // Get old receipt
    $sql = mysqli_query($mysqli,"SELECT expense_receipt FROM expenses WHERE expense_id = $expense_id");
    $row = mysqli_fetch_array($sql);
    $existing_file_name = sanitizeInput($row['expense_receipt']);

    // Check for and process attachment
    $extended_alert_description = '';
    if ($_FILES['file']['tmp_name'] != '') {
        if ($new_file_name = checkFileUpload($_FILES['file'], array('jpg', 'jpeg', 'gif', 'png', 'pdf'))) {

            $file_tmp_path = $_FILES['file']['tmp_name'];

            // directory in which the uploaded file will be moved
            $upload_file_dir = "uploads/expenses/";
            $dest_path = $upload_file_dir . $new_file_name;
            move_uploaded_file($file_tmp_path, $dest_path);

            //Delete old file
            unlink("uploads/expenses/$existing_file_name");

            mysqli_query($mysqli,"UPDATE expenses SET expense_receipt = '$new_file_name' WHERE expense_id = $expense_id");
            $extended_alert_description = '. File successfully uploaded.';
        } else {
            $_SESSION['alert_type'] = "error";
            $extended_alert_description = '. Error uploading file. Check upload directory is writable/correct file type/size';
        }
    }

    mysqli_query($mysqli,"UPDATE expenses SET expense_date = '$date', expense_amount = $amount, expense_account_id = $account, expense_vendor_id = $vendor, expense_client_id = $client, expense_category_id = $category, expense_description = '$description', expense_reference = '$reference' WHERE expense_id = $expense_id");

    $_SESSION['alert_message'] = "Expense modified" . $extended_alert_description;

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Expense', log_action = 'Modify', log_description = '$description', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_GET['delete_expense'])) {
    $expense_id = intval($_GET['delete_expense']);

    $sql = mysqli_query($mysqli,"SELECT * FROM expenses WHERE expense_id = $expense_id");
    $row = mysqli_fetch_array($sql);
    $expense_receipt = sanitizeInput($row['expense_receipt']);

    unlink("uploads/expenses/$expense_receipt");

    mysqli_query($mysqli,"DELETE FROM expenses WHERE expense_id = $expense_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Expense', log_action = 'Delete', log_description = '$expense_id', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Expense deleted";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['export_expenses_csv'])) {
    $date_from = sanitizeInput($_POST['date_from']);
    $date_to = sanitizeInput($_POST['date_to']);
    if (!empty($date_from) && !empty($date_to)) {
        $date_query = "AND DATE(expense_date) BETWEEN '$date_from' AND '$date_to'";
        $file_name_date = "$date_from-to-$date_to";
    }else{
        $date_query = "";
        $file_name_date = date('Y-m-d');
    }

    //get records from database
    $sql = mysqli_query($mysqli,"SELECT * FROM expenses
      LEFT JOIN categories ON expense_category_id = category_id
      LEFT JOIN vendors ON expense_vendor_id = vendor_id
      LEFT JOIN accounts ON expense_account_id = account_id
      WHERE expense_vendor_id > 0
      $date_query
      ORDER BY expense_date DESC
    ");

    if (mysqli_num_rows($sql) > 0) {
        $delimiter = ",";
        $filename = "$session_company_name-Expenses-$file_name_date.csv";

        //create a file pointer
        $f = fopen('php://memory', 'w');

        //set column headers
        $fields = array('Date', 'Amount', 'Vendor', 'Description', 'Category', 'Account');
        fputcsv($f, $fields, $delimiter);

        //output each row of the data, format line as csv and write to file pointer
        while($row = mysqli_fetch_assoc($sql)) {
            $lineData = array($row['expense_date'], $row['expense_amount'], $row['vendor_name'], $row['expense_description'], $row['category_name'], $row['account_name']);
            fputcsv($f, $lineData, $delimiter);
        }

        //move back to beginning of file
        fseek($f, 0);

        //set headers to download file rather than displayed
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '";');

        //output all remaining data on a file pointer
        fpassthru($f);
    }

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Expense', log_action = 'Export', log_description = '$session_name exported expenses to CSV File', log_ip = '$session_ip', log_user_agent = '$session_user_agent',  log_user_id = $session_user_id");

    exit;
}

if (isset($_POST['create_recurring_expense'])) {

    $frequency = intval($_POST['frequency']);
    $day = intval($_POST['day']);
    $month = intval($_POST['month']);
    $amount = floatval($_POST['amount']);
    $account = intval($_POST['account']);
    $vendor = intval($_POST['vendor']);
    $client = intval($_POST['client']);
    $category = intval($_POST['category']);
    $description = sanitizeInput($_POST['description']);
    $reference = sanitizeInput($_POST['reference']);

    $start_date = date('Y') . "-$month-$day";

    mysqli_query($mysqli,"INSERT INTO recurring_expenses SET recurring_expense_frequency = $frequency, recurring_expense_day = $day, recurring_expense_month = $month, recurring_expense_next_date = '$start_date', recurring_expense_description = '$description', recurring_expense_reference = '$reference', recurring_expense_amount = $amount, recurring_expense_currency_code = '$session_company_currency', recurring_expense_vendor_id = $vendor, recurring_expense_client_id = $client, recurring_expense_category_id = $category, recurring_expense_account_id = $account");

    $recurring_expense_id = mysqli_insert_id($mysqli);

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Recurring Expense', log_action = 'Create', log_description = '$description', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Recurring Expense added";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['edit_recurring_expense'])) {

    $recurring_expense_id = intval($_POST['recurring_expense_id']);
    $frequency = intval($_POST['frequency']);
    $day = intval($_POST['day']);
    $month = intval($_POST['month']);
    $amount = floatval($_POST['amount']);
    $account = intval($_POST['account']);
    $vendor = intval($_POST['vendor']);
    $client = intval($_POST['client']);
    $category = intval($_POST['category']);
    $description = sanitizeInput($_POST['description']);
    $reference = sanitizeInput($_POST['reference']);

    $start_date = date('Y') . "-$month-$day";

    mysqli_query($mysqli,"UPDATE recurring_expenses SET recurring_expense_frequency = $frequency, recurring_expense_day = $day, recurring_expense_month = $month, recurring_expense_next_date = '$start_date', recurring_expense_description = '$description', recurring_expense_reference = '$reference', recurring_expense_amount = $amount, recurring_expense_currency_code = '$session_company_currency', recurring_expense_vendor_id = $vendor, recurring_expense_client_id = $client, recurring_expense_category_id = $category, recurring_expense_account_id = $account WHERE recurring_expense_id = $recurring_expense_id");

    $recurring_expense_id = mysqli_insert_id($mysqli);

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Recurring Expense', log_action = 'Edit', log_description = '$description', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Recurring Expense edited";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_GET['delete_recurring_expense'])) {
    $recurring_expense_id = intval($_GET['delete_recurring_expense']);

    mysqli_query($mysqli,"DELETE FROM recurring_expenses WHERE recurring_expense_id = $recurring_expense_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Recurring Expense', log_action = 'Delete', log_description = '$recurring_expense_id', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "Recurring Expense deleted";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}
