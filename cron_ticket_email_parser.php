<?php
/*
 * CRON - Email Parser
 * Process emails and create/update tickets
 */

// Set working directory to the directory this cron script lives at.
chdir(dirname(__FILE__));

// Autoload Composer dependencies
require_once __DIR__ . '/plugins/php-imap/vendor/autoload.php';

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

    // If file is older than 3 minutes (180 seconds), delete and continue
    if ($file_age > 300) {
        unlink($lock_file_path);
        mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Cron-Email-Parser', log_action = 'Delete', log_description = 'Cron Email Parser detected a lock file was present but was over 10 minutes old so it removed it'");
    } else {
        mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Cron-Email-Parser', log_action = 'Locked', log_description = 'Cron Email Parser attempted to execute but was already executing, so instead it terminated.'");
        exit("Script is already running. Exiting.");
    }
}

// Create a lock file
file_put_contents($lock_file_path, "Locked");

// Webklex PHP-IMAP
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Message\Attachment;

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
    $message = preg_replace('/\s+/', ' ', $message); // Replace multiple spaces with a single space
    $message = nl2br($message); // Convert newlines to <br>
    
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

    foreach ($attachments as $attachment) {
        $att_name = $attachment->getName();
        $att_extarr = explode('.', $att_name);
        $att_extension = strtolower(end($att_extarr));

        if (in_array($att_extension, $allowed_extensions)) {
            $att_saved_filename = md5(uniqid(rand(), true)) . '.' . $att_extension;
            $att_saved_path = $att_dir . $att_saved_filename;
            $attachment->save($att_dir); // Save the attachment to the directory
            rename($att_dir . $attachment->getName(), $att_saved_path); // Rename the saved file to the hashed name

            $ticket_attachment_name = sanitizeInput($att_name);
            $ticket_attachment_reference_name = sanitizeInput($att_saved_filename);

            $ticket_attachment_name_esc = mysqli_real_escape_string($mysqli, $ticket_attachment_name);
            $ticket_attachment_reference_name_esc = mysqli_real_escape_string($mysqli, $ticket_attachment_reference_name);
            mysqli_query($mysqli, "INSERT INTO ticket_attachments SET ticket_attachment_name = '$ticket_attachment_name_esc', ticket_attachment_reference_name = '$ticket_attachment_reference_name_esc', ticket_attachment_ticket_id = $id");
        } else {
            $ticket_attachment_name_esc = mysqli_real_escape_string($mysqli, $att_name);
            mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Ticket', log_action = 'Update', log_description = 'Email parser: Blocked attachment $ticket_attachment_name_esc from Client contact $contact_email_esc for ticket $ticket_prefix_esc$ticket_number', log_client_id = $client_id_esc");
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
        foreach ($attachments as $attachment) {
            $att_name = $attachment->getName();
            $att_extarr = explode('.', $att_name);
            $att_extension = strtolower(end($att_extarr));

            if (in_array($att_extension, $allowed_extensions)) {
                $att_saved_filename = md5(uniqid(rand(), true)) . '.' . $att_extension;
                $att_saved_path = "uploads/tickets/" . $ticket_id . "/" . $att_saved_filename;
                $attachment->save("uploads/tickets/" . $ticket_id); // Save the attachment to the directory
                rename("uploads/tickets/" . $ticket_id . "/" . $attachment->getName(), $att_saved_path); // Rename the saved file to the hashed name

                $ticket_attachment_name = sanitizeInput($att_name);
                $ticket_attachment_reference_name = sanitizeInput($att_saved_filename);

                $ticket_attachment_name_esc = mysqli_real_escape_string($mysqli, $ticket_attachment_name);
                $ticket_attachment_reference_name_esc = mysqli_real_escape_string($mysqli, $ticket_attachment_reference_name);
                mysqli_query($mysqli, "INSERT INTO ticket_attachments SET ticket_attachment_name = '$ticket_attachment_name_esc', ticket_attachment_reference_name = '$ticket_attachment_reference_name_esc', ticket_attachment_reply_id = $reply_id, ticket_attachment_ticket_id = $ticket_id");
            } else {
                $ticket_attachment_name_esc = mysqli_real_escape_string($mysqli, $att_name);
                mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Ticket', log_action = 'Update', log_description = 'Email parser: Blocked attachment $ticket_attachment_name_esc from Client contact $from_email_esc for ticket $config_ticket_prefix$ticket_number_esc', log_client_id = $client_id");
            }
        }

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


// Function to create a folder in the mailbox if it doesn't exist
function createMailboxFolder($client, $folderName) {
    try {
        // Attempt to get the folder
        $folder = $client->getFolder($folderName);

        // If the folder doesn't exist, create it
        if (!$folder) {
            $client->createFolder($folderName);
            echo "Folder '$folderName' created successfully.";
            
            // Disconnect and reconnect to ensure the server registers the new folder
            $client->disconnect();
            sleep(1);  // Pause before reconnecting
            $client->connect();
        } else {
            echo "Folder '$folderName' already exists.";
        }

        // Re-fetch the folder after reconnecting
        return $client->getFolder($folderName);
        
    } catch (Exception $e) {
        echo "Error creating folder '$folderName': " . $e->getMessage();
        return null;
    }
}

// Function to subscribe to a folder in the mailbox
function subscribeMailboxFolder($folder) {
    if ($folder) {
        try {
            // Subscribe to the folder
            $folder->subscribe();
            echo "Folder '{$folder->name}' subscribed successfully.";
        } catch (Exception $e) {
            echo "Error subscribing to folder '{$folder->name}': " . $e->getMessage();
        }
    } else {
        echo "Cannot subscribe to folder because it does not exist.";
    }
}

// Initialize the client manager and create the client
$clientManager = new ClientManager();
$client = $clientManager->make([
    'host'          => $config_imap_host,
    'port'          => $config_imap_port,
    'encryption'    => $config_imap_encryption,
    'validate_cert' => true,
    'username'      => $config_imap_username,
    'password'      => $config_imap_password,
    'protocol'      => 'imap'
]);

// Connect to the IMAP server
$client->connect();

// Create the "ITFlow" mailbox folder if it doesn't exist
$folder = createMailboxFolder($client, 'ITFlow');

// Subscribe to the "ITFlow" mailbox folder
subscribeMailboxFolder($folder);

// Possible names for the inbox folder
$inboxNames = ['Inbox', 'INBOX', 'inbox'];

// Function to get the correct inbox folder
function getInboxFolder($client, $inboxNames) {
    foreach ($inboxNames as $name) {
        try {
            $folder = $client->getFolder($name);
            if ($folder) {
                return $folder;
            }
        } catch (Exception $e) {
            // Continue to the next name if the current one fails
            continue;
        }
    }
    throw new Exception("No inbox folder found.");
}

try {
    $inbox = getInboxFolder($client, $inboxNames);
    $messages = $inbox->query()->unseen()->get();
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}

if ($messages->count() > 0) {
    foreach ($messages as $message) {
        $email_processed = false;

        // Save original message
        mkdirMissing('uploads/tmp/');
        $original_message_file = "processed-eml-" . randomString(200) . ".eml";
        $eml_content = json_decode(json_encode($message->getHeader()), true)['raw'];
        $eml_content .= $message->getRawBody();
        file_put_contents("uploads/tmp/{$original_message_file}", $eml_content);

        $from_address = $message->getFrom();
        $from_name = sanitizeInput($from_address[0]->personal ?? 'Unknown');
        $from_email = sanitizeInput($from_address[0]->mail ?? 'itflow-guest@example.com');

        $from_domain = explode("@", $from_email);
        $from_domain = sanitizeInput(end($from_domain));

        $subject = sanitizeInput($message->getSubject() ?? 'No Subject');
        $date = sanitizeInput($message->getDate() ?? date('Y-m-d H:i:s'));
        $message_body = $message->getHtmlBody() ?? '';

        if (empty($message_body)) {
            $text_body = $message->getTextBody() ?? '';
            $message_body = nl2br(htmlspecialchars($text_body));
        }

        if (preg_match("/\[$config_ticket_prefix\d+\]/", $subject, $ticket_number)) {
            preg_match('/\d+/', $ticket_number[0], $ticket_number);
            $ticket_number = intval($ticket_number[0]);

            if (addReply($from_email, $date, $subject, $ticket_number, $message_body, $message->getAttachments())) {
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

                if (addTicket($contact_id, $contact_name, $contact_email, $client_id, $date, $subject, $message_body, $message->getAttachments(), $original_message_file)) {
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
                    customAction('contact_create', $ticket_id);

                    if (addTicket($contact_id, $contact_name, $contact_email, $client_id, $date, $subject, $message_body, $message->getAttachments(), $original_message_file)) {
                        $email_processed = true;
                    }
                } elseif ($config_ticket_email_parse_unknown_senders)  {
                    // Parse even if the sender is unknown
		    $bad_from_pattern = "/daemon|postmaster/i";
                    if (!(preg_match($bad_from_pattern, $from_email))) {
                        if (addTicket(0, $from_name, $from_email, 0, $date, $subject, $message_body, $message->getAttachments(), $original_message_file)) {
                            $email_processed = true;
                        }
                    }
                }
            }
        }

        if ($email_processed) {
            $message->setFlag(['Seen']);
            $message->move('ITFlow');
        } else {
            echo "Failed to process email - flagging for manual review.";
            $message->setFlag(['Flagged']);
        }

        if (file_exists("uploads/tmp/{$original_message_file}")) {
            unlink("uploads/tmp/{$original_message_file}");
        }
    }
}

$client->expunge();
$client->disconnect();

// Remove the lock file
unlink($lock_file_path);
