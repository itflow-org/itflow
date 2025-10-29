<?php

/*
 * ITFlow - GET/POST request handler for expenses
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['add_expense'])) {

    require_once 'expense_model.php';

    mysqli_query($mysqli,"INSERT INTO expenses SET expense_date = '$date', expense_amount = $amount, expense_currency_code = '$session_company_currency', expense_account_id = $account, expense_vendor_id = $vendor, expense_client_id = $client, expense_category_id = $category, expense_description = '$description', expense_reference = '$reference'");

    $expense_id = mysqli_insert_id($mysqli);

    // Check for and process attachment
    $extended_alert_description = '';
    
    if (isset($_FILES['file']['tmp_name'])) {

        if ($new_file_name = checkFileUpload($_FILES['file'], array('jpg', 'jpeg', 'gif', 'png', 'webp', 'pdf'))) {

            $file_tmp_path = $_FILES['file']['tmp_name'];

            // directory in which the uploaded file will be moved
            $upload_file_dir = "../uploads/expenses/";
            $dest_path = $upload_file_dir . $new_file_name;
            move_uploaded_file($file_tmp_path, $dest_path);

            mysqli_query($mysqli,"UPDATE expenses SET expense_receipt = '$new_file_name' WHERE expense_id = $expense_id");
            $extended_alert_description = '. File successfully uploaded.';
        }
    }

    logAction("Expense", "Create", "$session_name created expense $description", $client, $expense_id);

    flash_alert("Expense added" . $extended_alert_description);

    redirect();

}

if (isset($_POST['edit_expense'])) {

    require_once 'expense_model.php';

    $expense_id = intval($_POST['expense_id']);

    // Get old receipt
    $existing_file_name = sanitizeInput(getFieldById('expenses', $expense_id, 'expense_receipt'));

    // Check for and process attachment
    $extended_alert_description = '';
    if (isset($_FILES['file']['tmp_name'])) {
        if ($new_file_name = checkFileUpload($_FILES['file'], array('jpg', 'jpeg', 'gif', 'png', 'webp', 'pdf'))) {

            $file_tmp_path = $_FILES['file']['tmp_name'];

            // directory in which the uploaded file will be moved
            $upload_file_dir = "../uploads/expenses/";
            $dest_path = $upload_file_dir . $new_file_name;
            move_uploaded_file($file_tmp_path, $dest_path);

            //Delete old file
            unlink("../uploads/expenses/$existing_file_name");

            mysqli_query($mysqli,"UPDATE expenses SET expense_receipt = '$new_file_name' WHERE expense_id = $expense_id");
            $extended_alert_description = '. File successfully uploaded.';
        }
    }

    mysqli_query($mysqli,"UPDATE expenses SET expense_date = '$date', expense_amount = $amount, expense_account_id = $account, expense_vendor_id = $vendor, expense_client_id = $client, expense_category_id = $category, expense_description = '$description', expense_reference = '$reference' WHERE expense_id = $expense_id");

    logAction("Expense", "Edit", "$session_name edited expense $description", $client, $expense_id);

    flash_alert("Expense modified" . $extended_alert_description);

    redirect();

}

if (isset($_GET['delete_expense'])) {
    
    $expense_id = intval($_GET['delete_expense']);

    $sql = mysqli_query($mysqli,"SELECT * FROM expenses WHERE expense_id = $expense_id");
    $row = mysqli_fetch_array($sql);
    $expense_receipt = sanitizeInput($row['expense_receipt']);
    $expense_description = sanitizeInput($row['expense_description']);
    $client_id = intval($row['expense_client_id']);

    unlink("../uploads/expenses/$expense_receipt");

    mysqli_query($mysqli,"DELETE FROM expenses WHERE expense_id = $expense_id");

    logAction("Expense", "Delete", "$session_name deleted expense $expense_description", $client_id);

    flash_alert("Expense deleted", 'error');

    redirect();

}

if (isset($_POST['bulk_edit_expense_category'])) {

    $category_id = intval($_POST['bulk_category_id']);

    // Get Category name for logging and Notification
    $category_name = sanitizeInput(getFieldById('categories', $category_id, 'category_name'));

    // Assign category to Selected Expenses
    if (isset($_POST['expense_ids'])) {

        // Get Selected Count
        $count = count($_POST['expense_ids']);

        foreach($_POST['expense_ids'] as $expense_id) {
            $expense_id = intval($expense_id);

            // Get Expense Details for Logging
            $sql = mysqli_query($mysqli,"SELECT expense_description, expense_client_id FROM expenses WHERE expense_id = $expense_id");
            $row = mysqli_fetch_array($sql);
            $expense_description = sanitizeInput($row['expense_description']);
            $client_id = intval($row['expense_client_id']);

            mysqli_query($mysqli,"UPDATE expenses SET expense_category_id = $category_id WHERE expense_id = $expense_id");

            logAction("Expense", "Edit", "$session_name assigned expense $expense_descrition to category $category_name", $client_id, $expense_id);

        } // End Assign Loop

        logAction("Expense", "Bulk Edit", "$session_name assigned $count expenses to category $category_name");

        flash_alert("You assigned expense category <strong>$category_name</strong> to <strong>$count</strong> expense(s)");
    }

    redirect();

}

if (isset($_POST['bulk_edit_expense_account'])) {

    $account_id = intval($_POST['bulk_account_id']);

    // Get Account name for logging and Notification
    $account_name = sanitizeInput(getFieldById('accounts', $account_id, 'account_name'));

    // Assign account to Selected Expenses
    if (isset($_POST['expense_ids'])) {

        // Get Selected Contacts Count
        $count = count($_POST['expense_ids']);

        foreach($_POST['expense_ids'] as $expense_id) {
            $expense_id = intval($expense_id);

            // Get Expense Details for Logging
            $sql = mysqli_query($mysqli,"SELECT expense_description, expense_client_id FROM expenses WHERE expense_id = $expense_id");
            $row = mysqli_fetch_array($sql);
            $expense_description = sanitizeInput($row['expense_description']);
            $client_id = intval($row['expense_client_id']);

            mysqli_query($mysqli,"UPDATE expenses SET expense_account_id = $account_id WHERE expense_id = $expense_id");

            logAction("Expense", "Edit", "$session_name assigned expense $expense_descrition to account $account_name", $client_id, $expense_id);

        } // End Assign Loop

        logAction("Expense", "Bulk Edit", "$session_name assigned $count expense(s) to account $account_name");

        flash_alert("You assigned account <strong>$account_name</strong> to <strong>$count</strong> expense(s)");
    }

    redirect();

}

if (isset($_POST['bulk_edit_expense_client'])) {

    $client_id = intval($_POST['bulk_client_id']);

    // Get Client name for logging and Notification
    $client_name = sanitizeInput(getFieldById('clients', $client_id, 'client_name'));

    // Assign Client to Selected Expenses
    if (isset($_POST['expense_ids'])) {

        // Get Selected Count
        $count = count($_POST['expense_ids']);

        foreach($_POST['expense_ids'] as $expense_id) {
            $expense_id = intval($expense_id);

            // Get Expense Details for Logging
            $expense_description = sanitizeInput(getFieldById('expenses', $expense_id, 'expense_description'));

            mysqli_query($mysqli,"UPDATE expenses SET expense_client_id = $client_id WHERE expense_id = $expense_id");

            logAction("Expense", "Edit", "$session_name assigned expense $expense_descrition to client $client_name", $client_id, $expense_id);

        } // End Assign Loop

       flash_alert("You assigned Client <b>$client_name</b> to <b>$expense_count</b> expenses");
    }

    redirect();

}

if (isset($_POST['bulk_delete_expenses'])) {
    
    validateCSRFToken($_POST['csrf_token']);

    validateAdminRole();

    if (isset($_POST['expense_ids'])) {

        // Get Selected Count
        $count = count($_POST['expense_ids']);

        // Cycle through array and delete each expense
        foreach ($_POST['expense_ids'] as $expense_id) {

            $expense_id = intval($expense_id);

            $sql = mysqli_query($mysqli,"SELECT * FROM expenses WHERE expense_id = $expense_id");
            $row = mysqli_fetch_array($sql);
            $expense_description = sanitizeInput($row['expense_description']);
            $expense_receipt = sanitizeInput($row['expense_receipt']);
            $client_id = intval($row['expense_client_id']);

            unlink("../uploads/expenses/$expense_receipt");

            mysqli_query($mysqli, "DELETE FROM expenses WHERE expense_id = $expense_id");

            logAction("Expense", "Delete", "$session_name deleted expense $expense_descrition", $client_id);

        }

        logAction("Expense", "Bulk Delete", "$session_name deleted $count expense(s)");

        flash_alert("Deleted <strong>$count</strong> expense(s)", 'error');

    }

    redirect();

}

if (isset($_POST['export_expenses_csv'])) {
    
    $date_from = sanitizeInput($_POST['date_from']);
    $date_to = sanitizeInput($_POST['date_to']);
    $account = intval($_POST['account']);
    $vendor = intval($_POST['vendor']);
    $category = intval($_POST['category']);

    if (!empty($date_from) && !empty($date_to)) {
        $date_query = "AND DATE(expense_date) BETWEEN '$date_from' AND '$date_to'";
        $file_name_date = "$date_from-to-$date_to";
    }else{
        $date_query = "";
        $file_name_date = date('Y-m-d');
    }

    // Vendor Filter
    if ($account) {
        $account_query = "AND expense_account_id = $account";
    } else {
        $account_query = '';
    }

    // Vendor Filter
    if ($vendor) {
        $vendor_query = "AND expense_vendor_id = $vendor";
    } else {
        // Default - any
        $vendor_query = '';
    }

    // Category Filter
    if ($category) {
        $category_query = "AND expense_category_id = $category";
    } else {
        // Default - any
        $category_query = '';
    }

    //get records from database
    $sql = mysqli_query($mysqli,"SELECT * FROM expenses
      LEFT JOIN categories ON expense_category_id = category_id
      LEFT JOIN vendors ON expense_vendor_id = vendor_id
      LEFT JOIN accounts ON expense_account_id = account_id
      WHERE expense_vendor_id > 0
      $date_query
      $account_query
      $vendor_query
      $category_query
      ORDER BY expense_date DESC
    ");

    $num_rows = mysqli_num_rows($sql);
    if ($num_rows > 0) {
        $delimiter = ",";
        $enclosure = '"';
        $escape    = '\\';   // backslash
        $filename = sanitize_filename("$session_company_name-Expenses-" . date('Y-m-d_H-i-s') . ".csv");

        //create a file pointer
        $f = fopen('php://memory', 'w');

        //set column headers
        $fields = array('Date', 'Amount', 'Vendor', 'Description', 'Category', 'Account');
        fputcsv($f, $fields, $delimiter, $enclosure, $escape);

        //output each row of the data, format line as csv and write to file pointer
        while($row = mysqli_fetch_assoc($sql)) {
            $lineData = array($row['expense_date'], $row['expense_amount'], $row['vendor_name'], $row['expense_description'], $row['category_name'], $row['account_name']);
            fputcsv($f, $lineData, $delimiter, $enclosure, $escape);
        }

        //move back to beginning of file
        fseek($f, 0);

        //set headers to download file rather than displayed
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '";');

        //output all remaining data on a file pointer
        fpassthru($f);
    }

    logAction("Expense", "Export", "$session_name exported $num_rows expense(s) to CSV file");

    exit;

}
