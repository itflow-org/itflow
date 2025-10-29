<?php

/*
 * ITFlow - GET/POST request handler for invoices
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['add_invoice'])) {

    require_once 'invoice_model.php';

    $client_id = intval($_POST['client']);

    // Get Net Terms
    $client_net_terms = intval(getFieldById('clients', $client_id, 'client_net_terms'));

    //Get the last Invoice Number and add 1 for the new invoice number
    $invoice_number = $config_invoice_next_number;
    $new_config_invoice_next_number = $config_invoice_next_number + 1;
    mysqli_query($mysqli,"UPDATE settings SET config_invoice_next_number = $new_config_invoice_next_number WHERE company_id = 1");

    //Generate a unique URL key for clients to access
    $url_key = randomString(156);

    mysqli_query($mysqli,"INSERT INTO invoices SET invoice_prefix = '$config_invoice_prefix', invoice_number = $invoice_number, invoice_scope = '$scope', invoice_date = '$date', invoice_due = DATE_ADD('$date', INTERVAL $client_net_terms day), invoice_currency_code = '$session_company_currency', invoice_category_id = $category, invoice_status = 'Draft', invoice_url_key = '$url_key', invoice_client_id = $client_id");
    
    $invoice_id = mysqli_insert_id($mysqli);

    mysqli_query($mysqli,"INSERT INTO history SET history_status = 'Draft', history_description = 'Invoice created', history_invoice_id = $invoice_id");

    logAction("Invoice", "Create", "$session_name created Invoice $config_invoice_prefix$invoice_number - $scope", $client_id, $invoice_id);

    customAction('invoice_create', $invoice_id);

    flash_alert("Invoice <strong>$config_invoice_prefix$invoice_number</strong> created");

    redirect("invoice.php?invoice_id=$invoice_id");

}

if (isset($_POST['edit_invoice'])) {

    require_once 'invoice_model.php';

    $invoice_id = intval($_POST['invoice_id']);
    $due = sanitizeInput($_POST['due']);

    // Get Invoice Number and Prefix and Client ID for Logging
    $sql = mysqli_query($mysqli,"SELECT invoice_prefix, invoice_number, invoice_client_id FROM invoices WHERE invoice_id = $invoice_id");
    $row = mysqli_fetch_array($sql);
    $invoice_prefix = sanitizeInput($row['invoice_prefix']);
    $invoice_number = intval($row['invoice_number']);
    $client_id = intval($row['invoice_client_id']);

    // Calculate new total
    $sql = mysqli_query($mysqli,"SELECT * FROM invoice_items WHERE item_invoice_id = $invoice_id");
    $invoice_amount = 0;
    while($row = mysqli_fetch_array($sql)) {
        $item_total = floatval($row['item_total']);
        $invoice_amount = $invoice_amount + $item_total;
    }
    $invoice_amount = $invoice_amount - $invoice_discount;


    mysqli_query($mysqli,"UPDATE invoices SET invoice_scope = '$scope', invoice_date = '$date', invoice_due = '$due', invoice_category_id = $category, invoice_discount_amount = '$invoice_discount', invoice_amount = '$invoice_amount' WHERE invoice_id = $invoice_id");

    logAction("Invoice", "Edit", "$session_name edited Invoice $invoice_prefix$invoice_number - $scope", $client_id, $invoice_id);

    flash_alert("Invoice <strong>$invoice_prefix$invoice_number</strong> edited");

    redirect();

}

if (isset($_POST['add_invoice_copy'])) {

    $invoice_id = intval($_POST['invoice_id']);
    $date = sanitizeInput($_POST['date']);

    //Get Net Terms
    $sql = mysqli_query($mysqli,"SELECT client_net_terms FROM clients, invoices WHERE client_id = invoice_client_id AND invoice_id = $invoice_id");
    $row = mysqli_fetch_array($sql);
    $client_net_terms = intval($row['client_net_terms']);

    $new_invoice_number = $config_invoice_next_number;
    $new_config_invoice_next_number = $config_invoice_next_number + 1;
    mysqli_query($mysqli,"UPDATE settings SET config_invoice_next_number = $new_config_invoice_next_number WHERE company_id = 1");

    $sql = mysqli_query($mysqli,"SELECT * FROM invoices WHERE invoice_id = $invoice_id");
    $row = mysqli_fetch_array($sql);
    $invoice_scope = sanitizeInput($row['invoice_scope']);
    $invoice_discount_amount = floatval($row['invoice_discount_amount']);
    $invoice_amount = floatval($row['invoice_amount']);
    $invoice_currency_code = sanitizeInput($row['invoice_currency_code']);
    $invoice_note = sanitizeInput($row['invoice_note']);
    $client_id = intval($row['invoice_client_id']);
    $category_id = intval($row['invoice_category_id']);
    $old_invoice_prefix = sanitizeInput($row['invoice_prefix']);
    $old_invoice_number = intval($row['invoice_number']);

    //Generate a unique URL key for clients to access
    $url_key = randomString(156);

    mysqli_query($mysqli,"INSERT INTO invoices SET invoice_prefix = '$config_invoice_prefix', invoice_number = $new_invoice_number, invoice_scope = '$invoice_scope', invoice_date = '$date', invoice_due = DATE_ADD('$date', INTERVAL $client_net_terms day), invoice_category_id = $category_id, invoice_status = 'Draft', invoice_discount_amount = $invoice_discount_amount, invoice_amount = $invoice_amount, invoice_currency_code = '$invoice_currency_code', invoice_note = '$invoice_note', invoice_url_key = '$url_key', invoice_client_id = $client_id");

    $new_invoice_id = mysqli_insert_id($mysqli);

    mysqli_query($mysqli,"INSERT INTO history SET history_status = 'Draft', history_description = 'Copied INVOICE!', history_invoice_id = $new_invoice_id");

    $sql_items = mysqli_query($mysqli,"SELECT * FROM invoice_items WHERE item_invoice_id = $invoice_id");
    while($row = mysqli_fetch_array($sql_items)) {
        $item_id = intval($row['item_id']);
        $item_name = sanitizeInput($row['item_name']);
        $item_description = sanitizeInput($row['item_description']);
        $item_quantity = floatval($row['item_quantity']);
        $item_price = floatval($row['item_price']);
        $item_subtotal = floatval($row['item_subtotal']);
        $item_tax = floatval($row['item_tax']);
        $item_total = floatval($row['item_total']);
        $item_order = intval($row['item_order']);
        $tax_id = intval($row['item_tax_id']);

        mysqli_query($mysqli,"INSERT INTO invoice_items SET item_name = '$item_name', item_description = '$item_description', item_quantity = $item_quantity, item_price = $item_price, item_subtotal = $item_subtotal, item_tax = $item_tax, item_total = $item_total, item_order = $item_order, item_tax_id = $tax_id, item_invoice_id = $new_invoice_id");
    }

    logAction("Invoice", "Create", "$session_name created new Invoice $config_invoice_prefix$new_invoice_number from $old_invoice_prefix$old_invoice_prefix", $client_id, $new_invoice_id);

    customAction('invoice_create', $new_invoice_id);

    flash_alert("Created new Invoice <strong>$config_invoice_prefix$new_invoice_number</strong> from <strong>$old_invoice_prefix$old_invoice_prefix</strong>");

    redirect("invoice.php?invoice_id=$new_invoice_id");

}

if (isset($_GET['mark_invoice_sent'])) {

    $invoice_id = intval($_GET['mark_invoice_sent']);

    // Get Invoice Number and Prefix and Client ID for Logging
    $sql = mysqli_query($mysqli,"SELECT invoice_prefix, invoice_number, invoice_client_id FROM invoices WHERE invoice_id = $invoice_id");
    $row = mysqli_fetch_array($sql);
    $invoice_prefix = sanitizeInput($row['invoice_prefix']);
    $invoice_number = intval($row['invoice_number']);
    $client_id = intval($row['invoice_client_id']);

    mysqli_query($mysqli,"UPDATE invoices SET invoice_status = 'Sent' WHERE invoice_id = $invoice_id");

    mysqli_query($mysqli,"INSERT INTO history SET history_status = 'Sent', history_description = 'Invoice marked sent', history_invoice_id = $invoice_id");

    logAction("Invoice", "Edit", "$session_name marked invoice $invoice_prefix$invoice_number sent", $client_id, $invoice_id);

    flash_alert("Invoice marked sent");

    redirect();

}

if (isset($_GET['mark_invoice_non-billable'])) {

    $invoice_id = intval($_GET['mark_invoice_non-billable']);

    // Get Invoice Number and Prefix and Client ID for Logging
    $sql = mysqli_query($mysqli,"SELECT invoice_prefix, invoice_number, invoice_client_id FROM invoices WHERE invoice_id = $invoice_id");
    $row = mysqli_fetch_array($sql);
    $invoice_prefix = sanitizeInput($row['invoice_prefix']);
    $invoice_number = intval($row['invoice_number']);
    $client_id = intval($row['invoice_client_id']);

    mysqli_query($mysqli,"UPDATE invoices SET invoice_status = 'Non-Billable' WHERE invoice_id = $invoice_id");

    mysqli_query($mysqli,"INSERT INTO history SET history_status = 'Non-Billable', history_description = 'INVOICE marked Non-Billable', history_invoice_id = $invoice_id");

    logAction("Invoice", "Edit", "$session_name marked invoice $invoice_prefix$invoice_number Non-Billable", $client_id, $invoice_id);

    flash_alert("Invoice marked Non-Billable");

    redirect();

}

if (isset($_GET['cancel_invoice'])) {

    $invoice_id = intval($_GET['cancel_invoice']);

    // Get Invoice Number and Prefix and Client ID for Logging
    $sql = mysqli_query($mysqli,"SELECT invoice_prefix, invoice_number, invoice_client_id FROM invoices WHERE invoice_id = $invoice_id");
    $row = mysqli_fetch_array($sql);
    $invoice_prefix = sanitizeInput($row['invoice_prefix']);
    $invoice_number = intval($row['invoice_number']);
    $client_id = intval($row['invoice_client_id']);

    mysqli_query($mysqli,"UPDATE invoices SET invoice_status = 'Cancelled' WHERE invoice_id = $invoice_id");

    mysqli_query($mysqli,"INSERT INTO history SET history_status = 'Cancelled', history_description = 'Invoice cancelled', history_invoice_id = $invoice_id");

    logAction("Invoice", "Edit", "$session_name cancelled invoice $invoice_prefix$invoice_number", $client_id, $invoice_id);

    flash_alert("Invoice <strong>$invoice_prefix$invoice_number</strong> cancelled", 'error');

    redirect();

}

if (isset($_GET['delete_invoice'])) {
    
    $invoice_id = intval($_GET['delete_invoice']);

    // Get Invoice Number and Prefix and Client ID for Logging
    $sql = mysqli_query($mysqli,"SELECT invoice_prefix, invoice_number, invoice_client_id FROM invoices WHERE invoice_id = $invoice_id");
    $row = mysqli_fetch_array($sql);
    $invoice_prefix = sanitizeInput($row['invoice_prefix']);
    $invoice_number = intval($row['invoice_number']);
    $client_id = intval($row['invoice_client_id']);

    mysqli_query($mysqli,"DELETE FROM invoices WHERE invoice_id = $invoice_id");

    //Delete Items Associated with the Invoice
    $sql = mysqli_query($mysqli,"SELECT * FROM invoice_items WHERE item_invoice_id = $invoice_id");
    while($row = mysqli_fetch_array($sql)) {
        $item_id = intval($row['item_id']);
        mysqli_query($mysqli,"DELETE FROM invoice_items WHERE item_id = $item_id");
    }

    //Delete History Associated with the Invoice
    $sql = mysqli_query($mysqli,"SELECT * FROM history WHERE history_invoice_id = $invoice_id");
    while($row = mysqli_fetch_array($sql)) {
        $history_id = intval($row['history_id']);
        mysqli_query($mysqli,"DELETE FROM history WHERE history_id = $history_id");
    }

    //Delete Payments Associated with the Invoice
    $sql = mysqli_query($mysqli,"SELECT * FROM payments WHERE payment_invoice_id = $invoice_id");
    while($row = mysqli_fetch_array($sql)) {
        $payment_id = intval($row['payment_id']);
        mysqli_query($mysqli,"DELETE FROM payments WHERE payment_id = $payment_id");
    }

    //unlink tickets from invoice
    mysqli_query($mysqli,"UPDATE tickets SET ticket_invoice_id = 0 WHERE ticket_invoice_id = $invoice_id");

    logAction("Invoice", "Delete", "$session_name deleted invoice $invoice_prefix$invoice_number", $client_id);

    flash_alert("Invoice <strong>$invoice_prefix$invoice_number</strong> deleted", 'error');

    redirect();

}

if (isset($_POST['add_invoice_item'])) {
    
    enforceUserPermission('module_sales', 2);

    $invoice_id = intval($_POST['invoice_id']);
    $name = sanitizeInput($_POST['name']);
    $description = sanitizeInput($_POST['description']);
    $qty = floatval($_POST['qty']);
    $price = floatval($_POST['price']);
    $tax_id = intval($_POST['tax_id']);
    $item_order = intval($_POST['item_order']);
    $product_id = intval($_POST['product_id']);

    $subtotal = $price * $qty;
    
    // Update Product Inventory
    if ($product_id) {
         // Only enforce stock for tangible products
        $product_type = sanitizeInput(getFieldById('products', $product_id, 'product_type'));
        if ($product_type === 'product') {

            // Current available stock
            $sql = mysqli_query(
                $mysqli,
                "SELECT COALESCE(SUM(stock_qty), 0) AS available_stock
                 FROM product_stock
                 WHERE stock_product_id = $product_id"
            );
            $row = mysqli_fetch_array($sql);
            $available_stock = floatval($row['available_stock']);

            // Enough in stock?
            if ($available_stock >= $qty) {
                mysqli_query($mysqli,"INSERT INTO product_stock SET stock_qty = -$qty, stock_note = 'QTY $qty - Invoice $invoice_id', stock_product_id = $product_id");
            } else {
                // Not enough in stock: stop and notify
                flash_alert("Not Enough <strong>$name</strong> in stock", 'error');
                redirect();
            }
        }
    }

    // Tax
    if ($tax_id > 0) {
        $sql = mysqli_query($mysqli,"SELECT * FROM taxes WHERE tax_id = $tax_id");
        $row = mysqli_fetch_array($sql);
        $tax_percent = floatval($row['tax_percent']);
        $tax_amount = $subtotal * $tax_percent / 100;
    } else {
        $tax_amount = 0;
    }

    $total = $subtotal + $tax_amount;

    mysqli_query($mysqli,"INSERT INTO invoice_items SET item_name = '$name', item_description = '$description', item_quantity = $qty, item_price = $price, item_subtotal = $subtotal, item_tax = $tax_amount, item_total = $total, item_order = $item_order, item_tax_id = $tax_id, item_product_id = $product_id, item_invoice_id = $invoice_id");

    // Get Discount and Invoice Details
    $sql = mysqli_query($mysqli,"SELECT * FROM invoices WHERE invoice_id = $invoice_id");
    $row = mysqli_fetch_array($sql);
    $invoice_prefix = sanitizeInput($row['invoice_prefix']);
    $invoice_number = intval($row['invoice_number']);
    $client_id = intval($row['invoice_client_id']);
    $invoice_discount = floatval($row['invoice_discount_amount']);

    //add up all line items
    $sql = mysqli_query($mysqli,"SELECT * FROM invoice_items WHERE item_invoice_id = $invoice_id");
    $invoice_total = 0;
    while($row = mysqli_fetch_array($sql)) {
        $item_total = floatval($row['item_total']);
        $invoice_total = $invoice_total + $item_total;
    }
    $new_invoice_amount = $invoice_total - $invoice_discount;

    mysqli_query($mysqli,"UPDATE invoices SET invoice_amount = $new_invoice_amount WHERE invoice_id = $invoice_id");

    logAction("Invoice", "Edit", "$session_name added item $name to invoice $invoice_prefix$invoice_number", $client_id, $invoice_id);

    flash_alert("Item <strong>$name</strong> added to invoice");

    redirect();

}

if (isset($_POST['invoice_note'])) {
    
    enforceUserPermission('module_sales', 2);

    $invoice_id = intval($_POST['invoice_id']);
    $note = sanitizeInput($_POST['note']);

    // Get Invoice Details for logging
    $sql = mysqli_query($mysqli,"SELECT * FROM invoices WHERE invoice_id = $invoice_id");
    $row = mysqli_fetch_array($sql);
    $invoice_prefix = sanitizeInput($row['invoice_prefix']);
    $invoice_number = intval($row['invoice_number']);
    $client_id = intval($row['invoice_client_id']);

    mysqli_query($mysqli,"UPDATE invoices SET invoice_note = '$note' WHERE invoice_id = $invoice_id");

    logAction("Invoice", "Edit", "$session_name added note to invoice $invoice_prefix$invoice_number", $client_id, $invoice_id);

    flash_alert("Notes added");

    redirect();

}

if (isset($_POST['edit_item'])) {
    
    enforceUserPermission('module_sales', 2);

    $item_id = intval($_POST['item_id']);
    $name = sanitizeInput($_POST['name']);
    $description = sanitizeInput($_POST['description']);
    $qty = floatval($_POST['qty']);
    $price = floatval($_POST['price']);
    $tax_id = intval($_POST['tax_id']);
    $product_id = intval($_POST['product_id']);

    $subtotal = $price * $qty;

    if ($tax_id > 0) {
        $sql = mysqli_query($mysqli,"SELECT * FROM taxes WHERE tax_id = $tax_id");
        $row = mysqli_fetch_array($sql);
        $tax_percent = floatval($row['tax_percent']);
        $tax_amount = $subtotal * $tax_percent / 100;
    } else {
        $tax_amount = 0;
    }

    $total = $subtotal + $tax_amount;

    mysqli_query($mysqli,"UPDATE invoice_items SET item_name = '$name', item_description = '$description', item_quantity = $qty, item_price = $price, item_subtotal = $subtotal, item_tax = $tax_amount, item_total = $total, item_tax_id = $tax_id WHERE item_id = $item_id");

    // Determine what type of line item
    $sql = mysqli_query($mysqli,"SELECT item_invoice_id, item_quote_id, item_recurring_invoice_id FROM invoice_items WHERE item_id = $item_id");
    $row = mysqli_fetch_array($sql);
    $invoice_id = intval($row['item_invoice_id']);
    $quote_id = intval($row['item_quote_id']);
    $recurring_invoice_id = intval($row['item_recurring_invoice_id']);

    if ($invoice_id > 0) {
        //Get Discount Amount
        $sql = mysqli_query($mysqli,"SELECT * FROM invoices WHERE invoice_id = $invoice_id");
        $row = mysqli_fetch_array($sql);
        $invoice_prefix = sanitizeInput($row['invoice_prefix']);
        $invoice_number = intval($row['invoice_number']);
        $client_id = intval($row['invoice_client_id']);
        $invoice_discount = floatval($row['invoice_discount_amount']);

        //Update Invoice Balances by tallying up invoice items
        $sql_invoice_total = mysqli_query($mysqli,"SELECT SUM(item_total) AS invoice_total FROM invoice_items WHERE item_invoice_id = $invoice_id");
        $row = mysqli_fetch_array($sql_invoice_total);
        $new_invoice_amount = floatval($row['invoice_total']) - $invoice_discount;

        


        mysqli_query($mysqli,"UPDATE invoices SET invoice_amount = $new_invoice_amount WHERE invoice_id = $invoice_id");

        logAction("Invoice", "Edit", "$session_name edited item $name on invoice $invoice_prefix$invoice_number", $client_id, $invoice_id);

    } elseif ($quote_id > 0) {
        //Get Discount Amount
        $sql = mysqli_query($mysqli,"SELECT * FROM quotes WHERE quote_id = $quote_id");
        $row = mysqli_fetch_array($sql);
        $quote_prefix = sanitizeInput($row['quote_prefix']);
        $quote_number = intval($row['quote_number']);
        $client_id = intval($row['quote_client_id']);
        $quote_discount = floatval($row['quote_discount_amount']);

        //Update Quote Balances by tallying up items
        $sql_quote_total = mysqli_query($mysqli,"SELECT SUM(item_total) AS quote_total FROM invoice_items WHERE item_quote_id = $quote_id");
        $row = mysqli_fetch_array($sql_quote_total);
        $new_quote_amount = floatval($row['quote_total']) - $quote_discount;

        mysqli_query($mysqli,"UPDATE quotes SET quote_amount = $new_quote_amount WHERE quote_id = $quote_id");

        logAction("Quote", "Edit", "$session_name edited item $name on quote $quote_prefix$quote_number", $client_id, $quote_id);

    } else {
        //Get Discount Amount
        $sql = mysqli_query($mysqli,"SELECT * FROM recurring_invoices WHERE recurring_invoice_id = $recurring_invoice_id");
        $row = mysqli_fetch_array($sql);
        $recurring_invoice_prefix = sanitizeInput($row['recurring_invoice_prefix']);
        $recurring_invoice_number = intval($row['recurring_invoice_number']);
        $client_id = intval($row['recurring_invoice_client_id']);
        $recurring_invoice_discount = floatval($row['recurring_invoice_discount_amount']);

        //Update Invoice Balances by tallying up invoice items
        $sql_recurring_invoice_total = mysqli_query($mysqli,"SELECT SUM(item_total) AS recurring_invoice_total FROM invoice_items WHERE item_recurring_invoice_id = $recurring_invoice_id");
        $row = mysqli_fetch_array($sql_recurring_invoice_total);
        $new_recurring_invoice_amount = floatval($row['recurring_invoice_total']) - $recurring_invoice_discount;

        mysqli_query($mysqli,"UPDATE recurring_invoices SET recurring_invoice_amount = $new_recurring_invoice_amount WHERE recurring_invoice_id = $recurring_invoice_id");

        // Logging
        logAction("Recurring Invoice", "Edit", "$session_name edited item $name on recurring invoice $recurring_invoice_prefix$recurring_invoice_number", $client_id, $recurring_invoice_id);

    }

    flash_alert("Item <strong>$name</strong> updated");

    redirect();

}

if (isset($_GET['delete_invoice_item'])) {
    
    enforceUserPermission('module_sales', 2);

    $item_id = intval($_GET['delete_invoice_item']);

    $sql = mysqli_query($mysqli,"SELECT * FROM invoice_items WHERE item_id = $item_id");
    $row = mysqli_fetch_array($sql);
    $invoice_id = intval($row['item_invoice_id']);
    $item_name = sanitizeInput($row['item_name']);
    $item_quantity = floatval($row['item_quantity']);
    $item_product_id = intval($row['item_product_id']);
    $item_subtotal = floatval($row['item_subtotal']);
    $item_tax = floatval($row['item_tax']);
    $item_total = floatval($row['item_total']);

    $sql = mysqli_query($mysqli,"SELECT * FROM invoices WHERE invoice_id = $invoice_id");
    $row = mysqli_fetch_array($sql);
    $invoice_prefix = sanitizeInput($row['invoice_prefix']);
    $invoice_number = intval($row['invoice_number']);
    $client_id = intval($row['invoice_client_id']);

    $new_invoice_amount = floatval($row['invoice_amount']) - $item_total;

    mysqli_query($mysqli,"UPDATE invoices SET invoice_amount = $new_invoice_amount WHERE invoice_id = $invoice_id");

    mysqli_query($mysqli,"DELETE FROM invoice_items WHERE item_id = $item_id");

    // Return Product Inventory
    if ($item_product_id) {
        mysqli_query($mysqli,"INSERT INTO product_stock SET stock_qty = $item_quantity, stock_note = 'Returned QTY $item_quantity back to stock from Invoice $invoice_id', stock_product_id = $item_product_id");
    }

    logAction("Invoice", "Delete", "$session_name removed item $item_name from invoice $invoice_prefix$invoice_number", $client_id, $invoice_id);

    flash_alert("Item <strong>$item_name</strong> removed from invoice", 'error');

    redirect();

}

if (isset($_GET['email_invoice'])) {
    
    $invoice_id = intval($_GET['email_invoice']);

    $sql = mysqli_query($mysqli,"SELECT * FROM invoices
        LEFT JOIN clients ON invoice_client_id = client_id
        LEFT JOIN contacts ON clients.client_id = contacts.contact_client_id AND contact_primary = 1
        WHERE invoice_id = $invoice_id"
    );
    $row = mysqli_fetch_array($sql);

    $invoice_id = intval($row['invoice_id']);
    $invoice_prefix = sanitizeInput($row['invoice_prefix']);
    $invoice_number = intval($row['invoice_number']);
    $invoice_scope = sanitizeInput($row['invoice_scope']);
    $invoice_status = sanitizeInput($row['invoice_status']);
    $invoice_date = sanitizeInput($row['invoice_date']);
    $invoice_due = sanitizeInput($row['invoice_due']);
    $invoice_amount = floatval($row['invoice_amount']);
    $invoice_url_key = sanitizeInput($row['invoice_url_key']);
    $invoice_currency_code = sanitizeInput($row['invoice_currency_code']);
    $client_id = intval($row['client_id']);
    $client_name = sanitizeInput($row['client_name']);
    $contact_name = sanitizeInput($row['contact_name']);
    $contact_email = sanitizeInput($row['contact_email']);

    $sql = mysqli_query($mysqli,"SELECT * FROM companies WHERE company_id = 1");
    $row = mysqli_fetch_array($sql);

    $company_name = sanitizeInput($row['company_name']);
    $company_country = sanitizeInput($row['company_country']);
    $company_address = sanitizeInput($row['company_address']);
    $company_city = sanitizeInput($row['company_city']);
    $company_state = sanitizeInput($row['company_state']);
    $company_zip = sanitizeInput($row['company_zip']);
    $company_phone = sanitizeInput(formatPhoneNumber($row['company_phone'], $row['company_phone_country_code']));
    $company_email = sanitizeInput($row['company_email']);
    $company_website = sanitizeInput($row['company_website']);
    $company_logo = sanitizeInput($row['company_logo']);

    // Sanitize Config vars from get_settings.php
    $config_invoice_from_name = sanitizeInput($config_invoice_from_name);
    $config_invoice_from_email = sanitizeInput($config_invoice_from_email);

    $sql_payments = mysqli_query($mysqli,"SELECT * FROM payments, accounts WHERE payment_account_id = account_id AND payment_invoice_id = $invoice_id ORDER BY payment_id DESC");

    // Add up all the payments for the invoice and get the total amount paid to the invoice
    $sql_amount_paid = mysqli_query($mysqli,"SELECT SUM(payment_amount) AS amount_paid FROM payments WHERE payment_invoice_id = $invoice_id");
    $row = mysqli_fetch_array($sql_amount_paid);
    $amount_paid = floatval($row['amount_paid']);

    $balance = $invoice_amount - $amount_paid;

    if ($invoice_status == 'Paid') {
        $subject = "Invoice $invoice_prefix$invoice_number Receipt";
        $body = "Hello $contact_name,<br><br>Please click on the link below to see your invoice regarding \"$invoice_scope\" marked <b>paid</b>.<br><br><a href=\'https://$config_base_url/guest/guest_view_invoice.php?invoice_id=$invoice_id&url_key=$invoice_url_key\'>Invoice Link</a><br><br><br>--<br>$company_name - Billing<br>$config_invoice_from_email<br>$company_phone";
    } else {
        $subject = "Invoice $invoice_prefix$invoice_number";
        $body = "Hello $contact_name,<br><br>Please view the details of your invoice regarding \"$invoice_scope\" below.<br><br>Invoice: $invoice_prefix$invoice_number<br>Issue Date: $invoice_date<br>Total: " . numfmt_format_currency($currency_format, $invoice_amount, $invoice_currency_code) . "<br>Balance Due: " . numfmt_format_currency($currency_format, $balance, $invoice_currency_code) . "<br>Due Date: $invoice_due<br><br><br>To view your invoice, please click <a href=\'https://$config_base_url/guest/guest_view_invoice.php?invoice_id=$invoice_id&url_key=$invoice_url_key\'>here</a>.<br><br><br>--<br>$company_name - Billing<br>$config_invoice_from_email<br>$company_phone";
    }

    // Queue Mail
    $data = [
        [
            'from' => $config_invoice_from_email,
            'from_name' => $config_invoice_from_name,
            'recipient' => $contact_email,
            'recipient_name' => $contact_name,
            'subject' => $subject,
            'body' => $body
        ]
    ];

    addToMailQueue($data);

    // Get Email ID for reference
    $email_id = mysqli_insert_id($mysqli);

    flash_alert("Invoice sent!");
    
    mysqli_query($mysqli,"INSERT INTO history SET history_status = 'Sent', history_description = 'Invoice sent to the mail queue ID: $email_id', history_invoice_id = $invoice_id");

    // Don't change the status to sent if the status is anything but draft
    if ($invoice_status == 'Draft') {
        mysqli_query($mysqli,"UPDATE invoices SET invoice_status = 'Sent' WHERE invoice_id = $invoice_id");
    }

    logAction("Invoice", "Email", "$session_name Emailed $contact_email Invoice $invoice_prefix$invoice_number Email queued to Email ID: $email_id", $client_id, $invoice_id);

    // Send copies of the invoice to any additional billing contacts
    $sql_billing_contacts = mysqli_query(
        $mysqli,
        "SELECT contact_name, contact_email FROM contacts
        WHERE contact_billing = 1
        AND contact_email != '$contact_email'
        AND contact_email != ''
        AND contact_client_id = $client_id"
    );

    $data = [];

    while ($billing_contact = mysqli_fetch_array($sql_billing_contacts)) {
        $billing_contact_name = sanitizeInput($billing_contact['contact_name']);
        $billing_contact_email = sanitizeInput($billing_contact['contact_email']);

        $data = [
            [
                'from' => $config_invoice_from_email,
                'from_name' => $config_invoice_from_name,
                'recipient' => $billing_contact_email,
                'recipient_name' => $billing_contact_name,
                'subject' => $subject,
                'body' => $body
            ]
        ];

        logAction("Invoice", "Email", "$session_name Emailed $billing_contact_email Invoice $invoice_prefix$invoice_number Email queued Email ID: $email_id", $client_id, $invoice_id);

    }

    addToMailQueue($data);

    redirect();

}

if (isset($_POST['export_invoices_csv'])) {

    enforceUserPermission('module_sales');
    
    if (isset($_POST['client_id'])) {
        $client_id = intval($_POST['client_id']);
        $client_query = "AND invoice_client_id = $client_id";
        $client_name = getFieldById('clients', $client_id, 'client_name');
        $file_name_prepend = "$client_name-";
    } else {
        $client_query = '';
        $client_name = '';
        $file_name_prepend = "$session_company_name-";
    }

    $date_from = sanitizeInput($_POST['date_from']);
    $date_to = sanitizeInput($_POST['date_to']);
    if (!empty($date_from) && !empty($date_to)) {
        $date_query = "DATE(invoice_date) BETWEEN '$date_from' AND '$date_to'";
        $file_name_date = "$date_from-to-$date_to";
    }else{
        $date_query = "";
        $file_name_date = date('Y-m-d_H-i-s');
    }

    $sql = mysqli_query($mysqli,"SELECT * FROM invoices LEFT JOIN clients ON invoice_client_id = client_id WHERE $date_query $client_query ORDER BY invoice_number ASC");

    $num_rows = mysqli_num_rows($sql);

    if ($num_rows > 0) {
        $delimiter = ",";
        $enclosure = '"';
        $escape    = '\\';   // backslash
        $filename = sanitize_filename($file_name_prepend . "Invoices-$file_name_date.csv");

        //create a file pointer
        $f = fopen('php://memory', 'w');

        //set column headers
        $fields = array('Invoice Number', 'Scope', 'Amount', 'Issued Date', 'Due Date', 'Status');
        fputcsv($f, $fields, $delimiter, $enclosure, $escape);

        //output each row of the data, format line as csv and write to file pointer
        while($row = $sql->fetch_assoc()) {
            $lineData = array($row['invoice_prefix'] . $row['invoice_number'], $row['invoice_scope'], $row['invoice_amount'], $row['invoice_date'], $row['invoice_due'], $row['invoice_status'], $row['client_name']);
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

    logAction("Invoice", "Export", "$session_name exported $num_rows invoices to CSV file");

    exit;

}

if (isset($_POST['link_invoice_to_ticket'])) {
    
    $invoice_id = intval($_POST['invoice_id']);
    $ticket_id = intval($_POST['ticket_id']);

    mysqli_query($mysqli,"UPDATE invoices SET invoice_ticket_id = $ticket_id WHERE invoice_id = $invoice_id");

    flash_alert("Invoice linked to ticket");

    redirect();

}

if (isset($_POST['add_ticket_to_invoice'])) {
    
    $invoice_id = intval($_POST['invoice_id']);
    $ticket_id = intval($_POST['ticket_id']);

    mysqli_query($mysqli,"UPDATE tickets SET ticket_invoice_id = $invoice_id WHERE ticket_id = $ticket_id");

    flash_alert("Ticket linked to invoice");

    redirect("post.php?add_ticket_to_invoice=$invoice_id");

}

if (isset($_GET['export_invoice_pdf'])) {

    $invoice_id = intval($_GET['export_invoice_pdf']);

    $sql = mysqli_query(
        $mysqli,
        "SELECT * FROM invoices
        LEFT JOIN clients ON invoice_client_id = client_id
        LEFT JOIN contacts ON clients.client_id = contacts.contact_client_id AND contact_primary = 1
        LEFT JOIN locations ON clients.client_id = locations.location_client_id AND location_primary = 1
        WHERE invoice_id = $invoice_id
        $access_permission_query
        LIMIT 1"
    );

    $row = mysqli_fetch_array($sql);
    $invoice_id = intval($row['invoice_id']);
    $invoice_prefix = nullable_htmlentities($row['invoice_prefix']);
    $invoice_number = intval($row['invoice_number']);
    $invoice_scope = nullable_htmlentities($row['invoice_scope']);
    $invoice_status = nullable_htmlentities($row['invoice_status']);
    $invoice_date = nullable_htmlentities($row['invoice_date']);
    $invoice_due = nullable_htmlentities($row['invoice_due']);
    $invoice_amount = floatval($row['invoice_amount']);
    $invoice_discount = floatval($row['invoice_discount_amount']);
    $invoice_currency_code = nullable_htmlentities($row['invoice_currency_code']);
    $invoice_note = nullable_htmlentities($row['invoice_note']);
    $invoice_url_key = nullable_htmlentities($row['invoice_url_key']);
    $invoice_created_at = nullable_htmlentities($row['invoice_created_at']);
    $category_id = intval($row['invoice_category_id']);
    $client_id = intval($row['client_id']);
    $client_name = nullable_htmlentities($row['client_name']);
    $location_address = nullable_htmlentities($row['location_address']);
    $location_city = nullable_htmlentities($row['location_city']);
    $location_state = nullable_htmlentities($row['location_state']);
    $location_zip = nullable_htmlentities($row['location_zip']);
    $location_country = nullable_htmlentities($row['location_country']);
    $contact_email = nullable_htmlentities($row['contact_email']);
    $contact_phone_country_code = nullable_htmlentities($row['contact_phone_country_code']);
    $contact_phone = nullable_htmlentities(formatPhoneNumber($row['contact_phone'], $contact_phone_country_code));
    $contact_extension = nullable_htmlentities($row['contact_extension']);
    $contact_mobile_country_code = nullable_htmlentities($row['contact_mobile_country_code']);
    $contact_mobile = nullable_htmlentities(formatPhoneNumber($row['contact_mobile'], $contact_mobile_country_code));
    $client_website = nullable_htmlentities($row['client_website']);
    $client_currency_code = nullable_htmlentities($row['client_currency_code']);
    $client_net_terms = intval($row['client_net_terms']);
    if ($client_net_terms == 0) {
        $client_net_terms = $config_default_net_terms;
    }

    $sql = mysqli_query($mysqli, "SELECT * FROM companies WHERE company_id = 1");
    $row = mysqli_fetch_array($sql);
    $company_id = intval($row['company_id']);
    $company_name = nullable_htmlentities($row['company_name']);
    $company_country = nullable_htmlentities($row['company_country']);
    $company_address = nullable_htmlentities($row['company_address']);
    $company_city = nullable_htmlentities($row['company_city']);
    $company_state = nullable_htmlentities($row['company_state']);
    $company_zip = nullable_htmlentities($row['company_zip']);
    $company_phone_country_code = nullable_htmlentities($row['company_phone_country_code']);
    $company_phone = nullable_htmlentities(formatPhoneNumber($row['company_phone'], $company_phone_country_code));
    $company_email = nullable_htmlentities($row['company_email']);
    $company_website = nullable_htmlentities($row['company_website']);
    $company_tax_id = nullable_htmlentities($row['company_tax_id']);
    if ($config_invoice_show_tax_id && !empty($company_tax_id)) {
        $company_tax_id_display = "Tax ID: $company_tax_id";
    } else {
        $company_tax_id_display = "";
    }
    $company_logo = nullable_htmlentities($row['company_logo']);

    $sql_payments = mysqli_query($mysqli, "SELECT * FROM payments, accounts WHERE payment_account_id = account_id AND payment_invoice_id = $invoice_id ORDER BY payments.payment_id DESC");

    //Add up all the payments for the invoice and get the total amount paid to the invoice
    $sql_amount_paid = mysqli_query($mysqli, "SELECT SUM(payment_amount) AS amount_paid FROM payments WHERE payment_invoice_id = $invoice_id");
    $row = mysqli_fetch_array($sql_amount_paid);
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

    require_once("../plugins/TCPDF/tcpdf.php");

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
        <td style="font-size:10pt; line-height:1.4;">' . nl2br("$company_address\n$company_city $company_state $company_zip\n$company_country\n$company_phone\n$company_website\n$company_tax_id_display") . '</td>
        <td style="font-size:10pt; line-height:1.4;" align="right">' . nl2br("$location_address\n$location_city $location_state $location_zip\n$location_country\n$contact_email\n$contact_phone") . '</td>
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
    
    $sql_items = mysqli_query($mysqli, "SELECT * FROM invoice_items WHERE item_invoice_id = $invoice_id ORDER BY item_order ASC");
    while ($item = mysqli_fetch_array($sql_items)) {
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

if (isset($_POST['bulk_edit_invoice_category'])) {

    $category_id = intval($_POST['bulk_category_id']);

    // Get Category name for logging and Notification
    $category_name = sanitizeInput(getFieldById('categories', $category_id, 'category_name'));

    // Assign Income category to Selected Invoices
    if (isset($_POST['invoice_ids'])) {

        // Get Selected Count
        $count = count($_POST['invoice_ids']);

        foreach($_POST['invoice_ids'] as $invoice_id) {
            $invoice_id = intval($invoice_id);

            // Get Invoice Details for Logging
            $sql = mysqli_query($mysqli,"SELECT * FROM invoices WHERE invoice_id = $invoice_id");
            $row = mysqli_fetch_array($sql);
            $invoice_prefix = sanitizeInput($row['invoice_prefix']);
            $invoice_number = intval($row['invoice_number']);
            $invoice_scope = sanitizeInput($row['invoice_scope']);
            $client_id = intval($row['invoice_client_id']);

            mysqli_query($mysqli,"UPDATE invoices SET invoice_category_id = $category_id WHERE invoice_id = $invoice_id");

            logAction("Invoice", "Edit", "$session_name assigned Invoice $invoice_prefix$invoice_number to category $category_name", $client_id, $invoice_id);

        } // End Assign Loop

        logAction("Invoice", "Bulk Edit", "$session_name assigned $count invoices to category $category_name");

        flash_alert("Assigned income category <strong>$category_name</strong> to <strong>$count</strong> invoice(s)");
    }

    redirect();

}
