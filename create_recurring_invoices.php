<?php include("config.php"); ?>

<?php

//Send Recurring Invoices that match todays date and are active

$sql_recurring_invoices = mysqli_query($mysqli,"SELECT * FROM recurring_invoices, clients, invoices WHERE clients.client_id = invoices.client_id AND invoices.invoice_id = recurring_invoices.invoice_id AND recurring_invoices.recurring_invoice_next_date = CURDATE() AND recurring_invoices.recurring_invoice_status = 1");

while($row = mysqli_fetch_array($sql_recurring_invoices)){
  $recurring_invoice_id = $row['recurring_invoice_id'];
  $recurring_invoice_frequency = $row['recurring_invoice_frequency'];
  $recurring_invoice_status = $row['recurring_invoice_status'];
  $recurring_invoice_start_date = $row['recurring_invoice_start_date'];
  $recurring_invoice_last_sent = $row['recurring_invoice_last_sent'];
  $recurring_invoice_next_date = $row['recurring_invoice_next_date'];
  $invoice_id = $row['invoice_id'];
  $invoice_status = $row['invoice_status'];
  $invoice_amount = $row['invoice_amount'];
  $invoice_note = $row['invoice_note'];
  $invoice_category_id = $row['category_id'];
  $client_id = $row['client_id'];
  $client_name = $row['client_name'];
  $client_net_terms = $row['client_net_terms'];

  //Get the last Invoice Number and add 1 for the new invoice number
  $sql_invoice_number = mysqli_query($mysqli,"SELECT invoice_number FROM invoices ORDER BY invoice_number DESC LIMIT 1");
  $row = mysqli_fetch_array($sql_invoice_number);
  $new_invoice_number = $row['invoice_number'] + 1;

  mysqli_query($mysqli,"INSERT INTO invoices SET invoice_number = $new_invoice_number, invoice_date = CURDATE(), invoice_due = DATE_ADD(CURDATE(), INTERVAL $client_net_terms day) , invoice_amount = '$invoice_amount', invoice_note = '$invoice_note', category_id = $invoice_category_id, invoice_status = 'Sent', client_id = $client_id");

  $new_invoice_id = mysqli_insert_id($mysqli);

  
  //Copy Items from original invoice to new invoice
  $sql_invoice_items = mysqli_query($mysqli,"SELECT * FROM invoice_items WHERE invoice_id = $invoice_id ORDER BY invoice_item_id ASC");

  while($row = mysqli_fetch_array($sql_invoice_items)){
    $invoice_item_id = $row['invoice_item_id'];
    $invoice_item_name = $row['invoice_item_name'];
    $invoice_item_description = $row['invoice_item_description'];
    $invoice_item_quantity = $row['invoice_item_quantity'];
    $invoice_item_price = $row['invoice_item_price'];
    $invoice_item_subtotal = $row['invoice_item_price'];
    $invoice_item_tax = $row['invoice_item_tax'];
    $invoice_item_total = $row['invoice_item_total'];

    mysqli_query($mysqli,"INSERT INTO invoice_items SET invoice_item_name = '$invoice_item_name', invoice_item_description = '$invoice_item_description', invoice_item_quantity = $invoice_item_quantity, invoice_item_price = '$invoice_item_price', invoice_item_subtotal = '$invoice_item_subtotal', invoice_item_tax = '$invoice_item_tax', invoice_item_total = '$invoice_item_total', invoice_id = $new_invoice_id");
  }

  mysqli_query($mysqli,"INSERT INTO invoice_history SET invoice_history_date = CURDATE(), invoice_history_status = 'Draft', invoice_history_description = 'INVOICE added!', invoice_id = $new_invoice_id");

  //update the recurring invoice with the new dates
  mysqli_query($mysqli,"UPDATE recurring_invoices SET recurring_invoice_last_sent = CURDATE(), recurring_invoice_next_date = DATE_ADD(CURDATE(), INTERVAL 1 $recurring_invoice_frequency) , invoice_id = $new_invoice_id WHERE recurring_invoice_id = $recurring_invoice_id");
} 

?>
