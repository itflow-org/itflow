<?php include("config.php"); ?>
<?php include("functions.php"); ?>
<?php

require("vendor/PHPMailer-6.0.7/src/PHPMailer.php");
require("vendor/PHPMailer-6.0.7/src/SMTP.php");

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

?>

<?php

//GET ALERTS

//DOMAINS EXPIRING 

$domainAlertArray = [1, 14, 30, 90];

foreach ($domainAlertArray as $day)  {

  //Get Domains Expiring
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

    mysqli_query($mysqli,"INSERT INTO alerts SET alert_type = 'Domain', alert_message = 'Domain $domain_name will expire in $day Days on $domain_expire', alert_date = CURDATE()");

  }

}

//PAST DUE INVOICES

$invoiceAlertArray = [1, 14, 30, 90];

foreach ($invoiceAlertArray as $day)  {

  $sql = mysqli_query($mysqli,"SELECT * FROM invoices, clients
      WHERE invoices.client_id = clients.client_id
      AND invoices.invoice_status NOT LIKE 'Draft'
      AND invoices.invoice_status NOT LIKE 'Paid'
      AND invoices.invoice_status NOT LIKE 'Cancelled'
      AND DATE_ADD(invoices.invoice_due, INTERVAL $day DAY) = CURDATE()
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

  //Generate a unique URL key for clients to access
  $url_key = keygen();

  mysqli_query($mysqli,"INSERT INTO invoices SET invoice_number = $new_invoice_number, invoice_date = CURDATE(), invoice_due = DATE_ADD(CURDATE(), INTERVAL $client_net_terms day), invoice_amount = '$recurring_amount', invoice_note = '$recurring_note', category_id = $category_id, invoice_status = 'Sent', invoice_url_key = '$url_key', client_id = $client_id");

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

  mysqli_query($mysqli,"INSERT INTO history SET history_date = CURDATE(), history_status = 'Sent', history_description = 'Invoice Generated from Recurring!', invoice_id = $new_invoice_id");

  //update the recurring invoice with the new dates
  mysqli_query($mysqli,"UPDATE recurring SET recurring_last_sent = CURDATE(), recurring_next_date = DATE_ADD(CURDATE(), INTERVAL 1 $recurring_frequency) , invoice_id = $new_invoice_id WHERE recurring_id = $recurring_id");

  if($config_recurring_email_auto_send == 1){
    $sql = mysqli_query($mysqli,"SELECT * FROM invoices, clients
    WHERE invoices.client_id = clients.client_id
    AND invoices.invoice_id = $new_invoice_id"
    );

    $row = mysqli_fetch_array($sql);
    $invoice_number = $row['invoice_number'];
    $invoice_date = $row['invoice_date'];
    $invoice_due = $row['invoice_due'];
    $invoice_amount = $row['invoice_amount'];
    $invoice_url_key = $row['invoice_url_key'];
    $client_id = $row['client_id'];
    $client_name = $row['client_name'];
    $client_address = $row['client_address'];
    $client_city = $row['client_city'];
    $client_state = $row['client_state'];
    $client_zip = $row['client_zip'];
    $client_email = $row['client_email'];
    $client_phone = $row['client_phone'];
    if(strlen($client_phone)>2){ 
    $client_phone = substr($row['client_phone'],0,3)."-".substr($row['client_phone'],3,3)."-".substr($row['client_phone'],6,4);
    }
    $base_url = $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']);

    $mail = new PHPMailer(true);

    try{

        //Mail Server Settings

        //$mail->SMTPDebug = 2;                                       // Enable verbose debug output
        $mail->isSMTP();                                            // Set mailer to use SMTP
        $mail->Host       = $config_smtp_host;  // Specify main and backup SMTP servers
        $mail->SMTPAuth   = true;                                   // Enable SMTP authentication
        $mail->Username   = $config_smtp_username;                     // SMTP username
        $mail->Password   = $config_smtp_password;                               // SMTP password
        $mail->SMTPSecure = 'tls';                                  // Enable TLS encryption, `ssl` also accepted
        $mail->Port       = $config_smtp_port;                                    // TCP port to connect to

        //Recipients
        $mail->setFrom($config_mail_from_email, $config_mail_from_name);
        $mail->addAddress("$client_email", "$client_name");     // Add a recipient

        // Content
        $mail->isHTML(true);                                  // Set email format to HTML

        $mail->Subject = "Invoice $invoice_number";
        $mail->Body    = "Hello $client_name,<br><br>Please view the details of the invoice below.<br><br>Invoice: $invoice_number<br>Issue Date: $invoice_date<br>Total: $$invoice_amount<br>Due Date: $invoice_due<br><br><br>To view your invoice online click <a href='https://$base_url/guest_view_invoice.php?invoice_id=$new_invoice_id&url_key=$invoice_url_key'>here</a><br><br><br>~<br>$config_company_name<br>$config_company_phone";
        
        $mail->send();

        mysqli_query($mysqli,"INSERT INTO history SET history_date = CURDATE(), history_status = 'Sent', history_description = 'Auto Emailed Invoice!', invoice_id = $new_invoice_id");

        //Update Invoice Status to Sent
        mysqli_query($mysqli,"UPDATE invoices SET invoice_status = 'Sent', client_id = $client_id WHERE invoice_id = $new_invoice_id");

    }catch (Exception $e) {
        echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
        mysqli_query($mysqli,"INSERT INTO history SET history_date = CURDATE(), history_status = 'Draft', history_description = 'Failed to send Invoice!', invoice_id = $new_invoice_id");
    }
  }
}

?>