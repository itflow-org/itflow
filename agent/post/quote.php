<?php

/*
 * ITFlow - GET/POST request handler for quotes
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['add_quote'])) {

    validateCSRFToken();

    enforceUserPermission('module_sales', 2);

    require_once 'quote_model.php';

    $client_id = intval($_POST['client_id']);

    enforceClientAccess();

    // Atomically increment and get the new quote number
    mysqli_query($mysqli, "
        UPDATE settings
        SET
            config_quote_next_number = LAST_INSERT_ID(config_quote_next_number),
            config_quote_next_number = config_quote_next_number + 1
        WHERE company_id = 1
    ");

    $quote_number = mysqli_insert_id($mysqli);

    //Generate a unique URL key for clients to access
    $quote_url_key = randomString(32);

    mysqli_query($mysqli,"INSERT INTO quotes SET quote_prefix = '$config_quote_prefix', quote_number = $quote_number, quote_scope = '$scope', quote_date = '$date', quote_expire = '$expire', quote_discount_amount = $quote_discount, quote_amount = " . (0 - $quote_discount) . ", quote_currency_code = '$session_company_currency', quote_category_id = $category, quote_status = 'Draft', quote_url_key = '$quote_url_key', quote_client_id = $client_id");

    $quote_id = mysqli_insert_id($mysqli);

    mysqli_query($mysqli,"INSERT INTO history SET history_status = 'Draft', history_description = 'Quote created!', history_quote_id = $quote_id");

    logAudit("Quote", "Create", "$session_name created quote $config_quote_prefix$quote_number", $client_id, $quote_id);

    triggerCustomAction('quote_create', $quote_id);

    flashAlert("Quote <strong>$config_quote_prefix$quote_number</strong> created");

    redirect("quote.php?quote_id=$quote_id");

}

if (isset($_POST['add_quote_copy'])) {

    validateCSRFToken();

    enforceUserPermission('module_sales', 2);

    $quote_id = intval($_POST['quote_id']);
    $client_id = intval($_POST['client_id']);
    $date = escapeSql($_POST['date']);
    $expire = escapeSql($_POST['expire']);

    // Source: can you read the quote you're copying FROM?
    $source_client_id = intval(getFieldById('quotes', $quote_id, 'quote_client_id'));
    enforceClientAccess($source_client_id);

    // Destination: can you write to the client you're copying INTO?
    enforceClientAccess($client_id);

    $config_quote_prefix = escapeSql($config_quote_prefix);

    // Atomically increment and get the new quote number
    mysqli_query($mysqli, "
        UPDATE settings
        SET
            config_quote_next_number = LAST_INSERT_ID(config_quote_next_number),
            config_quote_next_number = config_quote_next_number + 1
        WHERE company_id = 1
    ");

    $quote_number = mysqli_insert_id($mysqli);

    $sql = mysqli_query($mysqli,"SELECT quote_amount, quote_category_id, quote_currency_code, quote_discount_amount, quote_note,
        quote_number, quote_prefix, quote_scope FROM quotes WHERE quote_id = $quote_id");
    $row = mysqli_fetch_assoc($sql);
    $original_quote_prefix = escapeSql($row['quote_prefix']);
    $original_quote_number = escapeSql($row['quote_number']);
    $quote_discount_amount = floatval($row['quote_discount_amount']);
    $quote_amount = floatval($row['quote_amount']);
    $quote_currency_code = escapeSql($row['quote_currency_code']);
    $quote_scope = escapeSql($row['quote_scope']);
    $quote_note = escapeSql($row['quote_note']);
    $category_id = intval($row['quote_category_id']);

    //Generate a unique URL key for clients to access
    $quote_url_key = randomString(32);

    mysqli_query($mysqli,"INSERT INTO quotes SET quote_prefix = '$config_quote_prefix', quote_number = $quote_number, quote_scope = '$quote_scope', quote_date = '$date', quote_expire = '$expire', quote_category_id = $category_id, quote_status = 'Draft', quote_discount_amount = $quote_discount_amount, quote_amount = $quote_amount, quote_currency_code = '$quote_currency_code', quote_note = '$quote_note', quote_url_key = '$quote_url_key', quote_client_id = $client_id");

    $new_quote_id = mysqli_insert_id($mysqli);

    mysqli_query($mysqli,"INSERT INTO history SET history_status = 'Draft', history_description = 'Quote copied!', history_quote_id = $new_quote_id");

    $sql_items = mysqli_query($mysqli,"SELECT item_description, item_id, item_name, item_order, item_price, item_quantity, item_subtotal,
        item_tax, item_tax_id, item_total, item_frequency FROM quote_items WHERE item_quote_id = $quote_id");
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
        $item_frequency = quoteFrequency($row['item_frequency']);
        $item_frequency = $item_frequency === '' ? 'NULL' : "'$item_frequency'";

        mysqli_query($mysqli,"INSERT INTO quote_items SET item_name = '$item_name', item_description = '$item_description', item_quantity = $item_quantity, item_price = $item_price, item_subtotal = $item_subtotal, item_tax = $item_tax, item_total = $item_total, item_order = $item_order, item_tax_id = $tax_id, item_frequency = $item_frequency, item_quote_id = $new_quote_id");
    }
    billingRecalc('quote', $new_quote_id);

    logAudit("Quote", "Create", "$session_name created quote $config_quote_prefix$quote_number from quote $original_quote_prefix$original_quote_number", $client_id, $new_quote_id);

    triggerCustomAction('quote_create', $new_quote_id);

    flashAlert("Quote copied");

    redirect("quote.php?quote_id=$new_quote_id");

}

/*
 * Make Invoice / Start Billing - an accepted quote's one-time lines on a
 * draft invoice, and its monthly, quarterly and yearly lines on recurring
 * invoices (new, or added to the client's). quoteConvert() does it.
 */
if (isset($_POST['add_quote_to_invoice'])) {

    validateCSRFToken();

    enforceUserPermission('module_sales', 2);

    $quote = billingRow('quote', intval($_POST['quote_id'] ?? 0));
    if (!$quote) {
        flashAlert('That quote no longer exists.', 'error');
        redirect();
    }
    $client_id = intval($quote['client_id']);

    enforceClientAccess();

    $recurring = [];
    foreach ((array) ($_POST['recurring'] ?? []) as $frequency => $choice) {
        $choice = (array) $choice;
        $recurring[(string) $frequency] = [
            'to' => intval($choice['to'] ?? 0),
            'start' => (string) ($choice['start'] ?? ''),
            'auto_send' => intval($choice['auto_send'] ?? 1),
        ];
    }
    $result = quoteConvert($quote['quote_id'], [
        'date' => (string) ($_POST['date'] ?? ''),
        'first_period' => !empty($_POST['first_period']),
        'recurring' => $recurring,
        'by' => $session_name,
    ], $error);
    if (!$result) {
        flashAlert(escapeHtml($error), 'error');
        redirect();
    }

    flashAlert('<strong>' . escapeHtml(billingNumber('quote', $quote)) . '</strong> invoiced as ' . escapeHtml(implode(', ', $result['numbers'])));

    if ($result['invoice_id']) {
        redirect("invoice.php?invoice_id={$result['invoice_id']}");
    }
    redirect('recurring_invoice.php?recurring_invoice_id=' . array_key_first($result['recurring']));

}

if (isset($_POST['edit_quote'])) {

    validateCSRFToken();

    enforceUserPermission('module_sales', 2);

    require_once 'quote_model.php';

    $quote_id = intval($_POST['quote_id']);

    // Get Quote Details for logging
    $sql = mysqli_query($mysqli,"SELECT quote_client_id, quote_number, quote_prefix FROM quotes WHERE quote_id = $quote_id");
    $row = mysqli_fetch_assoc($sql);
    $quote_prefix = escapeSql($row['quote_prefix']);
    $quote_number = escapeSql($row['quote_number']);
    $client_id = intval($row['quote_client_id']);

    enforceClientAccess();

    mysqli_query($mysqli,"UPDATE quotes SET quote_scope = '$scope', quote_date = '$date', quote_expire = '$expire', quote_discount_amount = $quote_discount, quote_category_id = $category WHERE quote_id = $quote_id");

    billingRecalc('quote', $quote_id);

    logAudit("Quote", "Edit", "$session_name edited quote $quote_prefix$quote_number", $client_id, $quote_id);

    flashAlert("Quote edited");

    redirect();

}

if (isset($_GET['delete_quote'])) {

    validateCSRFToken();

    enforceUserPermission('module_sales', 3);

    $quote_id = intval($_GET['delete_quote']);

    // Get Quote Details for logging
    $sql = mysqli_query($mysqli,"SELECT quote_client_id, quote_number, quote_prefix FROM quotes WHERE quote_id = $quote_id");
    $row = mysqli_fetch_assoc($sql);
    $quote_prefix = escapeSql($row['quote_prefix']);
    $quote_number = escapeSql($row['quote_number']);
    $client_id = intval($row['quote_client_id']);

    enforceClientAccess();

    mysqli_query($mysqli,"DELETE FROM quotes WHERE quote_id = $quote_id");

    //Delete Items Associated with the Quote
    $sql = mysqli_query($mysqli,"SELECT item_id FROM quote_items WHERE item_quote_id = $quote_id");
    while($row = mysqli_fetch_assoc($sql)) {;
        $item_id = intval($row['item_id']);
        mysqli_query($mysqli,"DELETE FROM quote_items WHERE item_id = $item_id");
    }

    //Delete History Associated with the Quote - a recurring invoice's entry for the lines it added stays theirs
    mysqli_query($mysqli,"DELETE FROM history WHERE history_quote_id = $quote_id AND history_recurring_invoice_id = 0");
    mysqli_query($mysqli,"UPDATE history SET history_quote_id = 0 WHERE history_quote_id = $quote_id");

    // What was made from it no longer points at it
    mysqli_query($mysqli,"UPDATE invoices SET invoice_quote_id = 0 WHERE invoice_quote_id = $quote_id");
    mysqli_query($mysqli,"UPDATE recurring_invoices SET recurring_invoice_quote_id = 0 WHERE recurring_invoice_quote_id = $quote_id");

    logAudit("Quote", "Delete", "$session_name deleted quote $quote_prefix$quote_number", $client_id);

    flashAlert("Quote <strong>$quote_prefix$quote_number</strong> deleted", 'error');

    if (isset($_GET['client_id'])) {
        $client_id = intval($_GET['client_id']);
        redirect("client_quotes.php?client_id=$client_id");
    } else {
        redirect("quotes.php");
    }

}

if (isset($_POST['mark_quote_sent'])) {

    validateCSRFToken();

    enforceUserPermission('module_sales', 2);

    $quote_id = intval($_POST['quote_id']);

    $sql = mysqli_query($mysqli,"SELECT quote_client_id, quote_number, quote_prefix FROM quotes WHERE quote_id = $quote_id");
    $row = mysqli_fetch_assoc($sql);
    $quote_prefix = escapeSql($row['quote_prefix']);
    $quote_number = escapeSql($row['quote_number']);
    $client_id = intval($row['quote_client_id']);

    enforceClientAccess();

    // The modal offers a fixed list, so anything else is a tampered form
    $sent_method = $_POST['sent_method'] ?? '';
    if (!in_array($sent_method, getSentMethods(), true)) {
        flashAlert("Invalid delivery method", 'error');
        redirect();
    }
    $sent_method = escapeSql($sent_method);

    $note = escapeSql(substr(trim($_POST['note'] ?? ''), 0, 500));

    mysqli_query($mysqli,"UPDATE quotes SET quote_status = 'Sent' WHERE quote_id = $quote_id");

    $history_description = "Quote marked sent by $session_name - $sent_method";
    if (!empty($note)) {
        $history_description .= "\nNote: $note";
    }

    logHistory('Sent', $history_description, 0, $quote_id);

    logAudit("Quote", "Sent", "$session_name marked quote $quote_prefix$quote_number as sent - $sent_method", $client_id, $quote_id);

    flashAlert("Quote marked sent");

    redirect();

}

if (isset($_GET['accept_quote'])) {

    validateCSRFToken();

    enforceUserPermission('module_sales', 2);

    $quote_id = intval($_GET['accept_quote']);

    $sql = mysqli_query($mysqli,"SELECT quote_client_id, quote_number, quote_prefix FROM quotes WHERE quote_id = $quote_id");
    $row = mysqli_fetch_assoc($sql);
    $quote_prefix = escapeSql($row['quote_prefix']);
    $quote_number = escapeSql($row['quote_number']);
    $client_id = intval($row['quote_client_id']);

    enforceClientAccess();

    mysqli_query($mysqli,"UPDATE quotes SET quote_status = 'Accepted' WHERE quote_id = $quote_id");

    mysqli_query($mysqli,"INSERT INTO history SET history_status = 'Accepted', history_description = 'Quote accepted by $session_name', history_quote_id = $quote_id");

    logAudit("Quote", "Edit", "$session_name marked quote $quote_prefix$quote_number as accepted", $client_id, $quote_id);

    triggerCustomAction('quote_accept', $quote_id);

    flashAlert("Quote accepted");

    redirect();

}

if (isset($_GET['decline_quote'])) {

    validateCSRFToken();

    enforceUserPermission('module_sales', 2);

    $quote_id = intval($_GET['decline_quote']);

    $sql = mysqli_query($mysqli,"SELECT quote_client_id, quote_number, quote_prefix FROM quotes WHERE quote_id = $quote_id");
    $row = mysqli_fetch_assoc($sql);
    $quote_prefix = escapeSql($row['quote_prefix']);
    $quote_number = escapeSql($row['quote_number']);
    $client_id = intval($row['quote_client_id']);

    enforceClientAccess();

    mysqli_query($mysqli,"UPDATE quotes SET quote_status = 'Declined' WHERE quote_id = $quote_id");

    mysqli_query($mysqli,"INSERT INTO history SET history_status = 'Cancelled', history_description = 'Quote declined by $session_name', history_quote_id = $quote_id");

    triggerCustomAction('quote_decline', $quote_id);

    logAudit("Quote", "Edit", "$session_name marked quote $quote_prefix$quote_number as declined", $client_id, $quote_id);

    flashAlert("Quote declined", 'error');

    redirect();

}

if (isset($_POST['email_quote'])) {

    validateCSRFToken();

    enforceUserPermission('module_sales', 2);

    $quote_id = intval($_POST['quote_id']);

    $sql = mysqli_query($mysqli,"SELECT client_id, client_name, quote_amount, quote_currency_code,
        quote_date, quote_expire, quote_number, quote_prefix, quote_scope, quote_status,
        quote_url_key FROM quotes
    LEFT JOIN clients ON quote_client_id = client_id
    WHERE quote_id = $quote_id"
    );

    $row = mysqli_fetch_assoc($sql);
    $quote_prefix = escapeSql($row['quote_prefix']);
    $quote_number = intval($row['quote_number']);
    $quote_scope = escapeSql($row['quote_scope']);
    $quote_status = escapeSql($row['quote_status']);
    $quote_date = escapeSql($row['quote_date']);
    $quote_expire = escapeSql($row['quote_expire']);
    $quote_amount = floatval($row['quote_amount']);
    $quote_url_key = escapeSql($row['quote_url_key']);
    $quote_currency_code = escapeSql($row['quote_currency_code']);
    $client_id = intval($row['client_id']);
    $client_name = escapeSql($row['client_name']);

    enforceClientAccess();

    // What it comes to - up front, and each month, quarter or year
    $quote_groups = quoteGroups(billingRow('quote', $quote_id, false));
    $quote_groups_recurring = quoteHasRecurring($quote_groups);
    $quote_cost_text = quoteAmountText($quote_groups, $row['quote_currency_code'] ?? $quote_currency_code);

    // Two ways in. Quick Send skips the modal and resolves the default
    // recipients server-side; the modal posts an explicit contacts[] list.
    // Scoping either lookup to this quote's client is what makes a tampered
    // contact_id harmless - it simply matches nothing.
    if (!empty($_POST['quick_send'])) {

        $recipient_filter = documentDefaultContactFilterSql('quote');

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
    $config_quote_from_name = escapeSql($config_quote_from_name);
    $config_quote_from_email = escapeSql($config_quote_from_email);
    $config_base_url = escapeSql($config_base_url);

    $subject = "Quote [$quote_scope]";

    // One queue row per selected contact, each greeting its own recipient
    $data = [];
    $recipient_labels = [];

    while ($recipient = mysqli_fetch_assoc($sql_recipients)) {
        $contact_name = escapeSql($recipient['contact_name']);
        $contact_email = escapeSql($recipient['contact_email']);

        $body = "Hello $contact_name,<br><br>Thank you for your inquiry, we are pleased to provide you with the following estimate.<br><br><br>$quote_scope<br>" . ($quote_groups_recurring ? 'Cost: ' : 'Total Cost: ') . escapeSql(htmlspecialchars($quote_cost_text)) . "<br><br><br>View and accept your estimate online <a href=\'https://$config_base_url/guest/guest_view_quote.php?quote_id=$quote_id&url_key=$quote_url_key\'>here</a><br><br><br>--<br>$company_name - Sales<br>$config_quote_from_email<br>$company_phone";

        $data[] = [
            'from' => $config_quote_from_email,
            'from_name' => $config_quote_from_name,
            'recipient' => $contact_email,
            'recipient_name' => $contact_name,
            'subject' => $subject,
            'body' => $body,
        ];

        $recipient_labels[] = "$contact_name <$contact_email>";

        logAudit("Quote", "Email", "$session_name emailed quote $quote_prefix$quote_number to $contact_email", $client_id, $quote_id);
    }

    addToMailQueue($data);

    $recipient_list = implode(', ', $recipient_labels);
    $recipient_count = count($recipient_labels);

    // Update History
    logHistory('Sent', "Quote emailed by $session_name to $recipient_list", 0, $quote_id);

    //Don't change the status to sent if the status is anything but draft
    if ($quote_status == 'Draft') {
        mysqli_query($mysqli,"UPDATE quotes SET quote_status = 'Sent' WHERE quote_id = $quote_id");
    }

    flashAlert("Quote queued to $recipient_count " . ($recipient_count == 1 ? "recipient" : "recipients"));

    redirect();

}

if (isset($_GET['mark_quote_invoiced'])) {

    validateCSRFToken();

    enforceUserPermission('module_sales', 2);

    $quote_id = intval($_GET['mark_quote_invoiced']);

    $sql = mysqli_query($mysqli,"SELECT quote_client_id, quote_number, quote_prefix FROM quotes WHERE quote_id = $quote_id");
    $row = mysqli_fetch_assoc($sql);
    $quote_prefix = escapeSql($row['quote_prefix']);
    $quote_number = escapeSql($row['quote_number']);
    $client_id = intval($row['quote_client_id']);

    enforceClientAccess();

    mysqli_query($mysqli,"UPDATE quotes SET quote_status = 'Invoiced' WHERE quote_id = $quote_id");

    mysqli_query($mysqli,"INSERT INTO history SET history_status = 'Invoiced', history_description = 'Quote marked as invoiced', history_quote_id = $quote_id");

    logAudit("Quote", "Sent", "$session_name marked quote $quote_prefix$quote_number as invoiced", $client_id, $quote_id);

    flashAlert("Quote marked invoiced");

    redirect();

}

if (isExportRequest('export_quotes')) {

    validateCSRFToken();

    // Exports are reads - see CONTRIBUTING.md
    enforceUserPermission('module_sales');

    $format = resolveExportFormat($_POST['export_quotes']);

    // Filters inherited from the quotes page - mirrors agent/quotes.php
    $filter_summary = [];

    if (!empty($_POST['client_id'])) {
        $client_id = intval($_POST['client_id']);
        $client_query = "AND quote_client_id = $client_id";
        $client_name = getFieldById('clients', $client_id, 'client_name');
        $file_name_prepend = "$client_name-";
        $filter_summary['Client'] = $client_name;

        enforceClientAccess();
    } else {
        $client_query = '';
        $client_id = 0; // for Logging
        $file_name_prepend = "$session_company_name-";
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
        "SELECT client_name, quote_amount, quote_monthly_amount, quote_date, quote_number, quote_prefix, quote_scope,
        quote_status FROM quotes
        LEFT JOIN clients ON quote_client_id = client_id
        LEFT JOIN categories ON quote_category_id = category_id
        WHERE (CONCAT(quote_prefix,quote_number) LIKE '%$q%' OR quote_scope LIKE '%$q%' OR category_name LIKE '%$q%' OR quote_status LIKE '%$q%' OR quote_amount LIKE '%$q%' OR client_name LIKE '%$q%')
        AND DATE(quote_date) BETWEEN '$dtf' AND '$dtt'
        " . clientScopeSql('quote_client_id') . "
        $client_query
        ORDER BY quote_number ASC"
    );

    $num_rows = mysqli_num_rows($sql);

    if ($num_rows > 0) {

        guardExportPdfRowCount($format, $num_rows);

        $export = beginExport('quotes', $format, $file_name_prepend . 'Quotes', 'Quotes', summarizeExportFilters($filter_summary));

        while ($row = mysqli_fetch_assoc($sql)) {
            $row['quote_number_display'] = $row['quote_prefix'] . $row['quote_number'];
            addExportRow($export, $row);
        }

        finishExport($export);
    }

    logAudit("Quote", "Export", "$session_name exported $num_rows quote(s) to a " . strtoupper($format) . " file", $client_id);

    exit;

}

if (isset($_GET['export_quote_pdf'])) {

    validateCSRFToken();

    enforceUserPermission('module_sales');

    $quote = billingRow('quote', intval($_GET['export_quote_pdf']));
    if (!$quote) {
        flashAlert('That quote no longer exists.', 'error');
        redirect('quotes.php');
    }
    $client_id = intval($quote['client_id']);

    enforceClientAccess();

    billingPdfSend(billingPdf('quote', $quote));

}
