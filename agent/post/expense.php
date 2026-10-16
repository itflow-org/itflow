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
 * (0 takes the client off), method ('' clears it) or billable (yes, with
 * markup, or no), to value. Expenses already billed on an invoice keep
 * their client and stay billed.
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
        if (!intval($value)) {
            $set .= ", expense_billable = 0";
        }
    } elseif ($field === 'billable') {
        $markup = billingNumberInput($_POST['markup'] ?? '');
        if ($value === 'no') {
            $set = "expense_billable = 0";
            $label = "them not to bill on";
        } elseif ($value === 'yes' && $markup !== null && $markup >= 0 && $markup <= 1000) {
            $set = "expense_billable = 1, expense_markup = $markup";
            $label = "them to bill on at " . rtrim(rtrim(number_format($markup, 2, '.', ''), '0'), '.') . "% markup";
        } elseif ($value === 'yes') {
            flashAlert('The markup is a percent, from 0 to 1000', 'error');
            redirect();
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
    $skipped = 0;
    foreach ($expense_ids as $expense_id) {
        $row = expenseRow($expense_id);
        if (!$row || $row['transfer_id'] || !intval($row['expense_vendor_id'])) {
            continue;
        }
        // Billed ones keep their client and stay billed; only a client's expenses can be billed on
        if (($row['expense_invoice_id'] && in_array($field, ['client', 'billable'], true))
            || ($field === 'billable' && $value === 'yes' && (!intval($row['expense_client_id']) || floatval($row['expense_amount']) <= 0))) {
            $skipped++;
            continue;
        }
        mysqli_query($mysqli, "UPDATE expenses SET $set WHERE expense_id = $expense_id");
        logAudit("Expense", "Edit", "$session_name set " . escapeSql($label) . " on the expense " . escapeSql(expenseLabel($row)), intval($row['expense_client_id']), $expense_id);
        $count++;
    }

    logAudit("Expense", "Bulk Edit", "$session_name set " . escapeSql($label) . " on $count expense" . ($count === 1 ? '' : 's'));

    flashAlert("Set " . escapeHtml($label) . " on <strong>$count</strong> expense" . ($count === 1 ? '' : 's')
        . ($skipped ? " - $skipped left as " . ($skipped === 1 ? 'it was' : 'they were') . ($field === 'billable' && $value === 'yes' ? ' (on an invoice already, or not for a client)' : ' (on an invoice already)') : ''),
        $count ? 'success' : 'warning');

    redirect();

}

/*
 * Take a billed expense off its invoice - the line comes off and it waits
 * to be billed again. Not once the invoice is paid or cancelled.
 */
if (isset($_GET['expense_unbill'])) {

    validateCSRFToken();

    enforceUserPermission('module_financial', 2);
    enforceUserPermission('module_sales', 2);

    $expense_id = intval($_GET['expense_unbill']);
    $row = expenseRow($expense_id);
    $number = $row ? expenseUnbill($expense_id, $error) : '';
    if (!$row || $number === '') {
        flashAlert($row ? $error : 'That expense no longer exists', 'error');
        redirect();
    }

    logAudit("Expense", "Unbill", "$session_name took the expense " . escapeSql(expenseLabel($row)) . " off invoice " . escapeSql($number), intval($row['expense_client_id']), $expense_id);
    if ($row['invoice_status'] !== null) {
        billingLog('invoice', intval($row['expense_invoice_id']), $row['invoice_status'], 'Expense taken off by ' . stripslashes($session_name) . ' - ' . expenseLabel($row));
    }

    flashAlert("Expense <strong>" . escapeHtml(expenseLabel($row)) . "</strong> taken off " . escapeHtml($number) . " - it is to bill again");

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

    $sql = mysqli_query($mysqli, "SELECT expenses.*, vendor_name, category_name, account_name, client_name, user_name, invoice_prefix, invoice_number
        FROM expenses " . expenseJoinsSql() . "
        LEFT JOIN invoices ON invoice_id = expense_invoice_id
        WHERE $where
        ORDER BY expense_date ASC, expense_id ASC");

    $num_rows = mysqli_num_rows($sql);

    if ($num_rows > 0) {

        guardExportPdfRowCount($format, $num_rows);

        $export = beginExport('expenses', $format, "$session_company_name-Expenses", 'Expenses', summarizeExportFilters($filter_summary));

        while ($row = mysqli_fetch_assoc($sql)) {
            $row['expense_receipt_display'] = $row['expense_receipt'] ? 'Yes' : 'No';
            $markup = rtrim(rtrim(number_format(floatval($row['expense_markup']), 2, '.', ''), '0'), '.') . '%';
            $row['expense_billing_display'] = $row['expense_invoice_id'] ? "Billed, $markup markup" : (intval($row['expense_billable']) ? "To bill, $markup markup" : '');
            $row['expense_billed_on'] = $row['expense_invoice_id'] && $row['invoice_number'] !== null ? $row['invoice_prefix'] . $row['invoice_number'] : '';
            addExportRow($export, $row);
        }

        finishExport($export);
    }

    logAudit("Expense", "Export", "$session_name exported $num_rows expense(s) to a " . strtoupper($format) . " file");

    exit;

}
