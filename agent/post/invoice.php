<?php

/*
 * ITFlow - GET/POST request handler for invoices
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['add_invoice'])) {

    validateCSRFToken();

    enforceUserPermission('module_sales', 2);

    require_once 'invoice_model.php';

    $client_id = intval($_POST['client_id']);

    enforceClientAccess();

    $invoice_amount = 0 - $invoice_discount;     // Calc amount if discount is applied, otherwise wrongly shows 0

    // Get Net Terms
    $client_net_terms = intval(getFieldById('clients', $client_id, 'client_net_terms'));

    // Atomically increment and get the new invoice number
    mysqli_query($mysqli, "
        UPDATE settings
        SET
            config_invoice_next_number = LAST_INSERT_ID(config_invoice_next_number),
            config_invoice_next_number = config_invoice_next_number + 1
        WHERE company_id = 1
    ");

    $invoice_number = mysqli_insert_id($mysqli);

    //Generate a unique URL key for clients to access
    $url_key = randomString(32);

    mysqli_query($mysqli,"INSERT INTO invoices SET invoice_prefix = '$config_invoice_prefix', invoice_number = $invoice_number, invoice_scope = '$scope', invoice_date = '$date', invoice_due = DATE_ADD('$date', INTERVAL $client_net_terms day), invoice_discount_amount = '$invoice_discount', invoice_amount = '$invoice_amount', invoice_currency_code = '$session_company_currency', invoice_category_id = $category, invoice_status = 'Draft', invoice_url_key = '$url_key', invoice_client_id = $client_id");

    $invoice_id = mysqli_insert_id($mysqli);

    mysqli_query($mysqli,"INSERT INTO history SET history_status = 'Draft', history_description = 'Invoice created by $session_name', history_invoice_id = $invoice_id");

    logAudit("Invoice", "Create", "$session_name created Invoice $config_invoice_prefix$invoice_number - $scope", $client_id, $invoice_id);

    triggerCustomAction('invoice_create', $invoice_id);

    flashAlert("Invoice <strong>$config_invoice_prefix$invoice_number</strong> created");

    redirect("invoice.php?invoice_id=$invoice_id");

}

if (isset($_POST['edit_invoice'])) {

    validateCSRFToken();

    enforceUserPermission('module_sales', 2);

    require_once 'invoice_model.php';

    $invoice_id = intval($_POST['invoice_id']);
    $due = escapeSql($_POST['due']);

    // Get Invoice Number and Prefix and Client ID for Logging
    $sql = mysqli_query($mysqli,"SELECT invoice_prefix, invoice_number, invoice_client_id FROM invoices WHERE invoice_id = $invoice_id");
    $row = mysqli_fetch_assoc($sql);
    $invoice_prefix = escapeSql($row['invoice_prefix']);
    $invoice_number = intval($row['invoice_number']);
    $client_id = intval($row['invoice_client_id']);

    enforceClientAccess();

    mysqli_query($mysqli,"UPDATE invoices SET invoice_scope = '$scope', invoice_date = '$date', invoice_due = '$due', invoice_category_id = $category, invoice_discount_amount = $invoice_discount WHERE invoice_id = $invoice_id");

    // The total from the lines less the new discount - and the status, if it has been paid against
    billingRecalc('invoice', $invoice_id);

    logAudit("Invoice", "Edit", "$session_name edited Invoice $invoice_prefix$invoice_number - $scope", $client_id, $invoice_id);

    flashAlert("Invoice <strong>$invoice_prefix$invoice_number</strong> edited");

    redirect();

}

if (isset($_POST['add_invoice_copy'])) {

    validateCSRFToken();

    enforceUserPermission('module_sales', 2);

    $invoice_id = intval($_POST['invoice_id']);
    $date = escapeSql($_POST['date']);

    //Get Net Terms
    $sql = mysqli_query($mysqli,"SELECT client_net_terms, invoice_amount, invoice_category_id, invoice_client_id,
        invoice_currency_code, invoice_discount_amount, invoice_note, invoice_number,
        invoice_prefix, invoice_scope FROM clients, invoices WHERE client_id = invoice_client_id AND invoice_id = $invoice_id");
    $row = mysqli_fetch_assoc($sql);
    $client_net_terms = intval($row['client_net_terms']); // 0 is On Receipt
    $invoice_scope = escapeSql($row['invoice_scope']);
    $invoice_discount_amount = floatval($row['invoice_discount_amount']);
    $invoice_amount = floatval($row['invoice_amount']);
    $invoice_currency_code = escapeSql($row['invoice_currency_code']);
    $invoice_note = escapeSql($row['invoice_note']);
    $client_id = intval($row['invoice_client_id']);
    $category_id = intval($row['invoice_category_id']);
    $old_invoice_prefix = escapeSql($row['invoice_prefix']);
    $old_invoice_number = intval($row['invoice_number']);

    enforceClientAccess();

    // Its products that track stock come off the shelf again - there has to be enough
    $copy_items = billingItems('invoice', $invoice_id);
    $shortfall = productStockShortfall($copy_items);
    if ($shortfall !== '') {
        flashAlert(escapeHtml($shortfall) . ' Add stock before copying it.', 'error');
        redirect();
    }

    // Atomically increment and get the new invoice number
    mysqli_query($mysqli, "
        UPDATE settings
        SET
            config_invoice_next_number = LAST_INSERT_ID(config_invoice_next_number),
            config_invoice_next_number = config_invoice_next_number + 1
        WHERE company_id = 1
    ");

    $new_invoice_number = mysqli_insert_id($mysqli);

    //Generate a unique URL key for clients to access
    $url_key = randomString(32);

    mysqli_query($mysqli,"INSERT INTO invoices SET invoice_prefix = '$config_invoice_prefix', invoice_number = $new_invoice_number, invoice_scope = '$invoice_scope', invoice_date = '$date', invoice_due = DATE_ADD('$date', INTERVAL $client_net_terms day), invoice_category_id = $category_id, invoice_status = 'Draft', invoice_discount_amount = $invoice_discount_amount, invoice_amount = $invoice_amount, invoice_currency_code = '$invoice_currency_code', invoice_note = '$invoice_note', invoice_url_key = '$url_key', invoice_client_id = $client_id");

    $new_invoice_id = mysqli_insert_id($mysqli);

    mysqli_query($mysqli,"INSERT INTO history SET history_status = 'Draft', history_description = 'Copied from $old_invoice_prefix$old_invoice_number by $session_name', history_invoice_id = $new_invoice_id");

    // The same lines, linked to the same products
    billingCopyLines($copy_items, 'invoice', $new_invoice_id);

    logAudit("Invoice", "Create", "$session_name created new Invoice $config_invoice_prefix$new_invoice_number from $old_invoice_prefix$old_invoice_number", $client_id, $new_invoice_id);

    triggerCustomAction('invoice_create', $new_invoice_id);

    flashAlert("Created new Invoice <strong>$config_invoice_prefix$new_invoice_number</strong> from <strong>$old_invoice_prefix$old_invoice_number</strong>");

    redirect("invoice.php?invoice_id=$new_invoice_id");

}

if (isset($_POST['mark_invoice_sent'])) {

    validateCSRFToken();

    enforceUserPermission('module_sales', 2);

    $invoice_id = intval($_POST['invoice_id']);

    // Get Invoice Number and Prefix and Client ID for Logging
    $sql = mysqli_query($mysqli,"SELECT invoice_prefix, invoice_number, invoice_client_id FROM invoices WHERE invoice_id = $invoice_id");
    $row = mysqli_fetch_assoc($sql);
    $invoice_prefix = escapeSql($row['invoice_prefix']);
    $invoice_number = intval($row['invoice_number']);
    $client_id = intval($row['invoice_client_id']);

    enforceClientAccess();

    // The modal offers a fixed list, so anything else is a tampered form
    $sent_method = $_POST['sent_method'] ?? '';
    if (!in_array($sent_method, getSentMethods(), true)) {
        flashAlert("Invalid delivery method", 'error');
        redirect();
    }
    $sent_method = escapeSql($sent_method);

    $note = escapeSql(substr(trim($_POST['note'] ?? ''), 0, 500));

    mysqli_query($mysqli,"UPDATE invoices SET invoice_status = 'Sent' WHERE invoice_id = $invoice_id");

    $history_description = "Invoice marked sent by $session_name - $sent_method";
    if (!empty($note)) {
        $history_description .= "\nNote: $note";
    }

    logHistory('Sent', $history_description, $invoice_id);

    logAudit("Invoice", "Edit", "$session_name marked invoice $invoice_prefix$invoice_number sent - $sent_method", $client_id, $invoice_id);

    flashAlert("Invoice marked sent");

    redirect();

}

if (isset($_GET['mark_invoice_non-billable'])) {

    validateCSRFToken();

    enforceUserPermission('module_sales', 2);

    $invoice_id = intval($_GET['mark_invoice_non-billable']);

    // Get Invoice Number and Prefix and Client ID for Logging
    $sql = mysqli_query($mysqli,"SELECT invoice_prefix, invoice_number, invoice_client_id FROM invoices WHERE invoice_id = $invoice_id");
    $row = mysqli_fetch_assoc($sql);
    $invoice_prefix = escapeSql($row['invoice_prefix']);
    $invoice_number = intval($row['invoice_number']);
    $client_id = intval($row['invoice_client_id']);

    enforceClientAccess();

    mysqli_query($mysqli,"UPDATE invoices SET invoice_status = 'Non-Billable' WHERE invoice_id = $invoice_id");

    mysqli_query($mysqli,"INSERT INTO history SET history_status = 'Non-Billable', history_description = 'INVOICE marked Non-Billable', history_invoice_id = $invoice_id");

    logAudit("Invoice", "Edit", "$session_name marked invoice $invoice_prefix$invoice_number Non-Billable", $client_id, $invoice_id);

    flashAlert("Invoice marked Non-Billable");

    redirect();

}

if (isset($_GET['cancel_invoice'])) {

    validateCSRFToken();

    enforceUserPermission('module_sales', 2);

    $invoice_id = intval($_GET['cancel_invoice']);

    // Get Invoice Number and Prefix and Client ID for Logging
    $sql = mysqli_query($mysqli,"SELECT invoice_prefix, invoice_number, invoice_client_id FROM invoices WHERE invoice_id = $invoice_id");
    $row = mysqli_fetch_assoc($sql);
    $invoice_prefix = escapeSql($row['invoice_prefix']);
    $invoice_number = intval($row['invoice_number']);
    $client_id = intval($row['invoice_client_id']);

    enforceClientAccess();

    mysqli_query($mysqli,"UPDATE invoices SET invoice_status = 'Cancelled' WHERE invoice_id = $invoice_id");

    mysqli_query($mysqli,"INSERT INTO history SET history_status = 'Cancelled', history_description = 'Invoice cancelled by $session_name', history_invoice_id = $invoice_id");

    // What it took off the shelf goes back
    productStockReturnInvoice($invoice_id, stripslashes("$invoice_prefix$invoice_number cancelled"));

    logAudit("Invoice", "Edit", "$session_name cancelled invoice $invoice_prefix$invoice_number", $client_id, $invoice_id);

    flashAlert("Invoice <strong>$invoice_prefix$invoice_number</strong> cancelled", 'error');

    redirect();

}

if (isset($_GET['delete_invoice'])) {

    validateCSRFToken();

    enforceUserPermission('module_sales', 3);

    $invoice_id = intval($_GET['delete_invoice']);

    // Get Invoice Number and Prefix and Client ID for Logging
    $sql = mysqli_query($mysqli,"SELECT invoice_prefix, invoice_number, invoice_client_id FROM invoices WHERE invoice_id = $invoice_id");
    $row = mysqli_fetch_assoc($sql);
    $invoice_prefix = escapeSql($row['invoice_prefix']);
    $invoice_number = intval($row['invoice_number']);
    $client_id = intval($row['invoice_client_id']);

    enforceClientAccess();

    // Its payments go with it - not once one has cleared on a reconciled statement
    $lock = reconcileInvoiceLock($invoice_id);
    if ($lock) {
        flashAlert(escapeHtml("A payment on $invoice_prefix$invoice_number cleared on the {$lock['account_name']} statement to " . date('M j, Y', strtotime($lock['reconciliation_statement_date'])) . ' - cancel the invoice instead, or undo that reconciliation to delete it'), 'error');
        redirect();
    }

    // What it took off the shelf goes back before its lines go
    productStockReturnInvoice($invoice_id, stripslashes("$invoice_prefix$invoice_number deleted"));

    mysqli_query($mysqli,"DELETE FROM invoices WHERE invoice_id = $invoice_id");

    //Delete Items Associated with the Invoice
    $sql = mysqli_query($mysqli,"SELECT item_id FROM invoice_items WHERE item_invoice_id = $invoice_id");
    while($row = mysqli_fetch_assoc($sql)) {
        $item_id = intval($row['item_id']);
        mysqli_query($mysqli,"DELETE FROM invoice_items WHERE item_id = $item_id");
    }

    //Delete History Associated with the Invoice
    $sql = mysqli_query($mysqli,"SELECT history_id FROM history WHERE history_invoice_id = $invoice_id");
    while($row = mysqli_fetch_assoc($sql)) {
        $history_id = intval($row['history_id']);
        mysqli_query($mysqli,"DELETE FROM history WHERE history_id = $history_id");
    }

    //Delete Payments Associated with the Invoice
    $sql = mysqli_query($mysqli,"SELECT payment_id FROM payments WHERE payment_invoice_id = $invoice_id");
    while($row = mysqli_fetch_assoc($sql)) {
        $payment_id = intval($row['payment_id']);
        mysqli_query($mysqli,"DELETE FROM payments WHERE payment_id = $payment_id");
    }

    //unlink tickets from invoice
    mysqli_query($mysqli,"UPDATE tickets SET ticket_invoice_id = 0 WHERE ticket_invoice_id = $invoice_id");

    // Assets made from its hardware stay on the client's assets, unlinked
    mysqli_query($mysqli, "UPDATE assets SET asset_invoice_id = 0, asset_invoice_item_id = 0 WHERE asset_invoice_id = $invoice_id");

    // Expenses and trips billed on it are to bill again
    mysqli_query($mysqli, "UPDATE expenses SET expense_invoice_id = NULL, expense_invoice_item_id = NULL WHERE expense_invoice_id = $invoice_id");
    mysqli_query($mysqli, "UPDATE trips SET trip_invoice_id = 0, trip_invoice_item_id = 0 WHERE trip_invoice_id = $invoice_id");

    logAudit("Invoice", "Delete", "$session_name deleted invoice $invoice_prefix$invoice_number", $client_id);

    flashAlert("Invoice <strong>$invoice_prefix$invoice_number</strong> deleted", 'error');

    redirect();

}

if (isset($_POST['email_invoice'])) {

    validateCSRFToken();

    enforceUserPermission('module_sales', 2);

    $invoice = billingRow('invoice', intval($_POST['invoice_id'] ?? 0));
    if (!$invoice) {
        flashAlert('That invoice no longer exists.', 'error');
        redirect('invoices.php');
    }
    $invoice_id = intval($invoice['invoice_id']);
    $client_id = intval($invoice['client_id']);
    $number = billingNumber('invoice', $invoice);

    enforceClientAccess();

    // Two ways in. Quick Send skips the window and goes to the primary and
    // billing contacts; the window posts the contacts picked. Either way only
    // this client's contacts can match, so a tampered id finds nobody.
    if (!empty($_POST['quick_send'])) {
        $recipients = billingEmailRecipients($client_id);
        if (!$recipients) {
            flashAlert("No default contacts to quick send to - use Send to choose recipients", 'error');
            redirect();
        }
    } else {
        $recipients = billingEmailRecipients($client_id, (array) ($_POST['contacts'] ?? []));
        if (!$recipients) {
            flashAlert(empty($_POST['contacts']) ? "Select at least one contact to send to" : "None of the selected contacts have a usable email address", 'error');
            redirect();
        }
    }

    // The invoice, or a reminder of what is still owed - with the agent's note and the PDF
    $kind = ($_POST['kind'] ?? '') === 'reminder' ? 'reminder' : 'invoice';
    $note = trim(mb_substr((string) ($_POST['note'] ?? ''), 0, 2000));
    $attach_pdf = !empty($_POST['quick_send']) ? billingReminderRules()['attach_pdf'] : !empty($_POST['attach_pdf']);
    [$subject, $email] = billingInvoiceEmail($invoice, ['kind' => $kind, 'note' => $note]);
    $attach = [];
    if ($attach_pdf && ($file = billingEmailPdf(billingPdf('invoice', $invoice)))) {
        $attach[] = $file;
    }
    $sent = billingEmailQueue($recipients, $subject, $email, ['attach' => $attach]);
    $reminder = $kind === 'reminder' && $email['kind'] === 'Payment reminder';

    $by = stripslashes($session_name);
    billingLog('invoice', $invoice_id, $invoice['invoice_status'] === 'Draft' ? 'Sent' : $invoice['invoice_status'],
        ($reminder ? 'Reminder emailed' : 'Invoice emailed') . " by $by to " . implode(', ', $sent) . ($attach ? ' with the PDF' : ''));
    foreach ($recipients as $recipient) {
        logAudit("Invoice", "Email", "$session_name emailed " . escapeSql($recipient['email']) . ($reminder ? " a reminder for" : "") . " invoice " . escapeSql($number), $client_id, $invoice_id);
    }

    // Sending a draft sends it - anything further along keeps its status
    if ($invoice['invoice_status'] === 'Draft') {
        mysqli_query($mysqli, "UPDATE invoices SET invoice_status = 'Sent' WHERE invoice_id = $invoice_id");
    }

    flashAlert(($reminder ? 'Reminder' : 'Invoice') . " queued to " . count($sent) . " " . (count($sent) === 1 ? "recipient" : "recipients"));

    redirect();

}


if (isset($_POST['send_statement'])) {

    validateCSRFToken();

    enforceUserPermission('module_sales', 2);

    $client_id = intval($_POST['client_id'] ?? 0);

    enforceClientAccess();

    // The period - a blank From is the whole history, a blank To is today
    $statement_to = trim((string) ($_POST['dtt'] ?? ''));
    $statement_from = trim((string) ($_POST['dtf'] ?? ''));
    $statement = billingStatement($client_id, $statement_from !== '' ? validateDate($statement_from) : '', $statement_to !== '' ? validateDate($statement_to) : '', !empty($_POST['include_paid']));
    if (!$statement['client']) {
        flashAlert('That client no longer exists.', 'error');
        redirect();
    }
    if (!$statement['invoices']) {
        flashAlert("No invoices to report for that period - nothing sent", 'error');
        redirect();
    }

    $recipients = billingEmailRecipients($client_id, (array) ($_POST['contacts'] ?? []));
    if (!$recipients) {
        flashAlert(empty($_POST['contacts']) ? "Select at least one contact to send to" : "None of the selected contacts have a usable email address", 'error');
        redirect();
    }

    // functions/billing_email.php - the statement, its PDF when asked for
    $sent = billingStatementSend($statement, array_column($recipients, 'id'), [
        'note' => trim(mb_substr((string) ($_POST['note'] ?? ''), 0, 2000)),
        'attach' => !empty($_POST['attach_pdf']),
    ]);

    $client_name = escapeSql($statement['client']['client_name']);
    logAudit("Client", "Statement", "$session_name emailed a statement (" . escapeSql(billingStatementPeriod($statement)) . ", " . count($statement['invoices']) . " invoices, "
        . escapeSql(billingMoney($statement['total'], $statement['currency'])) . " due) to " . escapeSql(implode(', ', $sent)), $client_id);

    flashAlert("Statement queued to " . count($sent) . " " . (count($sent) === 1 ? "recipient" : "recipients") . " - " . count($statement['invoices']) . " " . (count($statement['invoices']) === 1 ? "invoice" : "invoices") . ", <strong>" . escapeHtml(billingMoney($statement['total'], $statement['currency'])) . "</strong> due");

    redirect();

}

if (isset($_GET['export_client_statement_pdf'])) {

    validateCSRFToken();

    enforceUserPermission('module_sales');

    $client_id = intval($_GET['export_client_statement_pdf']);

    enforceClientAccess();

    $statement_to = trim((string) ($_GET['dtt'] ?? ''));
    $statement_from = trim((string) ($_GET['dtf'] ?? ''));
    $statement = billingStatement($client_id, $statement_from !== '' ? validateDate($statement_from) : '', $statement_to !== '' ? validateDate($statement_to) : '', !empty($_GET['include_paid']));
    if (!$statement['client']) {
        flashAlert('That client no longer exists.', 'error');
        redirect();
    }

    billingPdfSend(billingStatementPdf($statement));

}

if (isExportRequest('export_invoices')) {

    validateCSRFToken();

    enforceUserPermission('module_sales');

    $format = resolveExportFormat($_POST['export_invoices']);

    // Filters inherited from the invoices page - mirrors agent/invoices.php
    $filter_summary = [];

    if (!empty($_POST['client_id'])) {
        $client_id = intval($_POST['client_id']);
        $client_query = "AND invoice_client_id = $client_id";
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
    $overdue_query = '';
    if (!empty($_POST['status']) && $_POST['status'] == 'Draft') {
        $status_query = "invoice_status = 'Draft'";
        $filter_summary['Status'] = 'Draft';
    } elseif (!empty($_POST['status']) && $_POST['status'] == 'Unpaid') {
        $status_query = "invoice_status = 'Sent' OR invoice_status = 'Viewed' OR invoice_status = 'Partial'";
        $filter_summary['Status'] = 'Unpaid';
    } elseif (!empty($_POST['status']) && $_POST['status'] == 'Overdue') {
        $status_query = "invoice_status = 'Sent' OR invoice_status = 'Viewed' OR invoice_status = 'Partial'";
        $overdue_query = "AND (invoice_due < CURDATE())";
        $filter_summary['Status'] = 'Overdue';
    } elseif (!empty($_POST['status']) && $_POST['status'] == 'Paid') {
        $status_query = "invoice_status = 'Paid'";
        $filter_summary['Status'] = 'Paid';
    } elseif (!empty($_POST['status']) && $_POST['status'] == 'Cancelled') {
        $status_query = "invoice_status IN ('Cancelled', 'Non-Billable')";
        $filter_summary['Status'] = 'Cancelled or non-billable';
    } else {
        // Default - any
        $status_query = "invoice_status LIKE '%'";
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

    // Date Filter - drives the file name too, when a real range is in play
    $dtf = escapeSql(!empty($_POST['dtf']) ? $_POST['dtf'] : '1970-01-01');
    $dtt = escapeSql(!empty($_POST['dtt']) ? $_POST['dtt'] : '2099-12-31');
    $date_range = formatExportDateRange($dtf, $dtt);
    if ($date_range) {
        $filter_summary['Issued'] = $date_range;
        $file_name_append = "-$dtf-to-$dtt";
    } else {
        $file_name_append = '';
    }

    // Search Filter
    $q = escapeSql($_POST['q'] ?? '');
    if (!empty($q)) {
        $filter_summary['Search'] = $_POST['q'];
    }

    // Get records from database - same shape as the invoices page list query
    $sql = mysqli_query(
        $mysqli,
        "SELECT * FROM invoices
        LEFT JOIN clients ON invoice_client_id = client_id
        LEFT JOIN categories ON invoice_category_id = category_id
        WHERE ($status_query)
        $overdue_query
        $category_query
        AND DATE(invoice_date) BETWEEN '$dtf' AND '$dtt'
        AND (CONCAT(invoice_prefix,invoice_number) LIKE '%$q%' OR invoice_scope LIKE '%$q%' OR client_name LIKE '%$q%' OR invoice_status LIKE '%$q%' OR invoice_amount LIKE '%$q%' OR category_name LIKE '%$q%')
        " . clientScopeSql('invoice_client_id') . "
        $client_query
        ORDER BY invoice_number ASC"
    );

    $num_rows = mysqli_num_rows($sql);

    if ($num_rows > 0) {

        guardExportPdfRowCount($format, $num_rows);

        $export = beginExport('invoices', $format, $file_name_prepend . 'Invoices' . $file_name_append, 'Invoices', summarizeExportFilters($filter_summary));

        while ($row = mysqli_fetch_assoc($sql)) {
            $row['invoice_number_display'] = $row['invoice_prefix'] . $row['invoice_number'];

            // Subtotal, tax and tax by part are opt-in too
            if (isset($export['columns']['invoice_subtotal']) || isset($export['columns']['invoice_tax']) || isset($export['columns']['invoice_tax_parts'])) {
                $invoice_items = billingItems('invoice', $row['invoice_id']);
                $row['invoice_subtotal'] = round(array_sum(array_column($invoice_items, 'item_subtotal')), 2);
                $row['invoice_tax'] = round(array_sum(array_column($invoice_items, 'item_tax')), 2);
                $tax_parts = [];
                foreach (billingTaxParts($invoice_items)['parts'] as $label => $amount) {
                    $tax_parts[] = "$label: " . number_format($amount, 2, '.', '');
                }
                $row['invoice_tax_parts'] = implode('; ', $tax_parts);
            }

            // Paid / balance are opt-in columns - only pay for the lookup if asked
            if (isset($export['columns']['amount_paid']) || isset($export['columns']['invoice_balance'])) {
                $invoice_id = intval($row['invoice_id']);
                $payment_row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT SUM(payment_amount) AS amount_paid FROM payments WHERE payment_invoice_id = $invoice_id AND payment_archived_at IS NULL"));
                $row['amount_paid'] = floatval($payment_row['amount_paid']);
                $row['invoice_balance'] = floatval($row['invoice_amount']) - $row['amount_paid'];
            }

            addExportRow($export, $row);
        }

        finishExport($export);
    }

    logAudit("Invoice", "Export", "$session_name exported $num_rows invoice(s) to a " . strtoupper($format) . " file", $client_id);

    exit;

}

/*
 * Put tickets on an invoice - billable ones of the same client that are
 * resolved or closed and not on an invoice yet. ticket_ids[] (or ticket_id).
 */
/*
 * Add Tickets: put the client's unbilled tickets on this invoice - with a
 * line for each one's time (or one for all), or, with bill_time off, without
 * lines when the work is already on the invoice some other way.
 */
if (isset($_POST['add_ticket_to_invoice'])) {

    validateCSRFToken();

    enforceUserPermission('module_sales', 2);

    $invoice_id = intval($_POST['invoice_id']);
    $invoice = billingRow('invoice', $invoice_id);
    if (!$invoice) {
        flashAlert('That invoice no longer exists.', 'error');
        redirect();
    }
    $client_id = intval($invoice['client_id']);

    enforceClientAccess();

    $hours = [];
    foreach (array_unique(array_filter(array_map('intval', (array) ($_POST['ticket_ids'] ?? [$_POST['ticket_id'] ?? 0])))) as $ticket_id) {
        $hours[$ticket_id] = $_POST['hours'][$ticket_id] ?? null;
    }
    $bill_time = !empty($_POST['bill_time']);
    $result = billingTimeInvoice($client_id, $hours, [
        'invoice_id' => $invoice_id,
        'item_name' => (string) ($_POST['item_name'] ?? ''),
        'price' => (string) ($_POST['price'] ?? ''),
        'tax_id' => intval($_POST['tax_id'] ?? 0),
        'combine' => ($_POST['lines'] ?? '') === 'one',
        'lines' => $bill_time,
        'by' => stripslashes($session_name),
    ], $error);
    if (!$result) {
        flashAlert(escapeHtml($error), 'error');
        redirect("invoice.php?invoice_id=$invoice_id");
    }

    $count = count($result['tickets']);
    $what = $count === 1 ? 'Ticket <strong>' . escapeHtml($result['tickets'][0]) . '</strong>' : "<strong>$count tickets</strong>";
    flashAlert($bill_time
        ? "$what billed - " . billingQuantity($result['hours']) . ' h' . ($result['lines'] ? ' on ' . $result['lines'] . ' line' . ($result['lines'] === 1 ? '' : 's') : ', with no charge')
        : "$what added to the invoice, without lines");

    redirect("invoice.php?invoice_id=$invoice_id" . ($bill_time && $result['lines'] ? '#items' : ''));

}

/*
 * Bill the client's ticked expenses on this invoice, a line each, at the
 * price given for each (expense_price[id]; blank is its cost with its
 * markup), with the tax picked.
 */
if (isset($_POST['invoice_add_expenses'])) {

    validateCSRFToken();

    enforceUserPermission('module_sales', 2);
    enforceUserPermission('module_financial', 2);

    $invoice_id = intval($_POST['invoice_id'] ?? 0);
    $invoice = billingRow('invoice', $invoice_id);
    if (!$invoice) {
        flashAlert('That invoice no longer exists.', 'error');
        redirect();
    }
    $client_id = intval($invoice['client_id']);

    enforceClientAccess();

    $prices = [];
    foreach (array_unique(array_filter(array_map('intval', (array) ($_POST['expense_ids'] ?? [])))) as $expense_id) {
        $prices[$expense_id] = $_POST['expense_price'][$expense_id] ?? null;
    }
    $result = expenseBillInvoice($client_id, $prices, [
        'invoice_id' => $invoice_id,
        'tax_id' => intval($_POST['tax_id'] ?? 0),
        'by' => stripslashes($session_name),
    ], $error);
    if (!$result) {
        flashAlert(escapeHtml($error), 'error');
        redirect("invoice.php?invoice_id=$invoice_id");
    }

    flashAlert(($result['expenses'] === 1 ? '<strong>1 expense</strong>' : "<strong>{$result['expenses']} expenses</strong>") . ' billed - '
        . escapeHtml(billingMoney($result['amount'], $invoice['invoice_currency_code'])) . ' on ' . escapeHtml(billingMoney($result['cost'], $invoice['invoice_currency_code'])) . ' of cost');

    redirect("invoice.php?invoice_id=$invoice_id#items");

}

if (isset($_GET['remove_ticket_from_invoice'])) {

    validateCSRFToken();

    enforceUserPermission('module_sales', 2);

    $invoice_id = intval($_GET['invoice_id']);
    $ticket_id = intval($_GET['ticket_id']);

    $invoice = billingRow('invoice', $invoice_id);
    $ticket = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT ticket_prefix, ticket_number, ticket_client_id FROM tickets WHERE ticket_id = $ticket_id AND ticket_invoice_id = $invoice_id"));
    if (!$invoice || !$ticket) {
        flashAlert('That ticket is not on this invoice.', 'error');
        redirect();
    }
    $client_id = intval($invoice['client_id']);

    enforceClientAccess();

    mysqli_query($mysqli,"UPDATE tickets SET ticket_invoice_id = 0 WHERE ticket_id = $ticket_id");

    $ticket_number = $ticket['ticket_prefix'] . $ticket['ticket_number'];
    logTicketHistory($ticket_id, escapeSql('Taken off invoice ' . billingNumber('invoice', $invoice) . " by $session_name - unbilled again"));
    billingLog('invoice', $invoice_id, $invoice['invoice_status'], "Ticket $ticket_number taken off by $session_name");
    logAudit("Invoice", "Edit", escapeSql("$session_name took ticket $ticket_number off invoice " . billingNumber('invoice', $invoice)), $client_id, $invoice_id);

    flashAlert("Ticket <strong>" . escapeHtml($ticket_number) . "</strong> taken off the invoice - it is unbilled again");

    redirect("invoice.php?invoice_id=$invoice_id");

}

if (isset($_POST['invoice_assets_save'])) {

    validateCSRFToken();

    enforceUserPermission('module_sales');
    enforceUserPermission('module_support', 2);

    $invoice = billingRow('invoice', intval($_POST['invoice_id'] ?? 0));
    if (!$invoice) {
        flashAlert('That invoice no longer exists.', 'error');
        redirect('invoices.php');
    }
    $invoice_id = intval($invoice['invoice_id']);
    $client_id = intval($invoice['client_id']);

    enforceClientAccess();

    // functions/products.php - checks every row before it adds or links any
    $done = productSaleAssets($invoice_id, (array) ($_POST['lines'] ?? []), (array) ($_POST['units'] ?? []), [
        'status' => (string) ($_POST['status'] ?? ''),
        'location' => intval($_POST['location'] ?? 0),
    ], $error);
    if ($done === null) {
        flashAlert(escapeHtml($error), 'error');
        redirect();
    }

    $parts = [];
    if ($done['added']) {
        $parts[] = "<strong>{$done['added']}</strong> added";
    }
    if ($done['linked']) {
        $parts[] = "<strong>{$done['linked']}</strong> linked";
    }
    flashAlert(escapeHtml(billingNumber('invoice', $invoice)) . " hardware on " . escapeHtml($invoice['client_name']) . "'s assets - " . implode(', ', $parts));

    redirect();

}

if (isset($_GET['export_invoice_pdf'])) {

    validateCSRFToken();

    enforceUserPermission('module_sales');

    $invoice = billingRow('invoice', intval($_GET['export_invoice_pdf']));
    if (!$invoice) {
        flashAlert('That invoice no longer exists.', 'error');
        redirect('invoices.php');
    }
    $client_id = intval($invoice['client_id']);

    enforceClientAccess();

    billingPdfSend(billingPdf('invoice', $invoice));

}

if (isset($_GET['export_invoice_packing_slip'])) {

    validateCSRFToken();

    enforceUserPermission('module_sales');

    $invoice = billingRow('invoice', intval($_GET['export_invoice_packing_slip']));
    if (!$invoice) {
        flashAlert('That invoice no longer exists.', 'error');
        redirect('invoices.php');
    }
    $client_id = intval($invoice['client_id']);

    enforceClientAccess();

    billingPdfSend(billingPdf('invoice', $invoice, 'packing'));

}

if (isset($_POST['bulk_edit_invoice_category'])) {

    validateCSRFToken();

    enforceUserPermission('module_sales', 2);

    $category_id = intval($_POST['bulk_category_id']);

    // Get Category name for logging and Notification
    $category_name = escapeSql(getFieldById('categories', $category_id, 'category_name'));

    // Assign Income category to Selected Invoices
    if (isset($_POST['invoice_ids'])) {

        // Get Selected Count
        $count = count($_POST['invoice_ids']);

        foreach($_POST['invoice_ids'] as $invoice_id) {
            $invoice_id = intval($invoice_id);

            // Get Invoice Details for Logging
            $sql = mysqli_query($mysqli,"SELECT invoice_client_id, invoice_number, invoice_prefix, invoice_scope FROM invoices WHERE invoice_id = $invoice_id");
            $row = mysqli_fetch_assoc($sql);
            $invoice_prefix = escapeSql($row['invoice_prefix']);
            $invoice_number = intval($row['invoice_number']);
            $invoice_scope = escapeSql($row['invoice_scope']);
            $client_id = intval($row['invoice_client_id']);

            enforceClientAccess();

            mysqli_query($mysqli,"UPDATE invoices SET invoice_category_id = $category_id WHERE invoice_id = $invoice_id");

            logAudit("Invoice", "Edit", "$session_name assigned Invoice $invoice_prefix$invoice_number to category $category_name", $client_id, $invoice_id);

        } // End Assign Loop

        logAudit("Invoice", "Bulk Edit", "$session_name assigned $count invoices to category $category_name");

        flashAlert("Assigned income category <strong>$category_name</strong> to <strong>$count</strong> invoice(s)");
    }

    redirect();

}
