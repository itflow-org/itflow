<?php

/*
 * ITFlow - GET/POST request handler for expenses
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['add_expense'])) {

    validateCSRFToken();

    enforceUserPermission('module_financial', 2);

    require_once 'expense_model.php';

    if ($client_id) {
        enforceClientAccess();
    }
    
    mysqli_query($mysqli,"INSERT INTO expenses SET expense_date = '$date', expense_amount = $amount, expense_currency_code = '$session_company_currency', expense_account_id = $account, expense_vendor_id = $vendor, expense_client_id = $client_id, expense_category_id = $category, expense_description = '$description', expense_reference = '$reference'");

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

    logAudit("Expense", "Create", "$session_name created expense $description", $client_id, $expense_id);

    flashAlert("Expense added" . $extended_alert_description);

    redirect();

}

if (isset($_POST['edit_expense'])) {

    validateCSRFToken();

    enforceUserPermission('module_financial', 2);

    require_once 'expense_model.php';

    if ($client_id) {
        enforceClientAccess();
    }

    $expense_id = intval($_POST['expense_id']);

    // Get old receipt
    $existing_file_name = escapeSql(getFieldById('expenses', $expense_id, 'expense_receipt'));

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

    mysqli_query($mysqli,"UPDATE expenses SET expense_date = '$date', expense_amount = $amount, expense_account_id = $account, expense_vendor_id = $vendor, expense_client_id = $client_id, expense_category_id = $category, expense_description = '$description', expense_reference = '$reference' WHERE expense_id = $expense_id");

    logAudit("Expense", "Edit", "$session_name edited expense $description", $client_id, $expense_id);

    flashAlert("Expense modified" . $extended_alert_description);

    redirect();

}

if (isset($_GET['delete_expense'])) {

    validateCSRFToken();

    enforceUserPermission('module_financial', 3);

    $expense_id = intval($_GET['delete_expense']);

    $sql = mysqli_query($mysqli,"SELECT expense_client_id, expense_description, expense_receipt FROM expenses WHERE expense_id = $expense_id");
    $row = mysqli_fetch_assoc($sql);
    $expense_receipt = escapeSql($row['expense_receipt']);
    $expense_description = escapeSql($row['expense_description']);
    $client_id = intval($row['expense_client_id']);

    if ($client_id) {
        enforceClientAccess();
    }

    unlink("../uploads/expenses/$expense_receipt");

    mysqli_query($mysqli,"DELETE FROM expenses WHERE expense_id = $expense_id");

    logAudit("Expense", "Delete", "$session_name deleted expense $expense_description", $client_id);

    flashAlert("Expense deleted", 'error');

    redirect();

}

if (isset($_POST['bulk_edit_expense_category'])) {

    validateCSRFToken();

    enforceUserPermission('module_financial', 2);

    $category_id = intval($_POST['bulk_category_id']);

    // Get Category name for logging and Notification
    $category_name = escapeSql(getFieldById('categories', $category_id, 'category_name'));

    // Assign category to Selected Expenses
    if (isset($_POST['expense_ids'])) {

        // Get Selected Count
        $count = count($_POST['expense_ids']);

        foreach($_POST['expense_ids'] as $expense_id) {
            $expense_id = intval($expense_id);

            // Get Expense Details for Logging
            $sql = mysqli_query($mysqli,"SELECT expense_description, expense_client_id FROM expenses WHERE expense_id = $expense_id");
            $row = mysqli_fetch_assoc($sql);
            $expense_description = escapeSql($row['expense_description']);
            $client_id = intval($row['expense_client_id']);

            if ($client_id) {
                enforceClientAccess();
            }

            mysqli_query($mysqli,"UPDATE expenses SET expense_category_id = $category_id WHERE expense_id = $expense_id");

            logAudit("Expense", "Edit", "$session_name assigned expense $expense_description to category $category_name", $client_id, $expense_id);

        } // End Assign Loop

        logAudit("Expense", "Bulk Edit", "$session_name assigned $count expenses to category $category_name");

        flashAlert("You assigned expense category <strong>$category_name</strong> to <strong>$count</strong> expense(s)");
    }

    redirect();

}

if (isset($_POST['bulk_edit_expense_account'])) {

    validateCSRFToken();

    enforceUserPermission('module_financial', 2);

    $account_id = intval($_POST['bulk_account_id']);

    // Get Account name for logging and Notification
    $account_name = escapeSql(getFieldById('accounts', $account_id, 'account_name'));

    // Assign account to Selected Expenses
    if (isset($_POST['expense_ids'])) {

        // Get Selected Contacts Count
        $count = count($_POST['expense_ids']);

        foreach($_POST['expense_ids'] as $expense_id) {
            $expense_id = intval($expense_id);

            // Get Expense Details for Logging
            $sql = mysqli_query($mysqli,"SELECT expense_description, expense_client_id FROM expenses WHERE expense_id = $expense_id");
            $row = mysqli_fetch_assoc($sql);
            $expense_description = escapeSql($row['expense_description']);
            $client_id = intval($row['expense_client_id']);

            if ($client_id) {
                enforceClientAccess();
            }

            mysqli_query($mysqli,"UPDATE expenses SET expense_account_id = $account_id WHERE expense_id = $expense_id");

            logAudit("Expense", "Edit", "$session_name assigned expense $expense_description to account $account_name", $client_id, $expense_id);

        } // End Assign Loop

        logAudit("Expense", "Bulk Edit", "$session_name assigned $count expense(s) to account $account_name");

        flashAlert("You assigned account <strong>$account_name</strong> to <strong>$count</strong> expense(s)");
    }

    redirect();

}

if (isset($_POST['bulk_edit_expense_client'])) {

    validateCSRFToken();

    enforceUserPermission('module_financial', 2);

    $client_id = intval($_POST['bulk_client_id']);

    enforceClientAccess();

    // Get Client name for logging and Notification
    $client_name = escapeSql(getFieldById('clients', $client_id, 'client_name'));

    // Assign Client to Selected Expenses
    if (isset($_POST['expense_ids'])) {

        // Get Selected Count
        $count = count($_POST['expense_ids']);

        foreach($_POST['expense_ids'] as $expense_id) {
            $expense_id = intval($expense_id);

            // Get Expense Details for Logging
            $expense_description = escapeSql(getFieldById('expenses', $expense_id, 'expense_description'));

            mysqli_query($mysqli,"UPDATE expenses SET expense_client_id = $client_id WHERE expense_id = $expense_id");

            logAudit("Expense", "Edit", "$session_name assigned expense $expense_description to client $client_name", $client_id, $expense_id);

        } // End Assign Loop

       flashAlert("You assigned client <strong>$client_name</strong> to <strong>$count</strong> expense(s)");
    }

    redirect();

}

if (isset($_POST['bulk_delete_expenses'])) {

    validateCSRFToken();

    enforceUserPermission('module_financial', 3);

    if (isset($_POST['expense_ids'])) {

        // Get Selected Count
        $count = count($_POST['expense_ids']);

        // Cycle through array and delete each expense
        foreach ($_POST['expense_ids'] as $expense_id) {

            $expense_id = intval($expense_id);

            $sql = mysqli_query($mysqli,"SELECT expense_client_id, expense_description, expense_receipt FROM expenses WHERE expense_id = $expense_id");
            $row = mysqli_fetch_assoc($sql);
            $expense_description = escapeSql($row['expense_description']);
            $expense_receipt = escapeSql($row['expense_receipt']);
            $client_id = intval($row['expense_client_id']);

            if ($client_id) {
                enforceClientAccess();
            }

            unlink("../uploads/expenses/$expense_receipt");

            mysqli_query($mysqli, "DELETE FROM expenses WHERE expense_id = $expense_id");

            logAudit("Expense", "Delete", "$session_name deleted expense $expense_description", $client_id);

        }

        logAudit("Expense", "Bulk Delete", "$session_name deleted $count expense(s)");

        flashAlert("Deleted <strong>$count</strong> expense(s)", 'error');

    }

    redirect();

}

if (isset($_POST['export_expenses'])) {

    validateCSRFToken();

    // Exports are reads - see CONTRIBUTING.md
    enforceUserPermission('module_financial');

    $format = resolveExportFormat($_POST['export_expenses']);

    // Filters inherited from the expenses page - mirrors agent/expenses.php
    $filter_summary = [];

    $client_id = 0; // for Logging
    $file_name_prepend = "$session_company_name-";

    // Account Filter
    if (!empty($_POST['account'])) {
        $filter_account_id = intval($_POST['account']);
        $account_query = "AND (expense_account_id = $filter_account_id)";
        $filter_summary['Account'] = getFieldById('accounts', $filter_account_id, 'account_name');
    } else {
        // Default - any
        $account_query = '';
    }

    // Vendor Filter
    if (!empty($_POST['vendor'])) {
        $filter_vendor_id = intval($_POST['vendor']);
        $vendor_query = "AND (vendor_id = $filter_vendor_id)";
        $filter_summary['Vendor'] = getFieldById('vendors', $filter_vendor_id, 'vendor_name');
    } else {
        // Default - any
        $vendor_query = '';
    }

    // Category Filter
    if (!empty($_POST['category'])) {
        $filter_category_id = intval($_POST['category']);
        $category_query = "AND (category_id = $filter_category_id)";
        $filter_summary['Category'] = getFieldById('categories', $filter_category_id, 'category_name');
    } else {
        // Default - any
        $category_query = '';
    }

    // Date Filter
    $dtf = escapeSql(!empty($_POST['dtf']) ? $_POST['dtf'] : '1970-01-01');
    $dtt = escapeSql(!empty($_POST['dtt']) ? $_POST['dtt'] : '2099-12-31');
    $date_range = formatExportDateRange($dtf, $dtt);
    if ($date_range) {
        $filter_summary['Dated'] = $date_range;
    }

    // Search Filter
    $q = escapeSql($_POST['q'] ?? '');
    if (!empty($q)) {
        $filter_summary['Search'] = $_POST['q'];
    }

    $sql = mysqli_query(
        $mysqli,
        "SELECT * FROM expenses
        LEFT JOIN categories ON expense_category_id = category_id
        LEFT JOIN vendors ON expense_vendor_id = vendor_id
        LEFT JOIN accounts ON expense_account_id = account_id
        LEFT JOIN clients ON expense_client_id = client_id
        WHERE expense_vendor_id > 0
        AND DATE(expense_date) BETWEEN '$dtf' AND '$dtt'
        $vendor_query
        $category_query
        AND (vendor_name LIKE '%$q%' OR client_name LIKE '%$q%' OR category_name LIKE '%$q%' OR account_name LIKE '%$q%' OR expense_description LIKE '%$q%' OR expense_amount LIKE '%$q%')
        $account_query
        " . clientScopeSql('expense_client_id') . "
        ORDER BY expense_date ASC"
    );

    $num_rows = mysqli_num_rows($sql);

    if ($num_rows > 0) {

        guardExportPdfRowCount($format, $num_rows);

        $export = beginExport('expenses', $format, $file_name_prepend . 'Expenses', 'Expenses', summarizeExportFilters($filter_summary));

        while ($row = mysqli_fetch_assoc($sql)) {
            addExportRow($export, $row);
        }

        finishExport($export);
    }

    logAudit("Expense", "Export", "$session_name exported $num_rows expense(s) to a " . strtoupper($format) . " file", $client_id);

    exit;

}
