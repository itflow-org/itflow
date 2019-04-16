<?php 
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
  $invoice_amount = $row['invoice_amount'];
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

  $sql_payments = mysqli_query($mysqli,"SELECT * FROM payments, accounts WHERE payments.account_id = accounts.account_id AND payments.invoice_id = $invoice_id ORDER BY payments.payment_id DESC");

  //Add up all the payments for the invoice and get the total amount paid to the invoice
  $sql_amount_paid = mysqli_query($mysqli,"SELECT SUM(payment_amount) AS amount_paid FROM payments WHERE invoice_id = $invoice_id");
  $row = mysqli_fetch_array($sql_amount_paid);
  $amount_paid = $row['amount_paid'];

  $balance = $invoice_amount - $amount_paid;

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
    $total_tax = $invoice_item_tax + $total_tax;
    $sub_total = $invoice_item_price * $invoice_item_quantity + $sub_total;


    $invoice_items .= "
      <tr>
        <td align='center'>$invoice_item_name</td>
        <td>$invoice_item_description</td>
        <td class='cost'>$$invoice_item_price</td>
        <td align='center'>$invoice_item_quantity</td>
        <td class='cost'>$$invoice_item_tax</td>
        <td class='cost'>$$invoice_item_total</td>
      </tr>
    ";

  }

$html = '
<html>
<head>
<style>
body {font-family: sans-serif;
  font-size: 10pt;
}
p { margin: 0pt; }
table.items {
  border: 0.1mm solid #000000;
}
td { vertical-align: top; }
.items td {
  border-left: 0.1mm solid #000000;
  border-right: 0.1mm solid #000000;
}
table thead td { background-color: #EEEEEE;
  text-align: center;
  border: 0.1mm solid #000000;
  font-variant: small-caps;
}
.items td.blanktotal {
  background-color: #EEEEEE;
  border: 0.1mm solid #000000;
  background-color: #FFFFFF;
  border: 0mm none #000000;
  border-top: 0.1mm solid #000000;
  border-right: 0.1mm solid #000000;
}
.items td.totals {
  text-align: right;
  border: 0.1mm solid #000000;
}
.items td.cost {
  text-align: "." center;
}
</style>
</head>
<body>
<!--mpdf
<htmlpageheader name="myheader">
<table width="100%"><tr>
<td width="15%"><img width="75" height="75" src=" '.$config_invoice_logo.' "></img></td>
<td width="50%"><span style="font-weight: bold; font-size: 14pt;"> '.$config_company_name.' </span><br />' .$config_company_address.' <br /> '.$config_company_city.' '.$config_company_state.' '.$config_company_zip.'<br /> '.$config_company_phone.' </td>
<td width="35%" style="text-align: right;">Invoice No.<br /><span style="font-weight: bold; font-size: 12pt;"> INV-'.$invoice_number.' </span></td>
</tr></table>
</htmlpageheader>
<htmlpagefooter name="myfooter">
<div style="border-top: 1px solid #000000; font-size: 9pt; text-align: center; padding-top: 3mm; ">
Page {PAGENO} of {nb}
</div>
</htmlpagefooter>
<sethtmlpageheader name="myheader" value="on" show-this-page="1" />
<sethtmlpagefooter name="myfooter" value="on" />
mpdf-->
<div style="text-align: right">Date: '.$invoice_date.'</div>
<div style="text-align: right">Due: '.$invoice_due.' </div>
<table width="100%" style="font-family: serif;" cellpadding="10"><tr>
<td width="45%" style="border: 0.1mm solid #888888; "><span style="font-size: 7pt; color: #555555; font-family: sans;">BILL TO:</span><br /><br /><b> '.$client_name.' </b><br />'.$client_address.'<br />'.$client_city.' '.$client_state.' '.$client_zip.' <br /><br> '.$client_email.' <br /> '.$client_phone.'</td>
<td width="65%">&nbsp;</td>

</tr></table>
<br />
<table class="items" width="100%" style="font-size: 9pt; border-collapse: collapse; " cellpadding="8">
<thead>
<tr>
<td width="20%">Item</td>
<td width="25%">Description</td>
<td width="15%">Unit Cost</td>
<td width="10%">Quantity</td>
<td width="15%">Tax</td>
<td width="15%">Line Total</td>
</tr>
</thead>
<tbody>
'.$invoice_items.'
<tr>
<td class="blanktotal" colspan="4" rowspan="5"><h4>Notes</h4> '.$invoice_note.' </td>
<td class="totals">Subtotal:</td>
<td class="totals cost">$ '.number_format($sub_total,2).' </td>
</tr>
<tr>
<td class="totals">Tax:</td>
<td class="totals cost">$ '.number_format($total_tax,2).' </td>
</tr>
<tr>
<td class="totals">Total:</td>
<td class="totals cost">$ '.number_format($invoice_amount,2).' </td>
</tr>
<tr>
<td class="totals">Paid:</td>
<td class="totals cost">$ '.number_format($amount_paid,2).' </td>
</tr>
<tr>
<td class="totals"><b>Balance due:</b></td>
<td class="totals cost"><b>$ '.number_format($balance,2).' </b></td>
</tr>
</tbody>
</table>
<div style="text-align: center; font-style: italic;"> '.$config_invoice_footer.' </div>
</body>
</html>
';

$path = (getenv('MPDF_ROOT')) ? getenv('MPDF_ROOT') : __DIR__;
require_once $path . '/vendor/autoload.php';
$mpdf = new \Mpdf\Mpdf([
  'margin_left' => 20,
  'margin_right' => 15,
  'margin_top' => 48,
  'margin_bottom' => 25,
  'margin_header' => 10,
  'margin_footer' => 10
]);
$mpdf->SetProtection(array('print'));
$mpdf->SetTitle("$config_company_name - Invoice");
$mpdf->SetAuthor("$config_company_name");
if($invoice_status == 'Paid'){
  $mpdf->SetWatermarkText("Paid");
}
$mpdf->showWatermarkText = true;
$mpdf->watermark_font = 'DejaVuSansCondensed';
$mpdf->watermarkTextAlpha = 0.1;
$mpdf->SetDisplayMode('fullpage');
$mpdf->WriteHTML($html);
$mpdf->Output('uploads/invoice.pdf', 'F');



require("vendor/PHPMailer-6.0.7/src/PHPMailer.php");
require("vendor/PHPMailer-6.0.7/src/SMTP.php");

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$mail = new PHPMailer(true);

try {

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
 
  // Attachments
  //$mail->addAttachment('/var/tmp/file.tar.gz');         // Add attachments
  //$mail->addAttachment('/tmp/image.jpg', 'new.jpg');    // Optional name
  $mail->addAttachment('uploads/invoice.pdf');    // Optional name

  // Content
  $mail->isHTML(true);                                  // Set email format to HTML
  $mail->Subject = "Invoice $invoice_number - $invoice_date - Due $invoice_due";
  $mail->Body    = "Hello $client_name,<br><br>Thank you for choosing $config_company_name! -- attached to this email is your invoice in PDF form due on <b>$invoice_due</b> Please make all checks payable to $config_company_name and mail to $config_company_address $config_company_city $config_company_state $config_company_zip before <b>$invoice_due</b>.<br><br>If you have any questions please contact us at the number below.<br><br>~<br>$config_company_name<br>Automated Billing Department<br>$config_company_phone";
  //$mail->AltBody = 'This is the body in plain text for non-HTML mail clients';

  $mail->send();
  echo 'Message has been sent';


  
  mysqli_query($mysqli,"INSERT INTO invoice_history SET invoice_history_date = CURDATE(), invoice_history_status = 'Sent', invoice_history_description = 'Emailed Invoice!', invoice_id = $invoice_id");

  //Don't chnage the status to sent if the status is anything but draf
  if($invoice_status == 'Draft'){

    mysqli_query($mysqli,"UPDATE invoices SET invoice_status = 'Sent', client_id = $client_id");

  }

  $_SESSION['alert_message'] = "Invoice has been sent";

  header("Location: invoice.php?invoice_id=$invoice_id");


} catch (Exception $e) {
    echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
}

?>