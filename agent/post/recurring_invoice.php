<?php

/*
 * ITFlow - GET/POST request handler for recurring invoices
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['add_invoice_recurring'])) {

    validateCSRFToken();

    enforceUserPermission('module_sales', 2);

    $invoice_id = intval($_POST['invoice_id']);
    $recurring_invoice_frequency = validateRecurringFrequency($_POST['frequency']);

    $sql = mysqli_query($mysqli,"SELECT invoice_amount, invoice_category_id, invoice_client_id, invoice_currency_code,
        invoice_date, invoice_note, invoice_number, invoice_prefix, invoice_scope FROM invoices WHERE invoice_id = $invoice_id");
    $row = mysqli_fetch_assoc($sql);
    $invoice_prefix = escapeSql($row['invoice_prefix']);
    $invoice_number = intval($row['invoice_number']);
    $invoice_date = escapeSql(validateDate($row['invoice_date']));
    $invoice_amount = floatval($row['invoice_amount']);
    $invoice_currency_code = escapeSql($row['invoice_currency_code']);
    $invoice_scope = escapeSql($row['invoice_scope']);
    $invoice_note = escapeSql($row['invoice_note']);
    $client_id = intval($row['invoice_client_id']);
    $category_id = intval($row['invoice_category_id']);

    enforceClientAccess();

    // Atomically increment and get the new recurring_invoice number
    mysqli_query($mysqli, "
        UPDATE settings
        SET
            config_recurring_invoice_next_number = LAST_INSERT_ID(config_recurring_invoice_next_number),
            config_recurring_invoice_next_number = config_recurring_invoice_next_number + 1
        WHERE company_id = 1
    ");

    $recurring_invoice_number = mysqli_insert_id($mysqli);

    mysqli_query($mysqli,"INSERT INTO recurring_invoices SET recurring_invoice_prefix = '$config_recurring_invoice_prefix', recurring_invoice_number = $recurring_invoice_number, recurring_invoice_scope = '$invoice_scope', recurring_invoice_frequency = '$recurring_invoice_frequency', recurring_invoice_next_date = '" . recurringInvoiceStep($invoice_date, $recurring_invoice_frequency) . "', recurring_invoice_anchor_day = DAY('$invoice_date'), recurring_invoice_status = 1, recurring_invoice_amount = $invoice_amount, recurring_invoice_currency_code = '$invoice_currency_code', recurring_invoice_note = '$invoice_note', recurring_invoice_category_id = $category_id, recurring_invoice_client_id = $client_id");

    $recurring_invoice_id = mysqli_insert_id($mysqli);

    mysqli_query($mysqli,"INSERT INTO history SET history_status = 'Draft', history_description = 'Recurring Invoice Created from INVOICE!', history_recurring_invoice_id = $recurring_invoice_id");

    $sql_items = mysqli_query($mysqli,"SELECT item_description, item_id, item_name, item_order, item_price, item_quantity, item_subtotal,
        item_tax, item_tax_id, item_total FROM invoice_items WHERE item_invoice_id = $invoice_id");
    while($row = mysqli_fetch_assoc($sql_items)) {
        $item_id = intval($row['item_id']);
        $item_name = escapeSql($row['item_name']);
        $item_description = escapeSql($row['item_description']);
        $item_quantity = floatval($row['item_quantity']);
        $item_price = floatval($row['item_price']);
        $item_subtotal = floatval($row['item_subtotal']);
        $item_tax = floatval($row['item_tax']);
        $item_total = floatval($row['item_total']);
        $item_order = intval($row['item_order']);
        $tax_id = intval($row['item_tax_id']);

        mysqli_query($mysqli,"INSERT INTO recurring_invoice_items SET item_name = '$item_name', item_description = '$item_description', item_quantity = $item_quantity, item_price = $item_price, item_subtotal = $item_subtotal, item_tax = $item_tax, item_total = $item_total, item_order = $item_order, item_tax_id = $tax_id, item_recurring_invoice_id = $recurring_invoice_id");
    }

    logAudit("Recurring Invoice", "Create", "$session_name created recurring Invoice from Invoice $invoice_prefix$invoice_number", $client_id, $recurring_invoice_id);

    flashAlert("Created recurring Invoice from Invoice <strong>$invoice_prefix$invoice_number</strong>");

    redirect("recurring_invoice.php?recurring_invoice_id=$recurring_invoice_id");

}

if (isset($_POST['add_recurring_invoice'])) {

    validateCSRFToken();

    enforceUserPermission('module_sales', 2);

    $client_id = intval($_POST['client_id']);
    $frequency = validateRecurringFrequency($_POST['frequency']);
    $start_date = escapeSql(validateDate((string) ($_POST['start_date'] ?? '')));
    $category = intval($_POST['category']);
    $scope = escapeSql($_POST['scope']);
    $recurring_invoice_discount = round(floatval($_POST['recurring_invoice_discount'] ?? 0), 2);
    $auto_send = intval($_POST['auto_send'] ?? 1) ? 1 : 0;

    enforceClientAccess();

    // Atomically increment and get the new recurring_invoice number
    mysqli_query($mysqli, "
        UPDATE settings
        SET
            config_recurring_invoice_next_number = LAST_INSERT_ID(config_recurring_invoice_next_number),
            config_recurring_invoice_next_number = config_recurring_invoice_next_number + 1
        WHERE company_id = 1
    ");

    $recurring_invoice_number = mysqli_insert_id($mysqli);

    mysqli_query($mysqli,"INSERT INTO recurring_invoices SET recurring_invoice_prefix = '$config_recurring_invoice_prefix', recurring_invoice_number = $recurring_invoice_number, recurring_invoice_scope = '$scope', recurring_invoice_frequency = '$frequency', recurring_invoice_next_date = '$start_date', recurring_invoice_anchor_day = DAY('$start_date'), recurring_invoice_category_id = $category, recurring_invoice_status = 1, recurring_invoice_discount_amount = $recurring_invoice_discount, recurring_invoice_amount = " . (0 - $recurring_invoice_discount) . ", recurring_invoice_auto_send = $auto_send, recurring_invoice_currency_code = '$session_company_currency', recurring_invoice_client_id = $client_id");

    $recurring_invoice_id = mysqli_insert_id($mysqli);

    mysqli_query($mysqli,"INSERT INTO history SET history_status = 'Active', history_description = 'Recurring Invoice created', history_recurring_invoice_id = $recurring_invoice_id");

    logAudit("Recurring Invoice", "Create", "$session_name created recurring invoice $config_recurring_invoice_prefix$recurring_invoice_number - $scope", $client_id, $recurring_invoice_id);

    flashAlert("Recurring Invoice <strong>$config_recurring_invoice_prefix$recurring_invoice_number</strong> created");

    redirect("recurring_invoice.php?recurring_invoice_id=$recurring_invoice_id");

}

if (isset($_POST['edit_recurring_invoice'])) {

    validateCSRFToken();

    enforceUserPermission('module_sales', 2);

    $recurring_invoice_id = intval($_POST['recurring_invoice_id']);
    $frequency = validateRecurringFrequency($_POST['frequency']);
    $next_date = escapeSql(validateDate((string) ($_POST['next_date'] ?? '')));
    $category = intval($_POST['category']);
    $scope = escapeSql($_POST['scope']);
    $status = intval($_POST['status']);
    $recurring_invoice_discount = floatval($_POST['recurring_invoice_discount']);
    $auto_send = intval($_POST['auto_send']);

    // Get Recurring Invoice Details and Client ID for Logging
    $sql = mysqli_query($mysqli,"SELECT recurring_invoice_prefix, recurring_invoice_number, recurring_invoice_client_id FROM recurring_invoices WHERE recurring_invoice_id = $recurring_invoice_id");
    $row = mysqli_fetch_assoc($sql);
    $recurring_invoice_prefix = escapeSql($row['recurring_invoice_prefix']);
    $recurring_invoice_number = intval($row['recurring_invoice_number']);
    $client_id = intval($row['recurring_invoice_client_id']);

    enforceClientAccess();

    // The day of the month it bills on stays put unless the next date moves -
    // so a schedule on the 31st that is on the 28th this month keeps the 31st.
    // (Set before next_date: MySQL reads a column already set in the same
    // UPDATE as its new value.)
    mysqli_query($mysqli,"UPDATE recurring_invoices SET recurring_invoice_scope = '$scope', recurring_invoice_frequency = '$frequency', recurring_invoice_anchor_day = IF(recurring_invoice_next_date = '$next_date' AND recurring_invoice_anchor_day > 0, recurring_invoice_anchor_day, DAY('$next_date')), recurring_invoice_next_date = '$next_date', recurring_invoice_category_id = $category, recurring_invoice_discount_amount = $recurring_invoice_discount, recurring_invoice_auto_send = $auto_send, recurring_invoice_status = $status WHERE recurring_invoice_id = $recurring_invoice_id");

    billingRecalc('recurring', $recurring_invoice_id);

    mysqli_query($mysqli,"INSERT INTO history SET history_status = '" . ($status ? 'Active' : 'Paused') . "', history_description = 'Edited by $session_name', history_recurring_invoice_id = $recurring_invoice_id");

    logAudit("Recurring Invoice", "Edit", "$session_name edited recurring invoice $recurring_invoice_prefix$recurring_invoice_number - $scope", $client_id, $recurring_invoice_id);

    flashAlert("Recurring Invoice <strong>$recurring_invoice_prefix$recurring_invoice_number</strong> edited");

    redirect();

}

if (isset($_GET['delete_recurring_invoice'])) {

    validateCSRFToken();

    enforceUserPermission('module_sales', 3);

    $recurring_invoice_id = intval($_GET['delete_recurring_invoice']);

    // Get Recurring Invoice Details and Client ID for Logging
    $sql = mysqli_query($mysqli,"SELECT recurring_invoice_prefix, recurring_invoice_number, recurring_invoice_scope, recurring_invoice_client_id FROM recurring_invoices WHERE recurring_invoice_id = $recurring_invoice_id");
    $row = mysqli_fetch_assoc($sql);
    $recurring_invoice_prefix = escapeSql($row['recurring_invoice_prefix']);
    $recurring_invoice_number = intval($row['recurring_invoice_number']);
    $recurring_invoice_scope = escapeSql($row['recurring_invoice_scope']);
    $client_id = intval($row['recurring_invoice_client_id']);

    enforceClientAccess();

    mysqli_query($mysqli,"DELETE FROM recurring_invoices WHERE recurring_invoice_id = $recurring_invoice_id");

    // Its autopay goes with it - the nightly run would otherwise keep a card set up for nothing
    mysqli_query($mysqli,"DELETE FROM recurring_payments WHERE recurring_payment_recurring_invoice_id = $recurring_invoice_id");

    // The recurring expenses it covered are only our cost again
    mysqli_query($mysqli,"UPDATE recurring_expenses SET recurring_expense_recurring_invoice_id = NULL WHERE recurring_expense_recurring_invoice_id = $recurring_invoice_id");

    //Delete Items Associated with the Recurring
    $sql = mysqli_query($mysqli,"SELECT item_id FROM recurring_invoice_items WHERE item_recurring_invoice_id = $recurring_invoice_id");
    while($row = mysqli_fetch_assoc($sql)) {
        $item_id = intval($row['item_id']);
        mysqli_query($mysqli,"DELETE FROM recurring_invoice_items WHERE item_id = $item_id");
    }

    //Delete History Associated with the Invoice
    $sql = mysqli_query($mysqli,"SELECT history_id FROM history WHERE history_recurring_invoice_id = $recurring_invoice_id");
    while($row = mysqli_fetch_assoc($sql)) {
        $history_id = intval($row['history_id']);
        mysqli_query($mysqli,"DELETE FROM history WHERE history_id = $history_id");
    }

    logAudit("Recurring Invoice", "Delete", "$session_name deleted recurring invoice $recurring_invoice_prefix$recurring_invoice_number - $recurring_invoice_scope", $client_id);

    flashAlert("Recurring Invoice <strong>$recurring_invoice_prefix$recurring_invoice_number</strong> deleted", 'error');

    redirect();

}

/*
 * Invoice Now - the invoice for the next run, made today. The schedule moves
 * one period on from that run, so it takes its place rather than adding one.
 */
if (isset($_GET['force_recurring'])) {

    validateCSRFToken();

    enforceUserPermission('module_sales', 2);

    $recurring_invoice_id = intval($_GET['force_recurring']);
    $recurring = billingRow('recurring', $recurring_invoice_id);
    if (!$recurring) {
        flashAlert('That recurring invoice no longer exists.', 'error');
        redirect();
    }
    $client_id = intval($recurring['client_id']);

    enforceClientAccess();

    // The run the page showed - a second press (or a second tab) finds it
    // already billed instead of billing the one after
    $run = (string) ($_GET['run'] ?? '');
    $invoice_id = recurringInvoiceGenerate($recurring_invoice_id, stripslashes($session_name), true, $run !== '' ? $run : null);
    if (!$invoice_id) {
        flashAlert('The run on ' . escapeHtml($run !== '' ? $run : $recurring['recurring_invoice_next_date']) . ' was already billed - nothing more was made. It next runs on ' . escapeHtml(getFieldById('recurring_invoices', $recurring_invoice_id, 'recurring_invoice_next_date')) . '.', 'warning');
        redirect();
    }

    $invoice = billingRow('invoice', $invoice_id);
    $next = getFieldById('recurring_invoices', $recurring_invoice_id, 'recurring_invoice_next_date');
    flashAlert('<strong>' . escapeHtml(billingNumber('invoice', $invoice)) . '</strong> made from ' . escapeHtml(billingNumber('recurring', $recurring))
        . ($invoice['invoice_status'] === 'Sent' ? ' and emailed' : ' - a draft to check and send') . '. The next one is on ' . escapeHtml($next) . '.');

    redirect("invoice.php?invoice_id=$invoice_id");

}

if (isset($_POST['set_recurring_payment'])) {

    validateCSRFToken();

    enforceUserPermission('module_sales', 2);

    $recurring_invoice_id = intval($_POST['recurring_invoice_id']);
    $saved_payment_id = intval($_POST['saved_payment_id']);

    // Get Recurring Invoice Info for logging and alerting
    $sql = mysqli_query($mysqli, "SELECT recurring_invoice_amount, recurring_invoice_client_id, recurring_invoice_currency_code,
        recurring_invoice_number, recurring_invoice_prefix FROM recurring_invoices WHERE recurring_invoice_id = $recurring_invoice_id");
    $row = mysqli_fetch_assoc($sql);
    $client_id = intval($row['recurring_invoice_client_id']);
    $recurring_invoice_prefix = escapeSql($row['recurring_invoice_prefix']);
    $recurring_invoice_number = intval($row['recurring_invoice_number']);
    $recurring_invoice_currency_code = escapeSql($row['recurring_invoice_currency_code']);
    $recurring_invoice_amount = floatval($row['recurring_invoice_amount']);

    enforceClientAccess();

    if ($saved_payment_id) {

        // Get Payment provider and method
        $sql = mysqli_query($mysqli, "
            SELECT payment_provider_account, payment_provider_id, payment_provider_name,
                saved_payment_description FROM payment_providers
            LEFT JOIN client_saved_payment_methods ON saved_payment_provider_id = payment_provider_id
            WHERE saved_payment_id = $saved_payment_id
            AND saved_payment_client_id = $client_id
        ");

        $row = mysqli_fetch_assoc($sql);

        if (!$row) {
            flashAlert("Payment method not found for this client.", 'error');
            redirect();
        }

        $provider_id = intval($row['payment_provider_id']);
        $provider_name = escapeSql($row['payment_provider_name']);
        $account_id = intval($row['payment_provider_account']);
        $saved_payment_description = escapeSql($row['saved_payment_description']);

        mysqli_query($mysqli, "DELETE FROM recurring_payments WHERE recurring_payment_recurring_invoice_id = $recurring_invoice_id");
        mysqli_query($mysqli,"INSERT INTO recurring_payments SET recurring_payment_currency_code = '$recurring_invoice_currency_code', recurring_payment_account_id = $account_id, recurring_payment_method = 'Credit Card', recurring_payment_recurring_invoice_id = $recurring_invoice_id, recurring_payment_saved_payment_id = $saved_payment_id");
        // Get Payment ID for reference
        $recurring_payment_id = mysqli_insert_id($mysqli);

        logAudit("Recurring Invoice", "Auto Payment", "$session_name created Auto Pay for Recurring Invoice $recurring_invoice_prefix$recurring_invoice_number in the amount of " . numfmt_format_currency($currency_format, $recurring_invoice_amount, $recurring_invoice_currency_code), $client_id, $recurring_invoice_id);

        flashAlert("Automatic Payment <strong>$saved_payment_description</strong> enabled for Recurring Invoice $recurring_invoice_prefix$recurring_invoice_number");
    } else {
        // Delete
        mysqli_query($mysqli, "DELETE FROM recurring_payments WHERE recurring_payment_recurring_invoice_id = $recurring_invoice_id");

        logAudit("Recurring Invoice", "Auto Payment", "$session_name removed Auto Pay for Recurring Invoice $recurring_invoice_prefix$recurring_invoice_number in the amount of " . numfmt_format_currency($currency_format, $recurring_invoice_amount, $recurring_invoice_currency_code), $client_id, $recurring_invoice_id);

        flashAlert("Automatic Payment <strong>Disabled</strong> for Recurring Invoice $recurring_invoice_prefix$recurring_invoice_number", 'error');
    }

    redirect();

}

if (isExportRequest('export_recurring_invoices')) {

    validateCSRFToken();

    // Exports are reads - see CONTRIBUTING.md
    enforceUserPermission('module_sales');

    $format = resolveExportFormat($_POST['export_recurring_invoices']);

    // Filters inherited from the recurring invoices page - mirrors agent/recurring_invoices.php
    $filter_summary = [];

    if (!empty($_POST['client_id'])) {
        $client_id = intval($_POST['client_id']);
        $client_query = "AND recurring_invoice_client_id = $client_id";
        $client_name = getFieldById('clients', $client_id, 'client_name');
        $file_name_prepend = "$client_name-";
        $filter_summary['Client'] = $client_name;

        enforceClientAccess();
    } else {
        $client_query = '';
        $client_id = 0; // for Logging
        $file_name_prepend = "$session_company_name-";
    }

    // Status Filter
    if (isset($_POST['status']) && $_POST['status'] === 'inactive') {
        $status_query = "AND recurring_invoice_status = 0";
        $filter_summary['Status'] = 'Inactive';
    } elseif (isset($_POST['status']) && $_POST['status'] === 'active') {
        $status_query = "AND recurring_invoice_status = 1";
        $filter_summary['Status'] = 'Active';
    } else {
        // Default - any
        $status_query = '';
    }

    // Date Filter
    $dtf = escapeSql(!empty($_POST['dtf']) ? $_POST['dtf'] : '1970-01-01');
    $dtt = escapeSql(!empty($_POST['dtt']) ? $_POST['dtt'] : '2099-12-31');
    $date_range = formatExportDateRange($dtf, $dtt);
    if ($date_range) {
        $filter_summary['Created'] = $date_range;
    }

    // Search Filter
    $q = escapeSql($_POST['q'] ?? '');
    if (!empty($q)) {
        $filter_summary['Search'] = $_POST['q'];
    }

    $sql = mysqli_query(
        $mysqli,
        "SELECT client_name, recurring_invoice_amount, recurring_invoice_created_at,
        recurring_invoice_frequency, recurring_invoice_number, recurring_invoice_prefix,
        recurring_invoice_scope FROM recurring_invoices
        LEFT JOIN clients ON recurring_invoice_client_id = client_id
        LEFT JOIN categories ON recurring_invoice_category_id = category_id
        WHERE (CONCAT(recurring_invoice_prefix,recurring_invoice_number) LIKE '%$q%' OR recurring_invoice_frequency LIKE '%$q%' OR recurring_invoice_scope LIKE '%$q%' OR client_name LIKE '%$q%' OR category_name LIKE '%$q%')
        AND DATE(recurring_invoice_created_at) BETWEEN '$dtf' AND '$dtt'
        $status_query
        $client_query
        " . clientScopeSql('recurring_invoice_client_id') . "
        ORDER BY recurring_invoice_number ASC"
    );

    $num_rows = mysqli_num_rows($sql);

    if ($num_rows > 0) {

        guardExportPdfRowCount($format, $num_rows);

        $export = beginExport('recurring_invoices', $format, $file_name_prepend . 'RecurringInvoices', 'Recurring Invoices', summarizeExportFilters($filter_summary));

        while ($row = mysqli_fetch_assoc($sql)) {
            $row['recurring_invoice_number_display'] = $row['recurring_invoice_prefix'] . $row['recurring_invoice_number'];
            $row['recurring_invoice_frequency_display'] = recurringInvoiceFrequencies()[$row['recurring_invoice_frequency']] ?? ucfirst((string) $row['recurring_invoice_frequency']);
            addExportRow($export, $row);
        }

        finishExport($export);
    }

    logAudit("Recurring Invoice", "Export", "$session_name exported $num_rows recurring invoice(s) to a " . strtoupper($format) . " file", $client_id);

    exit;

}

if (isset($_GET['recurring_invoice_email_notify'])) {

    validateCSRFToken();

    enforceUserPermission('module_sales', 2);

    $recurring_invoice_email_notify = intval($_GET['recurring_invoice_email_notify']);
    $recurring_invoice_id = intval($_GET['recurring_invoice_id']);

    $sql = mysqli_query($mysqli,"SELECT recurring_invoice_client_id, recurring_invoice_number, recurring_invoice_prefix FROM recurring_invoices WHERE recurring_invoice_id = $recurring_invoice_id");
    $row = mysqli_fetch_assoc($sql);
    $recurring_invoice_prefix = escapeSql($row['recurring_invoice_prefix']);
    $recurring_invoice_number = intval($row['recurring_invoice_number']);
    $client_id = intval($row['recurring_invoice_client_id']);

    enforceClientAccess();

    mysqli_query($mysqli,"UPDATE recurring_invoices SET recurring_invoice_email_notify = $recurring_invoice_email_notify WHERE recurring_invoice_id = $recurring_invoice_id");

    // Wording
    if ($recurring_invoice_email_notify) {
        $notify_wording = "On";
    } else {
        $notify_wording = "Off";
    }

    logAudit("Recurring Invoice", "Edit", "$session_name turned $notify_wording Email Notifications for Recurring Invoice $recurring_invoice_prefix$recurring_invoice_number", $client_id, $recurring_invoice_id);

    flashAlert("Email Notifications <strong>$notify_wording</strong>", 'error');

    redirect();

}
