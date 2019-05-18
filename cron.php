<?php include("config.php"); ?>

<?php

//GET ALERTS

//DOMAINS EXPIRING 

$domainAlertArray = [1, 14, 30, 90];

foreach ($domainAlertArray as $day)  {

  //Get Domains Expiring within 1 days
  $sql = mysqli_query($mysqli,"SELECT * FROM domains, clients 
    WHERE domains.client_id = clients.client_id 
    AND domain_expire = CURDATE() + INTERVAL $day DAY
    ORDER BY domain_id DESC"
  );

  while($row = mysqli_fetch_array($sql)){
    $domain_id = $row['domain_id'];
    $domain_name = $row['domain_name'];
    $domain_expire = $row['domain_expire'];
    $client_id = $row['client_id'];
    $client_name = $row['client_name'];

    mysqli_query($mysqli,"INSERT INTO alerts SET alert_type = 'Domain', alert_message = 'Domain $domain_name will expire $day Days on $domain_expire', alert_date = CURDATE()");

  }

}

//PAST DUE INVOICES

$invoiceAlertArray = [1, 14, 30, 90];

foreach ($invoiceAlertArray as $day)  {

  $sql = mysqli_query($mysqli,"SELECT * FROM invoices, clients
      WHERE invoices.client_id = clients.client_id
      AND invoices.invoice_number > 0
      AND invoices.invoice_status NOT LIKE 'Draft'
      AND invoices.invoice_status NOT LIKE 'Paid'
      AND invoices.invoice_status NOT LIKE 'Cancelled'
      AND invoices.invoice_due = CURDATE() + INTERVAL $day DAY
      ORDER BY invoices.invoice_number DESC"
  );
        
  while($row = mysqli_fetch_array($sql)){
    $invoice_id = $row['invoice_id'];
    $invoice_number = $row['invoice_number'];
    $invoice_status = $row['invoice_status'];
    $invoice_date = $row['invoice_date'];
    $invoice_due = $row['invoice_due'];
    $invoice_amount = $row['invoice_amount'];
    $client_id = $row['client_id'];
    $client_name = $row['client_name'];

    mysqli_query($mysqli,"INSERT INTO alerts SET alert_type = 'Invoice', alert_message = 'Invoice INV-$invoice_number for $client_name in the amount of $invoice_amount is overdue by $day days', alert_date = CURDATE()");
  }

}

//LOW BALANCE ALERTS
$sql = mysqli_query($mysqli,"SELECT * FROM accounts ORDER BY account_id DESC");

while($row = mysqli_fetch_array($sql)){
  $account_id = $row['account_id'];
  $account_name = $row['account_name'];
  $opening_balance = $row['opening_balance'];

  $sql_payments = mysqli_query($mysqli,"SELECT SUM(payment_amount) AS total_payments FROM payments WHERE account_id = $account_id");
  $row = mysqli_fetch_array($sql_payments);
  $total_payments = $row['total_payments'];
  
  $sql_expenses = mysqli_query($mysqli,"SELECT SUM(expense_amount) AS total_expenses FROM expenses WHERE account_id = $account_id");
  $row = mysqli_fetch_array($sql_expenses);
  $total_expenses = $row['total_expenses'];

  $balance = $opening_balance + $total_payments - $total_expenses;

  if($balance < $config_account_balance_threshold){

    mysqli_query($mysqli,"INSERT INTO alerts SET alert_type = 'Account Low Balance', alert_message = 'Threshold of $config_account_balance_threshold triggered low balance of $balance on account $account_name', alert_date = CURDATE()");
  }

}

//Send Recurring Invoices that match todays date and are active

$sql_recurring = mysqli_query($mysqli,"SELECT * FROM recurring, clients WHERE clients.client_id = recurring.client_id AND recurring.recurring_next_date = CURDATE() AND recurring.recurring_status = 1");

while($row = mysqli_fetch_array($sql_recurring)){
  $recurring_id = $row['recurring_id'];
  $recurring_frequency = $row['recurring_frequency'];
  $recurring_status = $row['recurring_status'];
  $recurring_start_date = $row['recurring_start_date'];
  $recurring_last_sent = $row['recurring_last_sent'];
  $recurring_next_date = $row['recurring_next_date'];
  $recurring_amount = $row['recurring_amount'];
  $recurring_note = $row['recurring_note'];
  $category_id = $row['category_id'];
  $client_id = $row['client_id'];
  $client_name = $row['client_name'];
  $client_net_terms = $row['client_net_terms'];

  //Get the last Invoice Number and add 1 for the new invoice number
  $sql_invoice_number = mysqli_query($mysqli,"SELECT invoice_number FROM invoices ORDER BY invoice_number DESC LIMIT 1");
  $row = mysqli_fetch_array($sql_invoice_number);
  $new_invoice_number = $row['invoice_number'] + 1;

  mysqli_query($mysqli,"INSERT INTO invoices SET invoice_number = $new_invoice_number, invoice_date = CURDATE(), invoice_due = DATE_ADD(CURDATE(), INTERVAL $client_net_terms day) , invoice_amount = '$recurring_amount', invoice_note = '$recurring_note', category_id = $category_id, invoice_status = 'Sent', client_id = $client_id");

  $new_invoice_id = mysqli_insert_id($mysqli);
  
  //Copy Items from original invoice to new invoice
  $sql_invoice_items = mysqli_query($mysqli,"SELECT * FROM invoice_items WHERE recurring = $recurring_id ORDER BY item_id ASC");

  while($row = mysqli_fetch_array($sql_invoice_items)){
    $item_id = $row['item_id'];
    $item_name = $row['item_name'];
    $item_description = $row['item_description'];
    $item_quantity = $row['item_quantity'];
    $item_price = $row['item_price'];
    $item_subtotal = $row['item_price'];
    $item_tax = $row['item_tax'];
    $item_total = $row['item_total'];

    mysqli_query($mysqli,"INSERT INTO invoice_items SET item_name = '$item_name', item_description = '$item_description', item_quantity = $item_quantity, item_price = '$item_price', item_subtotal = '$item_subtotal', item_tax = '$item_tax', item_total = '$item_total', invoice_id = $new_invoice_id");
  }

  mysqli_query($mysqli,"INSERT INTO history SET history_date = CURDATE(), history_status = 'Sent', history_description = 'INVOICE Generated from Recurring!', invoice_id = $new_invoice_id");

  //update the recurring invoice with the new dates
  mysqli_query($mysqli,"UPDATE recurring SET recurring_last_sent = CURDATE(), recurring_next_date = DATE_ADD(CURDATE(), INTERVAL 1 $recurring_frequency) , invoice_id = $new_invoice_id WHERE recurring_id = $recurring_id");
}

?>