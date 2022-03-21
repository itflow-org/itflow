<?php
/*
 * Client Portal
 * Process GET/POST requests
 */

require_once("inc_portal.php");

if(isset($_GET['logout'])){
  setcookie("PHPSESSID", '', time() - 3600, "/");
  unset($_COOKIE['PHPSESSID']);

  session_unset();
  session_destroy();

  header('Location: login.php');
}

if(isset($_POST['add_ticket_comment'])){
  // HTML Purifier
  require("../plugins/htmlpurifier/HTMLPurifier.standalone.php");
  $purifier_config = HTMLPurifier_Config::createDefault();
  $purifier_config->set('URI.AllowedSchemes', ['data' => true, 'src' => true, 'http' => true, 'https' => true]);
  $purifier = new HTMLPurifier($purifier_config);

  $requested_ticket_id = intval($_POST['ticket_id']);

  // Not currently providing the client portal with a full summer note editor, but need to maintain line breaks.
  // In order to maintain line breaks consistently with the agent side, we need to allow HTML tags.
  // So, we need to convert line breaks to HTML and clean HTML with HTML Purifier
  $comment = trim(mysqli_real_escape_string($mysqli,$purifier->purify(html_entity_decode(nl2br($_POST['comment'])))));

  if(empty($comment)){
    header("Location: " . $_SERVER["HTTP_REFERER"]);
    exit;
  }

  // Verify the contact has access to the provided ticket ID
  $sql = mysqli_query($mysqli, "SELECT * FROM tickets WHERE ticket_id = '$requested_ticket_id' AND ticket_status != 'Closed' AND ticket_client_id = '$session_client_id' LIMIT 1");
  $row = mysqli_fetch_array($sql);
  $ticket_id = $row['ticket_id'];

  if(intval($ticket_id)){
    if($row['ticket_contact_id'] !== $session_contact_id AND $session_client_primary_contact_id !== $session_contact_id){
      // This would only happen if the user intentionally modifies the hidden ticket_id value
      header("Location: portal_post.php?logout");
      exit();
    }

    // Add comment
    mysqli_query($mysqli, "INSERT INTO ticket_replies SET ticket_reply = '$comment', ticket_reply_type = 'Client', ticket_reply_created_at = NOW(), ticket_reply_by = '$session_contact_id', ticket_reply_ticket_id = '$ticket_id', company_id = '$session_company_id'");

    // Update Ticket Last Response Field & set ticket to open as client has replied
    mysqli_query($mysqli,"UPDATE tickets SET ticket_status = 'Open', ticket_updated_at = NOW() WHERE ticket_id = $ticket_id AND ticket_client_id = '$session_client_id' LIMIT 1");

    header("Location: " . $_SERVER["HTTP_REFERER"]);
  }
  else{
    header("Location: portal_post.php?logout");
    exit();
  }
}

if(isset($_POST['add_ticket_feedback'])){
  $requested_ticket_id = intval($_POST['ticket_id']);
  $feedback = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['add_ticket_feedback'])));

  // Verify the contact has access to the provided ticket ID
  $sql = mysqli_query($mysqli, "SELECT * FROM tickets WHERE ticket_id = '$requested_ticket_id' AND ticket_status = 'Closed' AND ticket_client_id = '$session_client_id' LIMIT 1");
  $row = mysqli_fetch_array($sql);
  $ticket_id = $row['ticket_id'];
  $ticket_subject = $row['ticket_subject'];

  if(intval($ticket_id)){
    if($row['ticket_contact_id'] !== $session_contact_id AND $session_client_primary_contact_id !== $session_contact_id){
      header("Location: " . $_SERVER["HTTP_REFERER"]);
      exit("No access to this ticket ($requested_ticket_id)");
    }

    // Add feedback
    mysqli_query($mysqli, "UPDATE tickets SET ticket_feedback = '$feedback' WHERE ticket_id = '$ticket_id' AND ticket_client_id = '$session_client_id' LIMIT 1");

    if($feedback == "Bad"){
      mysqli_query($mysqli,"INSERT INTO notifications SET notification_type = 'Feedback', notification = '$session_contact_name rated ticket - $ticket_subject ($ticket_id) - as bad', notification_timestamp = NOW(), notification_client_id = '$session_client_id', company_id = '$session_company_id'");
    }

    header("Location: " . $_SERVER["HTTP_REFERER"]);
  }
  else{
    header("Location: portal_post.php?logout");
    exit();
  }


}

if(isset($_POST['add_ticket'])){

  // Get ticket prefix/number
  $sql_settings = mysqli_query($mysqli,"SELECT * FROM settings WHERE company_id = $session_company_id");
  $row = mysqli_fetch_array($sql_settings);
  $config_ticket_prefix = $row['config_ticket_prefix'];
  $config_ticket_next_number = $row['config_ticket_next_number'];

  // HTML Purifier
  require("../plugins/htmlpurifier/HTMLPurifier.standalone.php");
  $purifier_config = HTMLPurifier_Config::createDefault();
  $purifier_config->set('URI.AllowedSchemes', ['data' => true, 'src' => true, 'http' => true, 'https' => true]);
  $purifier = new HTMLPurifier($purifier_config);

  $client_id = $session_client_id;
  $contact = $session_contact_id;
  $subject = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['subject'])));
  $priority = $_POST['priority'];
  $details = trim(mysqli_real_escape_string($mysqli,$purifier->purify(html_entity_decode(nl2br($_POST['details'])))));

  // Ensure priority is low/med/high (as can be user defined)
  if($priority !== "Low" OR $priority !== "Medium" OR $priority !== "High"){
    $priority = "Low";
  }

  // Get the next Ticket Number and add 1 for the new ticket number
  $ticket_number = $config_ticket_next_number;
  $new_config_ticket_next_number = $config_ticket_next_number + 1;
  mysqli_query($mysqli,"UPDATE settings SET config_ticket_next_number = $new_config_ticket_next_number WHERE company_id = $session_company_id");

  mysqli_query($mysqli,"INSERT INTO tickets SET ticket_prefix = '$config_ticket_prefix', ticket_number = $ticket_number, ticket_subject = '$subject', ticket_details = '$details', ticket_priority = '$priority', ticket_status = 'Open', ticket_created_at = NOW(), ticket_created_by = '0', ticket_contact_id = $contact, ticket_client_id = $client_id, company_id = $session_company_id");
  $id = mysqli_insert_id($mysqli);

  //Logging
  mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Ticket', log_action = 'Create', log_description = 'Client contact $session_contact_name created ticket $subject', log_created_at = NOW(), log_client_id = $client_id, company_id = $session_company_id");

  header("Location: ticket.php?id=" . $id);

}