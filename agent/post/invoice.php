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

    // Calculate new total
    $sql = mysqli_query($mysqli,"SELECT item_total FROM invoice_items WHERE item_invoice_id = $invoice_id");
    $invoice_amount = 0;
    while($row = mysqli_fetch_assoc($sql)) {
        $item_total = floatval($row['item_total']);
        $invoice_amount = $invoice_amount + $item_total;
    }
    $invoice_amount = $invoice_amount - $invoice_discount;


    mysqli_query($mysqli,"UPDATE invoices SET invoice_scope = '$scope', invoice_date = '$date', invoice_due = '$due', invoice_category_id = $category, invoice_discount_amount = '$invoice_discount', invoice_amount = '$invoice_amount' WHERE invoice_id = $invoice_id");

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
    $client_net_terms = intval($row['client_net_terms']);
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

    mysqli_query($mysqli,"INSERT INTO history SET history_status = 'Draft', history_description = 'Copied INVOICE!', history_invoice_id = $new_invoice_id");

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

    logAudit("Invoice", "Create", "$session_name created new Invoice $config_invoice_prefix$new_invoice_number from $old_invoice_prefix$old_invoice_prefix", $client_id, $new_invoice_id);

    triggerCustomAction('invoice_create', $new_invoice_id);

    flashAlert("Created new Invoice <strong>$config_invoice_prefix$new_invoice_number</strong> from <strong>$old_invoice_prefix$old_invoice_prefix</strong>");

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

if (isset($_POST['add_invoice_item'])) {

    validateCSRFToken();

    enforceUserPermission('module_sales', 2);

    $invoice_id = intval($_POST['invoice_id']);
    $name = escapeSql($_POST['name']);
    $description = escapeSql($_POST['description']);
    $qty = floatval($_POST['qty']);
    $price = floatval($_POST['price']);
    $tax_id = intval($_POST['tax_id']);
    $item_order = intval($_POST['item_order']);
    $product_id = intval($_POST['product_id']);

    $client_id = intval(getFieldById('invoices', $invoice_id, 'invoice_client_id'));

    enforceClientAccess();

    $subtotal = $price * $qty;

    // Update Product Inventory
    if ($product_id) {
         // Only enforce stock for tangible products
        $product_type = escapeSql(getFieldById('products', $product_id, 'product_type'));
        if ($product_type === 'product') {

            // Current available stock
            $sql = mysqli_query(
                $mysqli,
                "SELECT COALESCE(SUM(stock_qty), 0) AS available_stock
                 FROM product_stock
                 WHERE stock_product_id = $product_id"
            );
            $row = mysqli_fetch_assoc($sql);
            $available_stock = floatval($row['available_stock']);

            // Enough in stock?
            if ($available_stock >= $qty) {
                mysqli_query($mysqli,"INSERT INTO product_stock SET stock_qty = -$qty, stock_note = 'QTY $qty - Invoice $invoice_id', stock_product_id = $product_id");
            } else {
                // Not enough in stock: stop and notify
                flashAlert("Not Enough <strong>$name</strong> in stock", 'error');
                redirect();
            }
        }
    }

    // Tax
    if ($tax_id > 0) {
        $sql = mysqli_query($mysqli,"SELECT tax_percent FROM taxes WHERE tax_id = $tax_id");
        $row = mysqli_fetch_assoc($sql);
        $tax_percent = floatval($row['tax_percent']);
        $tax_amount = $subtotal * $tax_percent / 100;
    } else {
        $tax_amount = 0;
    }

    $total = $subtotal + $tax_amount;

    mysqli_query($mysqli,"INSERT INTO invoice_items SET item_name = '$name', item_description = '$description', item_quantity = $qty, item_price = $price, item_subtotal = $subtotal, item_tax = $tax_amount, item_total = $total, item_order = $item_order, item_tax_id = $tax_id, item_product_id = $product_id, item_invoice_id = $invoice_id");

    // Get Discount and Invoice Details
    $sql = mysqli_query($mysqli,"SELECT invoice_discount_amount, invoice_number, invoice_prefix FROM invoices WHERE invoice_id = $invoice_id");
    $row = mysqli_fetch_assoc($sql);
    $invoice_prefix = escapeSql($row['invoice_prefix']);
    $invoice_number = intval($row['invoice_number']);
    $invoice_discount = floatval($row['invoice_discount_amount']);

    //add up all line items
    $sql = mysqli_query($mysqli,"SELECT item_total FROM invoice_items WHERE item_invoice_id = $invoice_id");
    $invoice_total = 0;
    while($row = mysqli_fetch_assoc($sql)) {
        $item_total = floatval($row['item_total']);
        $invoice_total = $invoice_total + $item_total;
    }
    $new_invoice_amount = $invoice_total - $invoice_discount;

    mysqli_query($mysqli,"UPDATE invoices SET invoice_amount = $new_invoice_amount WHERE invoice_id = $invoice_id");

    logAudit("Invoice", "Edit", "$session_name added item $name to invoice $invoice_prefix$invoice_number", $client_id, $invoice_id);

    flashAlert("Item <strong>$name</strong> added to invoice");

    redirect();

}

if (isset($_POST['edit_invoice_item'])) {

    validateCSRFToken();

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
        $sql = mysqli_query($mysqli,"SELECT tax_percent FROM taxes WHERE tax_id = $tax_id");
        $row = mysqli_fetch_assoc($sql);
        $tax_percent = floatval($row['tax_percent']);
        $tax_amount = $subtotal * $tax_percent / 100;
    } else {
        $tax_amount = 0;
    }

    $total = $subtotal + $tax_amount;

    // Determine what type of line item
    $sql = mysqli_query($mysqli,"SELECT item_invoice_id FROM invoice_items WHERE item_id = $item_id");
    $row = mysqli_fetch_assoc($sql);
    $invoice_id = intval($row['item_invoice_id']);

    //Get Discount Amount
    $sql = mysqli_query($mysqli,"SELECT invoice_client_id, invoice_discount_amount, invoice_number, invoice_prefix FROM invoices WHERE invoice_id = $invoice_id");
    $row = mysqli_fetch_assoc($sql);
    $invoice_prefix = escapeSql($row['invoice_prefix']);
    $invoice_number = intval($row['invoice_number']);
    $client_id = intval($row['invoice_client_id']);
    $invoice_discount = floatval($row['invoice_discount_amount']);

    enforceClientAccess();

    mysqli_query($mysqli,"UPDATE invoice_items SET item_name = '$name', item_description = '$description', item_quantity = $qty, item_price = $price, item_subtotal = $subtotal, item_tax = $tax_amount, item_total = $total, item_tax_id = $tax_id WHERE item_id = $item_id");

    //Update Invoice Balances by tallying up invoice items
    $sql_invoice_total = mysqli_query($mysqli,"SELECT SUM(item_total) AS invoice_total FROM invoice_items WHERE item_invoice_id = $invoice_id");
    $row = mysqli_fetch_assoc($sql_invoice_total);
    $new_invoice_amount = floatval($row['invoice_total']) - $invoice_discount;

    mysqli_query($mysqli,"UPDATE invoices SET invoice_amount = $new_invoice_amount WHERE invoice_id = $invoice_id");

    logAudit("Invoice", "Edit", "$session_name edited item $name on invoice $invoice_prefix$invoice_number", $client_id, $invoice_id);

    flashAlert("Item <strong>$name</strong> updated");

    redirect();

}

if (isset($_GET['delete_invoice_item'])) {

    validateCSRFToken();

    enforceUserPermission('module_sales', 2);

    $item_id = intval($_GET['delete_invoice_item']);

    $sql = mysqli_query($mysqli,"SELECT item_invoice_id, item_name, item_product_id, item_quantity, item_subtotal, item_tax,
        item_total FROM invoice_items WHERE item_id = $item_id");
    $row = mysqli_fetch_assoc($sql);
    $invoice_id = intval($row['item_invoice_id']);
    $item_name = escapeSql($row['item_name']);
    $item_quantity = floatval($row['item_quantity']);
    $item_product_id = intval($row['item_product_id']);
    $item_subtotal = floatval($row['item_subtotal']);
    $item_tax = floatval($row['item_tax']);
    $item_total = floatval($row['item_total']);

    $sql = mysqli_query($mysqli,"SELECT invoice_amount, invoice_client_id, invoice_number, invoice_prefix FROM invoices WHERE invoice_id = $invoice_id");
    $row = mysqli_fetch_assoc($sql);
    $invoice_prefix = escapeSql($row['invoice_prefix']);
    $invoice_number = intval($row['invoice_number']);
    $client_id = intval($row['invoice_client_id']);

    enforceClientAccess();

    $new_invoice_amount = floatval($row['invoice_amount']) - $item_total;

    mysqli_query($mysqli,"UPDATE invoices SET invoice_amount = $new_invoice_amount WHERE invoice_id = $invoice_id");

    mysqli_query($mysqli,"DELETE FROM invoice_items WHERE item_id = $item_id");

    // Return Product Inventory
    if ($item_product_id) {
        mysqli_query($mysqli,"INSERT INTO product_stock SET stock_qty = $item_quantity, stock_note = 'Returned QTY $item_quantity back to stock from Invoice $invoice_id', stock_product_id = $item_product_id");
    }

    logAudit("Invoice", "Delete", "$session_name removed item $item_name from invoice $invoice_prefix$invoice_number", $client_id, $invoice_id);

    flashAlert("Item <strong>$item_name</strong> removed from invoice", 'error');

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

    // Recipients come from the Send Email modal's contact picker. Scoping the
    // lookup to this invoice's client is what makes a tampered contact_id
    // harmless - it simply matches nothing.
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

if (isset($_POST['link_invoice_to_ticket'])) {

    validateCSRFToken();

    enforceUserPermission('module_sales', 2);

    $invoice_id = intval($_POST['invoice_id']);
    $ticket_id = intval($_POST['ticket_id']);

    $client_id = intval(getFieldById('invoices', $invoice_id, 'invoice_client_id'));

    enforceClientAccess();

    mysqli_query($mysqli,"UPDATE invoices SET invoice_ticket_id = $ticket_id WHERE invoice_id = $invoice_id");

    flashAlert("Invoice linked to ticket");

    redirect();

}

if (isset($_POST['add_ticket_to_invoice'])) {

    validateCSRFToken();

    enforceUserPermission('module_sales', 2);

    $invoice_id = intval($_POST['invoice_id']);
    $ticket_id = intval($_POST['ticket_id']);

    $client_id = intval(getFieldById('tickets', $ticket_id, 'ticket_client_id'));

    enforceClientAccess();

    mysqli_query($mysqli,"UPDATE tickets SET ticket_invoice_id = $invoice_id WHERE ticket_id = $ticket_id");

    flashAlert("Ticket linked to invoice");

    redirect("post.php?add_ticket_to_invoice=$invoice_id");

}

if (isset($_GET['export_invoice_pdf'])) {

    validateCSRFToken();

    enforceUserPermission('module_sales');

    $invoice_id = intval($_GET['export_invoice_pdf']);

    $sql = mysqli_query(
        $mysqli,
        "SELECT client_currency_code, client_id, client_name, client_net_terms, client_website,
            contact_email, contact_extension, contact_mobile, contact_mobile_country_code,
            contact_phone, contact_phone_country_code, invoice_amount, invoice_category_id,
            invoice_created_at, invoice_currency_code, invoice_date, invoice_discount_amount,
            invoice_due, invoice_id, invoice_note, invoice_number, invoice_prefix, invoice_scope,
            invoice_status, invoice_url_key, location_address, location_city, location_country,
            location_state, location_zip FROM invoices
        LEFT JOIN clients ON invoice_client_id = client_id
        LEFT JOIN contacts ON clients.client_id = contacts.contact_client_id AND contact_primary = 1
        LEFT JOIN locations ON clients.client_id = locations.location_client_id AND location_primary = 1
        WHERE invoice_id = $invoice_id
        " . clientScopeSql('invoice_client_id') . "
        LIMIT 1"
    );

    $row = mysqli_fetch_assoc($sql);
    $invoice_id = intval($row['invoice_id']);
    $invoice_prefix = escapeHtml($row['invoice_prefix']);
    $invoice_number = intval($row['invoice_number']);
    $invoice_scope = escapeHtml($row['invoice_scope']);
    $invoice_status = escapeHtml($row['invoice_status']);
    $invoice_date = escapeHtml($row['invoice_date']);
    $invoice_due = escapeHtml($row['invoice_due']);
    $invoice_amount = floatval($row['invoice_amount']);
    $invoice_discount = floatval($row['invoice_discount_amount']);
    $invoice_currency_code = escapeHtml($row['invoice_currency_code']);
    $invoice_note = escapeHtml($row['invoice_note']);
    $invoice_url_key = escapeHtml($row['invoice_url_key']);
    $invoice_created_at = escapeHtml($row['invoice_created_at']);
    $category_id = intval($row['invoice_category_id']);
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

    $sql = mysqli_query($mysqli, "SELECT company_address, company_city, company_country, company_email, company_id, company_logo,
        company_name, company_phone, company_phone_country_code, company_state, company_tax_id,
        company_website, company_zip FROM companies WHERE company_id = 1");
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
    $company_tax_id = escapeHtml($row['company_tax_id']);
    if ($config_invoice_show_tax_id && !empty($company_tax_id)) {
        $company_tax_id_display = "Tax ID: $company_tax_id";
    } else {
        $company_tax_id_display = "";
    }
    $company_logo = escapeHtml($row['company_logo']);

    $sql_payments = mysqli_query($mysqli, "SELECT * FROM payments, accounts WHERE payment_account_id = account_id AND payment_invoice_id = $invoice_id ORDER BY payments.payment_id DESC");

    //Add up all the payments for the invoice and get the total amount paid to the invoice
    $sql_amount_paid = mysqli_query($mysqli, "SELECT SUM(payment_amount) AS amount_paid FROM payments WHERE payment_invoice_id = $invoice_id");
    $row = mysqli_fetch_assoc($sql_amount_paid);
    $amount_paid = floatval($row['amount_paid']);

    $balance = $invoice_amount - $amount_paid;

    //check to see if overdue
    if ($invoice_status !== "Paid" && $invoice_status !== "Draft" && $invoice_status !== "Cancelled" && $invoice_status !== "Non-Billable") {
        $unixtime_invoice_due = strtotime($invoice_due) + 86400;
        if ($unixtime_invoice_due < time()) {
            $invoice_overdue = "Overdue";
        }
    }

    //Set Badge color based off of invoice status
    $invoice_badge_color = getInvoiceBadgeColor($invoice_status);

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
            <span style="font-size:18pt; font-weight:bold;">Invoice</span><br>
            <span style="font-size:14pt;">' . $invoice_prefix . $invoice_number . '</span><br>';
    if (strtolower($invoice_status) === 'paid') {
        $html .= '<span style="color:green; font-weight:bold;">PAID</span><br>';
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
        <td style="font-size:10pt; line-height:1.4;">' . nl2br(formatAddress($company_address, $company_city, $company_state, $company_zip, $company_country) . "\n$company_phone\n$company_website\n$company_tax_id_display") . '</td>
        <td style="font-size:10pt; line-height:1.4;" align="right">' . nl2br(formatAddress($location_address, $location_city, $location_state, $location_zip, $location_country) . "\n$contact_email\n$contact_phone") . '</td>
    </tr>
    </table><br>';

    // Date table
    $html .= '<table border="0" cellpadding="2" cellspacing="0" width="100%">
    <tr>
        <td width="60%"></td>
        <td width="20%" style="font-size:10pt;"><strong>Date:</strong></td>
        <td width="20%" style="font-size:10pt;" align="right">' . $invoice_date . '</td>
    </tr>
    <tr>
        <td></td>
        <td style="font-size:10pt;"><strong>Due:</strong></td>
        <td style="font-size:10pt;" align="right">' . $invoice_due . '</td>
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

    $sql_items = mysqli_query($mysqli, "SELECT item_description, item_name, item_price, item_quantity, item_tax, item_total FROM invoice_items WHERE item_invoice_id = $invoice_id ORDER BY item_order ASC");
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
            <td align="right">' . numfmt_format_currency($currency_format, $price, $invoice_currency_code) . '</td>
            <td align="right">' . numfmt_format_currency($currency_format, $tax, $invoice_currency_code) . '</td>
            <td align="right">' . numfmt_format_currency($currency_format, $total, $invoice_currency_code) . '</td>
        </tr>';
    }

    $html .= '</table><br><hr><br><br>';

    // Totals
    $html .= '<table width="100%" cellspacing="0" cellpadding="4">
    <tr>
        <td width="60%"><i style="font-size:9pt;">' . nl2br($invoice_note) . '</i></td>
        <td width="40%">
            <table width="100%" cellpadding="3" cellspacing="0">
                <tr><td>Subtotal:</td><td align="right">' . numfmt_format_currency($currency_format, $sub_total, $invoice_currency_code) . '</td></tr>';
    if ($invoice_discount > 0) {
        $html .= '<tr><td>Discount:</td><td align="right">-' . numfmt_format_currency($currency_format, $invoice_discount, $invoice_currency_code) . '</td></tr>';
    }
    if ($total_tax > 0) {
        $html .= '<tr><td>Tax:</td><td align="right">' . numfmt_format_currency($currency_format, $total_tax, $invoice_currency_code) . '</td></tr>';
    }
    $html .= '
    <tr><td>Total:</td><td align="right">' . numfmt_format_currency($currency_format, $invoice_amount, $invoice_currency_code) . '</td></tr>';
    if ($amount_paid > 0) {
        $html .= '<tr><td>Paid:</td><td align="right">' . numfmt_format_currency($currency_format, $amount_paid, $invoice_currency_code) . '</td></tr>';
    }
    $html .= '
    <tr><td><h3><strong>Balance:</strong></h3></td><td align="right"><h3><strong>' . numfmt_format_currency($currency_format, $balance, $invoice_currency_code) . '</strong></h3></td></tr>
    </table>
        </td>
    </tr>
    </table><br><br>';

    // Footer
    $html .= '<div style="text-align:center; font-size:9pt; color:gray;">' . nl2br($config_invoice_footer) . '</div>';

    $pdf->writeHTML($html, true, false, true, false, '');

    $filename = preg_replace('/[^A-Za-z0-9_\-]/', '_', "{$invoice_date}_{$company_name}_{$client_name}_Invoice_{$invoice_prefix}{$invoice_number}");
    $pdf->Output("$filename.pdf", 'I');

    exit;

}

if (isset($_GET['export_invoice_packing_slip'])) {

    validateCSRFToken();

    enforceUserPermission('module_sales');

    $invoice_id = intval($_GET['export_invoice_packing_slip']);

    $sql = mysqli_query(
        $mysqli,
        "SELECT client_id, client_name, contact_email, contact_extension, contact_phone,
            contact_phone_country_code, invoice_date, invoice_id, invoice_number, invoice_prefix,
            location_address, location_city, location_country, location_state, location_zip FROM invoices
        LEFT JOIN clients ON invoice_client_id = client_id
        LEFT JOIN contacts ON clients.client_id = contacts.contact_client_id AND contact_primary = 1
        LEFT JOIN locations ON clients.client_id = locations.location_client_id AND location_primary = 1
        WHERE invoice_id = $invoice_id
        " . clientScopeSql('invoice_client_id') . "
        LIMIT 1"
    );

    $row = mysqli_fetch_assoc($sql);
    $invoice_id = intval($row['invoice_id']);
    $invoice_prefix = escapeHtml($row['invoice_prefix']);
    $invoice_number = intval($row['invoice_number']);
    $invoice_date = escapeHtml($row['invoice_date']);
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

    enforceClientAccess();

    $sql = mysqli_query($mysqli, "SELECT company_address, company_city, company_country, company_email, company_id, company_logo,
        company_name, company_phone, company_phone_country_code, company_state, company_website,
        company_zip FROM companies WHERE company_id = 1");
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
            <span style="font-size:18pt; font-weight:bold;">Packing Slip</span><br>
            <span style="font-size:14pt;">' . $invoice_prefix . $invoice_number . '</span><br>';
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
        <td style="font-size:10pt; line-height:1.4;">' . nl2br(formatAddress($company_address, $company_city, $company_state, $company_zip, $company_country) . "\n$company_phone\n$company_website") . '</td>
        <td style="font-size:10pt; line-height:1.4;" align="right">' . nl2br(formatAddress($location_address, $location_city, $location_state, $location_zip, $location_country) . "\n$contact_email\n$contact_phone") . '</td>
    </tr>
    </table><br>';

    // Items header
    $html .= '
    <table border="0" cellpadding="5" cellspacing="0" width="100%">
    <tr style="background-color:#f0f0f0;">
        <th align="left" width="50%"><strong>Item</strong></th>
        <th align="center" width="40%"><strong>Qty</strong></th>
        <th align="right" width="10%"><strong>Picked?</strong></th>
    </tr>';

    // Load items
    $sub_total = 0;
    $total_tax = 0;

    $sql_items = mysqli_query($mysqli, "SELECT item_name, item_quantity FROM invoice_items WHERE item_invoice_id = $invoice_id ORDER BY item_order ASC");
    while ($item = mysqli_fetch_assoc($sql_items)) {
        $name = $item['item_name'];
        $qty = $item['item_quantity'];

        $html .= '
        <tr>
            <td><strong>' . $name . '</strong></td>
            <td align="center">' . number_format($qty, 2) . '</td>
            <td align="right">
                <table cellpadding="0" cellspacing="0">
                    <tr>
                        <td style="border:0.5px solid #000; width:12px; height:5px; margin-top:2px"></td>
                    </tr>
                </table>
            </td>
        </tr>';
    }
    $html .= '</table><br><br><br>';


    // Picked/Checked by
    $html .= '
    <table width="100%" cellspacing="0" cellpadding="8" style="font-size:10pt; margin-top:20px;">
        <tr>
            <td width="50%" style="border:1px solid #000; height:60px;">
                <strong>Picked By:</strong><br><br>
            </td>
            <td width="50%" style="border:1px solid #000; height:60px;">
                <strong>Checked By:</strong><br><br>
            </td>
        </tr>
    </table>
    <br><br>';

    $pdf->writeHTML($html, true, false, true, false, '');

    $filename = preg_replace('/[^A-Za-z0-9_\-]/', '_', "{$invoice_date}_{$company_name}_{$client_name}_Invoice_{$invoice_prefix}{$invoice_number}");
    $pdf->Output("$filename.pdf", 'I');

    exit;

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
