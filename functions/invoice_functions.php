<?php

// Invoice related functions
// CRUD Functions used by API

function createInvoice(
    $parameters
) {

    $client = intval($parameters['client']);
    $scope = sanitizeInput($parameters['scope']);
    $date = date($parameters['date']);
    $category = intval($parameters['category']);

    // Access global variables
    global $mysqli, $session_company_currency, $session_user_id, $session_ip, $session_user_agent, $config_invoice_prefix, $config_invoice_next_number;

    //Get Net Terms
    $sql = mysqli_query($mysqli,"SELECT client_net_terms FROM clients WHERE client_id = $client");
    $row = mysqli_fetch_array($sql);
    $client_net_terms = intval($row['client_net_terms']);

    //Get the last Invoice Number and add 1 for the new invoice number
    $invoice_number = $config_invoice_next_number;
    $new_config_invoice_next_number = $config_invoice_next_number + 1;
    mysqli_query($mysqli,"UPDATE settings SET config_invoice_next_number = $new_config_invoice_next_number WHERE company_id = 1");

    //Generate a unique URL key for clients to access
    $url_key = randomString(156);

    mysqli_query($mysqli,"INSERT INTO invoices SET invoice_prefix = '$config_invoice_prefix', invoice_number = $invoice_number, invoice_scope = '$scope', invoice_date = '$date', invoice_due = DATE_ADD('$date', INTERVAL $client_net_terms day), invoice_currency_code = '$session_company_currency', invoice_category_id = $category, invoice_status = 'Draft', invoice_url_key = '$url_key', invoice_client_id = $client");
    $invoice_id = mysqli_insert_id($mysqli);

    mysqli_query($mysqli,"INSERT INTO history SET history_status = 'Draft', history_description = 'INVOICE added!', history_invoice_id = $invoice_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Invoice', log_action = 'Create', log_description = '$config_invoice_prefix$invoice_number', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");

}

function readInvoice(
    $parameters
) {

    $invoice_id = intval($parameters['invoice_id']);

    // Access global variables
    global $mysqli;

    $sql = mysqli_query($mysqli,"SELECT * FROM invoices WHERE invoice_id = $invoice_id LIMIT 1");
    $row = mysqli_fetch_assoc($sql);

    return $row;

}

function updateInvoice(
    $parameters
) {
    $invoice_id = $parameters['invoice_id'];
    $invoice_data = readInvoice(['invoice_id' => $invoice_id]);

    $invoice_due = $parameters['invoice_due']??$invoice_data['invoice_due'];
    $invoice_prefix = $parameters['invoice_prefix']??$invoice_data['invoice_prefix'];
    $invoice_number = $parameters['invoice_number']??$invoice_data['invoice_number'];
    $invoice_scope = $parameters['invoice_scope']??$invoice_data['invoice_scope'];
    $invoice_currency_code = $parameters['invoice_currency_code']??$invoice_data['invoice_currency_code'];
    $invoice_category_id = $parameters['invoice_category_id']??$invoice_data['invoice_category_id'];
    $invoice_url_key = $parameters['invoice_url_key']??$invoice_data['invoice_url_key'];
    $invoice_client_id = $parameters['invoice_client_id']??$invoice_data['invoice_client_id'];

    // if invoice status is a parameter, use update invoice status function for that part
    // else, use the current status
    if (isset($parameters['invoice_status']) || isset($parameters['invoice_archived'])) {

        $invoice_status = $parameters['invoice_status'];
        $invoice_archived = $parameters['invoice_archived'];

        if ($invoice_archived == 1) {
            $invoice_status = 'Archived';
        }

        updateInvoiceStatus($invoice_id, $invoice_status);
        
        // check if thats the only parameter, if so, return
        if (count($parameters) == 1) { return ['status' => 'success']; }

    } else { $invoice_status = $invoice_data['invoice_status']; }
    
    // if null, output 'NULL' for SQL, else output the data
    $invoice_date = $parameters['invoice_date']??($invoice_data['invoice_date'] == NULL ? 'NULL' : $invoice_data['invoice_date']);

    // Access global variables
    global $mysqli, $session_user_id, $session_ip, $session_user_agent;

    mysqli_query($mysqli,"UPDATE invoices SET invoice_prefix = '$invoice_prefix', invoice_number = $invoice_number, invoice_scope = '$invoice_scope', invoice_date = '$invoice_date', invoice_due = '$invoice_due', invoice_currency_code = '$invoice_currency_code', invoice_category_id = $invoice_category_id, invoice_url_key = '$invoice_url_key', invoice_client_id = $invoice_client_id WHERE invoice_id = $invoice_id");

    mysqli_query($mysqli,"INSERT INTO history SET history_status = '$invoice_status', history_description = 'INVOICE updated!', history_invoice_id = $invoice_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Invoice', log_action = 'Update', log_description = '$invoice_prefix$invoice_number', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");

    $return_data = [
        'status' => 'success',
        'message' => "Invoice $invoice_number has been updated",
        'asset' => readInvoice(['invoice_id' => $invoice_id])
    ];

    return $return_data;
}

function deleteInvoice(
    $parameters
) {
    $invoice_id = intval($parameters['invoice_id']);

    // Access global variables
    global $mysqli, $session_user_id, $session_ip, $session_user_agent;

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

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Invoice', log_action = 'Delete', log_description = '$invoice_id', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");
}

// Below this are functions not called directly by the API
function copyInvoice(
    $invoice_id,
    $date
) {

    // Access global variables
    global $mysqli, $session_user_id, $session_ip, $session_user_agent, $config_invoice_prefix, $config_invoice_next_number;

    //Get Net Terms
    $sql = mysqli_query($mysqli,"SELECT client_net_terms FROM clients, invoices WHERE client_id = invoice_client_id AND invoice_id = $invoice_id");
    $row = mysqli_fetch_array($sql);
    $client_net_terms = intval($row['client_net_terms']);

    $invoice_number = $config_invoice_next_number;
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

    //Generate a unique URL key for clients to access
    $url_key = randomString(156);


    mysqli_query($mysqli,"INSERT INTO invoices SET invoice_prefix = '$config_invoice_prefix', invoice_number = $invoice_number, invoice_scope = '$invoice_scope', invoice_date = '$date', invoice_due = DATE_ADD('$date', INTERVAL $client_net_terms day), invoice_category_id = $category_id, invoice_status = 'Draft', invoice_discount_amount = $invoice_discount_amount, invoice_amount = $invoice_amount, invoice_currency_code = '$invoice_currency_code', invoice_note = '$invoice_note', invoice_url_key = '$url_key', invoice_client_id = $client_id");

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

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Invoice', log_action = 'Create', log_description = 'Copied Invoice', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");

}

function updateInvoiceStatus(
    $invoice_id,
    $status
) {

    // Check if invoice needs to be unarchived (status updated other than archive)
    $invoice_data = readInvoice(['invoice_id' => $invoice_id]);
    if ($invoice_data['status'] == 'Archived' && $invoice_data['status'] != $status) {
        $archived_query = ", invoice_archived_at = NULL";
    }

    switch($status):
        case "Draft":
            $history_description = "Invoice set to Draft";
            break;
        case "Sent":
            $history_description = "Invoice sent to client";
            break;
        case "Viewed":
            $history_description = "Invoice viewed by client";
            break;
        case "Paid":
            $history_description = "Invoice paid by client";
            break;
        case "Overdue":
            $history_description = "Invoice is overdue";
            break;
        case "Cancelled":
            $history_description = "Invoice cancelled";
            break;
        case "Archived":
            $history_description = "Invoice archived";
            $archived_query = ", invoice_archived_at = NOW()";
            break;
        default:
            $history_description = "Invoice status change error!";
            $status = "Draft";
            break;
    endswitch;

    // Access global variables
    global $mysqli, $session_user_id, $session_ip, $session_user_agent;

    mysqli_query($mysqli,"UPDATE invoices SET invoice_status = '$status'$archived_query WHERE invoice_id = $invoice_id");

    mysqli_query($mysqli,"INSERT INTO history SET history_status = '$status', history_description = '$history_description', history_invoice_id = $invoice_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Invoice', log_action = 'Status', log_description = 'Invoice ID $invoice_id', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");
}

function addInvoiceToTicket(
    $invoice_id,
    $ticket_id
) {
    // Access global variables
    global $mysqli, $session_user_id, $session_ip, $session_user_agent;

    mysqli_query($mysqli,"UPDATE invoices SET invoice_ticket_id = $ticket_id WHERE invoice_id = $invoice_id");
}
