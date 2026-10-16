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

        mysqli_query($mysqli,"INSERT INTO invoice_items SET item_name = '$item_name', item_description = '$item_description', item_quantity = $item_quantity, item_price = $item_price, item_subtotal = $item_subtotal, item_tax = $item_tax, item_total = $item_total, item_order = $item_order, item_tax_id = $tax_id, item_invoice_id = $new_invoice_id");
    }

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

    logAudit("Invoice", "Delete", "$session_name deleted invoice $invoice_prefix$invoice_number", $client_id);

    flashAlert("Invoice <strong>$invoice_prefix$invoice_number</strong> deleted", 'error');

    redirect();

}

if (isset($_POST['email_invoice'])) {

    validateCSRFToken();

    enforceUserPermission('module_sales', 2);

    $invoice_id = intval($_POST['invoice_id']);

    $sql = mysqli_query($mysqli,"SELECT client_id, client_name, invoice_amount, invoice_currency_code,
        invoice_date, invoice_due, invoice_id, invoice_number, invoice_prefix, invoice_scope,
        invoice_status, invoice_url_key FROM invoices
        LEFT JOIN clients ON invoice_client_id = client_id
        WHERE invoice_id = $invoice_id"
    );
    $row = mysqli_fetch_assoc($sql);

    $invoice_id = intval($row['invoice_id']);
    $invoice_prefix = escapeSql($row['invoice_prefix']);
    $invoice_number = intval($row['invoice_number']);
    $invoice_scope = escapeSql($row['invoice_scope']);
    $invoice_status = escapeSql($row['invoice_status']);
    $invoice_date = escapeSql(validateDate($row['invoice_date']));
    $invoice_due = escapeSql(validateDate($row['invoice_due']));
    $invoice_amount = floatval($row['invoice_amount']);
    $invoice_url_key = escapeSql($row['invoice_url_key']);
    $invoice_currency_code = escapeSql($row['invoice_currency_code']);
    $client_id = intval($row['client_id']);
    $client_name = escapeSql($row['client_name']);

    enforceClientAccess();

    // Two ways in. Quick Send skips the modal and resolves the default
    // recipients server-side; the modal posts an explicit contacts[] list.
    // Scoping either lookup to this invoice's client is what makes a tampered
    // contact_id harmless - it simply matches nothing.
    if (!empty($_POST['quick_send'])) {

        $recipient_filter = documentDefaultContactFilterSql('invoice');

    } else {

        $selected_contacts = $_POST['contacts'] ?? [];
        if (!is_array($selected_contacts)) {
            $selected_contacts = [];
        }
        $selected_contact_ids = array_filter(array_unique(array_map('intval', $selected_contacts)));

        if (empty($selected_contact_ids)) {
            flashAlert("Select at least one contact to send to", 'error');
            redirect();
        }

        $selected_contact_id_list = implode(',', $selected_contact_ids);
        $recipient_filter = "AND contact_id IN ($selected_contact_id_list)";

    }

    $sql_recipients = mysqli_query(
        $mysqli,
        "SELECT contact_email, contact_name FROM contacts
        WHERE contact_client_id = $client_id
        AND contact_archived_at IS NULL
        AND contact_email IS NOT NULL
        AND contact_email != ''
        $recipient_filter
        ORDER BY contact_primary DESC, contact_billing DESC, contact_name ASC"
    );

    if (mysqli_num_rows($sql_recipients) == 0) {
        if (!empty($_POST['quick_send'])) {
            flashAlert("No default contacts to quick send to - use Send Email to choose recipients", 'error');
        } else {
            flashAlert("None of the selected contacts have a usable email address", 'error');
        }
        redirect();
    }

    $sql = mysqli_query($mysqli,"SELECT company_address, company_city, company_country, company_email, company_logo, company_name,
        company_phone, company_phone_country_code, company_state, company_website, company_zip FROM companies WHERE company_id = 1");
    $row = mysqli_fetch_assoc($sql);

    $company_name = escapeSql($row['company_name']);
    $company_country = escapeSql($row['company_country']);
    $company_address = escapeSql($row['company_address']);
    $company_city = escapeSql($row['company_city']);
    $company_state = escapeSql($row['company_state']);
    $company_zip = escapeSql($row['company_zip']);
    $company_phone = escapeSql(formatPhoneNumber($row['company_phone'], $row['company_phone_country_code']));
    $company_email = escapeSql($row['company_email']);
    $company_website = escapeSql($row['company_website']);
    $company_logo = escapeSql($row['company_logo']);

    // Sanitize Config vars from get_settings.php
    $config_invoice_from_name = escapeSql($config_invoice_from_name);
    $config_invoice_from_email = escapeSql($config_invoice_from_email);

    // Add up all the payments for the invoice and get the total amount paid to the invoice
    $sql_amount_paid = mysqli_query($mysqli,"SELECT SUM(payment_amount) AS amount_paid FROM payments WHERE payment_invoice_id = $invoice_id");
    $row = mysqli_fetch_assoc($sql_amount_paid);
    $amount_paid = floatval($row['amount_paid']);

    $balance = $invoice_amount - $amount_paid;

    if ($invoice_status == 'Paid') {
        $subject = "Invoice $invoice_prefix$invoice_number Receipt";
    } else {
        $subject = "Invoice $invoice_prefix$invoice_number";
    }

    // One queue row per selected contact, each greeting its own recipient.
    // The old handler built a single body around the primary contact's name
    // and reused it for the billing copies, so those read "Hello <someone
    // else>" - visible enough with one hidden copy, wrong enough to keep once
    // the recipient list is something the agent picks.
    $data = [];
    $recipient_labels = [];

    while ($recipient = mysqli_fetch_assoc($sql_recipients)) {
        $contact_name = escapeSql($recipient['contact_name']);
        $contact_email = escapeSql($recipient['contact_email']);

        if ($invoice_status == 'Paid') {
            $body = "Hello $contact_name,<br><br>Please click on the link below to see your invoice regarding \"$invoice_scope\" marked <b>paid</b>.<br><br><a href=\'https://$config_base_url/guest/guest_view_invoice.php?invoice_id=$invoice_id&url_key=$invoice_url_key\'>Invoice Link</a><br><br><br>--<br>$company_name - Billing<br>$config_invoice_from_email<br>$company_phone";
        } else {
            $body = "Hello $contact_name,<br><br>Please view the details of your invoice regarding \"$invoice_scope\" below.<br><br>Invoice: $invoice_prefix$invoice_number<br>Issue Date: $invoice_date<br>Total: " . numfmt_format_currency($currency_format, $invoice_amount, $invoice_currency_code) . "<br>Balance Due: " . numfmt_format_currency($currency_format, $balance, $invoice_currency_code) . "<br>Due Date: $invoice_due<br><br><br>To view your invoice, please click <a href=\'https://$config_base_url/guest/guest_view_invoice.php?invoice_id=$invoice_id&url_key=$invoice_url_key\'>here</a>.<br><br><br>--<br>$company_name - Billing<br>$config_invoice_from_email<br>$company_phone";
        }

        $data[] = [
                'from' => $config_invoice_from_email,
                'from_name' => $config_invoice_from_name,
                'recipient' => $contact_email,
                'recipient_name' => $contact_name,
                'subject' => $subject,
                'body' => $body
        ];

        $recipient_labels[] = "$contact_name <$contact_email>";

        logAudit("Invoice", "Email", "$session_name emailed $contact_email Invoice $invoice_prefix$invoice_number", $client_id, $invoice_id);
    }

    addToMailQueue($data);

    $recipient_list = implode(', ', $recipient_labels);
    $recipient_count = count($recipient_labels);

    logHistory('Sent', "Invoice emailed by $session_name to $recipient_list", $invoice_id);

    // Don't change the status to sent if the status is anything but draft
    if ($invoice_status == 'Draft') {
        mysqli_query($mysqli,"UPDATE invoices SET invoice_status = 'Sent' WHERE invoice_id = $invoice_id");
    }

    flashAlert("Invoice queued to $recipient_count " . ($recipient_count == 1 ? "recipient" : "recipients"));

    redirect();

}


if (isset($_POST['send_statement'])) {

    validateCSRFToken();

    enforceUserPermission('module_sales', 2);

    $client_id = intval($_POST['client_id']);

    enforceClientAccess();

    $sql = mysqli_query($mysqli, "SELECT client_currency_code, client_name FROM clients WHERE client_id = $client_id LIMIT 1");
    $row = mysqli_fetch_assoc($sql);
    $client_name = escapeSql($row['client_name']);
    $client_currency_code = escapeSql($row['client_currency_code']);

    if (empty($client_currency_code)) {
        $client_currency_code = escapeSql($session_company_currency);
    }

    // Statement options. Emptiness has to be tested BEFORE validateDate(),
    // which falls back to today rather than returning empty - running a blank
    // From through it would silently produce a one-day statement.
    $statement_date_to = $_POST['dtt'] ?? '';
    if (empty($statement_date_to)) {
        $statement_date_to = date("Y-m-d");
    } else {
        $statement_date_to = validateDate($statement_date_to);
    }
    $statement_date_to = escapeSql($statement_date_to);

    $statement_date_from = $_POST['dtf'] ?? '';
    if (empty($statement_date_from)) {
        // Blank means the whole history, so there is no lower bound to apply
        $statement_date_query = '';
        $statement_period = "up to $statement_date_to";
    } else {
        $statement_date_from = escapeSql(validateDate($statement_date_from));
        $statement_date_query = "AND invoice_date >= '$statement_date_from'";
        $statement_period = "$statement_date_from to $statement_date_to";
    }

    $include_paid = !empty($_POST['include_paid']);

    // Recipients - same picker contract as the invoice send, so the same
    // client scoping applies and a tampered contact_id matches nothing
    $selected_contacts = $_POST['contacts'] ?? [];
    if (!is_array($selected_contacts)) {
        $selected_contacts = [];
    }
    $selected_contact_ids = array_filter(array_unique(array_map('intval', $selected_contacts)));

    if (empty($selected_contact_ids)) {
        flashAlert("Select at least one contact to send to", 'error');
        redirect();
    }

    $selected_contact_id_list = implode(',', $selected_contact_ids);

    $sql_recipients = mysqli_query(
        $mysqli,
        "SELECT contact_email, contact_name FROM contacts
        WHERE contact_id IN ($selected_contact_id_list)
        AND contact_client_id = $client_id
        AND contact_archived_at IS NULL
        AND contact_email IS NOT NULL
        AND contact_email != ''
        ORDER BY contact_primary DESC, contact_billing DESC, contact_name ASC"
    );

    if (mysqli_num_rows($sql_recipients) == 0) {
        flashAlert("None of the selected contacts have a usable email address", 'error');
        redirect();
    }

    // The statement lines. Draft / Cancelled / Non-Billable are not money the
    // client owes, so they never appear - same exclusion the Outstanding
    // Balances report uses. Payments are summed in a derived table rather than
    // joined directly, or an invoice with two payments would be counted twice.
    $sql_statement = mysqli_query(
        $mysqli,
        "SELECT invoice_amount, invoice_date, invoice_due, invoice_id, invoice_number, invoice_prefix,
            invoice_scope, invoice_status, invoice_url_key, IFNULL(amount_paid, 0) AS amount_paid
        FROM invoices
        LEFT JOIN (
            SELECT payment_invoice_id, SUM(payment_amount) AS amount_paid FROM payments
            WHERE payment_archived_at IS NULL
            GROUP BY payment_invoice_id
        ) AS invoice_payments ON payment_invoice_id = invoice_id
        WHERE invoice_client_id = $client_id
        AND invoice_status NOT IN ('Draft', 'Cancelled', 'Non-Billable')
        AND invoice_date <= '$statement_date_to'
        $statement_date_query
        " . ($include_paid ? '' : "AND invoice_amount - IFNULL(invoice_payments.amount_paid, 0) > 0") . "
        ORDER BY invoice_date ASC, invoice_number ASC"
    );

    if (mysqli_num_rows($sql_statement) == 0) {
        flashAlert("No invoices to report for that period - nothing sent", 'error');
        redirect();
    }

    $sql = mysqli_query($mysqli, "SELECT company_name, company_phone, company_phone_country_code FROM companies WHERE company_id = 1");
    $row = mysqli_fetch_assoc($sql);
    $company_name = escapeSql($row['company_name']);
    $company_phone = escapeSql(formatPhoneNumber($row['company_phone'], $row['company_phone_country_code']));

    $config_invoice_from_name = escapeSql($config_invoice_from_name);
    $config_invoice_from_email = escapeSql($config_invoice_from_email);
    $config_base_url = escapeSql($config_base_url);

    // Build the table once - it is identical for every recipient, only the
    // greeting differs. Everything interpolated here is already escaped:
    // addToMailQueue() writes the body into the queue raw.
    //
    // KEEP EVERY ATTRIBUTE IN THIS MARKUP DOUBLE-QUOTED. The body is spliced
    // into a single-quoted SQL literal, so a bare ' ends the statement and the
    // INSERT dies; a " is just a character there and needs nothing. The older
    // bodies above solve the same problem the other way, writing \' inside a
    // double-quoted PHP string so the backslash itself reaches MySQL - do not
    // copy that here, because \' inside a SINGLE-quoted PHP string collapses to
    // a bare quote and puts the crash straight back.
    //
    // escapeSql() is not an option on assembled markup: it strip_tags() first,
    // which would empty the table.
    $statement_rows = '';
    $statement_total = 0;
    $statement_line_count = 0;

    while ($row = mysqli_fetch_assoc($sql_statement)) {
        $invoice_id = intval($row['invoice_id']);
        $invoice_prefix = escapeSql($row['invoice_prefix']);
        $invoice_number = intval($row['invoice_number']);
        $invoice_scope = escapeSql($row['invoice_scope']);
        $invoice_status = escapeSql($row['invoice_status']);
        $invoice_date = escapeSql($row['invoice_date']);
        $invoice_due = escapeSql($row['invoice_due']);
        $invoice_url_key = escapeSql($row['invoice_url_key']);
        $invoice_amount = floatval($row['invoice_amount']);
        $amount_paid = floatval($row['amount_paid']);
        $invoice_balance = $invoice_amount - $amount_paid;

        $statement_total += $invoice_balance;
        $statement_line_count++;

        $invoice_link = "https://$config_base_url/guest/guest_view_invoice.php?invoice_id=$invoice_id&url_key=$invoice_url_key";

        $overdue_flag = '';
        if ($invoice_balance > 0 && !empty($invoice_due) && $invoice_due < date("Y-m-d")) {
            $overdue_flag = ' <span style="color:#dc3545;">(overdue)</span>';
        }

        $statement_rows .= '<tr>'
            . '<td style="padding:6px 10px; border-bottom:1px solid #dee2e6;"><a href="' . $invoice_link . '">' . "$invoice_prefix$invoice_number" . '</a></td>'
            . '<td style="padding:6px 10px; border-bottom:1px solid #dee2e6;">' . $invoice_scope . '</td>'
            . '<td style="padding:6px 10px; border-bottom:1px solid #dee2e6;">' . $invoice_date . '</td>'
            . '<td style="padding:6px 10px; border-bottom:1px solid #dee2e6;">' . $invoice_due . $overdue_flag . '</td>'
            . '<td style="padding:6px 10px; border-bottom:1px solid #dee2e6; text-align:right;">' . numfmt_format_currency($currency_format, $invoice_amount, $client_currency_code) . '</td>'
            . '<td style="padding:6px 10px; border-bottom:1px solid #dee2e6; text-align:right;">' . numfmt_format_currency($currency_format, $amount_paid, $client_currency_code) . '</td>'
            . '<td style="padding:6px 10px; border-bottom:1px solid #dee2e6; text-align:right;">' . numfmt_format_currency($currency_format, $invoice_balance, $client_currency_code) . '</td>'
            . '</tr>';
    }

    $statement_table = '<table cellpadding="0" cellspacing="0" style="border-collapse:collapse; font-size:13px;">'
        . '<tr style="background:#343a40; color:#ffffff;">'
        . '<th style="padding:6px 10px; text-align:left;">Invoice</th>'
        . '<th style="padding:6px 10px; text-align:left;">Scope</th>'
        . '<th style="padding:6px 10px; text-align:left;">Date</th>'
        . '<th style="padding:6px 10px; text-align:left;">Due</th>'
        . '<th style="padding:6px 10px; text-align:right;">Amount</th>'
        . '<th style="padding:6px 10px; text-align:right;">Paid</th>'
        . '<th style="padding:6px 10px; text-align:right;">Balance</th>'
        . '</tr>'
        . $statement_rows
        . '<tr><td colspan="6" style="padding:8px 10px; text-align:right;"><b>Total Balance Due</b></td>'
        . '<td style="padding:8px 10px; text-align:right;"><b>' . numfmt_format_currency($currency_format, $statement_total, $client_currency_code) . '</b></td></tr>'
        . '</table>';

    $subject = "Account Statement - $client_name";

    $data = [];
    $recipient_labels = [];

    while ($recipient = mysqli_fetch_assoc($sql_recipients)) {
        $contact_name = escapeSql($recipient['contact_name']);
        $contact_email = escapeSql($recipient['contact_email']);

        $body = "Hello $contact_name,<br><br>Please find your account statement for $statement_period below.<br><br>"
            . $statement_table
            . "<br><br>Click any invoice number above to view or pay it online.<br><br><br>--<br>$company_name - Billing<br>$config_invoice_from_email<br>$company_phone";

        $data[] = [
            'from' => $config_invoice_from_email,
            'from_name' => $config_invoice_from_name,
            'recipient' => $contact_email,
            'recipient_name' => $contact_name,
            'subject' => $subject,
            'body' => $body
        ];

        $recipient_labels[] = "$contact_name <$contact_email>";

        logAudit("Invoice", "Statement", "$session_name emailed an account statement ($statement_period, $statement_line_count invoices) to $contact_email", $client_id);
    }

    addToMailQueue($data);

    $recipient_count = count($recipient_labels);

    flashAlert("Statement queued to $recipient_count " . ($recipient_count == 1 ? "recipient" : "recipients"));

    redirect();

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
        'by' => $session_name,
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
