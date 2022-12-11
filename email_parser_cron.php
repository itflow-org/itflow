<?php
/*
 * CRON - Email Parser
 * Process emails and create/update tickets
 */

/*
TODO:
  - Attachments
  - Allow unregistered contacts for clients to create contacts/raise tickets based on email domain
  - Process unregistered contacts/clients into an inbox to allow a ticket to be created/ignored
  - Better handle replying to closed tickets
  - Support for authenticating with OAuth
  - Documentation

Relate PRs to https://github.com/itflow-org/itflow/issues/225 & https://forum.itflow.org/d/11-road-map & https://forum.itflow.org/d/31-tickets-from-email
*/

// Get ITFlow config & helper functions
include("config.php");
include("functions.php");

// Get settings for the "default" company
$session_company_id = 1;
include("get_settings.php");

// Check setting enabled
if ($config_ticket_email_parse == 0) {
  exit("Feature is not enabled - see Settings > Ticketing > Email-to-ticket parsing");
}

// Check IMAP function exists
if (!function_exists('imap_open')) {
  echo "PHP IMAP extension is not installed, quitting..";
  exit();
}

// Prepare connection string with encryption (TLS/SSL/<blank>)
$imap_mailbox = "$config_imap_host:$config_imap_port/imap/$config_imap_encryption";

// Connect to host via IMAP
$imap = imap_open("{{$imap_mailbox}}INBOX", $config_smtp_username, $config_smtp_password);

// Check connection
if (!$imap) {
  // Logging
  $extended_log_description = var_export(imap_errors(), true);
  mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Mail', log_action = 'Error', log_description = 'Failed to connect to IMAP: $extended_log_description', company_id = $session_company_id");

  exit("Could not connect to IMAP");
}

// Search for unread (UNSEEN) emails
$emails = imap_search($imap,'UNSEEN');

if ($emails) {

  // Sort
  rsort($emails);

  // Loop through each email
  foreach($emails as $email) {

    // Get message details
    $metadata = imap_fetch_overview($imap, $email,0); // Date, Subject, Size
    $header = imap_headerinfo($imap, $email); // To get the From as an email, not a contact name
    $message = imap_fetchbody($imap, $email, 1); // Body

    $from = trim(mysqli_real_escape_string($mysqli, htmlentities(strip_tags($header->from[0]->mailbox . "@" . $header->from[0]->host))));
    $subject = trim(mysqli_real_escape_string($mysqli, htmlentities(strip_tags($metadata[0]->subject))));
    $date = trim(mysqli_real_escape_string($mysqli, htmlentities(strip_tags($metadata[0]->date))));

    // Check if we can identify a ticket number (in square brackets)
    if (preg_match('/\[TCK-\d+\]/', $subject, $ticket_number)) {

      // Get the actual ticket number (without the brackets)
      preg_match('/\d+/', $ticket_number[0], $ticket_number);
      $ticket_number = intval($ticket_number[0]);

      // Split the email into just the latest reply, with some metadata
      //  We base this off the string "#--itflow--#" that we prepend the outgoing emails with (similar to the old school --reply above this line--)
      $message = explode("#--itflow--#", $message);
      $message = nl2br(htmlentities(strip_tags($message[0])));
      $message = "<i>Email from: $from at $date:-</i> <br><br>$message";

      // Lookup the ticket ID to add the reply to (just to check in-case the ID is different from the number).
      $ticket_sql = mysqli_query($mysqli, "SELECT * FROM tickets WHERE ticket_number = '$ticket_number' LIMIT 1");
      $row = mysqli_fetch_array($ticket_sql);
      $ticket_id = $row['ticket_id'];
      $ticket_reply_contact = $row['ticket_contact_id'];
      $ticket_assigned_to = $row['ticket_assigned_to'];
      $client_id = $row['ticket_client_id'];
      $session_company_id = $row['company_id'];
      $ticket_reply_type = 'Client'; // Setting to client as a default value

      // Check the ticket ID is valid
      if (intval($ticket_id) && $ticket_id !== '0') {

        // Check that ticket is open
        if ($row['ticket_status'] == "Closed") {

          // It's closed - let's notify someone that a client tried to reply
          mysqli_query($mysqli,"INSERT INTO notifications SET notification_type = 'Ticket', notification = '$from attempted to re-open ticket ID $ticket_id ($config_ticket_prefix$ticket_number) - check inbox manually to see email', notification_timestamp = NOW(), notification_client_id = '$client_id', company_id = '$session_company_id'");

        } else {

          // Ticket is open, proceed.

          // Check the email matches the contact's email - if it doesn't then mark the reply as internal (so the contact doesn't see it, and the tech can edit/delete if needed)
          // Niche edge case - possibly where CC's on an email reply to a ticket?
          $contact_sql = mysqli_query($mysqli, "SELECT contact_email FROM contacts WHERE contact_id = '$ticket_reply_contact'");
          $row = mysqli_fetch_array($contact_sql);
          if ($from !== $row['contact_email']) {
            $ticket_reply_type = 'Internal';
            $ticket_reply_contact = '0';
            $message = "<b>WARNING: Contact email mismatch</b><br>$message"; // Add a warning at the start of the message - for the techs benefit (think phishing/scams)
          }

          // Sanitize ticket reply
          $comment = trim(mysqli_real_escape_string($mysqli,$message));

          // Add the comment
          mysqli_query($mysqli, "INSERT INTO ticket_replies SET ticket_reply = '$message', ticket_reply_type = '$ticket_reply_type', ticket_reply_time_worked = '00:00:00', ticket_reply_created_at = NOW(), ticket_reply_by = '$ticket_reply_contact', ticket_reply_ticket_id = '$ticket_id', company_id = '$session_company_id'");

          // Update Ticket Last Response Field & set ticket to open as client has replied
          mysqli_query($mysqli,"UPDATE tickets SET ticket_status = 'Open', ticket_updated_at = NOW() WHERE ticket_id = $ticket_id AND ticket_client_id = '$client_id' LIMIT 1");

          echo "Updated existing ticket.<br>";
          mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Ticket', log_action = 'Update', log_description = 'Client contact $from updated ticket $subject via email', log_created_at = NOW(), log_client_id = $client_id, company_id = $session_company_id");
        }

      }


    } else {
      // Couldn't match this email to an existing ticket

      // Check if we can match the sender to a pre-existing contact
      $any_contact_sql = mysqli_query($mysqli, "SELECT * FROM contacts WHERE contact_email = '$from' LIMIT 1");
      $row = mysqli_fetch_array($any_contact_sql);
      $contact_id = $row['contact_id'];
      $contact_email = $row['contact_email'];
      $client_id = $row['contact_client_id'];
      $session_company_id = $row['company_id'];

      if ($from == $contact_email) {

        // Prep ticket details
        $message = nl2br(htmlentities(strip_tags($message)));
        $message = trim(mysqli_real_escape_string($mysqli,"<i>Email from: $from at $date:-</i> <br><br>$message"));

        // Get the next Ticket Number and add 1 for the new ticket number
        $ticket_number = $config_ticket_next_number;
        $new_config_ticket_next_number = $config_ticket_next_number + 1;
        mysqli_query($mysqli,"UPDATE settings SET config_ticket_next_number = $new_config_ticket_next_number WHERE company_id = $session_company_id");

        mysqli_query($mysqli,"INSERT INTO tickets SET ticket_prefix = '$config_ticket_prefix', ticket_number = $ticket_number, ticket_subject = '$subject', ticket_details = '$message', ticket_priority = 'Low', ticket_status = 'Open', ticket_created_at = NOW(), ticket_created_by = '0', ticket_contact_id = $contact_id, ticket_client_id = $client_id, company_id = $session_company_id");
        $id = mysqli_insert_id($mysqli);

        // Logging
        echo "Created new ticket.<br>";
        mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Ticket', log_action = 'Create', log_description = 'Client contact $from created ticket $subject via email', log_created_at = NOW(), log_client_id = $client_id, company_id = $session_company_id");

      } else {

        // Couldn't match this against a specific client contact -- do nothing for now
        // In the future, we'll try to match on client domain
        //  or even log this to an inbox in the ITFlow portal or something to allow a new contact/ticket to be created manually

      }

    }


  }

}