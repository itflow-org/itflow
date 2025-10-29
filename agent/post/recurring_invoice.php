<?php

/*
 * ITFlow - GET/POST request handler for recurring invoices
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['add_invoice_recurring'])) {

    $invoice_id = intval($_POST['invoice_id']);
    $recurring_invoice_frequency = sanitizeInput($_POST['frequency']);

    $sql = mysqli_query($mysqli,"SELECT * FROM invoices WHERE invoice_id = $invoice_id");
    $row = mysqli_fetch_array($sql);
    $invoice_prefix = sanitizeInput($row['invoice_prefix']);
    $invoice_number = intval($row['invoice_number']);
    $invoice_date = sanitizeInput($row['invoice_date']);
    $invoice_amount = floatval($row['invoice_amount']);
    $invoice_currency_code = sanitizeInput($row['invoice_currency_code']);
    $invoice_scope = sanitizeInput($row['invoice_scope']);
    $invoice_note = sanitizeInput($row['invoice_note']);
    $client_id = intval($row['invoice_client_id']);
    $category_id = intval($row['invoice_category_id']);

    //Get the last Recurring Invoice Number and add 1 for the new Recurring Invoice number
    $recurring_invoice_number = $config_recurring_invoice_next_number;
    $new_config_recurring_invoice_next_number = $config_recurring_invoice_next_number + 1;
    mysqli_query($mysqli,"UPDATE settings SET config_recurring_invoice_next_number = $new_config_recurring_invoice_next_number WHERE company_id = 1");

    mysqli_query($mysqli,"INSERT INTO recurring_invoices SET recurring_invoice_prefix = '$config_recurring_invoice_prefix', recurring_invoice_number = $recurring_invoice_number, recurring_invoice_scope = '$invoice_scope', recurring_invoice_frequency = '$recurring_invoice_frequency', recurring_invoice_next_date = DATE_ADD('$invoice_date', INTERVAL 1 $recurring_invoice_frequency), recurring_invoice_status = 1, recurring_invoice_amount = $invoice_amount, recurring_invoice_currency_code = '$invoice_currency_code', recurring_invoice_note = '$invoice_note', recurring_invoice_category_id = $category_id, recurring_invoice_client_id = $client_id");

    $recurring_invoice_id = mysqli_insert_id($mysqli);

    mysqli_query($mysqli,"INSERT INTO history SET history_status = 'Draft', history_description = 'Recurring Invoice Created from INVOICE!', history_recurring_invoice_id = $recurring_invoice_id");

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

        mysqli_query($mysqli,"INSERT INTO invoice_items SET item_name = '$item_name', item_description = '$item_description', item_quantity = $item_quantity, item_price = $item_price, item_subtotal = $item_subtotal, item_tax = $item_tax, item_total = $item_total, item_order = $item_order, item_tax_id = $tax_id, item_recurring_invoice_id = $recurring_invoice_id");
    }

    logAction("Recurring Invoice", "Create", "$session_name created recurring Invoice from Invoice $invoice_prefix$invoice_number", $client_id, $recurring_invoice_id);

    flash_alert("Created recurring Invoice from Invoice <strong>$invoice_prefix$invoice_number</strong>");

    redirect("recurring_invoice.php?recurring_invoice_id=$recurring_invoice_id");

}

if (isset($_POST['add_recurring_invoice'])) {

    $client_id = intval($_POST['client']);
    $frequency = sanitizeInput($_POST['frequency']);
    $start_date = sanitizeInput($_POST['start_date']);
    $category = intval($_POST['category']);
    $scope = sanitizeInput($_POST['scope']);

    //Get the last Recurring Number and add 1 for the new Recurring number
    $recurring_invoice_number = $config_recurring_invoice_next_number;
    $new_config_recurring_invoice_next_number = $config_recurring_invoice_next_number + 1;
    mysqli_query($mysqli,"UPDATE settings SET config_recurring_invoice_next_number = $new_config_recurring_invoice_next_number WHERE company_id = 1");

    mysqli_query($mysqli,"INSERT INTO recurring_invoices SET recurring_invoice_prefix = '$config_recurring_invoice_prefix', recurring_invoice_number = $recurring_invoice_number, recurring_invoice_scope = '$scope', recurring_invoice_frequency = '$frequency', recurring_invoice_next_date = '$start_date', recurring_invoice_category_id = $category, recurring_invoice_status = 1, recurring_invoice_currency_code = '$session_company_currency', recurring_invoice_client_id = $client_id");

    $recurring_invoice_id = mysqli_insert_id($mysqli);

    mysqli_query($mysqli,"INSERT INTO history SET history_status = 'Active', history_description = 'Recurring Invoice created', history_recurring_invoice_id = $recurring_invoice_id");

    logAction("Recurring Invoice", "Create", "$session_name created recurring invoice $config_recurring_invoice_prefix$recurring_invoice_number - $scope", $client_id, $recurring_invoice_id);

    flash_alert("Recurring Invoice <strong>$config_recurring_invoice_prefix$recurring_invoice_number</strong> created");

    redirect("recurring_invoice.php?recurring_invoice_id=$recurring_invoice_id");

}

if (isset($_POST['edit_recurring_invoice'])) {

    $recurring_invoice_id = intval($_POST['recurring_invoice_id']);
    $frequency = sanitizeInput($_POST['frequency']);
    $next_date = sanitizeInput($_POST['next_date']);
    $category = intval($_POST['category']);
    $scope = sanitizeInput($_POST['scope']);
    $status = intval($_POST['status']);
    $recurring_invoice_discount = floatval($_POST['recurring_invoice_discount']);

    // Get Recurring Invoice Details and Client ID for Logging
    $sql = mysqli_query($mysqli,"SELECT recurring_invoice_prefix, recurring_invoice_number, recurring_invoice_client_id FROM recurring_invoices WHERE recurring_invoice_id = $recurring_invoice_id");
    $row = mysqli_fetch_array($sql);
    $recurring_invoice_prefix = sanitizeInput($row['recurring_invoice_prefix']);
    $recurring_invoice_number = intval($row['recurring_invoice_number']);
    $client_id = intval($row['recurring_invoice_client_id']);

    //Calculate new total
    $sql = mysqli_query($mysqli,"SELECT * FROM invoice_items WHERE item_recurring_invoice_id = $recurring_invoice_id");
    $recurring_invoice_amount = 0;
    while($row = mysqli_fetch_array($sql)) {
        $item_total = floatval($row['item_total']);
        $recurring_invoice_amount = $recurring_invoice_amount + $item_total;
    }
    $recurring_invoice_amount = $recurring_invoice_amount - $recurring_invoice_discount;

    mysqli_query($mysqli,"UPDATE recurring_invoices SET recurring_invoice_scope = '$scope', recurring_invoice_frequency = '$frequency', recurring_invoice_next_date = '$next_date', recurring_invoice_category_id = $category, recurring_invoice_discount_amount = $recurring_invoice_discount, recurring_invoice_amount = $recurring_invoice_amount, recurring_invoice_status = $status WHERE recurring_invoice_id = $recurring_invoice_id");

    mysqli_query($mysqli,"INSERT INTO history SET history_status = '$status', history_description = 'Recurring Invoice edited', history_recurring_invoice_id = $recurring_invoice_id");

    logAction("Recurring Invoice", "Edit", "$session_name edited recurring invoice $recurring_invoice_prefix$recurring_invoice_number - $scope", $client_id, $recurring_invoice_id);

    flash_alert("Recurring Invoice <strong>$recurring_invoice_prefix$recurring_invoice_number</strong> edited");

    redirect();

}

if (isset($_GET['delete_recurring_invoice'])) {
    
    $recurring_invoice_id = intval($_GET['delete_recurring_invoice']);

    // Get Recurring Invoice Details and Client ID for Logging
    $sql = mysqli_query($mysqli,"SELECT recurring_invoice_prefix, recurring_invoice_number, recurring_invoice_scope, recurring_invoice_client_id FROM recurring_invoices WHERE recurring_invoice_id = $recurring_invoice_id");
    $row = mysqli_fetch_array($sql);
    $recurring_invoice_prefix = sanitizeInput($row['recurring_invoice_prefix']);
    $recurring_invoice_number = intval($row['recurring_invoice_number']);
    $recurring_invoice_scope = sanitizeInput($row['recurring_invoice_scope']);
    $client_id = intval($row['recurring_invoice_client_id']);

    mysqli_query($mysqli,"DELETE FROM recurring_invoices WHERE recurring_invoice_id = $recurring_invoice_id");

    //Delete Items Associated with the Recurring
    $sql = mysqli_query($mysqli,"SELECT * FROM invoice_items WHERE item_recurring_invoice_id = $recurring_invoice_id");
    while($row = mysqli_fetch_array($sql)) {
        $item_id = intval($row['item_id']);
        mysqli_query($mysqli,"DELETE FROM invoice_items WHERE item_id = $item_id");
    }

    //Delete History Associated with the Invoice
    $sql = mysqli_query($mysqli,"SELECT * FROM history WHERE history_recurring_invoice_id = $recurring_invoice_id");
    while($row = mysqli_fetch_array($sql)) {
        $history_id = intval($row['history_id']);
        mysqli_query($mysqli,"DELETE FROM history WHERE history_id = $history_id");
    }

    logAction("Recurring Invoice", "Delete", "$session_name deleted recurring invoice $recurring_invoice_prefix$recurring_invoice_number - $recurring_invoice_scope", $client_id);

    flash_alert("Recurring Invoice <strong>$recurring_invoice_prefix$recurring_invoice_number</strong> deleted", 'error');

    redirect();

}

if (isset($_POST['add_recurring_invoice_item'])) {

    $recurring_invoice_id = intval($_POST['recurring_invoice_id']);
    $name = sanitizeInput($_POST['name']);
    $description = sanitizeInput($_POST['description']);
    $qty = floatval($_POST['qty']);
    $price = floatval($_POST['price']);
    $tax_id = intval($_POST['tax_id']);
    $item_order = intval($_POST['item_order']);

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

    mysqli_query($mysqli,"INSERT INTO invoice_items SET item_name = '$name', item_description = '$description', item_quantity = $qty, item_price = $price, item_subtotal = $subtotal, item_tax = $tax_amount, item_total = $total, item_tax_id = $tax_id, item_order = $item_order, item_recurring_invoice_id = $recurring_invoice_id");


    $sql = mysqli_query($mysqli,"SELECT * FROM recurring_invoices WHERE recurring_invoice_id = $recurring_invoice_id");
    $row = mysqli_fetch_array($sql);
    $recurring_invoice_discount = floatval($row['recurring_invoice_discount_amount']);
    $recurring_invoice_prefix = sanitizeInput($row['recurring_invoice_prefix']);
    $recurring_invoice_number = intval($row['recurring_invoice_number']);
    $client_id = intval($row['recurring_invoice_client_id']);

    //add up all the items
    $sql = mysqli_query($mysqli,"SELECT * FROM invoice_items WHERE item_recurring_invoice_id = $recurring_invoice_id");
    $recurring_invoice_amount = 0;
    while($row = mysqli_fetch_array($sql)) {
        $item_total = floatval($row['item_total']);
        $recurring_invoice_amount = $recurring_invoice_amount + $item_total;
    }
    $recurring_invoice_amount = $recurring_invoice_amount - $recurring_invoice_discount;

    mysqli_query($mysqli,"UPDATE recurring_invoices SET recurring_invoice_amount = $recurring_invoice_amount WHERE recurring_invoice_id = $recurring_invoice_id");

    logAction("Recurring Invoice", "Edit", "$session_name added item $name to recurring invoice $recurring_invoice_prefix$recurring_invoice_number", $client_id, $recurring_invoice_id);

    flash_alert("Item <srrong>$name</strong> added to Recurring Invoice");

    redirect();

}

if (isset($_POST['recurring_invoice_note'])) {

    $recurring_invoice_id = intval($_POST['recurring_invoice_id']);
    $note = sanitizeInput($_POST['note']);

    // Get Recurring details for logging
    $sql = mysqli_query($mysqli,"SELECT recurring_invoice_prefix, recurring_invoice_number, recurring_invoice_client_id FROM recurring_invoices WHERE recurring_invoice_id = $recurring_invoice_id");
    $row = mysqli_fetch_array($sql);
    $recurring_invoice_prefix = sanitizeInput($row['recurring_invoice_prefix']);
    $recurring_invoice_number = intval($row['recurring_invoice_number']);
    $client_id = intval($row['recurring_invoice_client_id']);

    mysqli_query($mysqli,"UPDATE recurring_invoices SET recurring_invoice_note = '$note' WHERE recurring_invoice_id = $recurring_invoice_id");

    logAction("Recurring Invoice", "Edit", "$session_name added note to recurring invoice $recurring_invoice_prefix$recurring_invoice_number", $client_id, $recurring_invoice_id);

    flash_alert("Notes added");

    redirect();

}

if (isset($_GET['delete_recurring_invoice_item'])) {
    
    $item_id = intval($_GET['delete_recurring_invoice_item']);

    $sql = mysqli_query($mysqli,"SELECT * FROM invoice_items WHERE item_id = $item_id");
    $row = mysqli_fetch_array($sql);
    $recurring_invoice_id = intval($row['item_recurring_invoice_id']);
    $item_name = sanitizeInput($row['item_name']);
    $item_subtotal = floatval($row['item_subtotal']);
    $item_tax = floatval($row['item_tax']);
    $item_total = floatval($row['item_total']);

    $sql = mysqli_query($mysqli,"SELECT * FROM recurring_invoices WHERE recurring_invoice_id = $recurring_invoice_id");
    $row = mysqli_fetch_array($sql);
    $recurring_invoice_prefix = sanitizeInput($row['recurring_invoice_prefix']);
    $recurring_invoice_number = intval($row['recurring_invoice_number']);
    $client_id = intval($row['recurring_invoice_client_id']);
    
    $new_recurring_invoice_amount = floatval($row['recurring_invoice_amount']) - $item_total;

    mysqli_query($mysqli,"UPDATE recurring_invoices SET recurring_invoice_amount = $new_recurring_invoice_amount WHERE recurring_invoice_id = $recurring_invoice_id");

    mysqli_query($mysqli,"DELETE FROM invoice_items WHERE item_id = $item_id");

    logAction("Recurring Invoice", "Edit", "$session_name removed item $item_name from recurring invoice $recurring_invoice_prefix$recurring_invoice_number", $client_id);

    flash_alert("Item <strong>$item_name</strong> removed", 'error');

    redirect();

}

if (isset($_GET['force_recurring'])) {
    
    $recurring_invoice_id = intval($_GET['force_recurring']);

    $sql_recurring_invoices = mysqli_query($mysqli,"SELECT * FROM recurring_invoices, clients WHERE client_id = recurring_invoice_client_id AND recurring_invoice_id = $recurring_invoice_id");

    $row = mysqli_fetch_array($sql_recurring_invoices);
    $recurring_invoice_id = intval($row['recurring_invoice_id']);
    $recurring_invoice_scope = sanitizeInput($row['recurring_invoice_scope']);
    $recurring_invoice_frequency = sanitizeInput($row['recurring_invoice_frequency']);
    $recurring_invoice_status = sanitizeInput($row['recurring_invoice_status']);
    $recurring_invoice_last_sent = sanitizeInput($row['recurring_invoice_last_sent']);
    $recurring_invoice_next_date = sanitizeInput($row['recurring_invoice_next_date']);
    $recurring_invoice_discount_amount = floatval($row['recurring_invoice_discount_amount']);
    $recurring_invoice_amount = floatval($row['recurring_invoice_amount']);
    $recurring_invoice_currency_code = sanitizeInput($row['recurring_invoice_currency_code']);
    $recurring_invoice_note = sanitizeInput($row['recurring_invoice_note']);
    $category_id = intval($row['recurring_invoice_category_id']);
    $client_id = intval($row['recurring_invoice_client_id']);
    $client_net_terms = intval($row['client_net_terms']);

    //Get the last Invoice Number and add 1 for the new invoice number
    $new_invoice_number = $config_invoice_next_number;
    $new_config_invoice_next_number = $config_invoice_next_number + 1;
    mysqli_query($mysqli,"UPDATE settings SET config_invoice_next_number = $new_config_invoice_next_number WHERE company_id = 1");

    //Generate a unique URL key for clients to access
    $url_key = randomString(156);

    mysqli_query($mysqli,"INSERT INTO invoices SET invoice_prefix = '$config_invoice_prefix', invoice_number = $new_invoice_number, invoice_scope = '$recurring_invoice_scope', invoice_date = CURDATE(), invoice_due = DATE_ADD(CURDATE(), INTERVAL $client_net_terms day), invoice_discount_amount = $recurring_invoice_discount_amount, invoice_amount = $recurring_invoice_amount, invoice_currency_code = '$recurring_invoice_currency_code', invoice_note = '$recurring_invoice_note', invoice_category_id = $category_id, invoice_status = 'Sent', invoice_url_key = '$url_key', invoice_recurring_invoice_id = $recurring_invoice_id, invoice_client_id = $client_id");

    $new_invoice_id = mysqli_insert_id($mysqli);

    //Copy Items from original invoice to new invoice
    $sql_invoice_items = mysqli_query($mysqli,"SELECT * FROM invoice_items WHERE item_recurring_invoice_id = $recurring_invoice_id ORDER BY item_id ASC");

    while($row = mysqli_fetch_array($sql_invoice_items)) {
        $item_id = intval($row['item_id']);
        $item_name = sanitizeInput($row['item_name']);
        $item_description = sanitizeInput($row['item_description']);
        $item_quantity = floatval($row['item_quantity']);
        $item_price = floatval($row['item_price']);
        $item_subtotal = floatval($row['item_subtotal']);
        $item_order = intval($row['item_order']);
        $tax_id = intval($row['item_tax_id']);

        //Recalculate Item Tax since Tax percents can change.
        if ($tax_id > 0) {
            $sql = mysqli_query($mysqli,"SELECT * FROM taxes WHERE tax_id = $tax_id");
            $row = mysqli_fetch_array($sql);
            $tax_percent = floatval($row['tax_percent']);
            $item_tax_amount = $item_subtotal * $tax_percent / 100;
        } else {
            $item_tax_amount = 0;
        }

        $item_total = $item_subtotal + $item_tax_amount;

        //Update Recurring Items with new tax
        mysqli_query($mysqli,"UPDATE invoice_items SET item_tax = $item_tax_amount, item_total = $item_total, item_tax_id = $tax_id, item_order = $item_order WHERE item_id = $item_id");

        mysqli_query($mysqli,"INSERT INTO invoice_items SET item_name = '$item_name', item_description = '$item_description', item_quantity = $item_quantity, item_price = $item_price, item_subtotal = $item_subtotal, item_tax = $item_tax_amount, item_total = $item_total, item_tax_id = $tax_id, item_invoice_id = $new_invoice_id");
    }

    mysqli_query($mysqli,"INSERT INTO history SET history_status = 'Sent', history_description = 'Invoice Generated from Recurring!', history_invoice_id = $new_invoice_id");

    //Update Recurring Balances by tallying up recurring items also update recurring dates
    $sql_recurring_invoice_total = mysqli_query($mysqli,"SELECT SUM(item_total) AS recurring_invoice_total FROM invoice_items WHERE item_recurring_invoice_id = $recurring_invoice_id");
    $row = mysqli_fetch_array($sql_recurring_invoice_total);
    $new_recurring_invoice_amount = floatval($row['recurring_invoice_total']) - $recurring_invoice_discount_amount;

    mysqli_query($mysqli,"UPDATE recurring_invoices SET recurring_invoice_amount = $new_recurring_invoice_amount, recurring_invoice_last_sent = CURDATE(), recurring_invoice_next_date = DATE_ADD(CURDATE(), INTERVAL 1 $recurring_invoice_frequency) WHERE recurring_invoice_id = $recurring_invoice_id");

    //Also update the newly created invoice with the new amounts
    mysqli_query($mysqli,"UPDATE invoices SET invoice_amount = $new_recurring_invoice_amount WHERE invoice_id = $new_invoice_id");

    if ($config_recurring_auto_send_invoice == 1) {
        $sql = mysqli_query($mysqli,"SELECT * FROM invoices
            LEFT JOIN clients ON invoice_client_id = client_id
            LEFT JOIN contacts ON clients.client_id = contacts.contact_client_id AND contact_primary = 1
            WHERE invoice_id = $new_invoice_id"
        );
        $row = mysqli_fetch_array($sql);

        $invoice_prefix = sanitizeInput($row['invoice_prefix']);
        $invoice_number = intval($row['invoice_number']);
        $invoice_scope = sanitizeInput($row['invoice_scope']);
        $invoice_date = sanitizeInput($row['invoice_date']);
        $invoice_due = sanitizeInput($row['invoice_due']);
        $invoice_amount = floatval($row['invoice_amount']);
        $invoice_url_key = sanitizeInput($row['invoice_url_key']);
        $client_id = intval($row['client_id']);
        $client_name = sanitizeInput($row['client_name']);
        $contact_name = sanitizeInput($row['contact_name']);
        $contact_email = sanitizeInput($row['contact_email']);
        $contact_phone = sanitizeInput(formatPhoneNumber($row['contact_phone'], $row['contact_phone_country_code']));
        $contact_extension = intval($row['contact_extension']);
        $contact_mobile = sanitizeInput(formatPhoneNumber($row['contact_mobile'], $row['contact_mobile_country_code']));

        $sql = mysqli_query($mysqli,"SELECT * FROM companies WHERE company_id = 1");
        $row = mysqli_fetch_array($sql);
        $company_name = sanitizeInput($row['company_name']);
        $company_phone = sanitizeInput(formatPhoneNumber($row['company_phone'], $row['company_phone_country_code']));
        $company_email = sanitizeInput($row['company_email']);
        $company_website = sanitizeInput($row['company_website']);

        // Sanitize Config Vars
        $config_invoice_from_email = sanitizeInput($config_invoice_from_email);
        $config_invoice_from_name = sanitizeInput($config_invoice_from_name);

        // Email to client

        $subject = "Invoice $invoice_prefix$invoice_number";
        $body = "Hello $contact_name,<br><br>An invoice regarding \"$invoice_scope\" has been generated. Please view the details below.<br><br>Invoice: $invoice_prefix$invoice_number<br>Issue Date: $invoice_date<br>Total: $$invoice_amount<br>Due Date: $invoice_due<br><br><br>To view your invoice, please click <a href=\'https://$config_base_url/guest/guest_view_invoice.php?invoice_id=$new_invoice_id&url_key=$invoice_url_key\'>here</a>.<br><br><br>--<br>$company_name - Billing<br>$company_phone";


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
        $mail = addToMailQueue($data);

        if ($mail === true) {
            // Add send history
            mysqli_query($mysqli,"INSERT INTO history SET history_status = 'Sent', history_description = 'Force Emailed Invoice!', history_invoice_id = $new_invoice_id");

            // Update Invoice Status to Sent
            mysqli_query($mysqli,"UPDATE invoices SET invoice_status = 'Sent', invoice_client_id = $client_id WHERE invoice_id = $new_invoice_id");

        } else {
            // Error reporting
            appNotify("Mail", "Failed to send email to $contact_email");

            logAction("Mail", "Error", "Failed to send email to $contact_email regarding $subject. $mail");

        }

    } //End Recurring Invoices Loop

    logAction("Invoice", "Create", "$session_name forced recurring invoice into an invoice", $client_id, $new_invoice_id);

    customAction('invoice_create', $new_invoice_id);

    flash_alert("Recurring Invoice Forced");

    redirect();

}

if (isset($_POST['set_recurring_payment'])) {

    $recurring_invoice_id = intval($_POST['recurring_invoice_id']);
    $saved_payment_id = intval($_POST['saved_payment_id']);

    // Get Recurring Invoice Info for logging and alerting
    $sql = mysqli_query($mysqli, "SELECT * FROM recurring_invoices WHERE recurring_invoice_id = $recurring_invoice_id");
    $row = mysqli_fetch_array($sql);
    $client_id = intval($row['recurring_invoice_client_id']);
    $recurring_invoice_prefix = sanitizeInput($row['recurring_invoice_prefix']);
    $recurring_invoice_number = intval($row['recurring_invoice_number']);
    $recurring_invoice_currency_code = sanitizeInput($row['recurring_invoice_currency_code']);
    $recurring_invoice_amount = floatval($row['recurring_invoice_amount']);

    if ($saved_payment_id) {

        // Get Payment provider and method
        $sql = mysqli_query($mysqli, "
            SELECT * FROM payment_providers
            LEFT JOIN client_saved_payment_methods ON saved_payment_provider_id = payment_provider_id
            WHERE saved_payment_id = $saved_payment_id
        ");

        $row = mysqli_fetch_array($sql);

        $provider_id = intval($row['payment_provider_id']);
        $provider_name = sanitizeInput($row['payment_provider_name']);
        $account_id = intval($row['payment_provider_account']);
        $saved_payment_description = sanitizeInput($row['saved_payment_description']);

        mysqli_query($mysqli, "DELETE FROM recurring_payments WHERE recurring_payment_recurring_invoice_id = $recurring_invoice_id");
        mysqli_query($mysqli,"INSERT INTO recurring_payments SET recurring_payment_currency_code = '$recurring_invoice_currency_code', recurring_payment_account_id = $account_id, recurring_payment_method = 'Credit Card', recurring_payment_recurring_invoice_id = $recurring_invoice_id, recurring_payment_saved_payment_id = $saved_payment_id");
        // Get Payment ID for reference
        $recurring_payment_id = mysqli_insert_id($mysqli);

        logAction("Recurring Invoice", "Auto Payment", "$session_name created Auto Pay for Recurring Invoice $recurring_invoice_prefix$recurring_invoice_number in the amount of " . numfmt_format_currency($currency_format, $recurring_invoice_amount, $recurring_invoice_currency_code), $client_id, $recurring_invoice_id);

        flash_alert("Automatic Payment <strong>$saved_payment_description</strong> enabled for Recurring Invoice $recurring_invoice_prefix$recurring_invoice_number");
    } else {
        // Delete
        mysqli_query($mysqli, "DELETE FROM recurring_payments WHERE recurring_payment_recurring_invoice_id = $recurring_invoice_id");

        logAction("Recurring Invoice", "Auto Payment", "$session_name removed Auto Pay for Recurring Invoice $recurring_invoice_prefix$recurring_invoice_number in the amount of " . numfmt_format_currency($currency_format, $recurring_invoice_amount, $recurring_invoice_currency_code), $client_id, $recurring_invoice_id);

        flash_alert("Automatic Payment <strong>Disabled</strong> for Recurring Invoice $recurring_invoice_prefix$recurring_invoice_number", 'error');
    }

    redirect();

}

if (isset($_POST['export_client_recurring_invoice_csv'])) {
    
    $client_id = intval($_POST['client_id']);

    //get records from database
    $sql = mysqli_query($mysqli,"SELECT client_name FROM clients WHERE client_id = $client_id");
    $row = mysqli_fetch_array($sql);

    $client_name = $row['client_name'];

    $sql = mysqli_query($mysqli,"SELECT * FROM recurring_invoices WHERE recurring_invoice_client_id = $client_id ORDER BY recurring_invoice_number ASC");
    
    $num_rows = mysqli_num_rows($sql);

    if ($num_rows > 0) {
        $delimiter = ",";
        $filename = $client_name . "-Recurring Invoices-" . date('Y-m-d') . ".csv";

        //create a file pointer
        $f = fopen('php://memory', 'w');

        //set column headers
        $fields = array('Recurring Number', 'Scope', 'Amount', 'Frequency', 'Date Created');
        fputcsv($f, $fields, $delimiter);

        //output each row of the data, format line as csv and write to file pointer
        while($row = $sql->fetch_assoc()) {
            $lineData = array($row['recurring_invoice_prefix'] . $row['recurring_invoice_number'], $row['recurring_invoice_scope'], $row['recurring_invoice_amount'], ucwords($row['recurring_invoice_frequency'] . "ly"), $row['recurring_invoice_created_at']);
            fputcsv($f, $lineData, $delimiter);
        }

        //move back to beginning of file
        fseek($f, 0);

        //set headers to download file rather than displayed
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '";');

        //output all remaining data on a file pointer
        fpassthru($f);
    }

    logAction("Recurring Invoice", "Export", "$session_name exported $num_rows recurring invoices to CSV file");

    exit;

}

if (isset($_GET['recurring_invoice_email_notify'])) {
    
    $recurring_invoice_email_notify = intval($_GET['recurring_invoice_email_notify']);
    $recurring_invoice_id = intval($_GET['recurring_invoice_id']);

    $sql = mysqli_query($mysqli,"SELECT * FROM recurring_invoices WHERE recurring_invoice_id = $recurring_invoice_id");
    $row = mysqli_fetch_array($sql);
    $recurring_invoice_prefix = sanitizeInput($row['recurring_invoice_prefix']);
    $recurring_invoice_number = intval($row['recurring_invoice_number']);
    $client_id = intval($row['recurring_invoice_client_id']);

    mysqli_query($mysqli,"UPDATE recurring_invoices SET recurring_invoice_email_notify = $recurring_invoice_email_notify WHERE recurring_invoice_id = $recurring_invoice_id");

    // Wording
    if ($recurring_invoice_email_notify) {
        $notify_wording = "On";
    } else { 
        $notify_wording = "Off";
    }

    logAction("Recurring Invoice", "Edit", "$session_name turned $notify_wording Email Notifications for Recurring Invoice $recurring_invoice_prefix$recurring_invoice_number", $client_id, $recurring_invoice_id);

    flash_alert("Email Notifications <strong>$notify_wording</strong>", 'error');

    redirect();

}
