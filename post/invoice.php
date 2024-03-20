<?php

/*
 * ITFlow - GET/POST request handler for invoices & payments
 */

if (isset($_POST['add_invoice'])) {

    require_once 'post/invoice_model.php';

    $parameters['invoice_client_id'] = intval($_POST['client']);
    $parameters['invoice_date'] = sanitizeInput($_POST['date']);
    $parameters['invoice_category'] = intval($_POST['category']);
    $parameters['invoice_scope'] = sanitizeInput($_POST['scope']);

    $return_data = createInvoice($parameters);
    $invoice_id = $return_data['invoice']['invoice_id'];
    referWithAlert("Invoice added", "success", "invoice.php?invoice_id=$invoice_id");
}

if (isset($_POST['edit_invoice'])) {

    require_once 'post/invoice_model.php';

    $invoice_id = intval($_POST['invoice_id']);
    $due = sanitizeInput($_POST['due']);

    updateInvoice($parameters);
    referWithAlert("Invoice edited", "success");
}

if (isset($_POST['add_invoice_copy'])) {

    $invoice_id = intval($_POST['invoice_id']);
    $date = sanitizeInput($_POST['date']);

    $return_data = copyInvoice($invoice_id, $date);
    $invoice_id = $return_data['invoice']['invoice_id'];
    referWithAlert("Invoice copied", "success", "invoice.php?invoice_id=$invoice_id");
}

if (isset($_POST['add_invoice_recurring'])) {

    $invoice_id = intval($_POST['invoice_id']);
    $recurring_frequency = sanitizeInput($_POST['frequency']);

    createInvoiceFromRecurring($invoice_id, $recurring_frequency);
    referWithAlert("Recurring Invoice added from invoice", "success", "recurring_invoice.php?recurring_id=$recurring_id");

}

if (isset($_POST['add_recurring'])) {

    $client = intval($_POST['client']);
    $frequency = sanitizeInput($_POST['frequency']);
    $start_date = sanitizeInput($_POST['start_date']);
    $category = intval($_POST['category']);
    $scope = sanitizeInput($_POST['scope']);

    createRecurringInvoice(
        $client,
        $frequency,
        $start_date,
        $category,
        $scope
    );
    referWithAlert("Recurring Invoice added", "success");
}

if (isset($_POST['edit_recurring'])) {

    $recurring_id = intval($_POST['recurring_id']);
    $frequency = sanitizeInput($_POST['frequency']);
    $next_date = sanitizeInput($_POST['next_date']);
    $category = intval($_POST['category']);
    $scope = sanitizeInput($_POST['scope']);
    $status = intval($_POST['status']);
    $recurring_discount = floatval($_POST['recurring_discount']);

    updateRecurringInvoice(
        $recurring_id,
        $frequency,
        $next_date,
        $category,
        $scope,
        $status,
        $recurring_discount
    );
    referWithAlert("Recurring Invoice edited", "success");
}

if (isset($_GET['delete_recurring'])) {
    $recurring_id = intval($_GET['delete_recurring']);

    deleteRecurringInvoice($recurring_id);
    referWithAlert("Recurring Invoice deleted", "success");
}

if (isset($_POST['add_recurring_item'])) {

    $recurring_id = intval($_POST['recurring_id']);
    $name = sanitizeInput($_POST['name']);
    $description = sanitizeInput($_POST['description']);
    $qty = floatval($_POST['qty']);
    $price = floatval($_POST['price']);
    $tax_id = intval($_POST['tax_id']);
    $item_order = intval($_POST['item_order']);

    $item = [];
    $item['invoice_id'] = $recurring_id;
    $item['name'] = $name;
    $item['description'] = $description;
    $item['qty'] = $qty;
    $item['price'] = $price;
    $item['tax_id'] = $tax_id;
    $item['item_order'] = $item_order;

    createInvoiceItem("recurring", $item);
    referWithAlert("Item added", "success");
}

if (isset($_POST['recurring_note'])) {

    $recurring_id = intval($_POST['recurring_id']);
    $note = sanitizeInput($_POST['note']);

    mysqli_query($mysqli,"UPDATE recurring SET recurring_note = '$note' WHERE recurring_id = $recurring_id");
    referWithAlert("Notes added", "success");
}

if (isset($_GET['delete_recurring_item'])) {
    $item_id = intval($_GET['delete_recurring_item']);

    deleteInvoiceItem("recurring", $item_id);
    referWithAlert("Item deleted");
}

if (isset($_GET['mark_invoice_sent'])) {

    $invoice_id = intval($_GET['mark_invoice_sent']);

    updateInvoiceStatus($invoice_id, "Sent");
    referWithAlert("Invoice marked as sent", "success");
}

if (isset($_GET['cancel_invoice'])) {

    $invoice_id = intval($_GET['cancel_invoice']);

    updateInvoiceStatus($invoice_id, "Cancelled");
    referWithAlert("Invoice cancelled", "success");
}

if (isset($_GET['delete_invoice'])) {
    $invoice_id = intval($_GET['delete_invoice']);

    deleteInvoice($invoice_id);
    referWithAlert("Invoice deleted");
}

if (isset($_POST['add_invoice_item'])) {

    $invoice_id = intval($_POST['invoice_id']);
    $name = sanitizeInput($_POST['name']);
    $description = sanitizeInput($_POST['description']);
    $qty = floatval($_POST['qty']);
    $price = floatval($_POST['price']);
    $tax_id = intval($_POST['tax_id']);
    $item_order = intval($_POST['item_order']);

    $item = [];
    $item['item_invoice_id'] = $invoice_id;
    $item['item_name'] = $name;
    $item['item_description'] = $description;
    $item['item_qty'] = $qty;
    $item['item_price'] = $price;
    $item['item_tax_id'] = $tax_id;
    $item['item_order'] = $item_order;

    createInvoiceItem("invoice", $item);
    referWithAlert("Item added", "success");
}

if (isset($_POST['invoice_note'])) {

    $invoice_id = intval($_POST['invoice_id']);
    $note = sanitizeInput($_POST['note']);

    mysqli_query($mysqli,"UPDATE invoices SET invoice_note = '$note' WHERE invoice_id = $invoice_id");
    referWithAlert("Notes added", "success");
}

if (isset($_POST['edit_item'])) {

    $invoice_id = intval($_POST['invoice_id']);
    $quote_id = intval($_POST['quote_id']);
    $recurring_id = intval($_POST['recurring_id']);
    $item_id = intval($_POST['item_id']);
    $name = sanitizeInput($_POST['name']);
    $description = sanitizeInput($_POST['description']);
    $qty = floatval($_POST['qty']);
    $price = floatval($_POST['price']);
    $tax_id = intval($_POST['tax_id']);

    $item = [];
    $item['name'] = $name;
    $item['description'] = $description;
    $item['qty'] = $qty;
    $item['price'] = $price;
    $item['tax_id'] = $tax_id;
    $item['item_id'] = $item_id;
    $item['invoice_id'] = $invoice_id;
    $item['quote_id'] = $quote_id;
    $item['recurring_id'] = $recurring_id;

    updateInvoiceItem($item);
    referWithAlert("Item edited", "success");
}

if (isset($_GET['delete_invoice_item'])) {
    $item_id = intval($_GET['delete_invoice_item']);

    deleteInvoiceItem("invoice", $item_id);
    referWithAlert("Item deleted");
}

if (isset($_POST['add_payment'])) {

    $invoice_id = intval($_POST['invoice_id']);
    $balance = floatval($_POST['balance']);
    $date = sanitizeInput($_POST['date']);
    $amount = floatval($_POST['amount']);
    $account = intval($_POST['account']);
    $currency_code = sanitizeInput($_POST['currency_code']);
    $payment_method = sanitizeInput($_POST['payment_method']);
    $reference = sanitizeInput($_POST['reference']);
    $email_receipt = intval($_POST['email_receipt']);

    $payment = [];
    $payment['invoice_id'] = $invoice_id;
    $payment['balance'] = $balance;
    $payment['date'] = $date;
    $payment['amount'] = $amount;
    $payment['account'] = $account;
    $payment['currency_code'] = $currency_code;
    $payment['payment_method'] = $payment_method;
    $payment['reference'] = $reference;
    $payment['email_receipt'] = $email_receipt;

    createPayment($payment);
    referWithAlert("Payment added", "success");
}

if (isset($_POST['add_bulk_payment'])) {

    $client_id = intval($_POST['client_id']);
    $date = sanitizeInput($_POST['date']);
    $bulk_payment_amount = floatval($_POST['amount']);
    $bulk_payment_amount_static = floatval($_POST['amount']);
    $total_client_balance = floatval($_POST['balance']);
    $account = intval($_POST['account']);
    $currency_code = sanitizeInput($_POST['currency_code']);
    $payment_method = sanitizeInput($_POST['payment_method']);
    $reference = sanitizeInput($_POST['reference']);
    $email_receipt = intval($_POST['email_receipt']);

    $bulk_payment = [];
    $bulk_payment['client_id'] = $client_id;
    $bulk_payment['date'] = $date;
    $bulk_payment['bulk_payment_amount'] = $bulk_payment_amount;
    $bulk_payment['bulk_payment_amount_static'] = $bulk_payment_amount_static;
    $bulk_payment['total_client_balance'] = $total_client_balance;
    $bulk_payment['account'] = $account;
    $bulk_payment['currency_code'] = $currency_code;
    $bulk_payment['payment_method'] = $payment_method;
    $bulk_payment['reference'] = $reference;
    $bulk_payment['email_receipt'] = $email_receipt;

    createBulkPayment($bulk_payment);
    referWithAlert("Bulk Payment added", "success");
}

if (isset($_GET['delete_payment'])) {
    $payment_id = intval($_GET['delete_payment']);

    deletePayment($payment_id);
    referWithAlert("Payment deleted");
}

if (isset($_GET['email_invoice'])) {
    $invoice_id = intval($_GET['email_invoice']);

    emailInvoice($invoice_id);
    referWithAlert("Invoice sent", "success");
}

if (isset($_GET['force_recurring'])) {
    $recurring_id = intval($_GET['force_recurring']);

    forceRecurring($recurring_id);
    referWithAlert("Recurring Invoice forced", "success");
}

if (isset($_POST['export_client_invoices_csv'])) {
    $client_id = intval($_POST['client_id']);

    //get records from database
    $sql = mysqli_query($mysqli,"SELECT * FROM clients WHERE client_id = $client_id");
    $row = mysqli_fetch_array($sql);

    $client_name = $row['client_name'];

    $sql = mysqli_query($mysqli,"SELECT * FROM invoices WHERE invoice_client_id = $client_id ORDER BY invoice_number ASC");
    if ($sql->num_rows > 0) {
        $delimiter = ",";
        $filename = $client_name . "-Invoices-" . date('Y-m-d') . ".csv";

        //create a file pointer
        $f = fopen('php://memory', 'w');

        //set column headers
        $fields = array('Invoice Number', 'Scope', 'Amount', 'Issued Date', 'Due Date', 'Status');
        fputcsv($f, $fields, $delimiter);

        //output each row of the data, format line as csv and write to file pointer
        while($row = $sql->fetch_assoc()) {
            $lineData = array($row['invoice_prefix'] . $row['invoice_number'], $row['invoice_scope'], $row['invoice_amount'], $row['invoice_date'], $row['invoice_due'], $row['invoice_status']);
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

if (isset($_POST['export_client_recurring_csv'])) {
    $client_id = intval($_POST['client_id']);

    //get records from database
    $sql = mysqli_query($mysqli,"SELECT * FROM clients WHERE client_id = $client_id");
    $row = mysqli_fetch_array($sql);

    $client_name = $row['client_name'];

    $sql = mysqli_query($mysqli,"SELECT * FROM recurring WHERE recurring_client_id = $client_id ORDER BY recurring_number ASC");
    if ($sql->num_rows > 0) {
        $delimiter = ",";
        $filename = $client_name . "-Recurring Invoices-" . date('Y-m-d') . ".csv";

        //create a file pointer
        $f = fopen('php://memory', 'w');

        //set column headers
        $fields = array('Recurring Number', 'Scope', 'Amount', 'Frequency', 'Date Created');
        fputcsv($f, $fields, $delimiter);

        //output each row of the data, format line as csv and write to file pointer
        while($row = $sql->fetch_assoc()) {
            $lineData = array($row['recurring_prefix'] . $row['recurring_number'], $row['recurring_scope'], $row['recurring_amount'], ucwords($row['recurring_frequency'] . "ly"), $row['recurring_created_at']);
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

if (isset($_POST['export_client_payments_csv'])) {
    $client_id = intval($_POST['client_id']);

    //get records from database
    $sql = mysqli_query($mysqli,"SELECT * FROM clients WHERE client_id = $client_id");
    $row = mysqli_fetch_array($sql);

    $client_name = $row['client_name'];

    $sql = mysqli_query($mysqli,"SELECT * FROM payments, invoices WHERE invoice_client_id = $client_id AND payment_invoice_id = invoice_id ORDER BY payment_date ASC");
    if ($sql->num_rows > 0){
        $delimiter = ",";
        $filename = $client_name . "-Payments-" . date('Y-m-d') . ".csv";

        //create a file pointer
        $f = fopen('php://memory', 'w');

        //set column headers
        $fields = array('Payment Date', 'Invoice Date', 'Invoice Number', 'Invoice Amount', 'Payment Amount', 'Payment Method', 'Referrence');
        fputcsv($f, $fields, $delimiter);

        //output each row of the data, format line as csv and write to file pointer
        while($row = $sql->fetch_assoc()){
            $lineData = array($row['payment_date'], $row['invoice_date'], $row['invoice_prefix'] . $row['invoice_number'], $row['invoice_amount'], $row['payment_amount'], $row['payment_method'], $row['payment_reference']);
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

if (isset($_POST['update_recurring_item_order'])) {

    $item_id = intval($_POST['item_id']);
    $item_recurring_id = intval($_POST['item_recurring_id']);
    $item_direction = sanitizeInput($_POST['item_direction']);

    updateItemOrder("recurring", $item_id, $item_recurring_id, $item_direction);
    referWithAlert("Recurring Item Order Updated", "success");
}

if (isset($_POST['update_invoice_item_order'])) {

    $item_id = intval($_POST['item_id']);
    $item_invoice_id = intval($_POST['item_invoice_id']);
    $item_direction = sanitizeInput($_POST['item_direction']);

    updateItemOrder("invoice", $item_id, $item_invoice_id, $item_direction);
    referWithAlert("Invoice Item Order Updated", "success");
}

if (isset($_POST['link_invoice_to_ticket'])) {
    $invoice_id = intval($_POST['invoice_id']);
    $ticket_id = intval($_POST['ticket_id']);

    addInvoiceToTicket($invoice_id, $ticket_id);
    referWithAlert("Invoice linked to ticket", "success");
}

if (isset($_POST['add_ticket_to_invoice'])) {
    $invoice_id = intval($_POST['invoice_id']);
    $ticket_id = intval($_POST['ticket_id']);

    addInvoiceToTicket($invoice_id, $ticket_id);
    referWithAlert("Ticket linked to invoice", "success");
}

if (isset($_GET['apply_credit'])) {
    $credit_id = intval($_GET['apply_credit']);
    
    applyCredit($credit_id);
    referWithAlert("Credit applied", "success");
}

if (isset($_GET['delete_credit'])) {
    $credit_id = intval($_GET['delete_credit']);

    deleteCredit($credit_id);
    referWithAlert("Credit deleted", "success");
}
