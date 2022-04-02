<?php include("config.php"); ?>
<?php include("functions.php"); ?>
<?php

require("plugins/PHPMailer/src/PHPMailer.php");
require("plugins/PHPMailer/src/SMTP.php");

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

?>

<?php

$sql_companies = mysqli_query($mysqli,"SELECT * FROM companies, settings WHERE companies.company_id = settings.company_id");

while($row = mysqli_fetch_array($sql_companies)){
  $company_id = $row['company_id'];
  $company_name = $row['company_name'];
  $company_phone = formatPhoneNumber($row['company_phone']);
  $company_email = $row['company_email'];
  $company_website = $row['company_website'];
  $config_smtp_host = $row['config_smtp_host'];
  $config_smtp_username = $row['config_smtp_username'];
  $config_smtp_password = $row['config_smtp_password'];
  $config_smtp_port = $row['config_smtp_port'];
  $config_smtp_encryption = $row['config_smtp_encryption'];
  $config_base_url = $row['config_base_url'];

  $sql_campaigns = mysqli_query($mysqli,"SELECT * FROM campaigns WHERE company_id = $company_id AND campaign_status = 'Queued' AND campaign_scheduled_at < NOW()");

  while($row = mysqli_fetch_array($sql_campaigns)){
    $campaign_id = $row['campaign_id'];
    $campaign_id = $row['campaign_id'];
    $campaign_subject = $row['campaign_subject'];
    $campaign_from_name = $row['campaign_from_name'];
    $campaign_from_email = $row['campaign_from_email'];
    $campaign_content = $row['campaign_content'];
    $campaign_status = $row['campaign_status'];

    mysqli_query($mysqli,"UPDATE campaigns SET campaign_status = 'Sending' WHERE campaign_id = $campaign_id");

    $sql_messages = mysqli_query($mysqli,"SELECT * FROM campaign_messages
      LEFT JOIN contacts ON contact_id = message_contact_id
      LEFT JOIN clients ON primary_contact = contact_id
      WHERE message_sent_at IS NULL
      AND message_campaign_id = $campaign_id
      AND campaign_messages.company_id = $company_id
    ");
          
    while($row = mysqli_fetch_array($sql_messages)){
      $message_id = $row['message_id'];
      $message_hash = $row['message_hash'];
      $client_id = $row['client_id'];
      $client_name = $row['client_name'];
      $contact_id = $row['contact_id'];
      $contact_name = $row['contact_name'];
      $contact_email = $row['contact_email'];

      $mail = new PHPMailer(true);

      try{

        //Mail Server Settings

        $mail->SMTPDebug = 2;                                       // Enable verbose debug output
        $mail->isSMTP();                                            // Set mailer to use SMTP
        $mail->Host       = $config_smtp_host;  // Specify main and backup SMTP servers
        $mail->SMTPAuth   = true;                                   // Enable SMTP authentication
        $mail->Username   = $config_smtp_username;                     // SMTP username
        $mail->Password   = $config_smtp_password;                               // SMTP password
        $mail->SMTPSecure = $config_smtp_encryption;                                  // Enable TLS encryption, `ssl` also accepted
        $mail->Port       = $config_smtp_port;                                    // TCP port to connect to

        //Recipients
        $mail->setFrom($campaign_from_email, $campaign_from_name);
        $mail->addAddress("$contact_email", "$contact_name");     // Add a recipient

        // Content
        $mail->isHTML(true);                                  // Set email format to HTML

        $mail->Subject = "$campaign_subject";
        $mail->Body    = "Hello $contact_name,<br><br>$campaign_content
          <br><br>
          <img src='https://$config_base_url/campaign_track.php?message_id=$message_id&message_hash=$message_hash'>

          <br><br><br>~<br>$company_name<br>$company_phone";
        
        $mail->send();

        mysqli_query($mysqli,"UPDATE campaign_messages SET message_sent_at = NOW() WHERE message_id = $message_id");

      }catch (Exception $e) {
          echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
          mysqli_query($mysqli,"UPDATE campaign_messages SET message_bounced_at = NOW() WHERE message_id = $message_id");
      } //End Mail Try

    }
    mysqli_query($mysqli,"UPDATE campaigns SET campaign_status = 'Sent' WHERE campaign_id = $campaign_id");
  }
} //End Company Loop through

?>