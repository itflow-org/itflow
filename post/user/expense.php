<?php

/*
 * ITFlow - GET/POST request handler for expenses
 */

if (isset($_POST['add_expense'])) {

    require_once 'post/user/expense_model.php';

    mysqli_query($mysqli,"INSERT INTO expenses SET expense_date = '$date', expense_amount = $amount, expense_currency_code = '$session_company_currency', expense_account_id = $account, expense_vendor_id = $vendor, expense_client_id = $client, expense_category_id = $category, expense_description = '$description', expense_reference = '$reference'");

    $expense_id = mysqli_insert_id($mysqli);

    // Check for and process attachment
    $extended_alert_description = '';
    
    if (isset($_FILES['file']['tmp_name'])) {

        if ($new_file_name = checkFileUpload($_FILES['file'], array('jpg', 'jpeg', 'gif', 'png', 'webp', 'pdf'))) {

            $file_tmp_path = $_FILES['file']['tmp_name'];

            // directory in which the uploaded file will be moved
            $upload_file_dir = "uploads/expenses/";
            $dest_path = $upload_file_dir . $new_file_name;
            move_uploaded_file($file_tmp_path, $dest_path);

            mysqli_query($mysqli,"UPDATE expenses SET expense_receipt = '$new_file_name' WHERE expense_id = $expense_id");
            $extended_alert_description = '. File successfully uploaded.';
        }
    }

    //Logging
    logAction("Expense", "Create", "$session_name created expense $description", $client, $expense_id);

    $_SESSION['alert_message'] = "Expense added" . $extended_alert_description;

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['edit_expense'])) {

    require_once 'post/user/expense_model.php';


    $expense_id = intval($_POST['expense_id']);

    // Get old receipt
    $sql = mysqli_query($mysqli,"SELECT expense_receipt FROM expenses WHERE expense_id = $expense_id");
    $row = mysqli_fetch_array($sql);
    $existing_file_name = sanitizeInput($row['expense_receipt']);

    // Check for and process attachment
    $extended_alert_description = '';
    if (isset($_FILES['file']['tmp_name'])) {
        if ($new_file_name = checkFileUpload($_FILES['file'], array('jpg', 'jpeg', 'gif', 'png', 'webp', 'pdf'))) {

            $file_tmp_path = $_FILES['file']['tmp_name'];

            // directory in which the uploaded file will be moved
            $upload_file_dir = "uploads/expenses/";
            $dest_path = $upload_file_dir . $new_file_name;
            move_uploaded_file($file_tmp_path, $dest_path);

            //Delete old file
            unlink("uploads/expenses/$existing_file_name");

            mysqli_query($mysqli,"UPDATE expenses SET expense_receipt = '$new_file_name' WHERE expense_id = $expense_id");
            $extended_alert_description = '. File successfully uploaded.';
        }
    }

    mysqli_query($mysqli,"UPDATE expenses SET expense_date = '$date', expense_amount = $amount, expense_account_id = $account, expense_vendor_id = $vendor, expense_client_id = $client, expense_category_id = $category, expense_description = '$description', expense_reference = '$reference' WHERE expense_id = $expense_id");

    // Logging
    logAction("Expense", "Edit", "$session_name edited expense $description", $client, $expense_id);

    $_SESSION['alert_message'] = "Expense modified" . $extended_alert_description;

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_GET['delete_expense'])) {
    $expense_id = intval($_GET['delete_expense']);

    $sql = mysqli_query($mysqli,"SELECT * FROM expenses WHERE expense_id = $expense_id");
    $row = mysqli_fetch_array($sql);
    $expense_receipt = sanitizeInput($row['expense_receipt']);
    $expense_description = sanitizeInput($row['expense_description']);
    $client_id = intval($row['expense_client_id']);

    unlink("uploads/expenses/$expense_receipt");

    mysqli_query($mysqli,"DELETE FROM expenses WHERE expense_id = $expense_id");

    // Logging
    logAction("Expense", "Delete", "$session_name deleted expense $expense_description", $client_id);

    $_SESSION['alert_message'] = "Expense deleted";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['bulk_edit_expense_category'])) {

    $category_id = intval($_POST['bulk_category_id']);

    // Get Category name for logging and Notification
    $sql = mysqli_query($mysqli,"SELECT category_name FROM categories WHERE category_id = $category_id");
    $row = mysqli_fetch_array($sql);
    $category_name = sanitizeInput($row['category_name']);

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

            // Logging
            logAction("Expense", "Edit", "$session_name assigned expense $expense_descrition to category $category_name", $client_id, $expense_id);

        } // End Assign Loop

        // Logging
        logAction("Expense", "Bulk Edit", "$session_name assigned $count expenses to category $category_name");

        $_SESSION['alert_message'] = "You assigned expense category <strong>$category_name</strong> to <strong>$count</strong> expense(s)";
    }

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if (isset($_POST['bulk_edit_expense_account'])) {

    $account_id = intval($_POST['bulk_account_id']);

    // Get Account name for logging and Notification
    $sql = mysqli_query($mysqli,"SELECT account_name FROM accounts WHERE account_id = $account_id");
    $row = mysqli_fetch_array($sql);
    $account_name = sanitizeInput($row['account_name']);

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

            // Logging
            logAction("Expense", "Edit", "$session_name assigned expense $expense_descrition to account $account_name", $client_id, $expense_id);

        } // End Assign Loop

        // Logging
        logAction("Expense", "Bulk Edit", "$session_name assigned $count expense(s) to account $account_name");

        $_SESSION['alert_message'] = "You assigned account <strong>$account_name</strong> to <strong>$count</strong> expense(s)";
    }

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if (isset($_POST['bulk_edit_expense_client'])) {

    $client_id = intval($_POST['bulk_client_id']);

    // Get Client name for logging and Notification
    $sql = mysqli_query($mysqli,"SELECT client_name FROM clients WHERE client_id = $client_id");
    $row = mysqli_fetch_array($sql);
    $client_name = sanitizeInput($row['client_name']);

    // Assign Client to Selected Expenses
    if (isset($_POST['expense_ids'])) {

        // Get Selected Count
        $count = count($_POST['expense_ids']);

        foreach($_POST['expense_ids'] as $expense_id) {
            $expense_id = intval($expense_id);

            // Get Expense Details for Logging
            $sql = mysqli_query($mysqli,"SELECT expense_description FROM expenses WHERE expense_id = $expense_id");
            $row = mysqli_fetch_array($sql);
            $expense_description = sanitizeInput($row['expense_description']);

            mysqli_query($mysqli,"UPDATE expenses SET expense_client_id = $client_id WHERE expense_id = $expense_id");

            // Logging
            logAction("Expense", "Edit", "$session_name assigned expense $expense_descrition to client $client_name", $client_id, $expense_id);

        } // End Assign Loop

        $_SESSION['alert_message'] = "You assigned Client <b>$client_name</b> to <b>$expense_count</b> expenses";
    }

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if (isset($_POST['bulk_delete_expenses'])) {
    validateAdminRole();
    validateCSRFToken($_POST['csrf_token']);

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

            unlink("uploads/expenses/$expense_receipt");

            mysqli_query($mysqli, "DELETE FROM expenses WHERE expense_id = $expense_id");
            
            // Logging
            logAction("Expense", "Delete", "$session_name deleted expense $expense_descrition", $client_id);

        }

        // Logging
        logAction("Expense", "Bulk Delete", "$session_name deleted $count expense(s)");

        $_SESSION['alert_type'] = "error";
        $_SESSION['alert_message'] = "Deleted <strong>$count</strong> expense(s)";

    }

    header("Location: " . $_SERVER["HTTP_REFERER"]);
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

    // Logging
    logAction("Expense", "Export", "$session_name exported $num_rows expense(s) to CSV file");

    exit;
}

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

    $start_date = date('Y') . "-$month-$day";

    mysqli_query($mysqli,"INSERT INTO recurring_expenses SET recurring_expense_frequency = $frequency, recurring_expense_day = $day, recurring_expense_month = $month, recurring_expense_next_date = '$start_date', recurring_expense_description = '$description', recurring_expense_reference = '$reference', recurring_expense_amount = $amount, recurring_expense_currency_code = '$session_company_currency', recurring_expense_vendor_id = $vendor, recurring_expense_client_id = $client_id, recurring_expense_category_id = $category, recurring_expense_account_id = $account");

    $recurring_expense_id = mysqli_insert_id($mysqli);

    // Logging
    logAction("Recurring Expense", "Create", "$session_name created recurring expense $description", $client_id, $recurring_expense_id);

    $_SESSION['alert_message'] = "Recurring Expense created";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

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

    $start_date = date('Y') . "-$month-$day";

    mysqli_query($mysqli,"UPDATE recurring_expenses SET recurring_expense_frequency = $frequency, recurring_expense_day = $day, recurring_expense_month = $month, recurring_expense_next_date = '$start_date', recurring_expense_description = '$description', recurring_expense_reference = '$reference', recurring_expense_amount = $amount, recurring_expense_currency_code = '$session_company_currency', recurring_expense_vendor_id = $vendor, recurring_expense_client_id = $client_id, recurring_expense_category_id = $category, recurring_expense_account_id = $account WHERE recurring_expense_id = $recurring_expense_id");

    $recurring_expense_id = mysqli_insert_id($mysqli);

    //Logging
    logAction("Recurring Expense", "Edit", "$session_name edited recurring expense $description", $client_id, $recurring_expense_id);

    $_SESSION['alert_message'] = "Recurring Expense edited";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_GET['delete_recurring_expense'])) {
    $recurring_expense_id = intval($_GET['delete_recurring_expense']);

    // Get Recurring Expense Details for Logging
    $sql = mysqli_query($mysqli,"SELECT recurring_expense_description, recurring_expense_client_id FROM recurring_expenses WHERE recurring_expense_id = $recurring_expense_id");
    $row = mysqli_fetch_array($sql);
    $recurring_expense_description = sanitizeInput($row['recurring_expense_description']);
    $client_id = intval($row['recurring_expense_client_id']);

    mysqli_query($mysqli,"DELETE FROM recurring_expenses WHERE recurring_expense_id = $recurring_expense_id");

    // Logging
    logAction("Recurring Expense", "Delete", "$session_name deleted recurring expense $recurring_expense_description", $client_id);

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "Recurring Expense deleted";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}
