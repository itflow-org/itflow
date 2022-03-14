<?php

/*
 * Client Portal
 * Checks if the client is logged in or not
 */

if(!isset($_SESSION)){
  // HTTP Only cookies
  ini_set("session.cookie_httponly", True);
  if($config_https_only){
    // Tell client to only send cookie(s) over HTTPS
    ini_set("session.cookie_secure", True);
  }
  session_start();
}

if(!$_SESSION['client_logged_in']){
  header("Location: login.php");
  die;
}

// SESSION FINGERPRINT
$session_ip = strip_tags(mysqli_real_escape_string($mysqli,get_ip()));
$session_os = strip_tags(mysqli_real_escape_string($mysqli,get_os()));

// Get user agent
$session_user_agent = strip_tags(mysqli_real_escape_string($mysqli,$_SERVER['HTTP_USER_AGENT']));

// Get client info
$session_client_id = $_SESSION['client_id'];
$session_contact_id = $_SESSION['contact_id'];

