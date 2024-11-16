<?php
/*
 * CRON - Email Parser
 * Process emails and create/update tickets using PHP's native IMAP functions with UIDs
 */

// Start the timer
$script_start_time = microtime(true); // unComment when Debugging Execution time

// Set working directory to the directory this cron script lives at.
chdir(dirname(__FILE__));

// Get ITFlow config & helper functions
require_once "config.php";

// Set Timezone
require_once "inc_set_timezone.php";
require_once "functions.php";

// Get settings for the "default" company
require_once "get_settings.php";

$config_ticket_prefix = sanitizeInput($config_ticket_prefix);
$config_ticket_from_name = sanitizeInput($config_ticket_from_name);
$config_ticket_email_parse_unknown_senders = intval($row['config_ticket_email_parse_unknown_senders']);

// Get company name & phone & timezone
$sql = mysqli_query($mysqli, "SELECT * FROM companies, settings WHERE companies.company_id = settings.company_id AND companies.company_id = 1");
$row = mysqli_fetch_array($sql);
$company_name = sanitizeInput($row['company_name']);
$company_phone = sanitizeInput(formatPhoneNumber($row['company_phone']));

// Check setting enabled
if ($config_ticket_email_parse == 0) {
    exit("Email Parser: Feature is not enabled - check Settings > Ticketing > Email-to-ticket parsing. See https://docs.itflow.org/ticket_email_parse  -- Quitting..");
}

$argv = $_SERVER['argv'];

// Check Cron Key
if ($argv[1] !== $config_cron_key) {
    exit("Cron Key invalid  -- Quitting..");
}

// Get system temp directory
$temp_dir = sys_get_temp_dir();

// Create the path for the lock file using the temp directory
$lock_file_path = "{$temp_dir}/itflow_email_parser_{$installation_id}.lock";

// Check for lock file to prevent concurrent script runs
if (file_exists($lock_file_path)) {
    $file_age = time() - filemtime($lock_file_path);

    // If file is older than 5 minutes (300 seconds), delete and continue
    if ($file_age > 300) {
        unlink($lock_file_path);

        // Logging
        logAction("Cron-Email-Parser", "Delete", "Cron Email Parser detected a lock file was present but was over 5 minutes old so it removed it.");
    
    } else {
       
        // Logging
        logAction("Cron-Email-Parser", "Locked", "Cron Email Parser attempted to execute but was already executing, so instead it terminated.");

        exit("Script is already running. Exiting.");
    }
}

// Create a lock file
file_put_contents($lock_file_path, "Locked");

// PHP Mail Parser
use PhpMimeMailParser\Parser;
require_once "plugins/php-mime-mail-parser/Contracts/CharsetManager.php";
require_once "plugins/php-mime-mail-parser/Contracts/Middleware.php";
require_once "plugins/php-mime-mail-parser/Attachment.php";
require_once "plugins/php-mime-mail-parser/Charset.php";
require_once "plugins/php-mime-mail-parser/Exception.php";
require_once "plugins/php-mime-mail-parser/Middleware.php";
require_once "plugins/php-mime-mail-parser/MiddlewareStack.php";
require_once "plugins/php-mime-mail-parser/MimePart.php";
require_once "plugins/php-mime-mail-parser/Parser.php";

// Allowed attachment extensions
$allowed_extensions = array('jpg', 'jpeg', 'gif', 'png', 'webp', 'pdf', 'txt', 'md', 'doc', 'docx', 'csv', 'xls', 'xlsx', 'xlsm', 'zip', 'tar', 'gz');

// Function to raise a new ticket for a given contact and email them confirmation (if configured)
function addTicket($contact_id, $contact_name, $contact_email, $client_id, $date, $subject, $message, $attachments, $original_message_file) {
    global $mysqli, $config_app_name, $company_name, $company_phone, $config_ticket_prefix, $config_ticket_client_general_notifications, $config_ticket_new_ticket_notification_email, $config_base_url, $config_ticket_from_name, $config_ticket_from_email, $allowed_extensions;

    $ticket_number_sql = mysqli_fetch_array(mysqli_query($mysqli, "SELECT config_ticket_next_number FROM settings WHERE company_id = 1"));
    $ticket_number = intval($ticket_number_sql['config_ticket_next_number']);
    $new_config_ticket_next_number = $ticket_number + 1;
    mysqli_query($mysqli, "UPDATE settings SET config_ticket_next_number = $new_config_ticket_next_number WHERE company_id = 1");

    // Clean up the message
    $message = trim($message); // Remove leading/trailing whitespace
    $message = preg_replace('/\s+/', ' ', $message); // Replace multiple spaces with a single space
    $message = nl2br($message); // Convert newlines to <br>

    // Wrap the message in a div with controlled line height
    $message = "<i>Email from: <b>$contact_name</b> &lt;$contact_email&gt; at $date:-</i> <br><br><div style='line-height:1.5;'>$message</div>";

    $ticket_prefix_esc = mysqli_real_escape_string($mysqli, $config_ticket_prefix);
    $message_esc = mysqli_real_escape_string($mysqli, $message);
    $contact_email_esc = mysqli_real_escape_string($mysqli, $contact_email);
    $client_id = intval($client_id);

    //Generate a unique URL key for clients to access
    $url_key = randomString(156);

    mysqli_query($mysqli, "INSERT INTO tickets SET ticket_prefix = '$ticket_prefix_esc', ticket_number = $ticket_number, ticket_subject = '$subject', ticket_details = '$message_esc', ticket_priority = 'Low', ticket_status = 1, ticket_created_by = 0, ticket_contact_id = $contact_id, ticket_url_key = '$url_key', ticket_client_id = $client_id");
    $id = mysqli_insert_id($mysqli);

    // Logging
    logAction("Ticket", "Create", "Email parser: Client contact $contact_email_esc created ticket $ticket_prefix_esc$ticket_number ($subject) ($id)", $client_id, $id);

    mkdirMissing('uploads/tickets/');
    $att_dir = "uploads/tickets/" . $id . "/";
    mkdirMissing($att_dir);

    rename("uploads/tmp/{$original_message_file}", "{$att_dir}/{$original_message_file}");
    $original_message_file_esc = mysqli_real_escape_string($mysqli, $original_message_file);
    mysqli_query($mysqli, "INSERT INTO ticket_attachments SET ticket_attachment_name = 'Original-parsed-email.eml', ticket_attachment_reference_name = '$original_message_file_esc', ticket_attachment_ticket_id = $id");

    foreach ($attachments as $attachment) {
        $att_name = $attachment->getFilename();
        $att_extarr = explode('.', $att_name);
        $att_extension = strtolower(end($att_extarr));

        if (in_array($att_extension, $allowed_extensions)) {
            $att_saved_filename = md5(uniqid(rand(), true)) . '.' . $att_extension;
            $att_saved_path = $att_dir . $att_saved_filename;
            file_put_contents($att_saved_path, $attachment->getContent());

            $ticket_attachment_name = sanitizeInput($att_name);
            $ticket_attachment_reference_name = sanitizeInput($att_saved_filename);

            $ticket_attachment_name_esc = mysqli_real_escape_string($mysqli, $ticket_attachment_name);
            $ticket_attachment_reference_name_esc = mysqli_real_escape_string($mysqli, $ticket_attachment_reference_name);
            mysqli_query($mysqli, "INSERT INTO ticket_attachments SET ticket_attachment_name = '$ticket_attachment_name_esc', ticket_attachment_reference_name = '$ticket_attachment_reference_name_esc', ticket_attachment_ticket_id = $id");
        } else {
            $ticket_attachment_name_esc = mysqli_real_escape_string($mysqli, $att_name);
            logAction("Ticket", "Edit", "Email parser: Blocked attachment $ticket_attachment_name_esc from Client contact $contact_email_esc for ticket $ticket_prefix_esc$ticket_number", $client_id, $id);
        }
    }

    $data = [];
    if ($config_ticket_client_general_notifications == 1) {
        $subject_email = "Ticket created - [$config_ticket_prefix$ticket_number] - $subject";
        $body = "<i style='color: #808080'>##- Please type your reply above this line -##</i><br><br>Hello $contact_name,<br><br>Thank you for your email. A ticket regarding \"$subject\" has been automatically created for you.<br><br>Ticket: $config_ticket_prefix$ticket_number<br>Subject: $subject<br>Status: New<br>https://$config_base_url/portal/ticket.php?id=$id<br><br>--<br>$company_name - Support<br>$config_ticket_from_email<br>$company_phone";
        $data[] = [
            'from' => $config_ticket_from_email,
            'from_name' => $config_ticket_from_name,
            'recipient' => $contact_email,
            'recipient_name' => $contact_name,
            'subject' => $subject_email,
            'body' => mysqli_real_escape_string($mysqli, $body)
        ];
    }

    if ($config_ticket_new_ticket_notification_email) {
        if ($client_id == 0){
            $client_name = "Guest";
        } else {
            $client_sql = mysqli_query($mysqli, "SELECT client_name FROM clients WHERE client_id = $client_id");
            $client_row = mysqli_fetch_array($client_sql);
            $client_name = sanitizeInput($client_row['client_name']);
        }
        $email_subject = "$config_app_name - New Ticket - $client_name: $subject";
        $email_body = "Hello, <br><br>This is a notification that a new ticket has been raised in ITFlow. <br>Client: $client_name<br>Priority: Low (email parsed)<br>Link: https://$config_base_url/ticket.php?ticket_id=$id <br><br>--------------------------------<br><br><b>$subject</b><br>$message";

        $data[] = [
            'from' => $config_ticket_from_email,
            'from_name' => $config_ticket_from_name,
            'recipient' => $config_ticket_new_ticket_notification_email,
            'recipient_name' => $config_ticket_from_name,
            'subject' => $email_subject,
            'body' => mysqli_real_escape_string($mysqli, $email_body)
        ];
    }

    addToMailQueue($mysqli, $data);

    // Custom action/notif handler
    customAction('ticket_create', $id);

    return true;
}

// Add Reply Function
function addReply($from_email, $date, $subject, $ticket_number, $message, $attachments) {
    global $mysqli, $config_app_name, $company_name, $company_phone, $config_ticket_prefix, $config_base_url, $config_ticket_from_name, $config_ticket_from_email, $allowed_extensions;

    $ticket_reply_type = 'Client';
    // Clean up the message
    $message_parts = explode("##- Please type your reply above this line -##", $message);
    $message_body = $message_parts[0];
    $message_body = trim($message_body); // Remove leading/trailing whitespace
    $message_body = preg_replace('/\r\n|\r|\n/', ' ', $message_body); // Replace newlines with a space
    $message_body = nl2br($message_body); // Convert remaining newlines to <br>

    // Wrap the message in a div with controlled line height
    $message = "<i>Email from: $from_email at $date:-</i> <br><br><div style='line-height:1.5;'>$message_body</div>";

    $ticket_number_esc = intval($ticket_number);
    $message_esc = mysqli_real_escape_string($mysqli, $message);
    $from_email_esc = mysqli_real_escape_string($mysqli, $from_email);

    $row = mysqli_fetch_array(mysqli_query($mysqli, "SELECT ticket_id, ticket_subject, ticket_status, ticket_contact_id, ticket_client_id, contact_email, client_name
        FROM tickets
        LEFT JOIN contacts on tickets.ticket_contact_id = contacts.contact_id
        LEFT JOIN clients on tickets.ticket_client_id = clients.client_id
        WHERE ticket_number = $ticket_number_esc LIMIT 1"));

    if ($row) {
        $ticket_id = intval($row['ticket_id']);
        $ticket_subject = sanitizeInput($row['ticket_subject']);
        $ticket_status = sanitizeInput($row['ticket_status']);
        $ticket_reply_contact = intval($row['ticket_contact_id']);
        $ticket_contact_email = sanitizeInput($row['contact_email']);
        $client_id = intval($row['ticket_client_id']);
        $client_name = sanitizeInput($row['client_name']);

        if ($ticket_status == 5) {
            $config_ticket_prefix_esc = mysqli_real_escape_string($mysqli, $config_ticket_prefix);
            $ticket_number_esc = mysqli_real_escape_string($mysqli, $ticket_number);

            appNotify("Ticket", "Email parser: $from_email attempted to re-open ticket $config_ticket_prefix_esc$ticket_number_esc (ID $ticket_id) - check inbox manually to see email", "ticket.php?ticket_id=$ticket_id", $client_id);

            $email_subject = "Action required: This ticket is already closed";
            $email_body = "Hi there, <br><br>You've tried to reply to a ticket that is closed - we won't see your response. <br><br>Please raise a new ticket by sending a new e-mail to our support address below. <br><br>--<br>$company_name - Support<br>$config_ticket_from_email<br>$company_phone";

            $data = [
                [
                    'from' => $config_ticket_from_email,
                    'from_name' => $config_ticket_from_name,
                    'recipient' => $from_email,
                    'recipient_name' => $from_email,
                    'subject' => $email_subject,
                    'body' => mysqli_real_escape_string($mysqli, $email_body)
                ]
            ];

            addToMailQueue($mysqli, $data);

            return true;
        }

        if (empty($ticket_contact_email) || $ticket_contact_email !== $from_email) {
            $from_email_esc = mysqli_real_escape_string($mysqli, $from_email);
            $row = mysqli_fetch_array(mysqli_query($mysqli, "SELECT contact_id FROM contacts WHERE contact_email = '$from_email_esc' AND contact_client_id = $client_id LIMIT 1"));
            if ($row) {
                $ticket_reply_contact = intval($row['contact_id']);
            } else {
                $ticket_reply_type = 'Internal';
                $ticket_reply_contact = '0';
                $message = "<b>WARNING: Contact email mismatch</b><br>$message";
                $message_esc = mysqli_real_escape_string($mysqli, $message);
            }
        }

        mysqli_query($mysqli, "INSERT INTO ticket_replies SET ticket_reply = '$message_esc', ticket_reply_type = '$ticket_reply_type', ticket_reply_time_worked = '00:00:00', ticket_reply_by = $ticket_reply_contact, ticket_reply_ticket_id = $ticket_id");
        $reply_id = mysqli_insert_id($mysqli);

        mkdirMissing('uploads/tickets/');
        foreach ($attachments as $attachment) {
            $att_name = $attachment->getFilename();
            $att_extarr = explode('.', $att_name);
            $att_extension = strtolower(end($att_extarr));

            if (in_array($att_extension, $allowed_extensions)) {
                $att_saved_filename = md5(uniqid(rand(), true)) . '.' . $att_extension;
                $att_saved_path = "uploads/tickets/" . $ticket_id . "/" . $att_saved_filename;
                file_put_contents($att_saved_path, $attachment->getContent());

                $ticket_attachment_name = sanitizeInput($att_name);
                $ticket_attachment_reference_name = sanitizeInput($att_saved_filename);

                $ticket_attachment_name_esc = mysqli_real_escape_string($mysqli, $ticket_attachment_name);
                $ticket_attachment_reference_name_esc = mysqli_real_escape_string($mysqli, $ticket_attachment_reference_name);
                mysqli_query($mysqli, "INSERT INTO ticket_attachments SET ticket_attachment_name = '$ticket_attachment_name_esc', ticket_attachment_reference_name = '$ticket_attachment_reference_name_esc', ticket_attachment_reply_id = $reply_id, ticket_attachment_ticket_id = $ticket_id");
            } else {
                $ticket_attachment_name_esc = mysqli_real_escape_string($mysqli, $att_name);
                logAction("Ticket", "Edit", "Email parser: Blocked attachment $ticket_attachment_name_esc from Client contact $from_email_esc for ticket $config_ticket_prefix$ticket_number_esc", $client_id, $ticket_id);
            }
        }

        $ticket_assigned_to_sql = mysqli_query($mysqli, "SELECT ticket_assigned_to FROM tickets WHERE ticket_id = $ticket_id LIMIT 1");

        if ($ticket_assigned_to_sql) {
            $row = mysqli_fetch_array($ticket_assigned_to_sql);
            $ticket_assigned_to = intval($row['ticket_assigned_to']);

            if ($ticket_assigned_to) {
                $tech_sql = mysqli_query($mysqli, "SELECT user_email, user_name FROM users WHERE user_id = $ticket_assigned_to LIMIT 1");
                $tech_row = mysqli_fetch_array($tech_sql);
                $tech_email = sanitizeInput($tech_row['user_email']);
                $tech_name = sanitizeInput($tech_row['user_name']);

                $email_subject = "$config_app_name - Ticket updated - [$config_ticket_prefix$ticket_number] $ticket_subject";
                $email_body    = "Hello $tech_name,<br><br>A new reply has been added to the below ticket, check ITFlow for full details.<br><br>Client: $client_name<br>Ticket: $config_ticket_prefix$ticket_number<br>Subject: $ticket_subject<br><br>https://$config_base_url/ticket.php?ticket_id=$ticket_id";

                $data = [
                    [
                        'from' => $config_ticket_from_email,
                        'from_name' => $config_ticket_from_name,
                        'recipient' => $tech_email,
                        'recipient_name' => $tech_name,
                        'subject' => mysqli_real_escape_string($mysqli, $email_subject),
                        'body' => mysqli_real_escape_string($mysqli, $email_body)
                    ]
                ];

                addToMailQueue($mysqli, $data);
            }
        }

        mysqli_query($mysqli, "UPDATE tickets SET ticket_status = 2, ticket_resolved_at = NULL WHERE ticket_id = $ticket_id AND ticket_client_id = $client_id LIMIT 1");

        logAction("Ticket", "Edit", "Email parser: Client contact $from_email_esc updated ticket $config_ticket_prefix$ticket_number_esc ($subject)", $client_id, $ticket_id);

        customAction('ticket_reply_client', $ticket_id);

        return true;

    } else {
        return false;
    }
}

// Function to create a folder in the mailbox if it doesn't exist
function createMailboxFolder($imap, $mailbox, $folderName) {
    $folders = imap_list($imap, $mailbox, '*');
    $folderExists = false;
    if ($folders !== false) {
        foreach ($folders as $folder) {
            $folder = str_replace($mailbox, '', $folder);
            if ($folder == $folderName) {
                $folderExists = true;
                break;
            }
        }
    }
    if (!$folderExists) {
        imap_createmailbox($imap, $mailbox . imap_utf7_encode($folderName));
        imap_subscribe($imap, $mailbox . $folderName);
    }
}

// Initialize IMAP connection
$validate_cert = true; // or false based on your configuration

$imap_encryption = $config_imap_encryption; // e.g., 'ssl', 'tls', or '' (empty string) for none

// Start building the mailbox string
$mailbox = '{' . $config_imap_host . ':' . $config_imap_port;

// Only add the encryption part if $imap_encryption is not empty
if (!empty($imap_encryption)) {
    $mailbox .= '/' . $imap_encryption;
}

// Add the certificate validation part
if ($validate_cert) {
    $mailbox .= '/validate-cert';
} else {
    $mailbox .= '/novalidate-cert';
}

$mailbox .= '}';

// Append 'INBOX' to specify the mailbox folder
$inbox_mailbox = $mailbox . 'INBOX';

// Open the IMAP connection
$imap = imap_open($inbox_mailbox, $config_imap_username, $config_imap_password);

if ($imap === false) {
    echo "Error connecting to IMAP server: " . imap_last_error();
    exit;
}

// Create the "ITFlow" mailbox folder if it doesn't exist
createMailboxFolder($imap, $mailbox, 'ITFlow');

// Search for unseen messages and get UIDs
$emails = imap_search($imap, 'UNSEEN', SE_UID);

// Set Processed and Unprocessed Email count to 0
$processed_count = 0;
$unprocessed_count = 0;

if ($emails !== false) {
    foreach ($emails as $email_uid) {
        $email_processed = false;

        // Save original message
        mkdirMissing('uploads/tmp/');
        $original_message_file = "processed-eml-" . randomString(200) . ".eml";

        $raw_message = imap_fetchheader($imap, $email_uid, FT_UID) . imap_body($imap, $email_uid, FT_UID);
        file_put_contents("uploads/tmp/{$original_message_file}", $raw_message);

        // Parse the message using php-mime-mail-parser
        $parser = new \PhpMimeMailParser\Parser();
        $parser->setText($raw_message);

        // Get from address
        $from_addresses = $parser->getAddresses('from');
        $from_email = sanitizeInput($from_addresses[0]['address'] ?? 'itflow-guest@example.com');
        $from_name = sanitizeInput($from_addresses[0]['display'] ?? 'Unknown');

        $from_domain = explode("@", $from_email);
        $from_domain = sanitizeInput(end($from_domain));

        // Get subject
        $subject = sanitizeInput($parser->getHeader('subject') ?? 'No Subject');

        // Get date
        $date = sanitizeInput($parser->getHeader('date') ?? date('Y-m-d H:i:s'));

        // Get message body
        $message_body_html = $parser->getMessageBody('html');
        $message_body_text = $parser->getMessageBody('text');
        $message_body = $message_body_html ?: nl2br(htmlspecialchars($message_body_text));

        // Handle inline images
        $attachments = $parser->getAttachments();
        $inline_attachments = [];
        foreach ($attachments as $attachment) {
            if ($attachment->getContentDisposition() === 'inline' && $attachment->getContentID()) {
                $cid = trim($attachment->getContentID(), '<>');
                $data = base64_encode($attachment->getContent());
                $mime = $attachment->getContentType();
                $dataUri = "data:$mime;base64,$data";
                $message_body = str_replace("cid:$cid", $dataUri, $message_body);
            } else {
                $inline_attachments[] = $attachment;
            }
        }
        $attachments = $inline_attachments;

        // Process the email
        if (preg_match("/\[$config_ticket_prefix(\d+)\]/", $subject, $ticket_number_matches)) {
            $ticket_number = intval($ticket_number_matches[1]);

            if (addReply($from_email, $date, $subject, $ticket_number, $message_body, $attachments)) {
                $email_processed = true;
            }
        } else {
            // Check if the sender is a known contact
            $from_email_esc = mysqli_real_escape_string($mysqli, $from_email);
            $any_contact_sql = mysqli_query($mysqli, "SELECT * FROM contacts WHERE contact_email = '$from_email_esc' LIMIT 1");
            $row = mysqli_fetch_array($any_contact_sql);

            if ($row) {
                $contact_name = sanitizeInput($row['contact_name']);
                $contact_id = intval($row['contact_id']);
                $contact_email = sanitizeInput($row['contact_email']);
                $client_id = intval($row['contact_client_id']);

                if (addTicket($contact_id, $contact_name, $contact_email, $client_id, $date, $subject, $message_body, $attachments, $original_message_file)) {
                    $email_processed = true;
                }
            } else {
                // Check if the domain is associated with a client
                $from_domain_esc = mysqli_real_escape_string($mysqli, $from_domain);
                $domain_sql = mysqli_query($mysqli, "SELECT * FROM domains WHERE domain_name = '$from_domain_esc' LIMIT 1");
                $row = mysqli_fetch_assoc($domain_sql);

                if ($row && $from_domain == $row['domain_name']) {
                    $client_id = intval($row['domain_client_id']);

                    // Create a new contact
                    $contact_name = $from_name;
                    $contact_email = $from_email;
                    mysqli_query($mysqli, "INSERT INTO contacts SET contact_name = '".mysqli_real_escape_string($mysqli, $contact_name)."', contact_email = '".mysqli_real_escape_string($mysqli, $contact_email)."', contact_notes = 'Added automatically via email parsing.', contact_client_id = $client_id");
                    $contact_id = mysqli_insert_id($mysqli);

                    // Logging
                    logAction("Contact", "Create", "Email parser: created contact " . mysqli_real_escape_string($mysqli, $contact_name) . "", $client_id, $contact_id);
                    customAction('contact_create', $contact_id);

                    if (addTicket($contact_id, $contact_name, $contact_email, $client_id, $date, $subject, $message_body, $attachments, $original_message_file)) {
                        $email_processed = true;
                    }
                } elseif ($config_ticket_email_parse_unknown_senders) {
                    // Parse even if the sender is unknown
                    $bad_from_pattern = "/daemon|postmaster/i";
                    if (!(preg_match($bad_from_pattern, $from_email))) {
                        if (addTicket(0, $from_name, $from_email, 0, $date, $subject, $message_body, $attachments, $original_message_file)) {
                            $email_processed = true;
                        }
                    }
                }
            }
        }

        if ($email_processed) {
            // Mark the message as seen
            imap_setflag_full($imap, $email_uid, "\\Seen", ST_UID);
            // Move the message to the 'ITFlow' folder
            imap_mail_move($imap, $email_uid, 'ITFlow', CP_UID);
            // Get a Processed Email Count
            $processed_count++;
        } else {
            // Flag the message for manual review without marking it as read
            imap_setflag_full($imap, $email_uid, "\\Flagged", ST_UID);
            // Clear the Seen flag to ensure the email remains unread
            imap_clearflag_full($imap, $email_uid, "\\Seen", ST_UID);
            // Get an Unprocessed email count
            $unprocessed_count++;
        }

        // Delete the temporary message file
        if (file_exists("uploads/tmp/{$original_message_file}")) {
            unlink("uploads/tmp/{$original_message_file}");
        }
    }
}

// Expunge deleted mails
imap_expunge($imap);

// Close the IMAP connection
imap_close($imap);

// Calculate the total execution time -uncomment the code below to get exec time
$script_end_time = microtime(true);
$execution_time = $script_end_time - $script_start_time;
$execution_time_formatted = number_format($execution_time, 2);

// Insert a log entry into the logs table
$processed_info = "Processed: $processed_count email(s), Unprocessed: $unprocessed_count email(s)";
// Remove Comment below for Troubleshooting

//logAction("Cron-Email-Parser", "Execution", "Cron Email Parser executed in $execution_time_formatted seconds. $processed_info");

// END Calculate execution time

// Remove the lock file
unlink($lock_file_path);

// DEBUG
echo "\nLock File Path: $lock_file_path\n";
if (file_exists($lock_file_path)) {
    echo "\nLock is present\n\n";
}
echo "Processed Emails into tickets: $processed_count\n";
echo "Unprocessed Emails: $unprocessed_count\n";

?>
