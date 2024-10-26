<?php
/*
 * CRON - Email Parser
 * Process emails and create/update tickets using PHP's native IMAP functions with UIDs
 */

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
        mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Cron-Email-Parser', log_action = 'Delete', log_description = 'Cron Email Parser detected a lock file was present but was over 5 minutes old so it removed it'");
    } else {
        mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Cron-Email-Parser', log_action = 'Locked', log_description = 'Cron Email Parser attempted to execute but was already executing, so instead it terminated.'");
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

// ... [Your existing functions: addTicket(), addReply(), createMailboxFolder() remain unchanged] ...

// Initialize IMAP connection
$validate_cert = true; // or false based on your configuration

$imap_encryption = $config_imap_encryption; // e.g., 'ssl' or 'tls'

$mailbox = '{' . $config_imap_host . ':' . $config_imap_port . '/' . $imap_encryption;
if ($validate_cert) {
    $mailbox .= '/validate-cert';
} else {
    $mailbox .= '/novalidate-cert';
}
$mailbox .= '}';
$inbox_mailbox = $mailbox . 'INBOX';

$imap = imap_open($inbox_mailbox, $config_imap_username, $config_imap_password);

if ($imap === false) {
    echo "Error connecting to IMAP server: " . imap_last_error();
    exit;
}

// Create the "ITFlow" mailbox folder if it doesn't exist
createMailboxFolder($imap, $mailbox, 'ITFlow');

// Search for unseen messages and get UIDs
$emails = imap_search($imap, 'UNSEEN', SE_UID);

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
                    $password = password_hash(randomString(), PASSWORD_DEFAULT);
                    $contact_name = $from_name;
                    $contact_email = $from_email;
                    mysqli_query($mysqli, "INSERT INTO contacts SET contact_name = '".mysqli_real_escape_string($mysqli, $contact_name)."', contact_email = '".mysqli_real_escape_string($mysqli, $contact_email)."', contact_notes = 'Added automatically via email parsing.', contact_password_hash = '$password', contact_client_id = $client_id");
                    $contact_id = mysqli_insert_id($mysqli);

                    mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Contact', log_action = 'Create', log_description = 'Email parser: created contact ".mysqli_real_escape_string($mysqli, $contact_name)."', log_client_id = $client_id");
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
        } else {
            // Flag the message for manual review without marking it as read
            imap_setflag_full($imap, $email_uid, "\\Flagged", ST_UID);
            // Clear the Seen flag to ensure the email remains unread
            imap_clearflag_full($imap, $email_uid, "\\Seen", ST_UID);
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

// Remove the lock file
unlink($lock_file_path);
?>
