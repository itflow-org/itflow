<?php

/*
 * ITFlow - GET/POST request handler for invoices & payments
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['add_invoice'])) {

    require_once 'post/user/invoice_model.php';

    $client_id = intval($_POST['client']);

    //Get Net Terms
    $sql = mysqli_query($mysqli,"SELECT client_net_terms FROM clients WHERE client_id = $client_id");
    $row = mysqli_fetch_array($sql);
    $client_net_terms = intval($row['client_net_terms']);

    //Get the last Invoice Number and add 1 for the new invoice number
    $invoice_number = $config_invoice_next_number;
    $new_config_invoice_next_number = $config_invoice_next_number + 1;
    mysqli_query($mysqli,"UPDATE settings SET config_invoice_next_number = $new_config_invoice_next_number WHERE company_id = 1");

    //Generate a unique URL key for clients to access
    $url_key = randomString(156);

    mysqli_query($mysqli,"INSERT INTO invoices SET invoice_prefix = '$config_invoice_prefix', invoice_number = $invoice_number, invoice_scope = '$scope', invoice_date = '$date', invoice_due = DATE_ADD('$date', INTERVAL $client_net_terms day), invoice_currency_code = '$session_company_currency', invoice_category_id = $category, invoice_status = 'Draft', invoice_url_key = '$url_key', invoice_client_id = $client_id");
    
    $invoice_id = mysqli_insert_id($mysqli);

    mysqli_query($mysqli,"INSERT INTO history SET history_status = 'Draft', history_description = 'Invoice created', history_invoice_id = $invoice_id");

    // Logging
    logAction("Invoice", "Create", "$session_name created Invoice $config_invoice_prefix$invoice_number - $scope", $client_id, $invoice_id);

    customAction('invoice_create', $invoice_id);

    $_SESSION['alert_message'] = "Invoice <strong>$config_invoice_prefix$invoice_number</strong> created";

    header("Location: invoice.php?invoice_id=$invoice_id");
}

if (isset($_POST['edit_invoice'])) {

    require_once 'post/user/invoice_model.php';

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

    // Logging
    logAction("Invoice", "Edit", "$session_name edited Invoice $invoice_prefix$invoice_number - $scope", $client_id, $invoice_id);

    $_SESSION['alert_message'] = "Invoice <strong>$invoice_prefix$invoice_number</strong> edited";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

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

    //Logging
    logAction("Invoice", "Create", "$session_name created new Invoice $config_invoice_prefix$new_invoice_number from $old_invoice_prefix$old_invoice_prefix", $client_id, $new_invoice_id);

    customAction('invoice_create', $new_invoice_id);

    $_SESSION['alert_message'] = "Created new Invoice <strong>$config_invoice_prefix$new_invoice_number</strong> from <strong>$old_invoice_prefix$old_invoice_prefix</strong>";

    header("Location: invoice.php?invoice_id=$new_invoice_id");

}

if (isset($_POST['add_invoice_recurring'])) {

    $invoice_id = intval($_POST['invoice_id']);
    $recurring_frequency = sanitizeInput($_POST['frequency']);

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

    //Get the last Recurring Number and add 1 for the new Recurring number
    $recurring_number = $config_recurring_next_number;
    $new_config_recurring_next_number = $config_recurring_next_number + 1;
    mysqli_query($mysqli,"UPDATE settings SET config_recurring_next_number = $new_config_recurring_next_number WHERE company_id = 1");

    mysqli_query($mysqli,"INSERT INTO recurring SET recurring_prefix = '$config_recurring_prefix', recurring_number = $recurring_number, recurring_scope = '$invoice_scope', recurring_frequency = '$recurring_frequency', recurring_next_date = DATE_ADD('$invoice_date', INTERVAL 1 $recurring_frequency), recurring_status = 1, recurring_amount = $invoice_amount, recurring_currency_code = '$invoice_currency_code', recurring_note = '$invoice_note', recurring_category_id = $category_id, recurring_client_id = $client_id");

    $recurring_id = mysqli_insert_id($mysqli);

    mysqli_query($mysqli,"INSERT INTO history SET history_status = 'Draft', history_description = 'Recurring Created from INVOICE!', history_recurring_id = $recurring_id");

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

        mysqli_query($mysqli,"INSERT INTO invoice_items SET item_name = '$item_name', item_description = '$item_description', item_quantity = $item_quantity, item_price = $item_price, item_subtotal = $item_subtotal, item_tax = $item_tax, item_total = $item_total, item_order = $item_order, item_tax_id = $tax_id, item_recurring_id = $recurring_id");
    }

    // Logging
    logAction("Recurring Invoice", "Create", "$session_name created recurring Invoice from Invoice $invoice_prefix$invoice_number", $client_id, $recurring_id);

    $_SESSION['alert_message'] = "Created recurring Invoice from Invoice <strong>$invoice_prefix$invoice_number</strong>";

    header("Location: recurring_invoice.php?recurring_id=$recurring_id");

}

if (isset($_POST['add_recurring'])) {

    $client_id = intval($_POST['client']);
    $frequency = sanitizeInput($_POST['frequency']);
    $start_date = sanitizeInput($_POST['start_date']);
    $category = intval($_POST['category']);
    $scope = sanitizeInput($_POST['scope']);

    //Get the last Recurring Number and add 1 for the new Recurring number
    $recurring_number = $config_recurring_next_number;
    $new_config_recurring_next_number = $config_recurring_next_number + 1;
    mysqli_query($mysqli,"UPDATE settings SET config_recurring_next_number = $new_config_recurring_next_number WHERE company_id = 1");

    mysqli_query($mysqli,"INSERT INTO recurring SET recurring_prefix = '$config_recurring_prefix', recurring_number = $recurring_number, recurring_scope = '$scope', recurring_frequency = '$frequency', recurring_next_date = '$start_date', recurring_category_id = $category, recurring_status = 1, recurring_currency_code = '$session_company_currency', recurring_client_id = $client_id");

    $recurring_id = mysqli_insert_id($mysqli);

    mysqli_query($mysqli,"INSERT INTO history SET history_status = 'Active', history_description = 'Recurring Invoice created', history_recurring_id = $recurring_id");

    //Logging
    logAction("Recurring Invoice", "Create", "$session_name created recurring invoice $config_recurring_prefix$recurring_number - $scope", $client_id, $recurring_id);

    $_SESSION['alert_message'] = "Recurring Invoice <strong>$config_recurring_prefix$recurring_number</strong> created";

    header("Location: recurring_invoice.php?recurring_id=$recurring_id");

}

if (isset($_POST['edit_recurring'])) {

    $recurring_id = intval($_POST['recurring_id']);
    $frequency = sanitizeInput($_POST['frequency']);
    $next_date = sanitizeInput($_POST['next_date']);
    $category = intval($_POST['category']);
    $scope = sanitizeInput($_POST['scope']);
    $status = intval($_POST['status']);
    $recurring_discount = floatval($_POST['recurring_discount']);

    // Get Recurring Invoice Details and Client ID for Logging
    $sql = mysqli_query($mysqli,"SELECT recurring_prefix, recurring_number, recurring_client_id FROM recurring WHERE recurring_id = $recurring_id");
    $row = mysqli_fetch_array($sql);
    $recurring_invoice_prefix = sanitizeInput($row['recurring_prefix']);
    $recurring_invoice_number = intval($row['recurring_number']);
    $client_id = intval($row['recurring_client_id']);

    //Calculate new total
    $sql = mysqli_query($mysqli,"SELECT * FROM invoice_items WHERE item_recurring_id = $recurring_id");
    $recurring_amount = 0;
    while($row = mysqli_fetch_array($sql)) {
        $item_total = floatval($row['item_total']);
        $recurring_amount = $recurring_amount + $item_total;
    }
    $recurring_amount = $recurring_amount - $recurring_discount;

    mysqli_query($mysqli,"UPDATE recurring SET recurring_scope = '$scope', recurring_frequency = '$frequency', recurring_next_date = '$next_date', recurring_category_id = $category, recurring_discount_amount = $recurring_discount, recurring_amount = $recurring_amount, recurring_status = $status WHERE recurring_id = $recurring_id");

    mysqli_query($mysqli,"INSERT INTO history SET history_status = '$status', history_description = 'Recurring Invoice edited', history_recurring_id = $recurring_id");

    // Logging
    logAction("Recurring Invoice", "Edit", "$session_name edited recurring invoice $recurring_invoice_prefix$recurring_invoice_number - $scope", $client_id, $recurring_id);

    $_SESSION['alert_message'] = "Recurring Invoice <strong>$recurring_invoice_prefix$recurring_invoice_number</strong> edited";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_GET['delete_recurring'])) {
    $recurring_id = intval($_GET['delete_recurring']);

    // Get Recurring Invoice Details and Client ID for Logging
    $sql = mysqli_query($mysqli,"SELECT recurring_prefix, recurring_number, recurring_scope, recurring_client_id FROM recurring WHERE recurring_id = $recurring_id");
    $row = mysqli_fetch_array($sql);
    $recurring_invoice_prefix = sanitizeInput($row['recurring_prefix']);
    $recurring_invoice_number = intval($row['recurring_number']);
    $recurring_invoice_scope = sanitizeInput($row['recurring_scope']);
    $client_id = intval($row['recurring_client_id']);

    mysqli_query($mysqli,"DELETE FROM recurring WHERE recurring_id = $recurring_id");

    //Delete Items Associated with the Recurring
    $sql = mysqli_query($mysqli,"SELECT * FROM invoice_items WHERE item_recurring_id = $recurring_id");
    while($row = mysqli_fetch_array($sql)) {
        $item_id = intval($row['item_id']);
        mysqli_query($mysqli,"DELETE FROM invoice_items WHERE item_id = $item_id");
    }

    //Delete History Associated with the Invoice
    $sql = mysqli_query($mysqli,"SELECT * FROM history WHERE history_recurring_id = $recurring_id");
    while($row = mysqli_fetch_array($sql)) {
        $history_id = intval($row['history_id']);
        mysqli_query($mysqli,"DELETE FROM history WHERE history_id = $history_id");
    }

    // Logging
    logAction("Recurring Invoice", "Delete", "$session_name deleted recurring invoice $recurring_invoice_prefix$recurring_invoice_number - $recurring_invoice_scope", $client_id);

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "Recurring Invoice <strong>$recurring_invoice_prefix$recurring_invoice_number</strong> deleted";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['add_recurring_item'])) {

    $recurring_id = intval($_POST['recurring_id']);
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

    mysqli_query($mysqli,"INSERT INTO invoice_items SET item_name = '$name', item_description = '$description', item_quantity = $qty, item_price = $price, item_subtotal = $subtotal, item_tax = $tax_amount, item_total = $total, item_tax_id = $tax_id, item_order = $item_order, item_recurring_id = $recurring_id");


    $sql = mysqli_query($mysqli,"SELECT * FROM recurring WHERE recurring_id = $recurring_id");
    $row = mysqli_fetch_array($sql);
    $recurring_discount = floatval($row['recurring_discount_amount']);
    $recurring_invoice_prefix = sanitizeInput($row['recurring_prefix']);
    $recurring_invoice_number = intval($row['recurring_number']);
    $client_id = intval($row['recurring_client_id']);

    //add up all the items
    $sql = mysqli_query($mysqli,"SELECT * FROM invoice_items WHERE item_recurring_id = $recurring_id");
    $recurring_amount = 0;
    while($row = mysqli_fetch_array($sql)) {
        $item_total = floatval($row['item_total']);
        $recurring_amount = $recurring_amount + $item_total;
    }
    $recurring_amount = $recurring_amount - $recurring_discount;

    mysqli_query($mysqli,"UPDATE recurring SET recurring_amount = $recurring_amount WHERE recurring_id = $recurring_id");

    // Logging
    logAction("Recurring Invoice", "Edit", "$session_name added item $name to recurring invoice $recurring_invoice_prefix$recurring_invoice_number", $client_id, $recurring_id);

    $_SESSION['alert_message'] = "Item <srrong>$name</strong> added to Recurring Invoice";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['recurring_note'])) {

    $recurring_id = intval($_POST['recurring_id']);
    $note = sanitizeInput($_POST['note']);

    // Get Recurring details for logging
    $sql = mysqli_query($mysqli,"SELECT recurring_prefix, recurring_number, recurring_client_id FROM recurring WHERE recurring_id = $recurring_id");
    $row = mysqli_fetch_array($sql);
    $recurring_invoice_prefix = sanitizeInput($row['recurring_prefix']);
    $recurring_invoice_number = intval($row['recurring_number']);
    $client_id = intval($row['recurring_client_id']);

    mysqli_query($mysqli,"UPDATE recurring SET recurring_note = '$note' WHERE recurring_id = $recurring_id");

    // Logging
    logAction("Recurring Invoice", "Edit", "$session_name added note to recurring invoice $recurring_invoice_prefix$recurring_invoice_number", $client_id, $recurring_id);

    $_SESSION['alert_message'] = "Notes added";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_GET['delete_recurring_item'])) {
    $item_id = intval($_GET['delete_recurring_item']);

    $sql = mysqli_query($mysqli,"SELECT * FROM invoice_items WHERE item_id = $item_id");
    $row = mysqli_fetch_array($sql);
    $recurring_id = intval($row['item_recurring_id']);
    $item_name = sanitizeInput($row['item_name']);
    $item_subtotal = floatval($row['item_subtotal']);
    $item_tax = floatval($row['item_tax']);
    $item_total = floatval($row['item_total']);

    $sql = mysqli_query($mysqli,"SELECT * FROM recurring WHERE recurring_id = $recurring_id");
    $row = mysqli_fetch_array($sql);
    $recurring_invoice_prefix = sanitizeInput($row['recurring_prefix']);
    $recurring_invoice_number = intval($row['recurring_number']);
    $client_id = intval($row['recurring_client_id']);
    
    $new_recurring_amount = floatval($row['recurring_amount']) - $item_total;

    mysqli_query($mysqli,"UPDATE recurring SET recurring_amount = $new_recurring_amount WHERE recurring_id = $recurring_id");

    mysqli_query($mysqli,"DELETE FROM invoice_items WHERE item_id = $item_id");

    // Logging
    logAction("Recurring Invoice", "Edit", "$session_name removed item $item_name from recurring invoice $recurring_invoice_prefix$recurring_invoice_number", $client_id);

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "Item <strong>$item_name</strong> removed";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

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

    // Logging
    logAction("Invoice", "Edit", "$session_name marked invoice $invoice_prefix$invoice_number sent", $client_id, $invoice_id);

    $_SESSION['alert_message'] = "Invoice marked sent";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

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

    //Logging
    logAction("Invoice", "Edit", "$session_name marked invoice $invoice_prefix$invoice_number Non-Billable", $client_id, $invoice_id);

    $_SESSION['alert_message'] = "Invoice marked Non-Billable";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

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

    // Logging
    logAction("Invoice", "Edit", "$session_name cancelled invoice $invoice_prefix$invoice_number", $client_id, $invoice_id);

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "Invoice <strong>$invoice_prefix$invoice_number</strong> cancelled";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

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

    // Logging
    logAction("Invoice", "Delete", "$session_name deleted invoice $invoice_prefix$invoice_number", $client_id);

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "Invoice <strong>$invoice_prefix$invoice_number</strong> deleted";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['add_invoice_item'])) {

    $invoice_id = intval($_POST['invoice_id']);
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

    mysqli_query($mysqli,"INSERT INTO invoice_items SET item_name = '$name', item_description = '$description', item_quantity = $qty, item_price = $price, item_subtotal = $subtotal, item_tax = $tax_amount, item_total = $total, item_order = $item_order, item_tax_id = $tax_id, item_invoice_id = $invoice_id");

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

    // Logging
    logAction("Invoice", "Edit", "$session_name added item $name to invoice $invoice_prefix$invoice_number", $client_id, $invoice_id);

    $_SESSION['alert_message'] = "Item <strong>$name</strong> added to invoice";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['invoice_note'])) {

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

    $_SESSION['alert_message'] = "Notes added";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['edit_item'])) {

    $item_id = intval($_POST['item_id']);
    $name = sanitizeInput($_POST['name']);
    $description = sanitizeInput($_POST['description']);
    $qty = floatval($_POST['qty']);
    $price = floatval($_POST['price']);
    $tax_id = intval($_POST['tax_id']);

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
    $sql = mysqli_query($mysqli,"SELECT item_invoice_id, item_quote_id, item_recurring_id FROM invoice_items WHERE item_id = $item_id");
    $row = mysqli_fetch_array($sql);
    $invoice_id = intval($row['item_invoice_id']);
    $quote_id = intval($row['item_quote_id']);
    $recurring_id = intval($row['item_recurring_id']);

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

        // Logging
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

        // Logging
        logAction("Quote", "Edit", "$session_name edited item $name on quote $quote_prefix$quote_number", $client_id, $quote_id);

    } else {
        //Get Discount Amount
        $sql = mysqli_query($mysqli,"SELECT * FROM recurring WHERE recurring_id = $recurring_id");
        $row = mysqli_fetch_array($sql);
        $recurring_invoice_prefix = sanitizeInput($row['recurring_prefix']);
        $recurring_invoice_number = intval($row['recurring_number']);
        $client_id = intval($row['recurring_client_id']);
        $recurring_discount = floatval($row['recurring_discount_amount']);

        //Update Invoice Balances by tallying up invoice items
        $sql_recurring_total = mysqli_query($mysqli,"SELECT SUM(item_total) AS recurring_total FROM invoice_items WHERE item_recurring_id = $recurring_id");
        $row = mysqli_fetch_array($sql_recurring_total);
        $new_recurring_amount = floatval($row['recurring_total']) - $recurring_discount;

        mysqli_query($mysqli,"UPDATE recurring SET recurring_amount = $new_recurring_amount WHERE recurring_id = $recurring_id");

        // Logging
        logAction("Recurring Invoice", "Edit", "$session_name edited item $name on recurring invoice $recurring_invoice_prefix$recurring_invoice_number", $client_id, $recurring_id);

    }

    $_SESSION['alert_message'] = "Item <strong>$name</strong> updated";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_GET['delete_invoice_item'])) {
    $item_id = intval($_GET['delete_invoice_item']);

    $sql = mysqli_query($mysqli,"SELECT * FROM invoice_items WHERE item_id = $item_id");
    $row = mysqli_fetch_array($sql);
    $invoice_id = intval($row['item_invoice_id']);
    $item_name = sanitizeInput($row['item_name']);
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

    // Logging
    logAction("Invoice", "Delete", "$session_name removed item $item_name from invoice $invoice_prefix$invoice_number", $client_id, $invoice_id);

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "Item <strong>$item_name</strong> removed from invoice";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

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

    //Check to see if amount entered is greater than the balance of the invoice
    if ($amount > $balance) {
        $_SESSION['alert_message'] = "Payment is more than the balance";
        header("Location: " . $_SERVER["HTTP_REFERER"]);
    } else {
        mysqli_query($mysqli,"INSERT INTO payments SET payment_date = '$date', payment_amount = $amount, payment_currency_code = '$currency_code', payment_account_id = $account, payment_method = '$payment_method', payment_reference = '$reference', payment_invoice_id = $invoice_id");

        // Get Payment ID for reference
        $payment_id = mysqli_insert_id($mysqli);

        //Add up all the payments for the invoice and get the total amount paid to the invoice
        $sql_total_payments_amount = mysqli_query($mysqli,"SELECT SUM(payment_amount) AS payments_amount FROM payments WHERE payment_invoice_id = $invoice_id");
        $row = mysqli_fetch_array($sql_total_payments_amount);
        $total_payments_amount = floatval($row['payments_amount']);

        //Get the invoice total
        $sql = mysqli_query($mysqli,"SELECT * FROM invoices
            LEFT JOIN clients ON invoice_client_id = client_id
            LEFT JOIN contacts ON clients.client_id = contacts.contact_client_id AND contact_primary = 1
            WHERE invoice_id = $invoice_id"
        );

        $row = mysqli_fetch_array($sql);
        $invoice_amount = floatval($row['invoice_amount']);
        $invoice_prefix = sanitizeInput($row['invoice_prefix']);
        $invoice_number = intval($row['invoice_number']);
        $invoice_url_key = sanitizeInput($row['invoice_url_key']);
        $invoice_currency_code = sanitizeInput($row['invoice_currency_code']);
        $client_id = intval($row['client_id']);
        $client_name = sanitizeInput($row['client_name']);
        $contact_name = sanitizeInput($row['contact_name']);
        $contact_email = sanitizeInput($row['contact_email']);
        $contact_phone = sanitizeInput(formatPhoneNumber($row['contact_phone']));
        $contact_extension = preg_replace("/[^0-9]/", '',$row['contact_extension']);
        $contact_mobile = sanitizeInput(formatPhoneNumber($row['contact_mobile']));

        $sql = mysqli_query($mysqli,"SELECT * FROM companies WHERE company_id = 1");
        $row = mysqli_fetch_array($sql);

        $company_name = sanitizeInput($row['company_name']);
        $company_country = sanitizeInput($row['company_country']);
        $company_address = sanitizeInput($row['company_address']);
        $company_city = sanitizeInput($row['company_city']);
        $company_state = sanitizeInput($row['company_state']);
        $company_zip = sanitizeInput($row['company_zip']);
        $company_phone = sanitizeInput(formatPhoneNumber($row['company_phone']));
        $company_email = sanitizeInput($row['company_email']);
        $company_website = sanitizeInput($row['company_website']);
        $company_logo = sanitizeInput($row['company_logo']);

        // Sanitize Config vars from get_settings.php
        $config_invoice_from_name = sanitizeInput($config_invoice_from_name);
        $config_invoice_from_email = sanitizeInput($config_invoice_from_email);

        //Calculate the Invoice balance
        $invoice_balance = $invoice_amount - $total_payments_amount;

        $email_data = [];

        //Determine if invoice has been paid then set the status accordingly
        if ($invoice_balance == 0) {

            $invoice_status = "Paid";

            if ($email_receipt == 1) {

                $subject = "Payment Received - Invoice $invoice_prefix$invoice_number";
                $body = "Hello $contact_name,<br><br>We have received your payment in full for the amount of " . numfmt_format_currency($currency_format, $amount, $invoice_currency_code) . " for invoice <a href=\'https://$config_base_url/guest/guest_view_invoice.php?invoice_id=$invoice_id&url_key=$invoice_url_key\'>$invoice_prefix$invoice_number</a>. Please keep this email as a receipt for your records.<br><br>Amount Paid: " . numfmt_format_currency($currency_format, $amount, $invoice_currency_code) . "<br>Payment Method: $payment_method<br>Payment Reference: $reference<br><br>Thank you for your business!<br><br><br>--<br>$company_name - Billing Department<br>$config_invoice_from_email<br>$company_phone";

                // Queue Mail
                $email = [
                    'from' => $config_invoice_from_email,
                    'from_name' => $config_invoice_from_name,
                    'recipient' => $contact_email,
                    'recipient_name' => $contact_name,
                    'subject' => $subject,
                    'body' => $body
                ];

                $email_data[] = $email;

                // Add email to queue
                if (!empty($email)) {
                    addToMailQueue($email_data);
                }

                // Get Email ID for reference
                $email_id = mysqli_insert_id($mysqli);

                // Email Logging
                mysqli_query($mysqli,"INSERT INTO history SET history_status = 'Sent', history_description = 'Payment Receipt sent to mail queue ID: $email_id!', history_invoice_id = $invoice_id");
                logAction("Invoice", "Payment", "Payment receipt for invoice $invoice_prefix$invoice_number queued to $contact_email Email ID: $email_id", $client_id, $invoice_id);

            }

        } else {

            $invoice_status = "Partial";

            if ($email_receipt == 1) {

                $subject = "Partial Payment Received - Invoice $invoice_prefix$invoice_number";
                $body = "Hello $contact_name,<br><br>We have received partial payment in the amount of " . numfmt_format_currency($currency_format, $amount, $invoice_currency_code) . " and it has been applied to invoice <a href=\'https://$config_base_url/guest/guest_view_invoice.php?invoice_id=$invoice_id&url_key=$invoice_url_key\'>$invoice_prefix$invoice_number</a>. Please keep this email as a receipt for your records.<br><br>Amount Paid: " . numfmt_format_currency($currency_format, $amount, $invoice_currency_code) . "<br>Payment Method: $payment_method<br>Payment Reference: $reference<br>Invoice Balance: " . numfmt_format_currency($currency_format, $invoice_balance, $invoice_currency_code) . "<br><br>Thank you for your business!<br><br><br>~<br>$company_name - Billing<br>$config_invoice_from_email<br>$company_phone";

                // Queue Mail
                $email = [
                    'from' => $config_invoice_from_email,
                    'from_name' => $config_invoice_from_name,
                    'recipient' => $contact_email,
                    'recipient_name' => $contact_name,
                    'subject' => $subject,
                    'body' => $body
                ];

                $email_data[] = $email;

                // Add email to queue
                if (!empty($email)) {
                    addToMailQueue($email_data);
                }

                // Get Email ID for reference
                $email_id = mysqli_insert_id($mysqli);

                // Email Logging
                mysqli_query($mysqli,"INSERT INTO history SET history_status = 'Sent', history_description = 'Payment Receipt sent to mail queue ID: $email_id!', history_invoice_id = $invoice_id");
                logAction("Invoice", "Payment", "Payment receipt for invoice $invoice_prefix$invoice_number queued to $contact_email Email ID: $email_id", $client_id, $invoice_id);

            }

        }

        //Update Invoice Status
        mysqli_query($mysqli,"UPDATE invoices SET invoice_status = '$invoice_status' WHERE invoice_id = $invoice_id");

        //Add Payment to History
        mysqli_query($mysqli,"INSERT INTO history SET history_status = '$invoice_status', history_description = 'Payment added', history_invoice_id = $invoice_id");

        // Logging
        logAction("Invoice", "Payment", "Payment amount of " . numfmt_format_currency($currency_format, $amount, $invoice_currency_code) . " added to invoice $invoice_prefix$invoice_number", $client_id, $invoice_id);

        customAction('invoice_pay', $invoice_id);

        $_SESSION['alert_message'] .= "Payment amount <strong>" . numfmt_format_currency($currency_format, $amount, $invoice_currency_code) . "</strong> added";

        header("Location: " . $_SERVER["HTTP_REFERER"]);
    }
}

if (isset($_GET['add_payment_stripe'])) {

    enforceUserPermission('module_sales', 2);
    validateCSRFToken($_GET['csrf_token']);

    $invoice_id = intval($_GET['invoice_id']);

    // Get invoice details
    $sql = mysqli_query($mysqli,"SELECT * FROM invoices
            LEFT JOIN clients ON invoice_client_id = client_id
            LEFT JOIN contacts ON clients.client_id = contacts.contact_client_id AND contact_primary = 1
            WHERE invoice_id = $invoice_id"
    );
    $row = mysqli_fetch_array($sql);
    $invoice_number = intval($row['invoice_number']);
    $invoice_status = sanitizeInput($row['invoice_status']);
    $invoice_amount = floatval($row['invoice_amount']);
    $invoice_prefix = sanitizeInput($row['invoice_prefix']);
    $invoice_number = intval($row['invoice_number']);
    $invoice_url_key = sanitizeInput($row['invoice_url_key']);
    $invoice_currency_code = sanitizeInput($row['invoice_currency_code']);
    $client_id = intval($row['client_id']);
    $client_name = sanitizeInput($row['client_name']);
    $contact_name = sanitizeInput($row['contact_name']);
    $contact_email = sanitizeInput($row['contact_email']);
    $contact_phone = sanitizeInput(formatPhoneNumber($row['contact_phone']));
    $contact_extension = preg_replace("/[^0-9]/", '',$row['contact_extension']);
    $contact_mobile = sanitizeInput(formatPhoneNumber($row['contact_mobile']));

    // Get ITFlow company details
    $sql = mysqli_query($mysqli,"SELECT * FROM companies WHERE company_id = 1");
    $row = mysqli_fetch_array($sql);
    $company_name = sanitizeInput($row['company_name']);
    $company_country = sanitizeInput($row['company_country']);
    $company_address = sanitizeInput($row['company_address']);
    $company_city = sanitizeInput($row['company_city']);
    $company_state = sanitizeInput($row['company_state']);
    $company_zip = sanitizeInput($row['company_zip']);
    $company_phone = sanitizeInput(formatPhoneNumber($row['company_phone']));
    $company_email = sanitizeInput($row['company_email']);
    $company_website = sanitizeInput($row['company_website']);

    // Sanitize Config vars from get_settings.php
    $config_invoice_from_name = sanitizeInput($config_invoice_from_name);
    $config_invoice_from_email = sanitizeInput($config_invoice_from_email);

    // Get Client Stripe details
    $stripe_client_details = mysqli_fetch_array(mysqli_query($mysqli, "SELECT * FROM client_stripe WHERE client_id = $client_id LIMIT 1"));
    $stripe_id = sanitizeInput($stripe_client_details['stripe_id']);
    $stripe_pm = sanitizeInput($stripe_client_details['stripe_pm']);

    // Sanity checks
    if (!$config_stripe_enable || !$stripe_id || !$stripe_pm) {
        $_SESSION['alert_type'] = "error";
        $_SESSION['alert_message'] = "Stripe not enabled or no client card saved";
        header("Location: " . $_SERVER["HTTP_REFERER"]);
        exit();
    } elseif ($invoice_status !== 'Sent' && $invoice_status !== 'Viewed') {
        $_SESSION['alert_type'] = "error";
        $_SESSION['alert_message'] = "Invalid invoice state (draft/partial/paid/not billable)";
        header("Location: " . $_SERVER["HTTP_REFERER"]);
        exit();
    } elseif ($invoice_amount == 0) {
        $_SESSION['alert_type'] = "error";
        $_SESSION['alert_message'] = "Invalid invoice amount";
        header("Location: " . $_SERVER["HTTP_REFERER"]);
        exit();
    }

    // Initialize Stripe
    require_once __DIR__ . '/../../plugins/stripe-php/init.php';
    $stripe = new \Stripe\StripeClient($config_stripe_secret);

    $balance_to_pay = round($invoice_amount, 2);
    $pi_description = "ITFlow: $client_name payment of $invoice_currency_code $balance_to_pay for $invoice_prefix$invoice_number";

    // Create a payment intent
    try {
        $payment_intent = $stripe->paymentIntents->create([
            'amount' => intval($balance_to_pay * 100), // Times by 100 as Stripe expects values in cents
            'currency' => $invoice_currency_code,
            'customer' => $stripe_id,
            'payment_method' => $stripe_pm,
            'off_session' => true,
            'confirm' => true,
            'description' => $pi_description,
            'metadata' => [
                'itflow_client_id' => $client_id,
                'itflow_client_name' => $client_name,
                'itflow_invoice_number' => $invoice_prefix . $invoice_number,
                'itflow_invoice_id' => $invoice_id,
            ]
        ]);

        // Get details from PI
        $pi_id = sanitizeInput($payment_intent->id);
        $pi_date = date('Y-m-d', $payment_intent->created);
        $pi_amount_paid = floatval(($payment_intent->amount_received / 100));
        $pi_currency = strtoupper(sanitizeInput($payment_intent->currency));
        $pi_livemode = $payment_intent->livemode;

    } catch (Exception $e) {
        $error = $e->getMessage();
        error_log("Stripe payment error - encountered exception during payment intent for invoice ID $invoice_id / $invoice_prefix$invoice_number: $error");
        logApp("Stripe", "error", "Exception during PI for invoice ID $invoice_id: $error");
    }

    if ($payment_intent->status == "succeeded" && intval($balance_to_pay) == intval($pi_amount_paid)) {

        // Update Invoice Status
        mysqli_query($mysqli, "UPDATE invoices SET invoice_status = 'Paid' WHERE invoice_id = $invoice_id");

        // Add Payment to History
        mysqli_query($mysqli, "INSERT INTO payments SET payment_date = '$pi_date', payment_amount = $pi_amount_paid, payment_currency_code = '$pi_currency', payment_account_id = $config_stripe_account, payment_method = 'Stripe', payment_reference = 'Stripe - $pi_id', payment_invoice_id = $invoice_id");
        mysqli_query($mysqli, "INSERT INTO history SET history_status = 'Paid', history_description = 'Online Payment added (agent)', history_invoice_id = $invoice_id");

        // Email receipt
        if (!empty($config_smtp_host)) {
            $subject = "Payment Received - Invoice $invoice_prefix$invoice_number";
            $body = "Hello $contact_name,<br><br>We have received online payment for the amount of " . numfmt_format_currency($currency_format, $invoice_amount, $invoice_currency_code) . " for invoice <a href=\'https://$config_base_url/guest/guest_view_invoice.php?invoice_id=$invoice_id&url_key=$invoice_url_key\'>$invoice_prefix$invoice_number</a>. Please keep this email as a receipt for your records.<br><br>Amount Paid: " . numfmt_format_currency($currency_format, $invoice_amount, $invoice_currency_code) . "<br><br>Thank you for your business!<br><br><br>--<br>$company_name - Billing Department<br>$config_invoice_from_email<br>$company_phone";

            // Queue Mail
            $data = [
                [
                    'from' => $config_invoice_from_email,
                    'from_name' => $config_invoice_from_name,
                    'recipient' => $contact_email,
                    'recipient_name' => $contact_name,
                    'subject' => $subject,
                    'body' => $body,
                ]
            ];

            // Email the internal notification address too
            if (!empty($config_invoice_paid_notification_email)) {
                $subject = "Payment Received - $client_name - Invoice $invoice_prefix$invoice_number";
                $body = "Hello, <br><br>This is a notification that an invoice has been paid in ITFlow. Below is a copy of the receipt sent to the client:-<br><br>--------<br><br>Hello $contact_name,<br><br>We have received online payment for the amount of " . numfmt_format_currency($currency_format, $invoice_amount, $invoice_currency_code) . " for invoice <a href=\'https://$config_base_url/guest/guest_view_invoice.php?invoice_id=$invoice_id&url_key=$invoice_url_key\'>$invoice_prefix$invoice_number</a>. Please keep this email as a receipt for your records.<br><br>Amount Paid: " . numfmt_format_currency($currency_format, $invoice_amount, $invoice_currency_code) . "<br><br>Thank you for your business!<br><br><br>--<br>$company_name - Billing Department<br>$config_invoice_from_email<br>$company_phone";

                $data[] = [
                    'from' => $config_invoice_from_email,
                    'from_name' => $config_invoice_from_name,
                    'recipient' => $config_invoice_paid_notification_email,
                    'recipient_name' => $contact_name,
                    'subject' => $subject,
                    'body' => $body,
                ];
            }

            $mail = addToMailQueue($data);

            // Email Logging
            $email_id = mysqli_insert_id($mysqli);
            mysqli_query($mysqli,"INSERT INTO history SET history_status = 'Sent', history_description = 'Payment Receipt sent to mail queue ID: $email_id!', history_invoice_id = $invoice_id");
            logAction("Invoice", "Payment", "Payment receipt for invoice $invoice_prefix$invoice_number queued to $contact_email Email ID: $email_id", $client_id, $invoice_id);
        }

        // Log info
        $extended_log_desc = '';
        if (!$pi_livemode) {
            $extended_log_desc = '(DEV MODE)';
        }

        // Create Stripe payment gateway fee as an expense (if configured)
        if ($config_stripe_expense_vendor > 0 && $config_stripe_expense_category > 0) {
            $gateway_fee = round($invoice_amount * $config_stripe_percentage_fee + $config_stripe_flat_fee, 2);
            mysqli_query($mysqli,"INSERT INTO expenses SET expense_date = '$pi_date', expense_amount = $gateway_fee, expense_currency_code = '$invoice_currency_code', expense_account_id = $config_stripe_account, expense_vendor_id = $config_stripe_expense_vendor, expense_client_id = $client_id, expense_category_id = $config_stripe_expense_category, expense_description = 'Stripe Transaction for Invoice $invoice_prefix$invoice_number In the Amount of $balance_to_pay', expense_reference = 'Stripe - $pi_id $extended_log_desc'");
        }

        // Notify/log
        appNotify("Invoice Paid", "Invoice $invoice_prefix$invoice_number automatically paid", "invoice.php?invoice_id=$invoice_id", $client_id);
        logAction("Invoice", "Payment", "$session_name initiated Stripe payment amount of " . numfmt_format_currency($currency_format, $invoice_amount, $invoice_currency_code) . " added to invoice $invoice_prefix$invoice_number - $pi_id $extended_log_desc", $client_id, $invoice_id);
        customAction('invoice_pay', $invoice_id);

        $_SESSION['alert_message'] .= "Payment amount <strong>" . numfmt_format_currency($currency_format, $invoice_amount, $invoice_currency_code) . "</strong> added";
        header("Location: " . $_SERVER["HTTP_REFERER"]);

    } else {
        mysqli_query($mysqli, "INSERT INTO history SET history_status = 'Payment failed', history_description = 'Stripe pay failed due to payment error', history_invoice_id = $invoice_id");
        logAction("Invoice", "Payment", "Failed online payment amount of invoice $invoice_prefix$invoice_number due to Stripe payment error", $client_id, $invoice_id);
        $_SESSION['alert_type'] = "error";
        $_SESSION['alert_message'] = "Payment failed";
        header("Location: " . $_SERVER["HTTP_REFERER"]);
        exit();
    }

}

if (isset($_POST['add_bulk_payment'])) {

    $client_id = intval($_POST['client_id']);
    $date = sanitizeInput($_POST['date']);
    $bulk_payment_amount = floatval($_POST['amount']);
    $bulk_payment_amount_static = floatval($_POST['amount']);
    $total_account_balance = floatval($_POST['balance']);
    $account = intval($_POST['account']);
    $currency_code = sanitizeInput($_POST['currency_code']);
    $payment_method = sanitizeInput($_POST['payment_method']);
    $reference = sanitizeInput($_POST['reference']);
    $email_receipt = intval($_POST['email_receipt']);

    // Check if bulk_payment_amount exceeds total_account_balance
    if ($bulk_payment_amount > $total_account_balance) {
        $_SESSION['alert_type'] = "error";
        $_SESSION['alert_message'] = "Payment exceeds Client Balance.";
        header("Location: " . $_SERVER["HTTP_REFERER"]);
        exit;
    }

    // Get Invoices
    $sql_invoices = "SELECT * FROM invoices
        WHERE invoice_status != 'Draft'
        AND invoice_status != 'Paid'
        AND invoice_status != 'Cancelled'
        AND invoice_client_id = $client_id
        ORDER BY invoice_number ASC";
    $result_invoices = mysqli_query($mysqli, $sql_invoices);

    // Loop Through Each Invoice
    while ($row = mysqli_fetch_array($result_invoices)) {
        $invoice_id = intval($row['invoice_id']);
        $invoice_prefix = sanitizeInput($row['invoice_prefix']);
        $invoice_number = intval($row['invoice_number']);
        $invoice_amount = floatval($row['invoice_amount']);
        $invoice_url_key = sanitizeInput($row['invoice_url_key']);
        $invoice_balance_query = "SELECT SUM(payment_amount) AS amount_paid FROM payments WHERE payment_invoice_id = $invoice_id";
        $result_amount_paid = mysqli_query($mysqli, $invoice_balance_query);
        $row_amount_paid = mysqli_fetch_array($result_amount_paid);
        $amount_paid = floatval($row_amount_paid['amount_paid']);
        $invoice_balance = $invoice_amount - $amount_paid;

        if ($bulk_payment_amount <= 0) {
            break; // Exit the loop if no payment amount is left
        }

        if ($bulk_payment_amount >= $invoice_balance) {
            $payment_amount = $invoice_balance;
            $invoice_status = "Paid";
        } else {
            $payment_amount = $bulk_payment_amount;
            $invoice_status = "Partial";
        }

        // Subtract the payment amount from the bulk payment amount
        $bulk_payment_amount -= $payment_amount;

        // Get Invoice Remain Balance
        $remaining_invoice_balance = $invoice_balance - $payment_amount;

        // Add Payment
        $payment_query = "INSERT INTO payments (payment_date, payment_amount, payment_currency_code, payment_account_id, payment_method, payment_reference, payment_invoice_id) VALUES ('{$date}', {$payment_amount}, '{$currency_code}', {$account}, '{$payment_method}', '{$reference}', {$invoice_id})";
        mysqli_query($mysqli, $payment_query);
        $payment_id = mysqli_insert_id($mysqli);

        // Update Invoice Status
        $update_invoice_query = "UPDATE invoices SET invoice_status = '{$invoice_status}' WHERE invoice_id = {$invoice_id}";
        mysqli_query($mysqli, $update_invoice_query);

        // Add Payment to History
        $history_description = "Payment added";
        $add_history_query = "INSERT INTO history (history_status, history_description, history_invoice_id) VALUES ('{$invoice_status}', '{$history_description}', {$invoice_id})";
        mysqli_query($mysqli, $add_history_query);

        // Add to Email Body Invoice Portion
        $email_body_invoices .= "<br>Invoice <a href=\'https://$config_base_url/guest/guest_view_invoice.php?invoice_id=$invoice_id&url_key=$invoice_url_key\'>$invoice_prefix$invoice_number</a> - Outstanding Amount: " . numfmt_format_currency($currency_format, $invoice_balance, $currency_code) . " - Payment Applied: " . numfmt_format_currency($currency_format, $payment_amount, $currency_code) . " - New Balance: " . numfmt_format_currency($currency_format, $remaining_invoice_balance, $currency_code);

        customAction('invoice_pay', $invoice_id);

    } // End Invoice Loop

    // Send Email
    if ($email_receipt == 1) {

        // Get Client / Contact Info
        $sql_client = mysqli_query($mysqli,"SELECT * FROM clients
            LEFT JOIN contacts ON clients.client_id = contacts.contact_client_id 
            AND contact_primary = 1
            WHERE client_id = $client_id"
        );

        $row = mysqli_fetch_array($sql_client);
        $client_name = sanitizeInput($row['client_name']);
        $contact_name = sanitizeInput($row['contact_name']);
        $contact_email = sanitizeInput($row['contact_email']);

        $sql_company = mysqli_query($mysqli,"SELECT company_name, company_phone FROM companies WHERE company_id = 1");
        $row = mysqli_fetch_array($sql_company);

        $company_name = sanitizeInput($row['company_name']);
        $company_phone = sanitizeInput(formatPhoneNumber($row['company_phone']));

        // Sanitize Config vars from get_settings.php
        $config_invoice_from_name = sanitizeInput($config_invoice_from_name);
        $config_invoice_from_email = sanitizeInput($config_invoice_from_email);

        $subject = "Payment Received - Multiple Invoices";
        $body = "Hello $contact_name,<br><br>Thank you for your payment of " . numfmt_format_currency($currency_format, $bulk_payment_amount_static, $currency_code) . " We\'ve applied your payment to the following invoices, updating their balances accordingly:<br><br>$email_body_invoices<br><br><br>We appreciate your continued business!<br><br>Sincerely,<br>$company_name - Billing<br>$config_invoice_from_email<br>$company_phone";

        // Queue Mail
        mysqli_query($mysqli, "INSERT INTO email_queue SET email_recipient = '$contact_email', email_recipient_name = '$contact_name', email_from = '$config_invoice_from_email', email_from_name = '$config_invoice_from_name', email_subject = '$subject', email_content = '$body'");

        // Get Email ID for reference
        $email_id = mysqli_insert_id($mysqli);

        // Email Logging
        logAction("Payment", "Email", "Bulk Payment receipt for multiple Invoices queued to $contact_email Email ID: $email_id", $client_id);

        $_SESSION['alert_message'] .= "Email receipt queued and ";

    } // End Email

    // Logging
    logAction("Invoice", "Payment", "Bulk Payment amount of " . numfmt_format_currency($currency_format, $bulk_payment_amount_static, $currency_code) . " applied to multiple invoices", $client_id);

    $_SESSION['alert_message'] .= "Bulk Payment added";

    // Redirect Back
    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if (isset($_GET['delete_payment'])) {
    $payment_id = intval($_GET['delete_payment']);

    $sql = mysqli_query($mysqli,"SELECT * FROM payments WHERE payment_id = $payment_id");
    $row = mysqli_fetch_array($sql);
    $invoice_id = intval($row['payment_invoice_id']);
    $deleted_payment_amount = floatval($row['payment_amount']);

    //Add up all the payments for the invoice and get the total amount paid to the invoice
    $sql_total_payments_amount = mysqli_query($mysqli,"SELECT SUM(payment_amount) AS total_payments_amount FROM payments WHERE payment_invoice_id = $invoice_id");
    $row = mysqli_fetch_array($sql_total_payments_amount);
    $total_payments_amount = floatval($row['total_payments_amount']);

    // Get the invoice total and details
    $sql = mysqli_query($mysqli,"SELECT * FROM invoices WHERE invoice_id = $invoice_id");
    $row = mysqli_fetch_array($sql);
    $invoice_prefix = sanitizeInput($row['invoice_prefix']);
    $invoice_number = intval($row['invoice_number']);
    $client_id = intval($row['invoice_client_id']);
    $invoice_amount = floatval($row['invoice_amount']);

    //Calculate the Invoice balance
    $invoice_balance = $invoice_amount - $total_payments_amount + $deleted_payment_amount;

    //Determine if invoice has been paid
    if ($invoice_balance == 0) {
        $invoice_status = "Paid";
    } else {
        $invoice_status = "Partial";
    }

    //Update Invoice Status
    mysqli_query($mysqli,"UPDATE invoices SET invoice_status = '$invoice_status' WHERE invoice_id = $invoice_id");

    //Add Payment to History
    mysqli_query($mysqli,"INSERT INTO history SET history_status = '$invoice_status', history_description = 'Payment deleted', history_invoice_id = $invoice_id");

    mysqli_query($mysqli,"DELETE FROM payments WHERE payment_id = $payment_id");

    // Logging
    logAction("Invoice", "Edit", "$session_name deleted Payment on Invoice $invoice_prefix$invoice_number", $client_id, $invoice_id);

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "Payment deleted";
    if ($config_stripe_enable) {
        $_SESSION['alert_message'] = "Payment deleted - Stripe payments must be manually refunded in Stripe";
    }

    header("Location: " . $_SERVER["HTTP_REFERER"]);

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
    $company_phone = sanitizeInput(formatPhoneNumber($row['company_phone']));
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

    $_SESSION['alert_message'] = "Invoice sent to mail queue! <a class='text-bold text-light' href='admin_mail_queue.php'>Check Admin > Mail queue</a>";
    mysqli_query($mysqli,"INSERT INTO history SET history_status = 'Sent', history_description = 'Invoice sent to the mail queue ID: $email_id', history_invoice_id = $invoice_id");

    // Don't change the status to sent if the status is anything but draft
    if ($invoice_status == 'Draft') {
        mysqli_query($mysqli,"UPDATE invoices SET invoice_status = 'Sent' WHERE invoice_id = $invoice_id");
    }

    // Logging
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

        // Logging
        logAction("Invoice", "Email", "$session_name Emailed $billing_contact_email Invoice $invoice_prefix$invoice_number Email queued Email ID: $email_id", $client_id, $invoice_id);

    }

    addToMailQueue($data);

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['add_recurring_payment'])) {

    $recurring_id = intval($_POST['recurring_id']);
    $account = intval($_POST['account']);
    $currency_code = sanitizeInput($_POST['currency_code']);
    $payment_method = sanitizeInput($_POST['payment_method']);

    // Get Recurring Info for logging and alerting
    $sql = mysqli_query($mysqli, "SELECT * FROM recurring WHERE recurring_id = $recurring_id");
    $row = mysqli_fetch_array($sql);
    $recurring_prefix = sanitizeInput($row['recurring_prefix']);
    $recurring_number = intval($row['recurring_number']);
    $recurring_amount = floatval($row['recurring_amount']);
    $client_id = intval($row['recurring_client_id']);

    mysqli_query($mysqli,"INSERT INTO recurring_payments SET recurring_payment_currency_code = '$currency_code', recurring_payment_account_id = $account, recurring_payment_method = '$payment_method', recurring_payment_recurring_invoice_id = $recurring_id");

    // Get Payment ID for reference
    $recurring_payment_id = mysqli_insert_id($mysqli);

    // Logging
    logAction("Recurring Invoice", "Auto Payment", "$session_name created Auto Pay for Recurring Invoice $recurring_prefix$recurring_number in the amount of " . numfmt_format_currency($currency_format, $recurring_amount, $currency_code), $client_id, $recurring_id);


    $_SESSION['alert_message'] = "Automatic Payment created for <strong>$recurring_prefix$recurring_number</strong>";

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if (isset($_GET['delete_recurring_payment'])) {
    $recurring_payment_id = intval($_GET['delete_recurring_payment']);

    $sql = mysqli_query($mysqli,"SELECT * FROM recurring_payments WHERE recurring_payment_id = $recurring_payment_id");
    $row = mysqli_fetch_array($sql);
    $recurring_invoice_id = intval($row['recurring_payment_recurring_invoice_id']);

    // Get the invoice total and details
    $sql = mysqli_query($mysqli,"SELECT * FROM recurring WHERE recurring_id = $recurring_invoice_id");
    $row = mysqli_fetch_array($sql);
    $recurring_prefix = sanitizeInput($row['recurring_prefix']);
    $recurring_number = intval($row['recurring_number']);
    $client_id = intval($row['recurring_client_id']);

    mysqli_query($mysqli,"DELETE FROM recurring_payments WHERE recurring_payment_id = $recurring_payment_id");

    // Logging
    logAction("Recurring Invoice", "Auto Payment", "$session_name removed auto Pay from Recurring Invoice $recurring_prefix$recurring_number", $client_id, $recurring_invoice_id);

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "Auto Payment Removed for Recurring Invoice <strong>$recurring_prefix$recurring_number</strong>";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_GET['force_recurring'])) {
    $recurring_id = intval($_GET['force_recurring']);

    $sql_recurring = mysqli_query($mysqli,"SELECT * FROM recurring, clients WHERE client_id = recurring_client_id AND recurring_id = $recurring_id");

    $row = mysqli_fetch_array($sql_recurring);
    $recurring_id = intval($row['recurring_id']);
    $recurring_scope = sanitizeInput($row['recurring_scope']);
    $recurring_frequency = sanitizeInput($row['recurring_frequency']);
    $recurring_status = sanitizeInput($row['recurring_status']);
    $recurring_last_sent = sanitizeInput($row['recurring_last_sent']);
    $recurring_next_date = sanitizeInput($row['recurring_next_date']);
    $recurring_discount_amount = floatval($row['recurring_discount_amount']);
    $recurring_amount = floatval($row['recurring_amount']);
    $recurring_currency_code = sanitizeInput($row['recurring_currency_code']);
    $recurring_note = sanitizeInput($row['recurring_note']);
    $category_id = intval($row['recurring_category_id']);
    $client_id = intval($row['recurring_client_id']);
    $client_net_terms = intval($row['client_net_terms']);

    //Get the last Invoice Number and add 1 for the new invoice number
    $new_invoice_number = $config_invoice_next_number;
    $new_config_invoice_next_number = $config_invoice_next_number + 1;
    mysqli_query($mysqli,"UPDATE settings SET config_invoice_next_number = $new_config_invoice_next_number WHERE company_id = 1");

    //Generate a unique URL key for clients to access
    $url_key = randomString(156);

    mysqli_query($mysqli,"INSERT INTO invoices SET invoice_prefix = '$config_invoice_prefix', invoice_number = $new_invoice_number, invoice_scope = '$recurring_scope', invoice_date = CURDATE(), invoice_due = DATE_ADD(CURDATE(), INTERVAL $client_net_terms day), invoice_discount_amount = $recurring_discount_amount, invoice_amount = $recurring_amount, invoice_currency_code = '$recurring_currency_code', invoice_note = '$recurring_note', invoice_category_id = $category_id, invoice_status = 'Sent', invoice_url_key = '$url_key', invoice_client_id = $client_id");

    $new_invoice_id = mysqli_insert_id($mysqli);

    //Copy Items from original invoice to new invoice
    $sql_invoice_items = mysqli_query($mysqli,"SELECT * FROM invoice_items WHERE item_recurring_id = $recurring_id ORDER BY item_id ASC");

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
    $sql_recurring_total = mysqli_query($mysqli,"SELECT SUM(item_total) AS recurring_total FROM invoice_items WHERE item_recurring_id = $recurring_id");
    $row = mysqli_fetch_array($sql_recurring_total);
    $new_recurring_amount = floatval($row['recurring_total']) - $recurring_discount_amount;

    mysqli_query($mysqli,"UPDATE recurring SET recurring_amount = $new_recurring_amount, recurring_last_sent = CURDATE(), recurring_next_date = DATE_ADD(CURDATE(), INTERVAL 1 $recurring_frequency) WHERE recurring_id = $recurring_id");

    //Also update the newly created invoice with the new amounts
    mysqli_query($mysqli,"UPDATE invoices SET invoice_amount = $new_recurring_amount WHERE invoice_id = $new_invoice_id");

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
        $contact_phone = sanitizeInput(formatPhoneNumber($row['contact_phone']));
        $contact_extension = intval($row['contact_extension']);
        $contact_mobile = sanitizeInput(formatPhoneNumber($row['contact_mobile']));

        $sql = mysqli_query($mysqli,"SELECT * FROM companies WHERE company_id = 1");
        $row = mysqli_fetch_array($sql);
        $company_name = sanitizeInput($row['company_name']);
        $company_phone = sanitizeInput(formatPhoneNumber($row['company_phone']));
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

    // Logging
    logAction("Invoice", "Create", "$session_name forced recurring invoice into an invoice", $client_id, $new_invoice_id);

    customAction('invoice_create', $new_invoice_id);

    $_SESSION['alert_message'] = "Recurring Invoice Forced";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['export_invoices_csv'])) {
    if (isset($_POST['client_id'])) {
        $client_id = intval($_POST['client_id']);
        $client_query = "AND invoice_client_id = $client_id";
    } else {
        $client_query = '';
    }

    $date_from = sanitizeInput($_POST['date_from']);
    $date_to = sanitizeInput($_POST['date_to']);
    if (!empty($date_from) && !empty($date_to)) {
        $date_query = "DATE(invoice_date) BETWEEN '$date_from' AND '$date_to'";
        $file_name_date = "$date_from-to-$date_to";
    }else{
        $date_query = "";
        $file_name_date = date('Y-m-d');
    }

    $sql = mysqli_query($mysqli,"SELECT * FROM invoices LEFT JOIN clients ON invoice_client_id = client_id WHERE $date_query $client_query ORDER BY invoice_number ASC");

    $row = mysqli_fetch_array($sql);
    $client_name = $row['client_name'];

    $num_rows = mysqli_num_rows($sql);

    if ($num_rows > 0) {
        $delimiter = ",";
        $filename = "$session_company_name-Invoices-$file_name_date.csv";

        //create a file pointer
        $f = fopen('php://memory', 'w');

        //set column headers
        $fields = array('Invoice Number', 'Scope', 'Amount', 'Issued Date', 'Due Date', 'Status');
        fputcsv($f, $fields, $delimiter);

        //output each row of the data, format line as csv and write to file pointer
        while($row = $sql->fetch_assoc()) {
            $lineData = array($row['invoice_prefix'] . $row['invoice_number'], $row['invoice_scope'], $row['invoice_amount'], $row['invoice_date'], $row['invoice_due'], $row['invoice_status'], $row['client_name']);
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

    // Logging
    logAction("Invoice", "Export", "$session_name exported $num_rows invoices to CSV file");

    exit;

}

if (isset($_POST['export_client_recurring_csv'])) {
    $client_id = intval($_POST['client_id']);

    //get records from database
    $sql = mysqli_query($mysqli,"SELECT client_name FROM clients WHERE client_id = $client_id");
    $row = mysqli_fetch_array($sql);

    $client_name = $row['client_name'];

    $sql = mysqli_query($mysqli,"SELECT * FROM recurring WHERE recurring_client_id = $client_id ORDER BY recurring_number ASC");
    
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

    // Logging
    logAction("Recurring Invoice", "Export", "$session_name exported $num_rows recurring invoices to CSV file");

    exit;

}

if (isset($_POST['export_client_payments_csv'])) {
    $client_id = intval($_POST['client_id']);

    //get records from database
    $sql = mysqli_query($mysqli,"SELECT client_name FROM clients WHERE client_id = $client_id");
    $row = mysqli_fetch_array($sql);

    $client_name = $row['client_name'];

    $sql = mysqli_query($mysqli,"SELECT * FROM payments, invoices WHERE invoice_client_id = $client_id AND payment_invoice_id = invoice_id ORDER BY payment_date ASC");
    
    $num_rows = mysqli_num_rows($sql);

    if ($num_rows > 0) {
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

    // Logging
    logAction("Payments", "Export", "$session_name exported $num_rows payments to CSV file");

    exit;

}



if (isset($_POST['update_recurring_item_order'])) {

    $item_id = intval($_POST['item_id']);
    $item_recurring_id = intval($_POST['item_recurring_id']);

    $sql = mysqli_query($mysqli,"SELECT * FROM invoice_items WHERE item_id = $item_id");
    $row = mysqli_fetch_array($sql);
    $current_order = intval($row['item_order']);
    $update_direction = sanitizeInput($_POST['update_recurring_item_order']);

    switch ($update_direction)
    {
        case 'up':
            $new_order = $current_order - 1;
            break;
        case 'down':
            $new_order = $current_order + 1;
            break;
    }

    //Find item_id of current item in $new_order
    $other_sql = mysqli_query($mysqli,"SELECT * FROM invoice_items WHERE item_recurring_id = $item_recurring_id AND item_order = $new_order");
    $other_row = mysqli_fetch_array($other_sql);
    $other_item_id = intval($other_row['item_id']);
    $other_row_str = strval($other_row['item_name']);

    mysqli_query($mysqli,"UPDATE invoice_items SET item_order = $new_order WHERE item_id = $item_id");

    mysqli_query($mysqli,"UPDATE invoice_items SET item_order = $current_order WHERE item_id = $other_item_id");

    $_SESSION['alert_message'] = "recurring Item Order Updated";

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if (isset($_POST['update_invoice_item_order'])) {

    $item_id = intval($_POST['item_id']);
    $item_invoice_id = intval($_POST['item_invoice_id']);

    $sql = mysqli_query($mysqli,"SELECT * FROM invoice_items WHERE item_id = $item_id");
    $row = mysqli_fetch_array($sql);
    $current_order = intval($row['item_order']);
    $update_direction = sanitizeInput($_POST['update_invoice_item_order']);

    switch ($update_direction)
    {
        case 'up':
            $new_order = $current_order - 1;
            break;
        case 'down':
            $new_order = $current_order + 1;
            break;
    }

    //Find item_id of current item in $new_order
    $other_sql = mysqli_query($mysqli,"SELECT * FROM invoice_items WHERE item_invoice_id = $item_invoice_id AND item_order = $new_order");
    $other_row = mysqli_fetch_array($other_sql);
    $other_item_id = intval($other_row['item_id']);
    $other_row_str = strval($other_row['item_name']);

    mysqli_query($mysqli,"UPDATE invoice_items SET item_order = $new_order WHERE item_id = $item_id");

    mysqli_query($mysqli,"UPDATE invoice_items SET item_order = $current_order WHERE item_id = $other_item_id");

    $_SESSION['alert_message'] = "Invoice Item Order Updated";

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if (isset($_GET['recurring_invoice_email_notify'])) {
    $recurring_invoice_email_notify = intval($_GET['recurring_invoice_email_notify']);
    $recurring_id = intval($_GET['recurring_id']);

    $sql = mysqli_query($mysqli,"SELECT * FROM recurring WHERE recurring_id = $recurring_id");
    $row = mysqli_fetch_array($sql);
    $recurring_invoice_prefix = sanitizeInput($row['recurring_prefix']);
    $recurring_invoice_number = intval($row['recurring_number']);
    $client_id = intval($row['recurring_client_id']);

    mysqli_query($mysqli,"UPDATE recurring SET recurring_invoice_email_notify = $recurring_invoice_email_notify WHERE recurring_id = $recurring_id");

    // Wording
    if ($recurring_invoice_email_notify) {
        $notify_wording = "On";
    } else { 
        $notify_wording = "Off";
    }

    // Logging
    logAction("Recurring Invoice", "Edit", "$session_name turned $notify_wording Email Notifications for Recurring Invoice $recurring_invoice_prefix$recurring_invoice_number", $client_id, $recurring_id);

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "Email Notifications <strong>$notify_wording</strong>";

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if (isset($_POST['link_invoice_to_ticket'])) {
    $invoice_id = intval($_POST['invoice_id']);
    $ticket_id = intval($_POST['ticket_id']);

    mysqli_query($mysqli,"UPDATE invoices SET invoice_ticket_id = $ticket_id WHERE invoice_id = $invoice_id");

    $_SESSION['alert_message'] = "Invoice linked to ticket";

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if (isset($_POST['add_ticket_to_invoice'])) {
    $invoice_id = intval($_POST['invoice_id']);
    $ticket_id = intval($_POST['ticket_id']);

    mysqli_query($mysqli,"UPDATE tickets SET ticket_invoice_id = $invoice_id WHERE ticket_id = $ticket_id");

    $_SESSION['alert_message'] = "Ticket linked to invoice";

    header("Location: post.php?add_ticket_to_invoice=$invoice_id");
}
