<?php
/*
 * Client Portal
 * Landing / Home page for the client portal
 */

include('../config.php');
include('../functions.php');

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

$sql_settings = mysqli_query($mysqli,"SELECT config_azure_client_id FROM settings WHERE company_id = '1'");
$settings = mysqli_fetch_array($sql_settings);

//$client_id = "e821e3a6-02c8-40e8-9f22-b84d951a62e7";
//$client_secret = "axL7Q~hKbmIwqa3DoxJLy4p88AdBz96XAcNZW";

$client_id = $settings['config_azure_client_id'];

if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login'])){

  $email = strip_tags(mysqli_real_escape_string($mysqli, $_POST['email']));
  $password = $_POST['password'];

  if(!filter_var($email, FILTER_VALIDATE_EMAIL)){
    $_SESSION['login_message'] = 'Invalid e-mail';
  }
  else{
    $sql = mysqli_query($mysqli, "SELECT * FROM contacts WHERE contact_email = '$email' LIMIT 1");
    $row = mysqli_fetch_array($sql);
    if($row['contact_auth_method'] == 'local'){
      if(password_verify($password, $row['contact_password_hash'])){

        $_SESSION['client_logged_in'] = TRUE;
        $_SESSION['client_id'] = $row['contact_client_id'];
        $_SESSION['contact_id'] = $row['contact_id'];
        $_SESSION['company_id'] = $row['company_id'];
        $_SESSION['login_method'] = "local";

        header("Location: index.php");

        mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Client Login', log_action = 'Success', log_description = 'Client contact $row[contact_email] successfully logged in locally', log_ip = '$ip', log_user_agent = '$user_agent', log_created_at = NOW(), log_client_id = $row[contact_client_id]");

      }
      else{
        mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Client Login', log_action = 'Failed', log_description = 'Failed client portal login attempt using $email', log_ip = '$ip', log_user_agent = '$user_agent', log_created_at = NOW()");
        $_SESSION['login_message'] = 'Incorrect username or password.';
      }

    }
    else{
      mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Client Login', log_action = 'Failed', log_description = 'Failed client portal login attempt using $email', log_ip = '$ip', log_user_agent = '$user_agent', log_created_at = NOW()");
      $_SESSION['login_message'] = 'Incorrect username or password.';
    }
  }



}

?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <title><?php echo $config_app_name; ?> | Client Portal Login</title>

  <!-- Tell the browser to be responsive to screen width -->
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- Font Awesome -->
  <link rel="stylesheet" href="../plugins/fontawesome-free/css/all.min.css">

  <!-- Theme style -->
  <link rel="stylesheet" href="../dist/css/adminlte.min.css">

  <!-- Google Font: Source Sans Pro -->
  <link href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700" rel="stylesheet">
</head>
<div class="container">
  <br>
  <h2 align="center"><?php echo $config_app_name; ?> - Client Portal Login</h2>
  <div class="row">
    <div class="col-4 offset-2">
      <h4>Local</h4>
      <form action="login.php" method="post">
        <div class="form-group">
          <input class="form-control" type="text" name="email" placeholder="someone@example.com">
          <input class="form-control" type="password" name="password" placeholder="Password">
        </div>

        <button class="btn btn-primary" type="submit" name="login">Login</button>
      </form>
      <?php
      if(!empty($_SESSION['login_message'])){
        echo $_SESSION['login_message'];
        unset($_SESSION['login_message']);
      }
      ?>
    </div>

    <?php
    if(!empty($client_id)){ ?>
      <div class="col-4 offset-2">
        <h4>SSO</h4>
        <button type="button" class="btn btn-secondary" onclick="location.href = 'login_microsoft.php';">Login with Microsoft Azure AD</button>
      </div>
    </div>
  <?php } ?>
</div>

