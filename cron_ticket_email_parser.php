<?php
/*
 * CRON - Email Parser
 * Process emails and create/update tickets
 */

/*
TODO:
  - Process unregistered contacts/clients into an inbox to allow a ticket to be created/ignored
  - Support for authenticating with OAuth
  - Separate Mailbox Account for tickets 2022-12-14 - JQ

*/

// Set working directory to the directory this cron script lives at.
chdir(dirname(__FILE__));

// Get ITFlow config & helper functions
require_once "config.php";

require_once "functions.php";

// Get settings for the "default" company
require_once "get_settings.php";


// Get company name & phone
$sql = mysqli_query($mysqli, "SELECT company_name, company_phone FROM companies WHERE company_id = 1");
$row = mysqli_fetch_array($sql);
$company_name = $row['company_name'];
$company_phone = formatPhoneNumber($row['company_phone']);

// Check setting enabled
if ($config_ticket_email_parse == 0) {
    exit("Email Parser: Feature is not enabled - check Settings > Ticketing > Email-to-ticket parsing. See https://docs.itflow.org/ticket_email_parse  -- Quitting..");
}

$argv = $_SERVER['argv'];

// Check Cron Key
if ( $argv[1] !== $config_cron_key ) {
    exit("Cron Key invalid  -- Quitting..");
}

// Check IMAP extension works/installed
if (!function_exists('imap_open')) {
    exit("Email Parser: PHP IMAP extension is not installed. See https://docs.itflow.org/ticket_email_parse  -- Quitting..");
}

// Check mailparse extension works/installed
if (!function_exists('mailparse_msg_parse_file')) {
    exit("Email Parser: PHP mailparse extension is not installed. See https://docs.itflow.org/ticket_email_parse  -- Quitting..");
}

// Get system temp directory
$temp_dir = sys_get_temp_dir();

// Create the path for the lock file using the temp directory
$lock_file_path = "{$temp_dir}/itflow_email_parser_{$installation_id}.lock";

// Check for lock file to prevent concurrent script runs
if (file_exists($lock_file_path)) {
    $file_age = time() - filemtime($lock_file_path);

    // If file is older than 10 minutes (600 seconds), delete and continue
    if ($file_age > 600) {
        unlink($lock_file_path);
        mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Cron-Email-Parser', log_action = 'Delete', log_description = 'Cron Email Parser detected a lock file was present but was over 10 minutes old so it removed it'");
    } else {
        mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Cron-Email-Parser', log_action = 'Locked', log_description = 'Cron Email Parser attempted to execute but was already executing, so instead it terminated.'");
        exit("Script is already running. Exiting.");
    }
}

// Create a lock file
file_put_contents($lock_file_path, "Locked");

// PHP Mail Parser
use PhpMimeMailParser\Parser;

require_once "plugins/php-mime-mail-parser/src/Contracts/CharsetManager.php";

require_once "plugins/php-mime-mail-parser/src/Contracts/Middleware.php";

require_once "plugins/php-mime-mail-parser/src/Attachment.php";

require_once "plugins/php-mime-mail-parser/src/Charset.php";

require_once "plugins/php-mime-mail-parser/src/Exception.php";

require_once "plugins/php-mime-mail-parser/src/Middleware.php";

require_once "plugins/php-mime-mail-parser/src/MiddlewareStack.php";

require_once "plugins/php-mime-mail-parser/src/MimePart.php";

require_once "plugins/php-mime-mail-parser/src/Parser.php";


// Allowed attachment extensions
$allowed_extensions = array('jpg', 'jpeg', 'gif', 'png', 'webp', 'pdf', 'txt', 'md', 'doc', 'docx', 'csv', 'xls', 'xlsx', 'xlsm', 'zip', 'tar', 'gz');

// Function to raise a new ticket for a given contact and email them confirmation (if configured)
function addTicket($contact_id, $contact_name, $contact_email, $client_id, $date, $subject, $message, $attachments) {

    // Access global variables
    global $mysqli, $company_name, $company_phone, $config_ticket_prefix, $config_ticket_client_general_notifications, $config_ticket_new_ticket_notification_email, $config_base_url, $config_ticket_from_name, $config_ticket_from_email, $config_smtp_host, $config_smtp_port, $config_smtp_encryption, $config_smtp_username, $config_smtp_password, $allowed_extensions;

    // Get the next Ticket Number and add 1 for the new ticket number
    $ticket_number_sql = mysqli_fetch_array(mysqli_query($mysqli, "SELECT config_ticket_next_number FROM settings WHERE company_id = 1"));
    $ticket_number = intval($ticket_number_sql['config_ticket_next_number']);
    $new_config_ticket_next_number = $ticket_number + 1;
    mysqli_query($mysqli, "UPDATE settings SET config_ticket_next_number = $new_config_ticket_next_number WHERE company_id = 1");

    // Prep ticket details
    $message = nl2br($message);
    $message_escaped = mysqli_real_escape_string($mysqli, "<i>Email from: $contact_email at $date:-</i> <br><br>$message");

    mysqli_query($mysqli, "INSERT INTO tickets SET ticket_prefix = '$config_ticket_prefix', ticket_number = $ticket_number, ticket_subject = '$subject', ticket_details = '$message_escaped', ticket_priority = 'Low', ticket_status = 'Pending-Assignment', ticket_created_by = 0, ticket_contact_id = $contact_id, ticket_client_id = $client_id");
    $id = mysqli_insert_id($mysqli);

    // Logging
    echo "Created new ticket.<br>";
    mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Ticket', log_action = 'Create', log_description = 'Email parser: Client contact $contact_email created ticket $config_ticket_prefix$ticket_number ($subject) ($id)', log_client_id = $client_id");

    // Process attachments (after ticket is logged as created)
    mkdirMissing('uploads/tickets/');
    foreach ($attachments as $attachment) {

        // Get name and extension
        $att_name = $attachment->getFileName();
        $att_extarr = explode('.', $att_name);
        $att_extension = strtolower(end($att_extarr));

        // Check the extension is allowed
        if (in_array($att_extension, $allowed_extensions)) {

            // Setup directory for this ticket ID
            $att_dir = "uploads/tickets/" . $id . "/";
            mkdirMissing($att_dir);

            // Save attachment with a random name
            $att_saved_path = $attachment->save($att_dir, Parser::ATTACHMENT_RANDOM_FILENAME);

            // Access the random name to add into the database (this won't work on Windows)
            $att_tmparr = explode($att_dir, $att_saved_path);

            $ticket_attachment_name = sanitizeInput($att_name);
            $ticket_attachment_reference_name = sanitizeInput(end($att_tmparr));

            mysqli_query($mysqli, "INSERT INTO ticket_attachments SET ticket_attachment_name = '$ticket_attachment_name', ticket_attachment_reference_name = '$ticket_attachment_reference_name', ticket_attachment_ticket_id = $id");

        } else {
            $ticket_attachment_name = sanitizeInput($att_name);
            mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Ticket', log_action = 'Update', log_description = 'Email parser: Blocked attachment $ticket_attachment_name from Client contact $contact_email for ticket $config_ticket_prefix$ticket_number', log_client_id = $client_id");
        }

    }

    $data = [];
    // E-mail client notification that ticket has been created
    if ($config_ticket_client_general_notifications == 1) {

        // Insert email into queue (first, escape vars)
        $contact_email_escaped = sanitizeInput($contact_email);
        $contact_name_escaped = sanitizeInput($contact_name);

        $subject_escaped = mysqli_escape_string($mysqli, "Ticket created - [$config_ticket_prefix$ticket_number] - $subject");
        $body_escaped    = mysqli_escape_string($mysqli, "<i style='color: #808080'>##- Please type your reply above this line -##</i><br><br>Hello, $contact_name<br><br>Thank you for your email. A ticket regarding \"$subject\" has been automatically created for you.<br><br>Ticket: $config_ticket_prefix$ticket_number<br>Subject: $subject<br>Status: Open<br>https://$config_base_url/portal/ticket.php?id=$id<br><br>~<br>$company_name<br>Support Department<br>$config_ticket_from_email<br>$company_phone");

        $data[] = [
            'recipient' => $contact_email_escaped,
            'recipient_name' => $contact_name_escaped,
            'subject' => $subject_escaped,
            'body' => $body_escaped
        ];
    }

    // Notify agent DL of the new ticket, if populated with a valid email
    if ($config_ticket_new_ticket_notification_email) {

        // Get client info
        $client_sql = mysqli_query($mysqli, "SELECT client_name FROM clients WHERE client_id = $client_id");
        $client_row = mysqli_fetch_array($client_sql);
        $client_name = sanitizeInput($client_row['client_name']);

        // TODO: Fix Emojis and HTML opening tags sometimes breaking this "forwarding"
        $details = removeEmoji($message_escaped);

        $email_subject = mysqli_escape_string($mysqli, "ITFlow - New Ticket - $client_name: $subject");
        $email_body = "Hello, <br><br>This is a notification that a new ticket has been raised in ITFlow. <br>Client: $client_name<br>Priority: Low (email parsed)<br>Link: https://$config_base_url/ticket.php?ticket_id=$id <br><br>--------------------------------<br><br><b>$subject</b><br>$details";

        $data[] = [
            'recipient' => $config_ticket_new_ticket_notification_email,
            'recipient_name' => $config_ticket_from_name,
            'subject' => $email_subject,
            'body' => $email_body
        ];
    }

    addToMailQueue($mysqli, $data);

    return true;

}

function addReply($from_email, $date, $subject, $ticket_number, $message, $attachments) {
    // Add email as a comment/reply to an existing ticket

    // Access global variables
    global $mysqli, $company_name, $company_phone, $config_ticket_prefix, $config_base_url, $config_ticket_from_name, $config_ticket_from_email, $config_smtp_host, $config_smtp_port, $config_smtp_encryption, $config_smtp_username, $config_smtp_password, $allowed_extensions;

    // Set default reply type
    $ticket_reply_type = 'Client';

    // Capture just the latest/most recent email reply content
    //  based off the "##- Please type your reply above this line -##" line that we prepend the outgoing emails with
    $message = explode("##- Please type your reply above this line -##", $message);
    $message = nl2br($message[0]);
    $message = "<i>Email from: $from_email at $date:-</i> <br><br>$message";

    // Lookup the ticket ID
    $row = mysqli_fetch_array(mysqli_query($mysqli, "SELECT ticket_id, ticket_subject, ticket_status, ticket_contact_id, ticket_client_id, contact_email
        FROM tickets
        LEFT JOIN contacts on tickets.ticket_contact_id = contacts.contact_id
        WHERE ticket_number = $ticket_number LIMIT 1"));

    if ($row) {

        // Get ticket details
        $ticket_id = intval($row['ticket_id']);
        $ticket_status = $row['ticket_status'];
        $ticket_reply_contact = intval($row['ticket_contact_id']);
        $ticket_contact_email = $row['contact_email'];
        $client_id = intval($row['ticket_client_id']);

        // Check ticket isn't closed - tickets can't be re-opened
        if ($ticket_status == "Closed") {
            mysqli_query($mysqli, "INSERT INTO notifications SET notification_type = 'Ticket', notification = 'Email parser: $from_email attempted to re-open ticket $config_ticket_prefix$ticket_number (ID $ticket_id) - check inbox manually to see email', notification_action = 'ticket.php?ticket_id=$ticket_id', notification_client_id = $client_id");

            $email_subject = "Action required: This ticket is already closed";
            $email_body    = "Hi there, <br><br>You've tried to reply to a ticket that is closed - we won't see your response. <br><br>Please raise a new ticket by sending a fresh e-mail to our support address. <br><br>~<br>$company_name<br>Support Department<br>$config_ticket_from_email<br>$company_phone";

            sendSingleEmail(
                $config_smtp_host,
                $config_smtp_username,
                $config_smtp_password,
                $config_smtp_encryption,
                $config_smtp_port,
                $config_ticket_from_email,
                $config_ticket_from_name,
                $from_email,
                $from_email,
                $email_subject,
                $email_body
            );

            return false;
        }

        // Check WHO replied (was it the owner of the ticket or someone else on CC?)
        if (empty($ticket_contact_email) || $ticket_contact_email !== $from_email) {

            // It wasn't the contact currently assigned to the ticket, check if it's another registered contact for that client

            $row = mysqli_fetch_array(mysqli_query($mysqli, "SELECT contact_id FROM contacts WHERE contact_email = '$from_email' AND contact_client_id = $client_id LIMIT 1"));
            if ($row) {

                // Contact is known - we can keep the reply type as client
                $ticket_reply_contact = intval($row['contact_id']);

            } else {
                // Mark the reply as internal as we don't recognise the contact (so the actual contact doesn't see it, and the tech can edit/delete if needed)
                $ticket_reply_type = 'Internal';
                $ticket_reply_contact = '0';
                $message = "<b>WARNING: Contact email mismatch</b><br>$message"; // Add a warning at the start of the message - for the techs benefit (think phishing/scams)
            }
        }

        // Sanitize ticket reply
        $comment = trim(mysqli_real_escape_string($mysqli, $message));

        // Add the comment
        mysqli_query($mysqli, "INSERT INTO ticket_replies SET ticket_reply = '$comment', ticket_reply_type = '$ticket_reply_type', ticket_reply_time_worked = '00:00:00', ticket_reply_by = $ticket_reply_contact, ticket_reply_ticket_id = $ticket_id");

        $reply_id = mysqli_insert_id($mysqli);

        // Process attachments
        mkdirMissing('uploads/tickets/');
        foreach ($attachments as $attachment) {

            // Get name and extension
            $att_name = $attachment->getFileName();
            $att_extarr = explode('.', $att_name);
            $att_extension = strtolower(end($att_extarr));

            // Check the extension is allowed
            if (in_array($att_extension, $allowed_extensions)) {

                // Setup directory for this ticket ID
                $att_dir = "uploads/tickets/" . $ticket_id . "/";
                mkdirMissing($att_dir);

                // Save attachment with a random name
                $att_saved_path = $attachment->save($att_dir, Parser::ATTACHMENT_RANDOM_FILENAME);

                // Access the random name to add into the database (this won't work on Windows)
                $att_tmparr = explode($att_dir, $att_saved_path);

                $ticket_attachment_name = sanitizeInput($att_name);
                $ticket_attachment_reference_name = sanitizeInput(end($att_tmparr));

                mysqli_query($mysqli, "INSERT INTO ticket_attachments SET ticket_attachment_name = '$ticket_attachment_name', ticket_attachment_reference_name = '$ticket_attachment_reference_name', ticket_attachment_reply_id = $reply_id, ticket_attachment_ticket_id = $ticket_id");

            } else {
                $ticket_attachment_name = sanitizeInput($att_name);
                mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Ticket', log_action = 'Update', log_description = 'Email parser: Blocked attachment $ticket_attachment_name from Client contact $from_email for ticket $config_ticket_prefix$ticket_number', log_client_id = $client_id");
            }

        }

        // E-mail techs assigned to the ticket to notify them of the reply
        $ticket_assigned_to = mysqli_query($mysqli, "SELECT ticket_assigned_to FROM tickets WHERE ticket_id = $ticket_id LIMIT 1");

        // Update Ticket Last Response Field & set ticket to open as client has replied
        mysqli_query($mysqli, "UPDATE tickets SET ticket_status = 'Client-Replied' WHERE ticket_id = $ticket_id AND ticket_client_id = $client_id LIMIT 1");

        echo "Updated existing ticket.<br>";
        mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Ticket', log_action = 'Update', log_description = 'Email parser: Client contact $from_email updated ticket $config_ticket_prefix$ticket_number ($subject)', log_client_id = $client_id");

        return true;

    } else {
        // Invalid ticket number
        return false;
    }
}

// Prepare connection string with encryption (TLS/SSL/<blank>)
$imap_mailbox = "$config_imap_host:$config_imap_port/imap/$config_imap_encryption";

// Connect to host via IMAP
$imap = imap_open("{{$imap_mailbox}}INBOX", $config_imap_username, $config_imap_password);

// Check connection
if (!$imap) {
    // Logging
    //$extended_log_description = var_export(imap_errors(), true);
    // Remove the lock file
    unlink($lock_file_path);
    mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Mail', log_action = 'Error', log_description = 'Email parser: Failed to connect to IMAP. Details'");
    exit("Could not connect to IMAP");
}

// Check for the ITFlow mailbox that we move messages to once processed
$imap_folder = 'ITFlow';
$list = imap_list($imap, "{{$imap_mailbox}}", "*");
if (array_search("{{$imap_mailbox}}$imap_folder", $list) === false) {
    imap_createmailbox($imap, imap_utf7_encode("{{$imap_mailbox}}$imap_folder"));
    imap_subscribe($imap, imap_utf7_encode("{{$imap_mailbox}}$imap_folder"));
}

// Search for unread ("UNSEEN") emails
$emails = imap_search($imap, 'UNSEEN');

if ($emails) {

    // Sort
    rsort($emails);

    // Loop through each email
    foreach ($emails as $email) {

        // Default false
        $email_processed = false;

        // Get details from message and invoke PHP Mime Mail Parser
        $msg_to_parse = imap_fetchheader($imap, $email, FT_PREFETCHTEXT) . imap_body($imap, $email, FT_PEEK);
        $parser = new PhpMimeMailParser\Parser();
        $parser->setText($msg_to_parse);

        // Process message attributes

        $from_array = $parser->getAddresses('from')[0];
        $from_name = trim(mysqli_real_escape_string($mysqli, nullable_htmlentities(strip_tags($from_array['display']))));

        // Handle blank 'From' emails
        $from_email = "itflow-guest@example.com";
        if (filter_var($from_array['address'], FILTER_VALIDATE_EMAIL)) {
            $from_email = trim(mysqli_real_escape_string($mysqli, nullable_htmlentities(strip_tags($from_array['address']))));
        }

        $from_domain = explode("@", $from_array['address']);
        $from_domain = trim(mysqli_real_escape_string($mysqli, nullable_htmlentities(strip_tags(end($from_domain))))); // Use the final element in the array (as technically legal to have multiple @'s)

        $subject = sanitizeInput($parser->getHeader('subject'));
        $date = trim(mysqli_real_escape_string($mysqli, nullable_htmlentities(strip_tags($parser->getHeader('date')))));
        $attachments = $parser->getAttachments();

        // Get the message content
        //  (first try HTML parsing, but switch to plain text if the email is empty/plain-text only)
//        $message = $parser->getMessageBody('htmlEmbedded');
//        if (empty($message)) {
//            echo "DEBUG: Switching to plain text parsing for this message ($subject)";
//            $message = $parser->getMessageBody('text');
//        }

        // TODO: Default to getting HTML and fallback to plaintext, but HTML emails seem to break the forward/agent notifications

        $message = $parser->getMessageBody('text');

        // Check if we can identify a ticket number (in square brackets)
        if (preg_match("/\[$config_ticket_prefix\d+\]/", $subject, $ticket_number)) {

            // Looks like there's a ticket number in the subject line (e.g. [TCK-091]
            // Process as a ticket reply

            // Get the actual ticket number (without the brackets)
            preg_match('/\d+/', $ticket_number[0], $ticket_number);
            $ticket_number = intval($ticket_number[0]);

            if (addReply($from_email, $date, $subject, $ticket_number, $message, $attachments)) {
                $email_processed = true;
            }

        } else {
            // Couldn't match this email to an existing ticket

            // Check if we can match the sender to a pre-existing contact
            $any_contact_sql = mysqli_query($mysqli, "SELECT * FROM contacts WHERE contact_email = '$from_email' LIMIT 1");
            $row = mysqli_fetch_array($any_contact_sql);

            if ($row) {
                // Sender exists as a contact
                $contact_name = $row['contact_name'];
                $contact_id = intval($row['contact_id']);
                $contact_email = $row['contact_email'];
                $client_id = intval($row['contact_client_id']);

                if (addTicket($contact_id, $contact_name, $contact_email, $client_id, $date, $subject, $message, $attachments)) {
                    $email_processed = true;
                }

            } else {

                // Couldn't match this email to an existing ticket or an existing client contact
                // Checking to see if the sender domain matches a client website

                $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT * FROM domains WHERE domain_name = '$from_domain' LIMIT 1"));

                if ($row && $from_domain == $row['domain_name']) {

                    // We found a match - create a contact under this client and raise a ticket for them

                    // Client details
                    $client_id = intval($row['domain_client_id']);

                    // Contact details
                    $password = password_hash(randomString(), PASSWORD_DEFAULT);
                    $contact_name = $from_name;
                    $contact_email = $from_email;
                    mysqli_query($mysqli, "INSERT INTO contacts SET contact_name = '$contact_name', contact_email = '$contact_email', contact_notes = 'Added automatically via email parsing.', contact_password_hash = '$password', contact_client_id = $client_id");
                    $contact_id = mysqli_insert_id($mysqli);

                    // Logging for contact creation
                    echo "Created new contact.<br>";
                    mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Contact', log_action = 'Create', log_description = 'Email parser: created contact $contact_name', log_client_id = $client_id");

                    if (addTicket($contact_id, $contact_name, $contact_email, $client_id, $date, $subject, $message, $attachments)) {
                        $email_processed = true;
                    }

                } else {

                    // Couldn't match this email to an existing ticket, existing contact or an existing client via the "from" domain
                    //  In the future we might make a page where these can be nicely viewed / managed, but for now we'll just flag them in the Inbox as needing attention

                }

            }

        }

        // Deal with the message (move it if processed, flag it if not)
        if ($email_processed) {
            imap_setflag_full($imap, $email, "\\Seen");
            imap_mail_move($imap, $email, $imap_folder);
        } else {
            echo "Failed to process email - flagging for manual review.";
            imap_setflag_full($imap, $email, "\\Flagged");
        }

    }

}

imap_expunge($imap);
imap_close($imap);

// Remove the lock file
unlink($lock_file_path);
