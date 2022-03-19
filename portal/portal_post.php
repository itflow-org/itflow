<?php
/*
 * Client Portal
 * Process GET/POST requests
 */

include('../config.php');
include('../functions.php');
include('check_login.php');

$session_company_id = $_SESSION['company_id'];
$session_client_id = $_SESSION['client_id'];
$session_contact_id = $_SESSION['contact_id'];

if(!isset($_SESSION)){
  // HTTP Only cookies
  ini_set("session.cookie_httponly", True);
  if($config_https_only){
    // Tell client to only send cookie(s) over HTTPS
    ini_set("session.cookie_secure", True);
  }
  session_start();
}

if(isset($_POST['add_ticket_comment'])){
  $requested_ticket_id = intval($_POST['ticket_id']);
  $comment = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['comment'])));

  // Verify the client has access to the provided ticket ID
  $sql = mysqli_query($mysqli, "SELECT * FROM tickets WHERE ticket_id = '$requested_ticket_id' AND ticket_status != 'Closed' AND ticket_client_id = '$session_client_id'");
  $row = mysqli_fetch_array($sql);
  $ticket_id = $row['ticket_id'];

  // Add client comment
  mysqli_query($mysqli, "INSERT INTO ticket_replies SET ticket_reply = '$comment', ticket_reply_type = 'Client', ticket_reply_created_at = NOW(), ticket_reply_by = '$session_contact_id', ticket_reply_ticket_id = '$ticket_id', company_id = '$session_company_id'");

  // Update Ticket Last Response Field & set ticket to open as client has replied
  mysqli_query($mysqli,"UPDATE tickets SET ticket_status = 'Open', ticket_updated_at = NOW() WHERE ticket_id = $ticket_id AND company_id = $session_company_id");

  header("Location: " . $_SERVER["HTTP_REFERER"]);

}