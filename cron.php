<?php include("config.php"); ?>

<?php

//Get Alerts

//Get Domains Expiring
$sql = mysqli_query($mysqli,"SELECT * FROM client_domains, clients 
  WHERE client_domains.client_id = clients.client_id 
  AND client_domain_expire > CURDATE() 
  ORDER BY client_domain_id DESC"
);

while($row = mysqli_fetch_array($sql)){
  $client_domain_id = $row['client_domain_id'];
  $client_domain_name = $row['client_domain_name'];
  $client_domain_expire = $row['client_domain_expire'];
  $client_id = $row['client_id'];
  $client_name = $row['client_name'];

  mysqli_query($mysqli,"INSERT INTO alerts SET alert_type = 'Domain', alert_message = 'Domain $client_domain_name Expiring on $client_domain_expire', alert_date = CURDATE()");

}

//Get Low Account Balances
$sql = mysqli_query($mysqli,"SELECT * FROM accounts ORDER BY account_id DESC");

while($row = mysqli_fetch_array($sql)){
  $account_id = $row['account_id'];
  $account_name = $row['account_name'];
  $opening_balance = $row['opening_balance'];

  $sql_accounts = mysqli_query($mysqli,"SELECT * FROM accounts WHERE account_id = $account_id");
  $row = mysqli_fetch_array($sql_accounts);
  $opening_balance = $row['opening_balance'];

  $sql_payments = mysqli_query($mysqli,"SELECT SUM(payment_amount) AS total_payments FROM payments WHERE account_id = $account_id");
  $row = mysqli_fetch_array($sql_payments);
  $total_payments = $row['total_payments'];
  
  $sql_expenses = mysqli_query($mysqli,"SELECT SUM(expense_amount) AS total_expenses FROM expenses WHERE account_id = $account_id");
  $row = mysqli_fetch_array($sql_expenses);
  $total_expenses = $row['total_expenses'];

  $balance = $opening_balance + $total_payments - $total_expenses;

  mysqli_query($mysqli,"INSERT INTO alerts SET alert_type = 'Low Balance', alert_message = 'Low Balance $$balance for $account_name', alert_date = CURDATE()");

}

//Get Overdue Invoices

$sql = mysqli_query($mysqli,"SELECT * FROM invoices WHERE invoice_date_due > CURDATE() AND invoice_status NOT LIKE 'Paid' OR invoice_status NOT LIKE 'Draft' ORDER BY invoice_id DESC");

$invoice_id = $row['invoice_id'];
$invoice_amount = $row['invoice_amount'];
$invoice_date = $row['invoice_date'];
$invoice_date_due = $row['invoice_date_due'];

//Send Recurring Invoices

$sql = mysqli_query($mysqli,"SELECT * FROM recurring_invoices, clients, invoices WHERE clients.client_id = invoices.client_id AND invoices.invoice_id = recurring_invoices.invoice_id AND recurring_invoices.recurring_invoice_next_date = CURDATE()");

while($row = mysqli_fetch_array($sql)){
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

  //Get the last Invoice Number and add 1 for the new invoice number
  $sql = mysqli_query($mysqli,"SELECT invoice_number FROM invoices ORDER BY invoice_id DESC LIMIT 1");
  $row = mysqli_fetch_array($sql);
  $invoice_number = $row['invoice_number'] + 1;

  mysqli_query($mysqli,"INSERT INTO invoices SET invoice_number = $invoice_number, invoice_date = CURDATE(), invoice_due = CURDATE(), invoice_amount = '$invoice_amount', invoice_note = '$invoice_note', category_id = $invoice_category_id, invoice_status = 'Sent', client_id = $client_id");

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

  mysqli_query($mysqli,"UPDATE recurring_invoices SET recurring_invoice_last_sent = CURDATE(), recurring_invoice_next_date = DATE_ADD(CURDATE(), INTERVAL 1 $recurring_invoice_frequency) , recurring_invoice_id = $recurring_invoice_id");


//Send Past Due Invoice Reminders

$sql = mysqli_query($mysqli,"SELECT * FROM accounts ORDER BY account_id DESC"); 

?>
