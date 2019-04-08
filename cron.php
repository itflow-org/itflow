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

//Send Past Due Invoice Reminders

$sql = mysqli_query($mysqli,"SELECT * FROM accounts ORDER BY account_id DESC"); 

?>
