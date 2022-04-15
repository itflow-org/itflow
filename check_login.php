<?php

if(!isset($_SESSION)){
  // HTTP Only cookies
  ini_set("session.cookie_httponly", True);
  if($config_https_only){
    // Tell client to only send cookie(s) over HTTPS
    ini_set("session.cookie_secure", True);
  }
  session_start();
}

//Check to see if setup is enabled
if(!isset($config_enable_setup) || $config_enable_setup == 1){
  header("Location: setup.php");
  exit;
}

if(!$_SESSION['logged']){
  header("Location: login.php");
  die;
}

// SESSION FINGERPRINT
$session_ip = strip_tags(mysqli_real_escape_string($mysqli,get_ip()));
$session_os = strip_tags(mysqli_real_escape_string($mysqli,get_os()));

// User agent
$session_user_agent = strip_tags(mysqli_real_escape_string($mysqli,$_SERVER['HTTP_USER_AGENT']));

$session_user_id = $_SESSION['user_id'];

$sql = mysqli_query($mysqli,"SELECT * FROM users, user_settings WHERE users.user_id = user_settings.user_id AND users.user_id = $session_user_id");
$row = mysqli_fetch_array($sql);
$session_name = $row['user_name'];
$session_email = $row['user_email'];
$session_avatar = $row['user_avatar'];
$session_token = $row['user_token'];
$session_company_id = $row['user_default_company'];
$session_user_role = $row['user_role'];
if($session_user_role == 3){
  $session_user_role_display = "Administrator";
}elseif($session_user_role == 2){
  $session_user_role_display = "Technician";
}else{
  $session_user_role_display = "Accountant";
}

//LOAD USER COMPANY ACCESS PERMISSIONS
$session_user_company_access_sql = mysqli_query($mysqli,"SELECT company_id FROM user_companies WHERE user_id = $session_user_id");
$session_user_company_access_array = array();
while($row = mysqli_fetch_array($session_user_company_access_sql)){
  $session_user_company_access_array[] = $row['company_id'];
}
$session_user_company_access = implode(',',$session_user_company_access_array);

//Check to see if user has rights to company Prevents User from access a company he is not allowed to have access to.
if(!in_array($session_company_id,$session_user_company_access_array)){
  session_start();
  session_destroy();
  header('Location: login.php');
}

$sql = mysqli_query($mysqli,"SELECT * FROM companies WHERE company_id = $session_company_id");
$row = mysqli_fetch_array($sql);

$session_company_name = $row['company_name'];
$session_company_country = $row['company_country'];
$session_company_locale = $row['company_locale'];
$session_company_currency = $row['company_currency'];

include("get_settings.php");

//Detects if using an apple device and uses apple maps instead of google
$iPod = stripos($_SERVER['HTTP_USER_AGENT'],"iPod");
$iPhone = stripos($_SERVER['HTTP_USER_AGENT'],"iPhone");
$iPad = stripos($_SERVER['HTTP_USER_AGENT'],"iPad");

if($iPod || $iPhone || $iPad){
  $session_map_source = "apple";
}else{
  $session_map_source = "google";
}

//Get Notification Count for the badge on the top nav
$row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('notification_id') AS num FROM notifications WHERE notification_dismissed_at IS NULL AND company_id = $session_company_id"));
$num_notifications = $row['num'];

//Set Currency Format
$currency_format = numfmt_create($session_company_locale, NumberFormatter::CURRENCY);

// Role check failed wording
DEFINE("WORDING_ROLECHECK_FAILED", "You are not permitted to do that!");

?>