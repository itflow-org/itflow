<?php 
//Include PHP Mailer Config
	include("config.php");
	include("check_login.php");
?>

<?php 

  $invoice_id = intval($_GET['invoice_id']);

  $sql = mysqli_query($mysqli,"SELECT * FROM invoices, clients
    WHERE invoices.client_id = clients.client_id
    AND invoices.invoice_id = $invoice_id"
  );

  $row = mysqli_fetch_array($sql);
  $invoice_id = $row['invoice_id'];
  $invoice_number = $row['invoice_number'];
  $invoice_status = $row['invoice_status'];
  $invoice_date = $row['invoice_date'];
  $invoice_due = $row['invoice_due'];
  $invoice_subtotal = $row['invoice_subtotal'];
  $invoice_discount = $row['invoice_discount'];
  $invoice_tax = $row['invoice_tax'];
  $invoice_total = $row['invoice_total'];
  $invoice_paid = $row['invoice_paid'];
  $invoice_balance = $row['invoice_balance'];
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
  $client_website = $row['client_website'];

?>

<?php
require("vendor/PHPMailer-6.0.7/src/PHPMailer.php");
require("vendor/PHPMailer-6.0.7/src/SMTP.php");

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$mail = new PHPMailer(true);

try {

  //Mail Server Settings

  $mail->SMTPDebug = 2;                                       // Enable verbose debug output
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
 
  // Attachments
  //$mail->addAttachment('/var/tmp/file.tar.gz');         // Add attachments
  //$mail->addAttachment('/tmp/image.jpg', 'new.jpg');    // Optional name

  // Content
  $mail->isHTML(true);                                  // Set email format to HTML
  $mail->Subject = "Invoice $invoice_number - $invoice_date - Due $invoice_due";
  $mail->Body    = "Hello $client_name,<br><br>Thank you for choosing $config_company_name! -- attached to this email is your invoice in PDF form due on <b>$invoice_due</b> Please make all checks payable to $config_company_name and mail to $config_company_address $config_company_city $config_company_state $config_company_zip before <b>$invoice_due</b>.<br><br>If you have any questions please contact us at the number below.<br><br>~<br>$config_company_name<br>Automated Billing Department<br>$config_company_phone";
  //$mail->AltBody = 'This is the body in plain text for non-HTML mail clients';

  $mail->send();
  echo 'Message has been sent';

  mysqli_query($mysqli,"INSERT INTO invoice_history SET invoice_history_date = CURDATE(), invoice_history_status = 'Sent', invoice_history_description = 'Emailed Invoice!', invoice_id = $invoice_id");

  mysqli_query($mysqli,"UPDATE invoices SET invoice_status = 'Sent', client_id = $client_id");

  $_SESSION['alert_message'] = "Invoice has been sent";

  header("Location: invoice.php?invoice_id=$invoice_id");


} catch (Exception $e) {
    echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
}

?>