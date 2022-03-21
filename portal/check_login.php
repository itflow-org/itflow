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

// Get info from session
$session_client_id = $_SESSION['client_id'];
$session_contact_id = $_SESSION['contact_id'];
$session_company_id = $_SESSION['company_id'];

// Get contact info
$contact_sql = mysqli_query($mysqli, "SELECT * FROM contacts WHERE contact_id = '$session_contact_id' AND contact_client_id = '$session_client_id'");
$contact = mysqli_fetch_array($contact_sql);

$session_contact_name = $contact['contact_name'];
$session_contact_initials = initials($session_contact_name);
$session_contact_email = $contact['contact_email'];

// Get client info
$client_sql = mysqli_query($mysqli, "SELECT * FROM clients WHERE client_id = '$session_client_id'");
$client = mysqli_fetch_array($client_sql);

$session_client_name = $client['client_name'];
$session_client_primary_contact_id = $client['primary_contact'];