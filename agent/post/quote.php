<?php

/*
 * ITFlow - GET/POST request handler for quotes
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['add_quote'])) {

    validateCSRFToken($_POST['csrf_token']);

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

    mysqli_query($mysqli,"INSERT INTO quotes SET quote_prefix = '$config_quote_prefix', quote_number = $quote_number, quote_scope = '$scope', quote_date = '$date', quote_expire = '$expire', quote_currency_code = '$session_company_currency', quote_category_id = $category, quote_status = 'Draft', quote_url_key = '$quote_url_key', quote_client_id = $client_id");

    $quote_id = mysqli_insert_id($mysqli);

    mysqli_query($mysqli,"INSERT INTO history SET history_status = 'Draft', history_description = 'Quote created!', history_quote_id = $quote_id");

    logAudit("Quote", "Create", "$session_name created quote $config_quote_prefix$quote_number", $client_id, $quote_id);

    triggerCustomAction('quote_create', $quote_id);

    flashAlert("Quote <strong>$config_quote_prefix$quote_number</strong> created");

    redirect("quote.php?quote_id=$quote_id");

}

if (isset($_POST['add_quote_copy'])) {

    validateCSRFToken($_POST['csrf_token']);

    enforceUserPermission('module_sales', 2);

    $quote_id = intval($_POST['quote_id']);
    $client_id = intval($_POST['client_id']);
    $date = escapeSql($_POST['date']);
    $expire = escapeSql($_POST['expire']);

    enforceClientAccess();

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

    $sql = mysqli_query($mysqli,"SELECT * FROM quotes WHERE quote_id = $quote_id");
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

    $sql_items = mysqli_query($mysqli,"SELECT * FROM quote_items WHERE item_quote_id = $quote_id");
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

        mysqli_query($mysqli,"INSERT INTO quote_items SET item_name = '$item_name', item_description = '$item_description', item_quantity = $item_quantity, item_price = $item_price, item_subtotal = $item_subtotal, item_tax = $item_tax, item_total = $item_total, item_order = $item_order, item_tax_id = $tax_id, item_quote_id = $new_quote_id");
    }

    logAudit("Quote", "Create", "$session_name created quote $config_quote_prefix$quote_number from quote $original_quote_prefix$original_quote_number", $client_id, $new_quote_id);

    triggerCustomAction('quote_create', $new_quote_id);

    flashAlert("Quote copied");

    redirect("quote.php?quote_id=$new_quote_id");

}

if (isset($_POST['add_quote_to_invoice'])) {

    validateCSRFToken($_POST['csrf_token']);

    enforceUserPermission('module_sales', 2);

    $quote_id = intval($_POST['quote_id']);
    $date = escapeSql($_POST['date']);

    $sql = mysqli_query($mysqli,"SELECT * FROM clients, quotes WHERE client_id = quote_client_id AND quote_id = $quote_id");
    $row = mysqli_fetch_assoc($sql);
    $client_net_terms = intval($row['client_net_terms']);
    $quote_prefix = escapeSql($row['quote_prefix']);
    $quote_number = escapeSql($row['quote_number']);
    $quote_discount_amount = floatval($row['quote_discount_amount']);
    $quote_amount = floatval($row['quote_amount']);
    $quote_currency_code = escapeSql($row['quote_currency_code']);
    $quote_scope = escapeSql($row['quote_scope']);
    $quote_note = escapeSql($row['quote_note']);

    $client_id = intval($row['quote_client_id']);
    $category_id = intval($row['quote_category_id']);

    enforceClientAccess();

    $config_invoice_prefix = escapeSql($config_invoice_prefix);

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

    mysqli_query($mysqli,"INSERT INTO invoices SET invoice_prefix = '$config_invoice_prefix', invoice_number = $invoice_number, invoice_scope = '$quote_scope', invoice_date = '$date', invoice_due = DATE_ADD(CURDATE(), INTERVAL $client_net_terms day), invoice_category_id = $category_id, invoice_status = 'Draft', invoice_discount_amount = $quote_discount_amount, invoice_amount = $quote_amount, invoice_currency_code = '$quote_currency_code', invoice_note = '$quote_note', invoice_url_key = '$url_key', invoice_client_id = $client_id");

    $new_invoice_id = mysqli_insert_id($mysqli);

    mysqli_query($mysqli,"INSERT INTO history SET history_status = 'Draft', history_description = 'Invoice created from quote $quote_prefix$quote_number', history_invoice_id = $new_invoice_id");

    $sql_items = mysqli_query($mysqli,"SELECT * FROM quote_items WHERE item_quote_id = $quote_id");
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

    mysqli_query($mysqli,"UPDATE quotes SET quote_status = 'Invoiced' WHERE quote_id = $quote_id");

    mysqli_query($mysqli,"INSERT INTO history SET history_status = 'Invoiced', history_description = 'Quote invoiced as $config_invoice_prefix$invoice_number', history_quote_id = $quote_id");

    logAudit("Invoice", "Create", "$session_name created invoice $config_invoice_prefix$invoice_number from quote $config_quote_prefix$quote_number", $client_id, $new_invoice_id);

    // Check & update any quote-ticket association
    $ticket_id = 0;
    $sql_ticket = "SELECT ticket_id, ticket_prefix, ticket_number
        FROM tickets
        WHERE ticket_quote_id = $quote_id
        LIMIT 1";
    $result_ticket = mysqli_query($mysqli, $sql_ticket);

    if ($result_ticket && $row = mysqli_fetch_assoc($result_ticket)) {
        $ticket_id = intval($row['ticket_id']);
        $ticket_prefix = escapeSql($row['ticket_prefix']);
        $ticket_number = intval($row['ticket_number']);

        mysqli_query($mysqli, "UPDATE tickets SET ticket_invoice_id = $new_invoice_id WHERE ticket_id = $ticket_id AND ticket_invoice_id = '0'"); // Only if ticket doesn't already have an invoice
    }

    triggerCustomAction('invoice_create', $new_invoice_id);

    flashAlert("Invoice created from quote <strong>$quote_prefix$quote_number</strong>");

    redirect("invoice.php?invoice_id=$new_invoice_id");

}

if (isset($_POST['add_quote_item'])) {

    validateCSRFToken($_POST['csrf_token']);

    enforceUserPermission('module_sales', 2);

    $quote_id = intval($_POST['quote_id']);
    $name = escapeSql($_POST['name']);
    $description = escapeSql($_POST['description']);
    $qty = floatval($_POST['qty']);
    $price = floatval($_POST['price']);
    $tax_id = intval($_POST['tax_id']);
    $item_order = intval($_POST['item_order']);

    $client_id = intval(getFieldById('quotes', $quote_id, 'quote_client_id'));

    enforceClientAccess();

    $subtotal = $price * $qty;

    if ($tax_id > 0) {
        $sql = mysqli_query($mysqli,"SELECT * FROM taxes WHERE tax_id = $tax_id");
        $row = mysqli_fetch_assoc($sql);
        $tax_percent = floatval($row['tax_percent']);
        $tax_amount = $subtotal * $tax_percent / 100;
    }else{
        $tax_amount = 0;
    }

    $total = $subtotal + $tax_amount;

    mysqli_query($mysqli,"INSERT INTO quote_items SET item_name = '$name', item_description = '$description', item_quantity = $qty, item_price = $price, item_subtotal = $subtotal, item_tax = $tax_amount, item_total = $total, item_tax_id = $tax_id, item_order = $item_order, item_quote_id = $quote_id");

    // Get Quote Details
    $sql = mysqli_query($mysqli,"SELECT * FROM quotes WHERE quote_id = $quote_id");
    $row = mysqli_fetch_assoc($sql);
    $quote_prefix = escapeSql($row['quote_prefix']);
    $quote_number = escapeSql($row['quote_number']);
    $quote_discount_amount = floatval($row['quote_discount_amount']);
    $client_id = intval($row['quote_client_id']);

    //add up the total of all items
    $sql = mysqli_query($mysqli,"SELECT * FROM quote_items WHERE item_quote_id = $quote_id");
    $quote_amount = 0;
    while($row = mysqli_fetch_assoc($sql)) {
        $item_total = floatval($row['item_total']);
        $quote_amount = $quote_amount + $item_total;
    }
    $new_quote_amount = $quote_amount - $quote_discount_amount;

    mysqli_query($mysqli,"UPDATE quotes SET quote_amount = $new_quote_amount WHERE quote_id = $quote_id");

    logAudit("Quote", "Edit", "$session_name added item $name to quote $quote_prefix$quote_number", $client_id, $quote_id);

    flashAlert("Item <strong>$name</strong> added");

    redirect();

}

if (isset($_POST['edit_quote_item'])) {

    validateCSRFToken($_POST['csrf_token']);

    enforceUserPermission('module_sales', 2);

    $item_id = intval($_POST['item_id']);
    $name = escapeSql($_POST['name']);
    $description = escapeSql($_POST['description']);
    $qty = floatval($_POST['qty']);
    $price = floatval($_POST['price']);
    $tax_id = intval($_POST['tax_id']);
    $product_id = intval($_POST['product_id']);

    $subtotal = $price * $qty;

    if ($tax_id > 0) {
        $sql = mysqli_query($mysqli,"SELECT * FROM taxes WHERE tax_id = $tax_id");
        $row = mysqli_fetch_assoc($sql);
        $tax_percent = floatval($row['tax_percent']);
        $tax_amount = $subtotal * $tax_percent / 100;
    } else {
        $tax_amount = 0;
    }

    $total = $subtotal + $tax_amount;

    // Get Quote ID from Item ID
    $sql = mysqli_query($mysqli,"SELECT item_quote_id FROM quote_items WHERE item_id = $item_id");
    $row = mysqli_fetch_assoc($sql);
    $quote_id = intval($row['item_quote_id']);

    //Get Discount Amount
    $sql = mysqli_query($mysqli,"SELECT * FROM quotes WHERE quote_id = $quote_id");
    $row = mysqli_fetch_assoc($sql);
    $quote_prefix = escapeSql($row['quote_prefix']);
    $quote_number = intval($row['quote_number']);
    $client_id = intval($row['quote_client_id']);
    $quote_discount = floatval($row['quote_discount_amount']);

    enforceClientAccess();

    mysqli_query($mysqli,"UPDATE quote_items SET item_name = '$name', item_description = '$description', item_quantity = $qty, item_price = $price, item_subtotal = $subtotal, item_tax = $tax_amount, item_total = $total, item_tax_id = $tax_id WHERE item_id = $item_id");

    //Update Quote Balances by tallying up items
    $sql_quote_total = mysqli_query($mysqli,"SELECT SUM(item_total) AS quote_total FROM quote_items WHERE item_quote_id = $quote_id");
    $row = mysqli_fetch_assoc($sql_quote_total);
    $new_quote_amount = floatval($row['quote_total']) - $quote_discount;

    mysqli_query($mysqli,"UPDATE quotes SET quote_amount = $new_quote_amount WHERE quote_id = $quote_id");

    logAudit("Quote", "Edit", "$session_name edited item $name on quote $quote_prefix$quote_number", $client_id, $quote_id);

    flashAlert("Item <strong>$name</strong> updated");

    redirect();

}

if (isset($_POST['quote_note'])) {

    validateCSRFToken($_POST['csrf_token']);

    enforceUserPermission('module_sales', 2);

    $quote_id = intval($_POST['quote_id']);
    $note = escapeSql($_POST['note']);

    // Get Quote Details
    $sql = mysqli_query($mysqli,"SELECT * FROM quotes WHERE quote_id = $quote_id");
    $row = mysqli_fetch_assoc($sql);
    $quote_prefix = escapeSql($row['quote_prefix']);
    $quote_number = escapeSql($row['quote_number']);
    $client_id = intval($row['quote_client_id']);

    enforceClientAccess();

    mysqli_query($mysqli,"UPDATE quotes SET quote_note = '$note' WHERE quote_id = $quote_id");

    logAudit("Quote", "Edit", "$session_name added notes to quote $quote_prefix$quote_number", $client_id, $quote_id);

    flashAlert("Notes added");

    redirect();

}

if (isset($_POST['edit_quote'])) {

    validateCSRFToken($_POST['csrf_token']);

    enforceUserPermission('module_sales', 2);

    require_once 'quote_model.php';

    $quote_id = intval($_POST['quote_id']);

    // Get Quote Details for logging
    $sql = mysqli_query($mysqli,"SELECT * FROM quotes WHERE quote_id = $quote_id");
    $row = mysqli_fetch_assoc($sql);
    $quote_prefix = escapeSql($row['quote_prefix']);
    $quote_number = escapeSql($row['quote_number']);
    $client_id = intval($row['quote_client_id']);

    enforceClientAccess();

    //Calculate the new quote amount
    $sql = mysqli_query($mysqli,"SELECT * FROM quote_items WHERE item_quote_id = $quote_id");
    $quote_amount = 0;
    while($row = mysqli_fetch_assoc($sql)) {
        $item_total = floatval($row['item_total']);
        $quote_amount = $quote_amount + $item_total;
    }
    $quote_amount = $quote_amount - $quote_discount;

    mysqli_query($mysqli,"UPDATE quotes SET quote_scope = '$scope', quote_date = '$date', quote_expire = '$expire', quote_discount_amount = '$quote_discount', quote_amount = '$quote_amount', quote_category_id = $category WHERE quote_id = $quote_id");

    logAudit("Quote", "Edit", "$session_name edited quote $quote_prefix$quote_number", $client_id, $quote_id);

    flashAlert("Quote edited");

    redirect();

}

if (isset($_GET['delete_quote'])) {

    validateCSRFToken($_GET['csrf_token']);

    enforceUserPermission('module_sales', 3);

    $quote_id = intval($_GET['delete_quote']);

    // Get Quote Details for logging
    $sql = mysqli_query($mysqli,"SELECT * FROM quotes WHERE quote_id = $quote_id");
    $row = mysqli_fetch_assoc($sql);
    $quote_prefix = escapeSql($row['quote_prefix']);
    $quote_number = escapeSql($row['quote_number']);
    $client_id = intval($row['quote_client_id']);

    enforceClientAccess();

    mysqli_query($mysqli,"DELETE FROM quotes WHERE quote_id = $quote_id");

    //Delete Items Associated with the Quote
    $sql = mysqli_query($mysqli,"SELECT * FROM quote_items WHERE item_quote_id = $quote_id");
    while($row = mysqli_fetch_assoc($sql)) {;
        $item_id = intval($row['item_id']);
        mysqli_query($mysqli,"DELETE FROM quote_items WHERE item_id = $item_id");
    }

    //Delete History Associated with the Quote
    $sql = mysqli_query($mysqli,"SELECT * FROM history WHERE history_quote_id = $quote_id");
    while($row = mysqli_fetch_assoc($sql)) {;
        $history_id = intval($row['history_id']);
        mysqli_query($mysqli,"DELETE FROM history WHERE history_id = $history_id");
    }

    logAudit("Quote", "Delete", "$session_name deleted quote $quote_prefix$quote_number", $client_id);

    flashAlert("Quote <strong>$quote_prefix$quote_number</strong> deleted", 'error');

    if (isset($_GET['client_id'])) {
        $client_id = intval($_GET['client_id']);
        redirect("client_quotes.php?client_id=$client_id");
    } else {
        redirect("quotes.php");
    }

}

if (isset($_GET['delete_quote_item'])) {

    validateCSRFToken($_GET['csrf_token']);

    enforceUserPermission('module_sales', 2);

    $item_id = intval($_GET['delete_quote_item']);

    $sql = mysqli_query($mysqli,"SELECT * FROM quote_items WHERE item_id = $item_id");
    $row = mysqli_fetch_assoc($sql);
    $item_name = escapeSql($row['item_name']);
    $quote_id = intval($row['item_quote_id']);
    $item_subtotal = floatval($row['item_subtotal']);
    $item_tax = floatval($row['item_tax']);
    $item_total = floatval($row['item_total']);

    $sql = mysqli_query($mysqli,"SELECT * FROM quotes WHERE quote_id = $quote_id");
    $row = mysqli_fetch_assoc($sql);
    $quote_prefix = escapeSql($row['quote_prefix']);
    $quote_number = escapeSql($row['quote_number']);
    $client_id = intval($row['quote_client_id']);

    enforceClientAccess();

    $new_quote_amount = floatval($row['quote_amount']) - $item_total;

    mysqli_query($mysqli,"UPDATE quotes SET quote_amount = $new_quote_amount WHERE quote_id = $quote_id");

    mysqli_query($mysqli,"DELETE FROM quote_items WHERE item_id = $item_id");

    logAudit("Quote", "Edit", "$session_name removed item $item_name from $quote_prefix$quote_number", $client_id, $quote_id);

    flashAlert("Item <strong>$item_name</strong> removed", 'error');

    redirect();

}

if (isset($_GET['mark_quote_sent'])) {

    validateCSRFToken($_GET['csrf_token']);

    enforceUserPermission('module_sales', 2);

    $quote_id = intval($_GET['mark_quote_sent']);

    $sql = mysqli_query($mysqli,"SELECT * FROM quotes WHERE quote_id = $quote_id");
    $row = mysqli_fetch_assoc($sql);
    $quote_prefix = escapeSql($row['quote_prefix']);
    $quote_number = escapeSql($row['quote_number']);
    $client_id = intval($row['quote_client_id']);

    enforceClientAccess();

    mysqli_query($mysqli,"UPDATE quotes SET quote_status = 'Sent' WHERE quote_id = $quote_id");

    mysqli_query($mysqli,"INSERT INTO history SET history_status = 'Sent', history_description = 'Quote marked sent', history_quote_id = $quote_id");

    logAudit("Quote", "Sent", "$session_name marked quote $quote_prefix$quote_number as sent", $client_id, $quote_id);

    flashAlert("Quote marked sent");

    redirect();

}

if (isset($_GET['accept_quote'])) {

    validateCSRFToken($_GET['csrf_token']);

    enforceUserPermission('module_sales', 2);

    $quote_id = intval($_GET['accept_quote']);

    $sql = mysqli_query($mysqli,"SELECT * FROM quotes WHERE quote_id = $quote_id");
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

    validateCSRFToken($_GET['csrf_token']);

    enforceUserPermission('module_sales', 2);

    $quote_id = intval($_GET['decline_quote']);

    $sql = mysqli_query($mysqli,"SELECT * FROM quotes WHERE quote_id = $quote_id");
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

if (isset($_GET['email_quote'])) {

    validateCSRFToken($_GET['csrf_token']);

    enforceUserPermission('module_sales', 2);

    $quote_id = intval($_GET['email_quote']);

    $sql = mysqli_query($mysqli,"SELECT * FROM quotes
    LEFT JOIN clients ON quote_client_id = client_id
    LEFT JOIN contacts ON clients.client_id = contacts.contact_client_id AND contact_primary = 1
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
    $contact_name = escapeSql($row['contact_name']);
    $contact_email = escapeSql($row['contact_email']);

    enforceClientAccess();

    $sql = mysqli_query($mysqli,"SELECT * FROM companies WHERE company_id = 1");
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
    $body = "Hello $contact_name,<br><br>Thank you for your inquiry, we are pleased to provide you with the following estimate.<br><br><br>$quote_scope<br>Total Cost: " . numfmt_format_currency($currency_format, $quote_amount, $quote_currency_code) . "<br><br><br>View and accept your estimate online <a href=\'https://$config_base_url/guest/guest_view_quote.php?quote_id=$quote_id&url_key=$quote_url_key\'>here</a><br><br><br>--<br>$company_name - Sales<br>$config_quote_from_email<br>$company_phone";

    // Queue Mail
    $data = [
        [
            'from' => $config_quote_from_email,
            'from_name' => $config_quote_from_name,
            'recipient' => $contact_email,
            'recipient_name' => $contact_name,
            'subject' => $subject,
            'body' => $body,
        ]
    ];
    addToMailQueue($data);

    // Update History
    mysqli_query($mysqli,"INSERT INTO history SET history_status = 'Sent', history_description = 'Emailed Quote', history_quote_id = $quote_id");

    logAudit("Quote", "Email", "$session_name emailed quote $quote_prefix$quote_number to $contact_email", $client_id, $quote_id);

    flashAlert("Quote sent!");

    //Don't change the status to sent if the status is anything but draft
    if ($quote_status == 'Draft') {
        mysqli_query($mysqli,"UPDATE quotes SET quote_status = 'Sent' WHERE quote_id = $quote_id");
    }

    redirect();

}

if (isset($_GET['mark_quote_invoiced'])) {

    validateCSRFToken($_GET['csrf_token']);

    enforceUserPermission('module_sales', 2);

    $quote_id = intval($_GET['mark_quote_invoiced']);

    $sql = mysqli_query($mysqli,"SELECT * FROM quotes WHERE quote_id = $quote_id");
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

if(isset($_POST['export_quotes_csv'])){

    validateCSRFToken($_POST['csrf_token']);

    enforceUserPermission('module_sales');

    if ($_POST['client_id']) {
        $client_id = intval($_POST['client_id']);
        $client_query = "WHERE quote_client_id = $client_id";
        // Get Client Name for logging
        $client_name = getFieldById('clients', $client_id, 'client_name');
        $file_name_prepend = "$client_name-";
        enforceClientAccess();
    } else {
        $client_query = 'WHERE 1=1';
        $client_name = '';
        $file_name_prepend = "$session_company_name";
    }

    $sql = mysqli_query($mysqli,"SELECT * FROM quotes LEFT JOIN clients ON client_id = quote_client_id $client_query $access_permission_query ORDER BY quote_number ASC");

    $num_rows = mysqli_num_rows($sql);

    if($num_rows > 0){
        $delimiter = ",";
        $enclosure = '"';
        $escape    = '\\';   // backslash
        $filename = sanitizeFilename($file_name_prepend . "Quotes-" . date('Y-m-d_H-i-s') . ".csv");

        //create a file pointer
        $f = fopen('php://memory', 'w');

        //set column headers
        $fields = array('Quote Number', 'Scope', 'Amount', 'Date', 'Status');
        fputcsv($f, $fields, $delimiter, $enclosure, $escape);

        //output each row of the data, format line as csv and write to file pointer
        while($row = $sql->fetch_assoc()){
            $lineData = array($row['quote_prefix'] . $row['quote_number'], $row['quote_scope'], $row['quote_amount'], $row['quote_date'], $row['quote_status']);
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

    logAudit("Quote", "Export", "$session_name exported $num_rows quote(s) to a CSV file");

    flashAlert("Exported <strong>$num_rows</strong> quote(s)");

    exit;

}

if (isset($_GET['export_quote_pdf'])) {

    validateCSRFToken($_GET['csrf_token']);

    enforceUserPermission('module_sales');

    $quote_id = intval($_GET['export_quote_pdf']);

    $sql = mysqli_query(
        $mysqli,
        "SELECT * FROM quotes
        LEFT JOIN clients ON quote_client_id = client_id
        LEFT JOIN contacts ON clients.client_id = contacts.contact_client_id AND contact_primary = 1
        LEFT JOIN locations ON clients.client_id = locations.location_client_id AND location_primary = 1
        WHERE quote_id = $quote_id
        $access_permission_query
        LIMIT 1"
    );

    $row = mysqli_fetch_assoc($sql);
    $quote_id = intval($row['quote_id']);
    $quote_prefix = escapeHtml($row['quote_prefix']);
    $quote_number = intval($row['quote_number']);
    $quote_scope = escapeHtml($row['quote_scope']);
    $quote_status = escapeHtml($row['quote_status']);
    $quote_date = escapeHtml($row['quote_date']);
    $quote_expire = escapeHtml($row['quote_expire']);
    $quote_amount = floatval($row['quote_amount']);
    $quote_discount = floatval($row['quote_discount_amount']);
    $quote_currency_code = escapeHtml($row['quote_currency_code']);
    $quote_note = escapeHtml($row['quote_note']);
    $quote_url_key = escapeHtml($row['quote_url_key']);
    $quote_created_at = escapeHtml($row['quote_created_at']);
    $category_id = intval($row['quote_category_id']);
    $client_id = intval($row['client_id']);
    $client_name = escapeHtml($row['client_name']);
    $location_address = escapeHtml($row['location_address']);
    $location_city = escapeHtml($row['location_city']);
    $location_state = escapeHtml($row['location_state']);
    $location_zip = escapeHtml($row['location_zip']);
    $location_country = escapeHtml($row['location_country']);
    $contact_email = escapeHtml($row['contact_email']);
    $contact_phone_country_code = escapeHtml($row['contact_phone_country_code']);
    $contact_phone = escapeHtml(formatPhoneNumber($row['contact_phone'], $contact_phone_country_code));
    $contact_extension = escapeHtml($row['contact_extension']);
    $contact_mobile_country_code = escapeHtml($row['contact_mobile_country_code']);
    $contact_mobile = escapeHtml(formatPhoneNumber($row['contact_mobile'], $contact_mobile_country_code));
    $client_website = escapeHtml($row['client_website']);
    $client_currency_code = escapeHtml($row['client_currency_code']);
    $client_net_terms = intval($row['client_net_terms']);
    if ($client_net_terms == 0) {
        $client_net_terms = $config_default_net_terms;
    }

    enforceClientAccess();

    $sql = mysqli_query($mysqli, "SELECT * FROM companies, settings WHERE companies.company_id = settings.company_id AND companies.company_id = 1");
    $row = mysqli_fetch_assoc($sql);

    $company_id = intval($row['company_id']);
    $company_name = escapeHtml($row['company_name']);
    $company_country = escapeHtml($row['company_country']);
    $company_address = escapeHtml($row['company_address']);
    $company_city = escapeHtml($row['company_city']);
    $company_state = escapeHtml($row['company_state']);
    $company_zip = escapeHtml($row['company_zip']);
    $company_phone_country_code = escapeHtml($row['company_phone_country_code']);
    $company_phone = escapeHtml(formatPhoneNumber($row['company_phone'], $company_phone_country_code));
    $company_email = escapeHtml($row['company_email']);
    $company_website = escapeHtml($row['company_website']);
    $company_logo = escapeHtml($row['company_logo']);

    //Set Badge color based off of quote status
    if ($quote_status == "Sent") {
        $quote_badge_color = "warning text-white";
    } elseif ($quote_status == "Viewed") {
        $quote_badge_color = "primary";
    } elseif ($quote_status == "Accepted") {
        $quote_badge_color = "success";
    } elseif ($quote_status == "Declined") {
        $quote_badge_color = "danger";
    } elseif ($quote_status == "Invoiced") {
        $quote_badge_color = "info";
    } else {
        $quote_badge_color = "secondary";
    }

    require_once("../libs/TCPDF/tcpdf.php");

    // Start TCPDF
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetMargins(10, 10, 10);
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->AddPage();
    $pdf->SetFont('helvetica', '', 10);

    // Logo + Right Columns
    $html = '<table width="100%" cellspacing="0" cellpadding="3">
    <tr>
        <td width="40%">';
    if (!empty($company_logo) && file_exists("../uploads/settings/$company_logo")) {
        $html .= '<img src="/uploads/settings/' . $company_logo . '" width="120">';
    }
    $html .= '</td>
        <td width="60%" align="right">
            <span style="font-size:18pt; font-weight:bold;">QUOTE</span><br>
            <span style="font-size:14pt;">' . $quote_prefix . $quote_number . '</span><br>';
    if (strtolower($quote_status) === 'accepted') {
        $html .= '<span style="color:green; font-weight:bold;">ACCEPTED</span><br>';
    }
    if (strtolower($quote_status) === 'declined') {
        $html .= '<span style="color:red; font-weight:bold;">DECLINED</span><br>';
    }
    $html .= '</td>
    </tr>
    </table><br>';

    // Billing titles
    $html .= '<table width="100%" cellspacing="0" cellpadding="2">
    <tr>
        <td width="50%" style="font-size:14pt; font-weight:bold;">' . $company_name . '</td>
        <td width="50%" align="right" style="font-size:14pt; font-weight:bold;">' . $client_name . '</td>
    </tr>
    <tr>
        <td style="font-size:10pt; line-height:1.4;">' . nl2br("$company_address\n$company_city $company_state $company_zip\n$company_country\n$company_phone\n$company_website") . '</td>
        <td style="font-size:10pt; line-height:1.4;" align="right">' . nl2br("$location_address\n$location_city $location_state $location_zip\n$location_country\n$contact_email\n$contact_phone") . '</td>
    </tr>
    </table><br>';

    // Date table
    $html .= '<table border="0" cellpadding="2" cellspacing="0" width="100%">
    <tr>
        <td width="60%"></td>
        <td width="20%" style="font-size:10pt;"><strong>Date:</strong></td>
        <td width="20%" style="font-size:10pt;" align="right">' . $quote_date . '</td>
    </tr>
    <tr>
        <td></td>
        <td style="font-size:10pt;"><strong>Expires:</strong></td>
        <td style="font-size:10pt;" align="right">' . $quote_expire . '</td>
    </tr>
    </table><br><br>';

    // Items header
    $html .= '
    <table border="0" cellpadding="5" cellspacing="0" width="100%">
    <tr style="background-color:#f0f0f0;">
        <th align="left" width="40%"><strong>Item</strong></th>
        <th align="center" width="10%"><strong>Qty</strong></th>
        <th align="right" width="15%"><strong>Price</strong></th>
        <th align="right" width="15%"><strong>Tax</strong></th>
        <th align="right" width="20%"><strong>Amount</strong></th>
    </tr>';

    // Load items
    $sub_total = 0;
    $total_tax = 0;

    $sql_items = mysqli_query($mysqli, "SELECT * FROM quote_items WHERE item_quote_id = $quote_id ORDER BY item_order ASC");
    while ($item = mysqli_fetch_assoc($sql_items)) {
        $name = $item['item_name'];
        $desc = $item['item_description'];
        $qty = $item['item_quantity'];
        $price = $item['item_price'];
        $tax = $item['item_tax'];
        $total = $item['item_total'];

        $sub_total += $price * $qty;
        $total_tax += $tax;

        $html .= '
        <tr>
            <td><strong>' . $name . '</strong>
                <br><span style="font-style:italic; font-size:9pt;">' . nl2br($desc) . '</span>
            </td>
            <td align="center">' . number_format($qty, 2) . '</td>
            <td align="right">' . numfmt_format_currency($currency_format, $price, $quote_currency_code) . '</td>
            <td align="right">' . numfmt_format_currency($currency_format, $tax, $quote_currency_code) . '</td>
            <td align="right">' . numfmt_format_currency($currency_format, $total, $quote_currency_code) . '</td>
        </tr>';
    }

    $html .= '</table><br><hr><br><br>';

    // Totals
    $html .= '<table width="100%" cellspacing="0" cellpadding="4">
    <tr>
        <td width="60%"><i style="font-size:9pt;">' . nl2br($quote_note) . '</i></td>
        <td width="40%">
            <table width="100%" cellpadding="3" cellspacing="0">
                <tr><td>Subtotal:</td><td align="right">' . numfmt_format_currency($currency_format, $sub_total, $quote_currency_code) . '</td></tr>';
    if ($quote_discount > 0) {
        $html .= '<tr><td>Discount:</td><td align="right">-' . numfmt_format_currency($currency_format, $quote_discount, $quote_currency_code) . '</td></tr>';
    }
    if ($total_tax > 0) {
        $html .= '<tr><td>Tax:</td><td align="right">' . numfmt_format_currency($currency_format, $total_tax, $quote_currency_code) . '</td></tr>';
    }
    $html .= '
    <tr><td><h3><strong>Total:</strong></h3></td><td align="right"><h3><strong>' . numfmt_format_currency($currency_format, $quote_amount, $quote_currency_code) . '</strong></h3></td></tr>
    </table>
        </td>
    </tr>
    </table><br><br>';

    // Footer
    $html .= '<div style="text-align:center; font-size:9pt; color:gray;">' . nl2br($config_quote_footer) . '</div>';

    $pdf->writeHTML($html, true, false, true, false, '');

    $filename = preg_replace('/[^A-Za-z0-9_\-]/', '_', "{$quote_date}_{$company_name}_{$client_name}_Quote_{$quote_prefix}{$quote_number}");
    $pdf->Output("$filename.pdf", 'I');

    exit;

}
