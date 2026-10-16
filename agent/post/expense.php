<?php

/*
 * ITFlow - GET/POST request handler for expenses
 *
 * New, Edit, Copy and Refund all post through expenseSave() in
 * functions/expenses.php, with the receipt as `file`. The checks there keep
 * an expense to one of our vendors, an expense category, an account and a
 * client the agent can see.
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['add_expense'])) {

    validateCSRFToken();

    enforceUserPermission('module_financial', 2);

    $expense_id = expenseSave($_POST, 0, $error);
    if (!$expense_id) {
        flashAlert($error, 'error');
        redirect();
    }

    $receipt_error = '';
    expenseReceiptStore($expense_id, $_FILES['file'] ?? null, $receipt_error);

    $row = expenseRow($expense_id, false);
    $label = expenseLabel($row);
    $refund = intval($row['expense_refund_of_id']) > 0;

    logAudit("Expense", $refund ? "Refund" : "Create", "$session_name " . ($refund ? 'recorded a refund from ' : 'added the expense ') . escapeSql($label), intval($row['expense_client_id']), $expense_id);

    if ($receipt_error) {
        flashAlert(($refund ? 'Refund' : 'Expense') . " <strong>" . escapeHtml($label) . "</strong> added, but " . lcfirst(escapeHtml($receipt_error)), 'warning');
    } else {
        flashAlert(($refund ? 'Refund' : 'Expense') . " <strong>" . escapeHtml($label) . "</strong> added" . ($row['expense_receipt'] ? ' with its receipt' : ''));
    }

    redirect();

}

if (isset($_POST['edit_expense'])) {

    validateCSRFToken();

    enforceUserPermission('module_financial', 2);

    $expense_id = intval($_POST['expense_id'] ?? 0);

    if (!expenseSave($_POST, $expense_id, $error)) {
        flashAlert($error, 'error');
        redirect();
    }

    $receipt_error = '';
    if (!empty($_POST['receipt_remove'])) {
        expenseReceiptRemove($expense_id);
    } else {
        expenseReceiptStore($expense_id, $_FILES['file'] ?? null, $receipt_error);
    }

    $row = expenseRow($expense_id, false);
    $label = expenseLabel($row);

    logAudit("Expense", "Edit", "$session_name edited the expense " . escapeSql($label), intval($row['expense_client_id']), $expense_id);

    if ($receipt_error) {
        flashAlert("Expense <strong>" . escapeHtml($label) . "</strong> saved, but " . lcfirst(escapeHtml($receipt_error)), 'warning');
    } else {
        flashAlert("Expense <strong>" . escapeHtml($label) . "</strong> saved");
    }

    redirect();

}

// A receipt added from the expense's quick look
if (isset($_POST['expense_receipt_save'])) {

    validateCSRFToken();

    enforceUserPermission('module_financial', 2);

    $row = expenseRow(intval($_POST['expense_id'] ?? 0));
    if (!$row || $row['transfer_id']) {
        flashAlert('That expense no longer exists', 'error');
        redirect();
    }
    $expense_id = intval($row['expense_id']);

    $receipt_error = '';
    if (!expenseReceiptStore($expense_id, $_FILES['file'] ?? null, $receipt_error)) {
        flashAlert($receipt_error ?: 'Choose the receipt to attach', 'error');
        redirect();
    }

    logAudit("Expense", "Receipt", "$session_name attached a receipt to the expense " . escapeSql(expenseLabel($row)), intval($row['expense_client_id']), $expense_id);

    flashAlert("Receipt attached to <strong>" . escapeHtml(expenseLabel($row)) . "</strong>");

    redirect();

}

if (isset($_GET['delete_expense'])) {

    validateCSRFToken();

    enforceUserPermission('module_financial', 3);

    $row = expenseDelete(intval($_GET['delete_expense']), $error);
    if (!$row) {
        flashAlert($error, 'error');
        redirect();
    }

    logAudit("Expense", "Delete", "$session_name deleted the expense " . escapeSql(expenseLabel($row)), intval($row['expense_client_id']));

    flashAlert("Expense <strong>" . escapeHtml(expenseLabel($row)) . "</strong> deleted", 'error');

    redirect();

}

/*
 * Set one thing on the ticked expenses: field = category, account, client
 * (0 takes the client off) or method ('' clears it), to value.
 */
if (isset($_POST['bulk_edit_expenses'])) {

    validateCSRFToken();

    enforceUserPermission('module_financial', 2);

    $field = (string) ($_POST['field'] ?? '');
    $value = trim((string) ($_POST['value'] ?? ''));
    $expense_ids = array_unique(array_filter(array_map('intval', (array) ($_POST['expense_ids'] ?? []))));

    // What the value is, and that it can be picked
    $set = '';
    $label = '';
    if ($field === 'category') {
        $categories = expenseCategories();
        if (isset($categories[intval($value)])) {
            $set = "expense_category_id = " . intval($value);
            $label = "the category to " . $categories[intval($value)]['category_name'];
        }
    } elseif ($field === 'account') {
        $accounts = paymentAccounts();
        if (isset($accounts[intval($value)])) {
            $set = "expense_account_id = " . intval($value);
            $label = "the account to " . $accounts[intval($value)]['account_name'];
        }
    } elseif ($field === 'client') {
        $client_name = intval($value) ? getFieldById('clients', intval($value), 'client_name') : '';
        if (!intval($value)) {
            $set = "expense_client_id = 0";
            $label = "no client";
        } elseif ($client_name !== null && mysqli_num_rows(mysqli_query($mysqli, "SELECT client_id FROM clients WHERE client_id = " . intval($value) . " " . clientScopeSql('client_id')))) {
            $set = "expense_client_id = " . intval($value);
            $label = "the client to $client_name";
        }
    } elseif ($field === 'method') {
        if ($value === '') {
            $set = "expense_payment_method = NULL";
            $label = "no payment method";
        } elseif (in_array($value, paymentMethods(), true)) {
            $set = "expense_payment_method = '" . escapeSql($value) . "'";
            $label = "the payment method to $value";
        }
    }
    if ($set === '' || !$expense_ids) {
        flashAlert($expense_ids ? 'Pick what to set them to' : 'Tick the expenses to change first', 'error');
        redirect();
    }

    $count = 0;
    foreach ($expense_ids as $expense_id) {
        $row = expenseRow($expense_id);
        if (!$row || $row['transfer_id'] || !intval($row['expense_vendor_id'])) {
            continue;
        }
        mysqli_query($mysqli, "UPDATE expenses SET $set WHERE expense_id = $expense_id");
        logAudit("Expense", "Edit", "$session_name set " . escapeSql($label) . " on the expense " . escapeSql(expenseLabel($row)), intval($row['expense_client_id']), $expense_id);
        $count++;
    }

    logAudit("Expense", "Bulk Edit", "$session_name set " . escapeSql($label) . " on $count expense" . ($count === 1 ? '' : 's'));

    flashAlert("Set " . escapeHtml($label) . " on <strong>$count</strong> expense" . ($count === 1 ? '' : 's'));

    redirect();

}

if (isset($_POST['bulk_delete_expenses'])) {

    validateCSRFToken();

    enforceUserPermission('module_financial', 3);

    $count = 0;
    foreach (array_unique(array_filter(array_map('intval', (array) ($_POST['expense_ids'] ?? [])))) as $expense_id) {
        $row = expenseDelete($expense_id);
        if (!$row) {
            continue;
        }
        logAudit("Expense", "Delete", "$session_name deleted the expense " . escapeSql(expenseLabel($row)), intval($row['expense_client_id']));
        $count++;
    }

    logAudit("Expense", "Bulk Delete", "$session_name deleted $count expense" . ($count === 1 ? '' : 's'));

    flashAlert("Deleted <strong>$count</strong> expense" . ($count === 1 ? '' : 's'), 'error');

    redirect();

}

if (isExportRequest('export_expenses')) {

    validateCSRFToken();

    // Exports are reads - see CONTRIBUTING.md
    enforceUserPermission('module_financial');

    $format = resolveExportFormat($_POST['export_expenses']);

    // The same filters as the expenses list (functions/expenses.php)
    $where = expenseFiltersSql($_POST, $filter_summary);

    $sql = mysqli_query($mysqli, "SELECT expenses.*, vendor_name, category_name, account_name, client_name, user_name
        FROM expenses " . expenseJoinsSql() . "
        WHERE $where
        ORDER BY expense_date ASC, expense_id ASC");

    $num_rows = mysqli_num_rows($sql);

    if ($num_rows > 0) {

        guardExportPdfRowCount($format, $num_rows);

        $export = beginExport('expenses', $format, "$session_company_name-Expenses", 'Expenses', summarizeExportFilters($filter_summary));

        while ($row = mysqli_fetch_assoc($sql)) {
            $row['expense_receipt_display'] = $row['expense_receipt'] ? 'Yes' : 'No';
            addExportRow($export, $row);
        }

        finishExport($export);
    }

    logAudit("Expense", "Export", "$session_name exported $num_rows expense(s) to a " . strtoupper($format) . " file");

    exit;

}
