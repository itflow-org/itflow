<?php

/*
 * ITFlow - GET/POST request handler for quotes
 */

if (isset($_POST['add_quote'])) {

    require_once 'post/quote_model.php';


    $client = intval($_POST['client']);

    //Get the last Quote Number and add 1 for the new Quote number
    $quote_number = $config_quote_next_number;
    $new_config_quote_next_number = $config_quote_next_number + 1;
    mysqli_query($mysqli,"UPDATE settings SET config_quote_next_number = $new_config_quote_next_number WHERE company_id = 1");

    //Generate a unique URL key for clients to access
    $quote_url_key = randomString(156);

    mysqli_query($mysqli,"INSERT INTO quotes SET quote_prefix = '$config_quote_prefix', quote_number = $quote_number, quote_scope = '$scope', quote_date = '$date', quote_expire = '$expire', quote_currency_code = '$session_company_currency', quote_category_id = $category, quote_status = 'Draft', quote_url_key = '$quote_url_key', quote_client_id = $client");

    $quote_id = mysqli_insert_id($mysqli);

    mysqli_query($mysqli,"INSERT INTO history SET history_status = 'Draft', history_description = 'Quote created!', history_quote_id = $quote_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Quote', log_action = 'Create', log_description = '$quote_prefix$quote_number', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Quote added";

    header("Location: quote.php?quote_id=$quote_id");

}

if (isset($_POST['add_quote_copy'])) {

    $quote_id = intval($_POST['quote_id']);
    $date = sanitizeInput($_POST['date']);
    $expire = sanitizeInput($_POST['expire']);

    //Get the last Invoice Number and add 1 for the new invoice number
    $quote_number = $config_quote_next_number;
    $new_config_quote_next_number = $config_quote_next_number + 1;
    mysqli_query($mysqli,"UPDATE settings SET config_quote_next_number = $new_config_quote_next_number WHERE company_id = 1");

    $sql = mysqli_query($mysqli,"SELECT * FROM quotes WHERE quote_id = $quote_id");
    $row = mysqli_fetch_array($sql);
    $quote_amount = floatval($row['quote_amount']);
    $quote_currency_code = sanitizeInput($row['quote_currency_code']);
    $quote_scope = sanitizeInput($row['quote_scope']);
    $quote_note = sanitizeInput($row['quote_note']);
    $client_id = intval($row['quote_client_id']);
    $category_id = intval($row['quote_category_id']);

    //Generate a unique URL key for clients to access
    $quote_url_key = randomString(156);

    mysqli_query($mysqli,"INSERT INTO quotes SET quote_prefix = '$config_quote_prefix', quote_number = $quote_number, quote_scope = '$quote_scope', quote_date = '$date', quote_expire = '$expire', quote_category_id = $category_id, quote_status = 'Draft', quote_amount = $quote_amount, quote_currency_code = '$quote_currency_code', quote_note = '$quote_note', quote_url_key = '$quote_url_key', quote_client_id = $client_id");

    $new_quote_id = mysqli_insert_id($mysqli);

    mysqli_query($mysqli,"INSERT INTO history SET history_status = 'Draft', history_description = 'Quote copied!', history_quote_id = $new_quote_id");

    $sql_items = mysqli_query($mysqli,"SELECT * FROM invoice_items WHERE item_quote_id = $quote_id");
    while($row = mysqli_fetch_array($sql_items)) {
        $item_id = intval($row['item_id']);
        $item_name = sanitizeInput($row['item_name']);
        $item_description = sanitizeInput($row['item_description']);
        $item_quantity = floatval($row['item_quantity']);
        $item_price = floatval($row['item_price']);
        $item_subtotal = floatval($row['item_subtotal']);
        $item_tax = floatval($row['item_tax']);
        $item_total = floatval($row['item_total']);
        $tax_id = intval($row['item_tax_id']);

        mysqli_query($mysqli,"INSERT INTO invoice_items SET item_name = '$item_name', item_description = '$item_description', item_quantity = $item_quantity, item_price = $item_price, item_subtotal = $item_subtotal, item_tax = $item_tax, item_total = $item_total, item_tax_id = $tax_id, item_quote_id = $new_quote_id");
    }

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Quote', log_action = 'Create', log_description = 'Copied Quote', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Quote copied";

    header("Location: quote.php?quote_id=$new_quote_id");

}

if (isset($_POST['add_quote_to_invoice'])) {

    $quote_id = intval($_POST['quote_id']);
    $date = sanitizeInput($_POST['date']);
    $client_net_terms = intval($_POST['client_net_terms']);

    $invoice_number = $config_invoice_next_number;
    $new_config_invoice_next_number = $config_invoice_next_number + 1;
    mysqli_query($mysqli,"UPDATE settings SET config_invoice_next_number = $new_config_invoice_next_number WHERE company_id = 1");

    $sql = mysqli_query($mysqli,"SELECT * FROM quotes WHERE quote_id = $quote_id");
    $row = mysqli_fetch_array($sql);
    $quote_amount = floatval($row['quote_amount']);
    $quote_currency_code = sanitizeInput($row['quote_currency_code']);
    $quote_scope = sanitizeInput($row['quote_scope']);
    $quote_note = sanitizeInput($row['quote_note']);

    $client_id = intval($row['quote_client_id']);
    $category_id = intval($row['quote_category_id']);

    //Generate a unique URL key for clients to access
    $url_key = randomString(156);

    mysqli_query($mysqli,"INSERT INTO invoices SET invoice_prefix = '$config_invoice_prefix', invoice_number = $invoice_number, invoice_scope = '$quote_scope', invoice_date = '$date', invoice_due = DATE_ADD(CURDATE(), INTERVAL $client_net_terms day), invoice_category_id = $category_id, invoice_status = 'Draft', invoice_amount = $quote_amount, invoice_currency_code = '$quote_currency_code', invoice_note = '$quote_note', invoice_url_key = '$url_key', invoice_client_id = $client_id");

    $new_invoice_id = mysqli_insert_id($mysqli);

    mysqli_query($mysqli,"INSERT INTO history SET history_status = 'Draft', history_description = 'Quote copied to Invoice!', history_invoice_id = $new_invoice_id");

    $sql_items = mysqli_query($mysqli,"SELECT * FROM invoice_items WHERE item_quote_id = $quote_id");
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

    mysqli_query($mysqli,"UPDATE quotes SET quote_status = 'Invoiced' WHERE quote_id = $quote_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Quote', log_action = 'Create', log_description = 'Quote copied to Invoice', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Quote copied to Invoice";

    header("Location: invoice.php?invoice_id=$new_invoice_id");

}

if (isset($_POST['add_quote_item'])) {

    $quote_id = intval($_POST['quote_id']);
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
    }else{
        $tax_amount = 0;
    }

    $total = $subtotal + $tax_amount;

    mysqli_query($mysqli,"INSERT INTO invoice_items SET item_name = '$name', item_description = '$description', item_quantity = $qty, item_price = $price, item_subtotal = $subtotal, item_tax = $tax_amount, item_total = $total, item_tax_id = $tax_id, item_order = $item_order, item_quote_id = $quote_id");

    //Get Discount
    $sql = mysqli_query($mysqli,"SELECT * FROM quotes WHERE quote_id = $quote_id");
    $row = mysqli_fetch_array($sql);

    $quote_discount_amount = floatval($row['quote_discount_amount']);


    //add up the total of all items
    $sql = mysqli_query($mysqli,"SELECT * FROM invoice_items WHERE item_quote_id = $quote_id");
    $quote_amount = 0;
    while($row = mysqli_fetch_array($sql)) {
        $item_total = floatval($row['item_total']);
        $quote_amount = $quote_amount + $item_total;
    }
    $new_quote_amount = $quote_amount - $quote_discount_amount;

    mysqli_query($mysqli,"UPDATE quotes SET quote_amount = $new_quote_amount WHERE quote_id = $quote_id");

    $_SESSION['alert_message'] = "Item added";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['quote_note'])) {

    $quote_id = intval($_POST['quote_id']);
    $note = sanitizeInput($_POST['note']);

    mysqli_query($mysqli,"UPDATE quotes SET quote_note = '$note' WHERE quote_id = $quote_id");

    $_SESSION['alert_message'] = "Notes added";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['edit_quote'])) {

    require_once 'post/quote_model.php';

    $quote_id = intval($_POST['quote_id']);

    //Calculate the new quote amount
    $sql = mysqli_query($mysqli,"SELECT * FROM invoice_items WHERE item_quote_id = $quote_id");
    $quote_amount = 0;
    while($row = mysqli_fetch_array($sql)) {
        $item_total = floatval($row['item_total']);
        $quote_amount = $quote_amount + $item_total;
    }
    $quote_amount = $quote_amount - $quote_discount;


    mysqli_query($mysqli,"UPDATE quotes SET quote_scope = '$scope', quote_date = '$date', quote_expire = '$expire', quote_discount_amount = '$quote_discount', quote_amount = '$quote_amount', quote_category_id = $category WHERE quote_id = $quote_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Quote', log_action = 'Modify', log_description = '$quote_id', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Quote modified";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_GET['delete_quote'])) {
    $quote_id = intval($_GET['delete_quote']);

    mysqli_query($mysqli,"DELETE FROM quotes WHERE quote_id = $quote_id");

    //Delete Items Associated with the Quote
    $sql = mysqli_query($mysqli,"SELECT * FROM invoice_items WHERE item_quote_id = $quote_id");
    while($row = mysqli_fetch_array($sql)) {;
        $item_id = intval($row['item_id']);
        mysqli_query($mysqli,"DELETE FROM invoice_items WHERE item_id = $item_id");
    }

    //Delete History Associated with the Quote
    $sql = mysqli_query($mysqli,"SELECT * FROM history WHERE history_quote_id = $quote_id");
    while($row = mysqli_fetch_array($sql)) {;
        $history_id = intval($row['history_id']);
        mysqli_query($mysqli,"DELETE FROM history WHERE history_id = $history_id");
    }

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Quote', log_action = 'Delete', log_description = '$quote_id', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Quotes deleted";

    if (isset($_GET['client_id'])) {
        $client_id = intval($_GET['client_id']);
        header("Location: client_quotes.php?client_id=$client_id");
    } else {
        header("Location: quotes.php");
    }

}

if (isset($_GET['delete_quote_item'])) {
    $item_id = intval($_GET['delete_quote_item']);

    $sql = mysqli_query($mysqli,"SELECT * FROM invoice_items WHERE item_id = $item_id");
    $row = mysqli_fetch_array($sql);
    $quote_id = intval($row['item_quote_id']);
    $item_subtotal = floatval($row['item_subtotal']);
    $item_tax = floatval($row['item_tax']);
    $item_total = floatval($row['item_total']);

    $sql = mysqli_query($mysqli,"SELECT * FROM quotes WHERE quote_id = $quote_id");
    $row = mysqli_fetch_array($sql);

    $new_quote_amount = floatval($row['quote_amount']) - $item_total;

    mysqli_query($mysqli,"UPDATE quotes SET quote_amount = $new_quote_amount WHERE quote_id = $quote_id");

    mysqli_query($mysqli,"DELETE FROM invoice_items WHERE item_id = $item_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Quote Item', log_action = 'Delete', log_description = '$item_id from $quote_id', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Item deleted";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_GET['mark_quote_sent'])) {

    $quote_id = intval($_GET['mark_quote_sent']);

    mysqli_query($mysqli,"UPDATE quotes SET quote_status = 'Sent' WHERE quote_id = $quote_id");

    mysqli_query($mysqli,"INSERT INTO history SET history_status = 'Sent', history_description = 'QUOTE marked sent', history_quote_id = $quote_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Quote', log_action = 'Update', log_description = '$quote_id marked sent', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Quote marked sent";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_GET['accept_quote'])) {

    $quote_id = intval($_GET['accept_quote']);

    mysqli_query($mysqli,"UPDATE quotes SET quote_status = 'Accepted' WHERE quote_id = $quote_id");

    mysqli_query($mysqli,"INSERT INTO history SET history_status = 'Accepted', history_description = 'Quote accepted!', history_quote_id = $quote_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Quote', log_action = 'Modify', log_description = 'Accepted Quote $quote_id', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Quote accepted";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_GET['decline_quote'])) {

    $quote_id = intval($_GET['decline_quote']);

    mysqli_query($mysqli,"UPDATE quotes SET quote_status = 'Declined' WHERE quote_id = $quote_id");

    mysqli_query($mysqli,"INSERT INTO history SET history_status = 'Cancelled', history_description = 'Quote declined!', history_quote_id = $quote_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Quote', log_action = 'Modify', log_description = 'Declined Quote $quote_id', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Quote declined";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_GET['email_quote'])) {

    $quote_id = intval($_GET['email_quote']);

    $sql = mysqli_query($mysqli,"SELECT * FROM quotes
    LEFT JOIN clients ON quote_client_id = client_id
    LEFT JOIN contacts ON clients.client_id = contacts.contact_client_id AND contact_primary = 1
    WHERE quote_id = $quote_id"
    );

    $row = mysqli_fetch_array($sql);
    $quote_prefix = $row['quote_prefix'];
    $quote_number = intval($row['quote_number']);
    $quote_scope = $row['quote_scope'];
    $quote_status = $row['quote_status'];
    $quote_date = $row['quote_date'];
    $quote_expire = $row['quote_expire'];
    $quote_amount = floatval($row['quote_amount']);
    $quote_url_key = $row['quote_url_key'];
    $quote_currency_code = $row['quote_currency_code'];
    $client_id = intval($row['client_id']);
    $client_name = $row['client_name'];
    $contact_name = $row['contact_name'];
    $contact_email = $row['contact_email'];
    $quote_prefix_escaped = sanitizeInput($row['quote_prefix']);
    $contact_name_escaped = sanitizeInput($row['contact_name']);
    $contact_email_escaped = sanitizeInput($row['contact_email']);

    $sql = mysqli_query($mysqli,"SELECT * FROM companies WHERE company_id = 1");
    $row = mysqli_fetch_array($sql);

    $company_name = $row['company_name'];
    $company_country = $row['company_country'];
    $company_address = $row['company_address'];
    $company_city = $row['company_city'];
    $company_state = $row['company_state'];
    $company_zip = $row['company_zip'];
    $company_phone = formatPhoneNumber($row['company_phone']);
    $company_email = $row['company_email'];
    $company_website = $row['company_website'];
    $company_logo = $row['company_logo'];

    // Sanitize Config vars from get_settings.php
    $config_quote_from_name_escaped = sanitizeInput($config_quote_from_name);
    $config_quote_from_email_escaped = sanitizeInput($config_quote_from_email);

    $subject = sanitizeInput("Quote [$quote_scope]");
    $body    = mysqli_escape_string($mysqli, "Hello $contact_name,<br><br>Thank you for your inquiry, we are pleased to provide you with the following estimate.<br><br><br>$quote_scope<br>Total Cost: " . numfmt_format_currency($currency_format, $quote_amount, $quote_currency_code) . "<br><br><br>View and accept your estimate online <a href='https://$config_base_url/guest_view_quote.php?quote_id=$quote_id&url_key=$quote_url_key'>here</a><br><br><br>~<br>$company_name<br>Sales<br>$config_quote_from_email<br>$company_phone");

    // Queue Mail
    mysqli_query($mysqli, "INSERT INTO email_queue SET email_recipient = '$contact_email_escaped', email_recipient_name = '$contact_name_escaped', email_from = '$config_quote_from_email_escaped', email_from_name = '$config_quote_from_name_escaped', email_subject = '$subject', email_content = '$body'");

    // Get Email ID for reference
    $email_id = mysqli_insert_id($mysqli);

    // Logging
    mysqli_query($mysqli,"INSERT INTO history SET history_status = 'Sent', history_description = 'Emailed Quote!', history_quote_id = $quote_id");
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Quote', log_action = 'Email', log_description = '$session_name emailed Quote $quote_prefix_escaped$quote_number to $contact_email_escaped Email ID: ', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $quote_id");

    $_SESSION['alert_message'] = "Quote has been sent";

    //Don't change the status to sent if the status is anything but draft
    if ($quote_status == 'Draft') {
        mysqli_query($mysqli,"UPDATE quotes SET quote_status = 'Sent' WHERE quote_id = $quote_id");
    }

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['export_client_quotes_csv'])){
    $client_id = intval($_POST['client_id']);

    //get records from database
    $sql = mysqli_query($mysqli,"SELECT * FROM clients WHERE client_id = $client_id");
    $row = mysqli_fetch_array($sql);

    $client_name = $row['client_name'];

    $sql = mysqli_query($mysqli,"SELECT * FROM quotes WHERE quote_client_id = $client_id ORDER BY quote_number ASC");
    if($sql->num_rows > 0){
        $delimiter = ",";
        $filename = $client_name . "-Quotes-" . date('Y-m-d') . ".csv";

        //create a file pointer
        $f = fopen('php://memory', 'w');

        //set column headers
        $fields = array('Quote Number', 'Scope', 'Amount', 'Date', 'Status');
        fputcsv($f, $fields, $delimiter);

        //output each row of the data, format line as csv and write to file pointer
        while($row = $sql->fetch_assoc()){
            $lineData = array($row['quote_prefix'] . $row['quote_number'], $row['quote_scope'], $row['quote_amount'], $row['quote_date'], $row['quote_status']);
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
    exit;

}

if (isset($_POST['update_quote_item_order'])) {

    if ($_POST['update_quote_item_order'] == 'up') {
        $item_id = intval($_POST['item_id']);
        $item_quote_id = intval($_POST['item_quote_id']);

        $sql = mysqli_query($mysqli,"SELECT * FROM invoice_items WHERE item_id = $item_id");
        $row = mysqli_fetch_array($sql);
        $item_order = intval($row['item_order']);

        $new_item_order = $item_order - 1;

        //Check if new item order is used
        $sql = mysqli_query($mysqli,"SELECT * FROM invoice_items WHERE item_quote_id = $item_quote_id AND item_order = $new_item_order");

        //Redo the entire order of list
        while ($row = mysqli_fetch_array($sql)) {
            $item_id = intval($row['item_id']);
            $item_order = intval($row['item_order']);

            $new_item_order = $item_order + 1;

            mysqli_query($mysqli,"UPDATE invoice_items SET item_order = $new_item_order WHERE item_id = $item_id");
        }



        mysqli_query($mysqli,"UPDATE invoice_items SET item_order = $item_order WHERE item_quote_id = $item_quote_id AND item_order = $new_item_order");
        mysqli_query($mysqli,"UPDATE invoice_items SET item_order = $new_item_order WHERE item_id = $item_id");

        $_SESSION['alert_message'] = "Item moved up";

        header("Location: " . $_SERVER["HTTP_REFERER"]);

    }

    if ($_POST['update_quote_item_order'] == 'down') {
        $item_id = intval($_POST['item_id']);
        $item_quote_id = intval($_POST['item_quote_id']);

        $sql = mysqli_query($mysqli,"SELECT * FROM invoice_items WHERE item_id = $item_id");
        $row = mysqli_fetch_array($sql);
        $item_order = intval($row['item_order']);

        $new_item_order = $item_order + 1;

        mysqli_query($mysqli,"UPDATE invoice_items SET item_order = $item_order WHERE item_quote_id = $item_quote_id AND item_order = $new_item_order");
        mysqli_query($mysqli,"UPDATE invoice_items SET item_order = $new_item_order WHERE item_id = $item_id");

        $_SESSION['alert_message'] = "Item moved down";

        header("Location: " . $_SERVER["HTTP_REFERER"]);

    }

}
