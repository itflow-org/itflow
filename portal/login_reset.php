<?php
/*
 * Client Portal
 * Password reset page
 */

// Initiate PHPMailer
require_once("../plugins/PHPMailer/src/PHPMailer.php");
require_once("../plugins/PHPMailer/src/SMTP.php");
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$session_company_id = 1;
require_once('../config.php');
require_once('../functions.php');
require_once ('../get_settings.php');

if (empty($config_smtp_host)) {
  header("Location: login.php");
  exit();
}

if(!isset($_SESSION)){
  // HTTP Only cookies
  ini_set("session.cookie_httponly", True);
  if($config_https_only){
    // Tell client to only send cookie(s) over HTTPS
    ini_set("session.cookie_secure", True);
  }
  session_start();
}

$ip = strip_tags(mysqli_real_escape_string($mysqli,get_ip()));
$user_agent = strip_tags(mysqli_real_escape_string($mysqli,$_SERVER['HTTP_USER_AGENT']));

$company_sql = mysqli_query($mysqli, "SELECT company_name FROM companies WHERE company_id = '1'");
$company_results = mysqli_fetch_array($company_sql);
$company_name = $company_results['company_name'];

DEFINE("WORDING_ERROR", "Something went wrong! Your link may have expired. Please request a new password reset e-mail.");

if ($_SERVER['REQUEST_METHOD'] == "POST") {

  /*
   * Send password reset email
   */
  if(isset($_POST['password_reset_email_request'])){

    $email = strip_tags(mysqli_real_escape_string($mysqli, $_POST['email']));

    $sql = mysqli_query($mysqli, "SELECT contact_id, contact_name, contact_email, contact_client_id, company_id FROM contacts WHERE contact_email = '$email' AND contact_auth_method = 'local' LIMIT 1");
    $row = mysqli_fetch_assoc($sql);

    $id = $row['contact_id'];
    $name = $row['contact_name'];
    $client = $row['contact_client_id'];
    $company = $row['company_id'];

    if ($row['contact_email'] == $email) {
      $token = key32gen();
      $url = "https://$config_base_url/portal/login_reset.php?email=$email&token=$token&client=$client";
      mysqli_query($mysqli, "UPDATE contacts SET contact_password_reset_token = '$token' WHERE contact_id = $id LIMIT 1");
      mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Contact', log_action = 'Modify', log_description = 'Sent a portal password reset e-mail for $email.', log_ip = '$ip', log_user_agent = '$user_agent', log_created_at = NOW(), log_client_id = $client, company_id = $company");

      // Send email
      $mail = new PHPMailer(true);

      try{
        //Mail Server Settings
        $mail->SMTPDebug = false;                                   // No debug output as client facing
        $mail->isSMTP();                                            // Set mailer to use SMTP
        $mail->Host       = $config_smtp_host;                      // Specify main and backup SMTP servers
        $mail->SMTPAuth   = true;                                   // Enable SMTP authentication
        $mail->Username   = $config_smtp_username;                  // SMTP username
        $mail->Password   = $config_smtp_password;                  // SMTP password
        $mail->SMTPSecure = $config_smtp_encryption;                // Enable TLS encryption, `ssl` also accepted
        $mail->Port       = $config_smtp_port;                      // TCP port to connect to

        //Recipients
        $mail->setFrom($config_mail_from_email, $config_mail_from_name);
        $mail->addAddress("$email", "$name");          // Add user as recipient

        // Content
        $mail->isHTML(true);                                  // Set email format to HTML

        $mail->Subject = "Password reset for $company_name ITFlow Portal";
        $mail->Body    = "Hello, $name<br><br>Someone (probably you) has requested a new password for your account on $company_name's ITFlow Client Portal. <br><br><b>Please <a href='$url'>click here</a> to reset your password.</b> <br><br>Alternatively, copy and paste this URL into your browser: $url<br><br><i>If you didn't request this change, you can safely ignore this email.</i><br><br>~<br>$company_name<br>Support Department<br>$config_mail_from_email";
        $mail->send();
      }
      catch(Exception $e){
        echo "Message could not be sent. Please contact $company_name.";
        mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Contact', log_action = 'Modify', log_description = 'FAILED to send a portal password reset e-mail for $email due to PHP Mailer error.', log_ip = '$ip', log_user_agent = '$user_agent', log_created_at = NOW(), log_client_id = $client, company_id = $company");
        exit();
      }
      //End Mail IF Try-Catch
    } else {
      sleep(rand(2, 4)); // Mimic the e-mail send delay even if email is invalid to help prevent user enumeration
    }

    $_SESSION['login_message'] = "If your account exists, a reset link is on it's way!";

    /*
     * Do password reset
     */
  }
  elseif(isset($_POST['password_reset_set_password'])){

    if(!isset($_POST['new_password']) || !isset($_POST['email']) || !isset($_POST['token']) || !isset($_POST['client'])) {
      $_SESSION['login_message'] = WORDING_ERROR;
    }

    $token = strip_tags(mysqli_real_escape_string($mysqli, $_POST['token']));
    $email = strip_tags(mysqli_real_escape_string($mysqli, $_POST['email']));
    $client = intval(strip_tags(mysqli_real_escape_string($mysqli, $_POST['client'])));

    // Query user
    $sql = mysqli_query($mysqli, "SELECT * FROM contacts WHERE contact_email = '$email' AND contact_password_reset_token = '$token' AND contact_client_id = $client AND contact_auth_method = 'local' LIMIT 1");
    $contact_row = mysqli_fetch_array($sql);
    $contact_id = $contact_row['contact_id'];
    $name = $contact_row['contact_name'];
    $company = $contact_row['company_id'];

    // Ensure the token is correct
    if (sha1($contact_row['contact_password_reset_token']) == sha1($token)) {

      // Set password, invalidate token, logging
      $password = mysqli_real_escape_string($mysqli, password_hash($_POST['new_password'], PASSWORD_DEFAULT));
      mysqli_query($mysqli, "UPDATE contacts SET contact_password_hash = '$password', contact_password_reset_token = NULL WHERE contact_id = $contact_id LIMIT 1");
      mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Contact', log_action = 'Modify', log_description = 'Reset portal password for $email.', log_ip = '$ip', log_user_agent = '$user_agent', log_created_at = NOW(), log_client_id = $client, company_id = $company");

      // Send confirmation email
      $mail = new PHPMailer(true);

      try{
        //Mail Server Settings
        $mail->SMTPDebug = false;                                   // No debug output as client facing
        $mail->isSMTP();                                            // Set mailer to use SMTP
        $mail->Host       = $config_smtp_host;                      // Specify main and backup SMTP servers
        $mail->SMTPAuth   = true;                                   // Enable SMTP authentication
        $mail->Username   = $config_smtp_username;                  // SMTP username
        $mail->Password   = $config_smtp_password;                  // SMTP password
        $mail->SMTPSecure = $config_smtp_encryption;                // Enable TLS encryption, `ssl` also accepted
        $mail->Port       = $config_smtp_port;                      // TCP port to connect to

        //Recipients
        $mail->setFrom($config_mail_from_email, $config_mail_from_name);
        $mail->addAddress("$email", "$name");          // Add user as recipient

        // Content
        $mail->isHTML(true);                                  // Set email format to HTML

        $mail->Subject = "Password reset confirmation for $company_name ITFlow Portal";
        $mail->Body    = "Hello, $name<br><br>Your password for your account on $company_name's ITFlow Client Portal was successfully reset. You should be all set! <br><br><b>If you didn't reset your password, please get in touch ASAP.</b><br><br>~<br>$company_name<br>Support Department<br>$config_mail_from_email";
        $mail->send();
      }
      catch(Exception $e){
        echo "Message could not be sent. Please contact $company_name.";
        mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Contact', log_action = 'Modify', log_description = 'FAILED to send a password reset e-mail for $email due to PHP Mailer error.', log_ip = '$ip', log_user_agent = '$user_agent', log_created_at = NOW(), log_client_id = $client, company_id = $company");
        exit();
      }
      //End Mail IF Try-Catch

      // Redirect to login page
      $_SESSION['login_message'] = "Password reset successfully!";
      header("Location: login.php");
      exit();

    } else {
      $_SESSION['login_message'] = WORDING_ERROR;
    }


  }


}

?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <title><?php echo $company_name; ?> | Password Reset</title>

  <!-- Tell the browser to be responsive to screen width -->
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex">

  <!-- Font Awesome -->
  <link rel="stylesheet" href="../plugins/fontawesome-free/css/all.min.css">

  <!-- Theme style -->
  <link rel="stylesheet" href="../dist/css/adminlte.min.css">

  <!-- Google Font: Source Sans Pro -->
  <link href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700" rel="stylesheet">
</head>

<body class="hold-transition login-page">
<div class="login-box">
  <div class="login-logo"><b><?=$company_name?></b> <br>Password Reset</h2></div>
  <div class="card">
    <div class="card-body login-card-body">

      <form method="post">

        <?php
        /*
         * Password reset form
         */
        if (isset($_GET['token']) && isset($_GET['email']) && isset($_GET['client'])) {

          $token = strip_tags(mysqli_real_escape_string($mysqli, $_GET['token']));
          $email = strip_tags(mysqli_real_escape_string($mysqli, $_GET['email']));
          $client = intval(strip_tags(mysqli_real_escape_string($mysqli, $_GET['client'])));

          $sql = mysqli_query($mysqli, "SELECT * FROM contacts WHERE contact_email = '$email' AND contact_password_reset_token = '$token' AND contact_client_id = $client LIMIT 1");
          $contact_row = mysqli_fetch_array($sql);

          // Sanity check
          if (sha1($contact_row['contact_password_reset_token']) == sha1($token)) { ?>

            <div class="input-group mb-3">
              <input type="password" class="form-control" placeholder="New Password" name="new_password" required minlength="8">
              <div class="input-group-append">
                <div class="input-group-text">
                  <span class="fas fa-lock"></span>
                </div>
              </div>
            </div>

            <input type="hidden" name="token" value="<?=$token?>">
            <input type="hidden" name="email" value="<?=$email?>">
            <input type="hidden" name="client" value="<?=$client?>">

            <button type="submit" class="btn btn-success btn-block mb-3" name="password_reset_set_password">Reset password</button>


          <?php } else {

            $_SESSION['login_message'] = WORDING_ERROR;

          }


          /*
           * Else: Just show the form to request a reset token email
           */
        } else { ?>

          <div class="input-group mb-3">
            <input type="text" class="form-control" placeholder="Registered Client Email" name="email" required autofocus>
            <div class="input-group-append">
              <div class="input-group-text">
                <span class="fas fa-envelope"></span>
              </div>
            </div>
          </div>

          <button type="submit" class="btn btn-success btn-block mb-3" name="password_reset_email_request">Reset my password</button>

        <?php }
        ?>

      </form>

      <p class="login-box-msg text-danger">
        <?php
        // Show feedback from session
        if(!empty($_SESSION['login_message'])){
          echo $_SESSION['login_message'];
          unset($_SESSION['login_message']);
        }
        ?>
      </p>

      <a href="login.php">Back to login</a>


    </div>
    <!-- /.login-card-body -->

  </div>
  <!-- /.div.card -->

</div>
<!-- /.login-box -->

<!-- jQuery -->
<script src="../plugins/jquery/jquery.min.js"></script>
<!-- Bootstrap 4 -->
<script src="../plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<!-- AdminLTE App -->
<script src="../dist/js/adminlte.min.js"></script>

<script src="../plugins/Show-Hide-Passwords-Bootstrap-4/bootstrap-show-password.min.js"></script>

<!-- Prevents resubmit on refresh or back -->
<script>
    if(window.history.replaceState){
        window.history.replaceState(null,null,window.location.href);
    }
</script>

</body>
</html>