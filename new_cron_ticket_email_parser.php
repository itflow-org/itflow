<?php
/*
 * CRON - Email Parser
 * Process emails and create/update tickets
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

// Allowed attachment extensions
$allowed_extensions = array('jpg', 'jpeg', 'gif', 'png', 'webp', 'pdf', 'txt', 'md', 'doc', 'docx', 'csv', 'xls', 'xlsx', 'xlsm', 'zip', 'tar', 'gz');

// Function to raise a new ticket for a given contact and email them confirmation (if configured)
function addTicket($contact_id, $contact_name, $contact_email, $client_id, $date, $subject, $message, $attachments, $original_message_file) {
    global $mysqli, $config_app_name, $company_name, $company_phone, $config_ticket_prefix, $config_ticket_client_general_notifications, $config_ticket_new_ticket_notification_email, $config_base_url, $config_ticket_from_name, $config_ticket_from_email, $config_smtp_host, $config_smtp_port, $config_smtp_encryption, $config_smtp_username, $config_smtp_password, $allowed_extensions;

    $ticket_number_sql = mysqli_fetch_array(mysqli_query($mysqli, "SELECT config_ticket_next_number FROM settings WHERE company_id = 1"));
    $ticket_number = intval($ticket_number_sql['config_ticket_next_number']);
    $new_config_ticket_next_number = $ticket_number + 1;
    mysqli_query($mysqli, "UPDATE settings SET config_ticket_next_number = $new_config_ticket_next_number WHERE company_id = 1");

    // Clean up the message
    $message = trim($message); // Remove leading/trailing whitespace

    // Process inline images in the message
    $message = processInlineImages($message, $attachments, $ticket_number);

    // Wrap the message in a div with controlled line height
    $message = "<i>Email from: <b>$contact_name</b> &lt;$contact_email&gt; at $date:-</i> <br><br><div style='line-height:1.5;'>$message</div>";

    $ticket_prefix_esc = mysqli_real_escape_string($mysqli, $config_ticket_prefix);
    $subject_esc = mysqli_real_escape_string($mysqli, $subject);
    $message_esc = mysqli_real_escape_string($mysqli, $message);
    $contact_email_esc = mysqli_real_escape_string($mysqli, $contact_email);
    $client_id_esc = intval($client_id);

    //Generate a unique URL key for clients to access
    $url_key = randomString(156);

    mysqli_query($mysqli, "INSERT INTO tickets SET ticket_prefix = '$ticket_prefix_esc', ticket_number = $ticket_number, ticket_subject = '$subject_esc', ticket_details = '$message_esc', ticket_priority = 'Low', ticket_status = 1, ticket_created_by = 0, ticket_contact_id = $contact_id, ticket_url_key = '$url_key', ticket_client_id = $client_id_esc");
    $id = mysqli_insert_id($mysqli);

    mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Ticket', log_action = 'Create', log_description = 'Email parser: Client contact $contact_email_esc created ticket $ticket_prefix_esc$ticket_number ($subject_esc) ($id)', log_client_id = $client_id_esc");

    mkdirMissing('uploads/tickets/');
    $att_dir = "uploads/tickets/" . $id . "/";
    mkdirMissing($att_dir);

    rename("uploads/tmp/{$original_message_file}", "{$att_dir}/{$original_message_file}");
    $original_message_file_esc = mysqli_real_escape_string($mysqli, $original_message_file);
    mysqli_query($mysqli, "INSERT INTO ticket_attachments SET ticket_attachment_name = 'Original-parsed-email.eml', ticket_attachment_reference_name = '$original_message_file_esc', ticket_attachment_ticket_id = $id");

    // Process attachments (excluding inline images)
    processAttachments($attachments, $att_dir, $id, null, $contact_email);

    $data = [];
    if ($config_ticket_client_general_notifications == 1) {
        $subject_email = "Ticket created - [$config_ticket_prefix$ticket_number] - $subject";
        $body = "<i style='color: #808080'>##- Please type your reply above this line -##</i><br><br>Hello $contact_name,<br><br>Thank you for your email. A ticket regarding \"$subject\" has been automatically created for you.<br><br>Ticket: $config_ticket_prefix$ticket_number<br>Subject: $subject<br>Status: New<br>https://$config_base_url/portal/ticket.php?id=$id<br><br>--<br>$company_name - Support<br>$config_ticket_from_email<br>$company_phone";
        $data[] = [
            'from' => $config_ticket_from_email,
            'from_name' => $config_ticket_from_name,
            'recipient' => $contact_email,
            'recipient_name' => $contact_name,
            'subject' => mysqli_real_escape_string($mysqli, $subject_email),
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
            'subject' => mysqli_real_escape_string($mysqli, $email_subject),
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
    global $mysqli, $config_app_name, $company_name, $company_phone, $config_ticket_prefix, $config_base_url, $config_ticket_from_name, $config_ticket_from_email, $config_smtp_host, $config_smtp_port, $config_smtp_encryption, $config_smtp_username, $config_smtp_password, $allowed_extensions;

    $ticket_reply_type = 'Client';
    // Clean up the message
    $message_parts = explode("##- Please type your reply above this line -##", $message);
    $message_body = $message_parts[0];
    $message_body = trim($message_body); // Remove leading/trailing whitespace

    // Process inline images in the message
    $message_body = processInlineImages($message_body, $attachments, $ticket_number);

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
            $ticket_id_esc = intval($ticket_id);
            $client_id_esc = intval($client_id);

            mysqli_query($mysqli, "INSERT INTO notifications SET notification_type = 'Ticket', notification = 'Email parser: $from_email attempted to re-open ticket $config_ticket_prefix_esc$ticket_number_esc (ID $ticket_id_esc) - check inbox manually to see email', notification_action = 'ticket.php?ticket_id=$ticket_id_esc', notification_client_id = $client_id_esc");

            $email_subject = "Action required: This ticket is already closed";
            $email_body = "Hi there, <br><br>You've tried to reply to a ticket that is closed - we won't see your response. <br><br>Please raise a new ticket by sending a new e-mail to our support address below. <br><br>--<br>$company_name - Support<br>$config_ticket_from_email<br>$company_phone";

            $data = [
                [
                    'from' => $config_ticket_from_email,
                    'from_name' => $config_ticket_from_name,
                    'recipient' => $from_email,
                    'recipient_name' => $from_email,
                    'subject' => mysqli_real_escape_string($mysqli, $email_subject),
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
        $att_dir = "uploads/tickets/" . $ticket_id . "/";
        mkdirMissing($att_dir);

        // Process attachments (excluding inline images)
        processAttachments($attachments, $att_dir, $ticket_id, $reply_id, $from_email);

        $ticket_assigned_to = mysqli_query($mysqli, "SELECT ticket_assigned_to FROM tickets WHERE ticket_id = $ticket_id LIMIT 1");

        if ($ticket_assigned_to) {
            $row = mysqli_fetch_array($ticket_assigned_to);
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

        mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Ticket', log_action = 'Update', log_description = 'Email parser: Client contact $from_email_esc updated ticket $config_ticket_prefix$ticket_number_esc ($subject)', log_client_id = $client_id");

        customAction('ticket_reply_client', $ticket_id);

        return true;

    } else {
        return false;
    }
}

// Function to process inline images in the HTML body
function processInlineImages($html_body, &$attachments, $ticket_number) {
    global $config_base_url;

    // Create a mapping of Content-ID to attachment data
    $inline_images = array();
    foreach ($attachments as $key => $attachment) {
        if (!empty($attachment['content_id'])) {
            $content_id = trim($attachment['content_id'], '<>');
            $inline_images[$content_id] = $attachment;
            unset($attachments[$key]); // Remove inline images from attachments array
        }
    }

    // Replace cid references in the HTML body
    if (!empty($inline_images)) {
        // Ensure the images directory exists
        $images_dir = "uploads/inline_images/";
        mkdirMissing($images_dir);

        foreach ($inline_images as $content_id => $attachment) {
            $att_name = $attachment['filename'];
            $att_extarr = explode('.', $att_name);
            $att_extension = strtolower(end($att_extarr));

            // Generate a unique filename
            $att_saved_filename = md5(uniqid(rand(), true)) . '.' . $att_extension;
            $att_saved_path = $images_dir . $att_saved_filename;

            // Save the inline image
            file_put_contents($att_saved_path, $attachment['data']);

            // Update the HTML body to point to the saved image
            $html_body = str_replace('cid:' . $content_id, "https://$config_base_url/$att_saved_path", $html_body);
        }
    }

    return $html_body;
}

// Function to process attachments (excluding inline images)
function processAttachments($attachments, $att_dir, $ticket_id, $reply_id = null, $from_email = '') {
    global $mysqli, $allowed_extensions, $config_ticket_prefix;

    foreach ($attachments as $attachment) {
        $att_name = $attachment['filename'];
        $att_extarr = explode('.', $att_name);
        $att_extension = strtolower(end($att_extarr));

        if (in_array($att_extension, $allowed_extensions)) {
            $att_saved_filename = md5(uniqid(rand(), true)) . '.' . $att_extension;
            $att_saved_path = $att_dir . $att_saved_filename;
            file_put_contents($att_saved_path, $attachment['data']);

            $ticket_attachment_name = sanitizeInput($att_name);
            $ticket_attachment_reference_name = sanitizeInput($att_saved_filename);

            $ticket_attachment_name_esc = mysqli_real_escape_string($mysqli, $ticket_attachment_name);
            $ticket_attachment_reference_name_esc = mysqli_real_escape_string($mysqli, $ticket_attachment_reference_name);

            $reply_clause = $reply_id ? ", ticket_attachment_reply_id = $reply_id" : "";

            mysqli_query($mysqli, "INSERT INTO ticket_attachments SET ticket_attachment_name = '$ticket_attachment_name_esc', ticket_attachment_reference_name = '$ticket_attachment_reference_name_esc', ticket_attachment_ticket_id = $ticket_id $reply_clause");
        } else {
            $ticket_attachment_name_esc = mysqli_real_escape_string($mysqli, $att_name);
            $from_email_esc = mysqli_real_escape_string($mysqli, $from_email);
            mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Ticket', log_action = 'Update', log_description = 'Email parser: Blocked attachment $ticket_attachment_name_esc from Client contact $from_email_esc for ticket $config_ticket_prefix$ticket_id', log_client_id = $ticket_id");
        }
    }
}

// Function to construct the mailbox string
function getMailboxString($host, $port, $encryption, $validate_cert, $folder = 'INBOX') {
    $mailbox = "{" . $host . ":" . $port . "/";

    // Handle encryption
    if ($encryption == 'ssl') {
        $mailbox .= 'imap/ssl';
    } elseif ($encryption == 'tls') {
        $mailbox .= 'imap/tls';
    } else {
        $mailbox .= 'imap';
    }

    // Handle validate_cert
    if (!$validate_cert) {
        $mailbox .= '/novalidate-cert';
    }

    $mailbox .= '}' . $folder;

    return $mailbox;
}

// Function to create a folder in the mailbox if it doesn't exist
function createMailboxFolder($imap, $folderName) {
    $mailboxPath = "{" . $GLOBALS['config_imap_host'] . "}" . $folderName;
    $folders = imap_list($imap, "{" . $GLOBALS['config_imap_host'] . "}", "*");
    $folderExists = false;
    if ($folders) {
        foreach ($folders as $folder) {
            $folderShortName = str_replace("{" . $GLOBALS['config_imap_host'] . "}", '', $folder);
            if ($folderShortName == $folderName) {
                $folderExists = true;
                break;
            }
        }
    }

    if (!$folderExists) {
        if (imap_createmailbox($imap, imap_utf7_encode($mailboxPath))) {
            echo "Folder '$folderName' created successfully.\n";
            // Subscribe to the folder
            imap_subscribe($imap, imap_utf7_encode($mailboxPath));
        } else {
            echo "Error creating folder '$folderName': " . imap_last_error() . "\n";
        }
    }
}

// Function to get the body of the email
function getBody($imap, $email_number, $structure, &$attachments) {
    $body = '';

    if (!isset($structure->parts)) { // Simple message, no attachments
        $body = imap_body($imap, $email_number);
        if ($structure->encoding == 3) { // Base64
            $body = base64_decode($body);
        } elseif ($structure->encoding == 4) { // Quoted-Printable
            $body = quoted_printable_decode($body);
        }
    } else {
        // Multipart message
        $body = get_part($imap, $email_number, $structure, 0, $attachments);
    }

    return $body;
}

function get_part($imap, $email_number, $part, $part_no, &$attachments) {
    $data = '';
    $params = array();
    if ($part->ifparameters) {
        foreach ($part->parameters as $param) {
            $params[strtolower($param->attribute)] = $param->value;
        }
    }
    if ($part->ifdparameters) {
        foreach ($part->dparameters as $param) {
            $params[strtolower($param->attribute)] = $param->value;
        }
    }

    // Identify if the part is an attachment
    $is_attachment = false;
    $filename = '';
    $name = '';
    $content_id = '';

    if ($part->ifdisposition) {
        if (strtolower($part->disposition) == 'attachment' || strtolower($part->disposition) == 'inline') {
            $is_attachment = true;
        }
    }

    if ($part->ifid) {
        $content_id = trim($part->id, '<>');
        $is_attachment = true;
    }

    if ($params) {
        if (isset($params['filename']) || isset($params['name'])) {
            $is_attachment = true;
            $filename = isset($params['filename']) ? $params['filename'] : $params['name'];
        }
    }

    // If it's an attachment, process it
    if ($is_attachment) {
        $attachment = array(
            'filename' => $filename,
            'content_id' => $content_id,
            'data' => imap_fetchbody($imap, $email_number, $part_no ? $part_no : 1),
            'encoding' => $part->encoding
        );

        // Decode the data
        if ($part->encoding == 3) { // Base64
            $attachment['data'] = base64_decode($attachment['data']);
        } elseif ($part->encoding == 4) { // Quoted-Printable
            $attachment['data'] = quoted_printable_decode($attachment['data']);
        }

        $attachments[] = $attachment;
    }

    // If the part is HTML, return it
    if ($part->type == 0 && strtolower($part->subtype) == 'html') {
        $data = imap_fetchbody($imap, $email_number, $part_no ? $part_no : 1);
        if ($part->encoding == 3) {
            $data = base64_decode($data);
        } elseif ($part->encoding == 4) {
            $data = quoted_printable_decode($data);
        }
        return $data;
    }

    // If there are sub-parts, recursively get the HTML part
    if (isset($part->parts) && count($part->parts)) {
        $index = 1;
        foreach ($part->parts as $sub_part) {
            $prefix = $part_no ? $part_no . '.' . $index : $index;
            $result = get_part($imap, $email_number, $sub_part, $prefix, $attachments);
            if ($result) {
                return $result;
            }
            $index++;
        }
    }

    return '';
}

// Now, connect to the IMAP server
$mailbox = getMailboxString($config_imap_host, $config_imap_port, $config_imap_encryption, true);

$imap = imap_open($mailbox, $config_imap_username, $config_imap_password);

if (!$imap) {
    echo "Error connecting to IMAP server: " . imap_last_error();
    exit;
}

// Create the "ITFlow" mailbox folder if it doesn't exist
createMailboxFolder($imap, 'ITFlow');

// Search for unseen emails
$emails = imap_search($imap, 'UNSEEN');

if ($emails) {
    foreach ($emails as $email_number) {
        $email_processed = false;

        // Fetch the email header
        $header = imap_headerinfo($imap, $email_number);

        // Fetch the email structure
        $structure = imap_fetchstructure($imap, $email_number);

        $attachments = array();

        // Get the message body
        $message_body = getBody($imap, $email_number, $structure, $attachments);

        // Get the raw message for saving
        $raw_header = imap_fetchheader($imap, $email_number);
        $raw_body = imap_body($imap, $email_number);
        $eml_content = $raw_header . $raw_body;

        // Save the original message
        $original_message_file = "processed-eml-" . randomString(200) . ".eml";
        mkdirMissing('uploads/tmp/');
        file_put_contents("uploads/tmp/{$original_message_file}", $eml_content);

        // Get the from address
        $from = $header->from[0];
        $from_name = sanitizeInput(imap_utf8($from->personal ?? 'Unknown'));
        $from_email = sanitizeInput($from->mailbox . '@' . $from->host);

        // Get the subject
        $subject = sanitizeInput(imap_utf8($header->subject ?? 'No Subject'));

        // Get the date
        $date = sanitizeInput($header->date ?? date('Y-m-d H:i:s'));

        $from_domain = explode("@", $from_email);
        $from_domain = sanitizeInput(end($from_domain));

        // Now process the message
        if (preg_match("/\[$config_ticket_prefix\d+\]/", $subject, $ticket_number_match)) {
            preg_match('/\d+/', $ticket_number_match[0], $ticket_number);
            $ticket_number = intval($ticket_number[0]);

            if (addReply($from_email, $date, $subject, $ticket_number, $message_body, $attachments)) {
                $email_processed = true;
            }
        } else {
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
                $from_domain_esc = mysqli_real_escape_string($mysqli, $from_domain);
                $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT * FROM domains WHERE domain_name = '$from_domain_esc' LIMIT 1"));

                if ($row && $from_domain == $row['domain_name']) {
                    $client_id = intval($row['domain_client_id']);

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
                } elseif ($config_ticket_email_parse_unknown_senders)  {
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
            imap_setflag_full($imap, $email_number, "\\Seen");
            imap_mail_move($imap, $email_number, 'ITFlow');
            // Expunge the mailbox to apply changes
            imap_expunge($imap);
        } else {
            echo "Failed to process email - flagging for manual review.\n";
            imap_setflag_full($imap, $email_number, "\\Flagged");
            // No need to expunge here unless desired
        }

        if (file_exists("uploads/tmp/{$original_message_file}")) {
            unlink("uploads/tmp/{$original_message_file}");
        }
    }
}

// Close the IMAP connection
imap_close($imap);

// Remove the lock file
unlink($lock_file_path);
