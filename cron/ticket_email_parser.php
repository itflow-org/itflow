<?php
/*
 * CRON - Email Parser (Webklex PHP-IMAP)
 * Process emails and create/update tickets using Webklex\PHPIMAP instead of native IMAP
 */

// Start the timer
$script_start_time = microtime(true);

// Set working directory to the directory this cron script lives at.
chdir(dirname(__FILE__));

// Ensure we're running from command line
if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line.\n");
}

// Autoload (Webklex & any composer deps)
require_once "../plugins/vendor/autoload.php";

// Get ITFlow config & helper functions
require_once "../config.php";

// Set Timezone
require_once "../includes/inc_set_timezone.php";
require_once "../functions.php";

// Get settings for the "default" company
require_once "../includes/load_global_settings.php";

$config_ticket_prefix = sanitizeInput($config_ticket_prefix);
$config_ticket_from_name = sanitizeInput($config_ticket_from_name);
$config_ticket_email_parse_unknown_senders = intval($row['config_ticket_email_parse_unknown_senders']);

// Get company name & phone & timezone
$sql = mysqli_query($mysqli, "SELECT * FROM companies, settings WHERE companies.company_id = settings.company_id AND companies.company_id = 1");
$row = mysqli_fetch_array($sql);
$company_name = sanitizeInput($row['company_name']);
$company_phone = sanitizeInput(formatPhoneNumber($row['company_phone'], $row['company_phone_country_code']));

// Check setting enabled
if ($config_ticket_email_parse == 0) {
    logApp("Cron-Email-Parser", "error", "Cron Email Parser unable to run - not enabled in admin settings.");
    exit("Email Parser: Feature is not enabled - check Settings > Ticketing > Email-to-ticket parsing. See https://docs.itflow.org/ticket_email_parse  -- Quitting..");
}

// System temp directory & lock
$temp_dir = sys_get_temp_dir();
$lock_file_path = "{$temp_dir}/itflow_email_parser_{$installation_id}.lock";

if (file_exists($lock_file_path)) {
    $file_age = time() - filemtime($lock_file_path);
    if ($file_age > 300) {
        unlink($lock_file_path);
        logApp("Cron-Email-Parser", "warning", "Cron Email Parser detected a lock file was present but was over 5 minutes old so it removed it.");
    } else {
        logApp("Cron-Email-Parser", "warning", "Lock file present. Cron Email Parser attempted to execute but was already executing, so instead it terminated.");
        exit("Script is already running. Exiting.");
    }
}
file_put_contents($lock_file_path, "Locked");

// Ensure lock gets removed even on fatal error
register_shutdown_function(function() use ($lock_file_path) {
    if (file_exists($lock_file_path)) {
        @unlink($lock_file_path);
    }
});

// Allowed attachment extensions
$allowed_extensions = array('jpg', 'jpeg', 'gif', 'png', 'webp', 'pdf', 'txt', 'md', 'doc', 'docx', 'csv', 'xls', 'xlsx', 'xlsm', 'zip', 'tar', 'gz');

/** ------------------------------------------------------------------
 * Ticket / Reply helpers (unchanged)
 * ------------------------------------------------------------------ */
function addTicket($contact_id, $contact_name, $contact_email, $client_id, $date, $subject, $message, $attachments, $original_message_file) {
    global $mysqli, $config_app_name, $company_name, $company_phone, $config_ticket_prefix, $config_ticket_client_general_notifications, $config_ticket_new_ticket_notification_email, $config_base_url, $config_ticket_from_name, $config_ticket_from_email, $config_ticket_default_billable, $allowed_extensions;

    $ticket_number_sql = mysqli_fetch_array(mysqli_query($mysqli, "SELECT config_ticket_next_number FROM settings WHERE company_id = 1"));
    $ticket_number = intval($ticket_number_sql['config_ticket_next_number']);
    $new_config_ticket_next_number = $ticket_number + 1;
    mysqli_query($mysqli, "UPDATE settings SET config_ticket_next_number = $new_config_ticket_next_number WHERE company_id = 1");

    // Clean up the message
    $message = trim($message);
    $message = preg_replace('/\s+/', ' ', $message);
    $message = nl2br($message);
    $message = "<i>Email from: <b>$contact_name</b> &lt;$contact_email&gt; at $date:-</i> <br><br><div style='line-height:1.5;'>$message</div>";

    $ticket_prefix_esc = mysqli_real_escape_string($mysqli, $config_ticket_prefix);
    $message_esc = mysqli_real_escape_string($mysqli, $message);
    $contact_email_esc = mysqli_real_escape_string($mysqli, $contact_email);
    $client_id = intval($client_id);

    $url_key = randomString(156);

    mysqli_query($mysqli, "INSERT INTO tickets SET ticket_prefix = '$ticket_prefix_esc', ticket_number = $ticket_number, ticket_source = 'Email', ticket_subject = '$subject', ticket_details = '$message_esc', ticket_priority = 'Low', ticket_status = 1, ticket_billable = $config_ticket_default_billable, ticket_created_by = 0, ticket_contact_id = $contact_id, ticket_url_key = '$url_key', ticket_client_id = $client_id");
    $id = mysqli_insert_id($mysqli);

    // Logging
    logAction("Ticket", "Create", "Email parser: Client contact $contact_email_esc created ticket $ticket_prefix_esc$ticket_number ($subject) ($id)", $client_id, $id);

    mkdirMissing('../uploads/tickets/');
    $att_dir = "../uploads/tickets/" . $id . "/";
    mkdirMissing($att_dir);

    // Move original .eml into the ticket folder
    rename("../uploads/tmp/{$original_message_file}", "{$att_dir}/{$original_message_file}");
    $original_message_file_esc = mysqli_real_escape_string($mysqli, $original_message_file);
    mysqli_query($mysqli, "INSERT INTO ticket_attachments SET ticket_attachment_name = 'Original-parsed-email.eml', ticket_attachment_reference_name = '$original_message_file_esc', ticket_attachment_ticket_id = $id");

    // Save non-inline attachments
    foreach ($attachments as $attachment) {
        $att_name = $attachment['name'];
        $att_extension = strtolower(pathinfo($att_name, PATHINFO_EXTENSION));

        if (in_array($att_extension, $allowed_extensions)) {
            $att_saved_filename = md5(uniqid(rand(), true)) . '.' . $att_extension;
            $att_saved_path = $att_dir . $att_saved_filename;
            file_put_contents($att_saved_path, $attachment['content']);

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

    // Guest ticket watchers
    if ($client_id == 0) {
        mysqli_query($mysqli, "INSERT INTO ticket_watchers SET watcher_email = '$contact_email_esc', watcher_ticket_id = $id");
    }

    $data = [];
    if ($config_ticket_client_general_notifications == 1) {
        $subject_email = "Ticket created - [$config_ticket_prefix$ticket_number] - $subject";
        $body = "<i style='color: #808080'>##- Please type your reply above this line -##</i><br><br>Hello $contact_name,<br><br>Thank you for your email. A ticket regarding \"$subject\" has been automatically created for you.<br><br>Ticket: $config_ticket_prefix$ticket_number<br>Subject: $subject<br>Status: New<br>Portal: <a href='https://$config_base_url/guest/guest_view_ticket.php?ticket_id=$id&url_key=$url_key'>View ticket</a><br><br>--<br>$company_name - Support<br>$config_ticket_from_email<br>$company_phone";
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
        if ($client_id == 0) {
            $client_name = "Guest";
        } else {
            $client_sql = mysqli_query($mysqli, "SELECT client_name FROM clients WHERE client_id = $client_id");
            $client_row = mysqli_fetch_array($client_sql);
            $client_name = sanitizeInput($client_row['client_name']);
        }
        $email_subject = "$config_app_name - New Ticket - $client_name: $subject";
        $email_body = "Hello, <br><br>This is a notification that a new ticket has been raised in ITFlow. <br>Client: $client_name<br>Priority: Low (email parsed)<br>Link: https://$config_base_url/agent/ticket.php?ticket_id=$id <br><br>--------------------------------<br><br><b>$subject</b><br>$message";

        $data[] = [
            'from' => $config_ticket_from_email,
            'from_name' => $config_ticket_from_name,
            'recipient' => $config_ticket_new_ticket_notification_email,
            'recipient_name' => $config_ticket_from_name,
            'subject' => $email_subject,
            'body' => mysqli_real_escape_string($mysqli, $email_body)
        ];
    }

    addToMailQueue($data);
    customAction('ticket_create', $id);

    return true;
}

function addReply($from_email, $date, $subject, $ticket_number, $message, $attachments) {
    global $mysqli, $config_app_name, $company_name, $company_phone, $config_ticket_prefix, $config_base_url, $config_ticket_from_name, $config_ticket_from_email, $allowed_extensions;

    $ticket_reply_type = 'Client';
    $message_parts = explode("##- Please type your reply above this line -##", $message);
    $message_body = $message_parts[0];
    $message_body = trim($message_body);
    $message_body = preg_replace('/\r\n|\r|\n/', ' ', $message_body);
    $message_body = nl2br($message_body);

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
            $ticket_number_esc2 = mysqli_real_escape_string($mysqli, $ticket_number);

            appNotify("Ticket", "Email parser: $from_email attempted to re-open ticket $config_ticket_prefix_esc$ticket_number_esc2 (ID $ticket_id) - check inbox manually to see email", "/agent/ticket.php?ticket_id=$ticket_id", $client_id);

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

            addToMailQueue($data);
            return true;
        }

        if (empty($ticket_contact_email) || $ticket_contact_email !== $from_email) {
            $from_email_esc2 = mysqli_real_escape_string($mysqli, $from_email);
            $row2 = mysqli_fetch_array(mysqli_query($mysqli, "SELECT contact_id FROM contacts WHERE contact_email = '$from_email_esc2' AND contact_client_id = $client_id LIMIT 1"));
            if ($row2) {
                $ticket_reply_contact = intval($row2['contact_id']);
            } else {
                $ticket_reply_type = 'Internal';
                $ticket_reply_contact = '0';
                $message = "<b>WARNING: Contact email mismatch</b><br>$message";
                $message_esc = mysqli_real_escape_string($mysqli, $message);
            }
        }

        mysqli_query($mysqli, "INSERT INTO ticket_replies SET ticket_reply = '$message_esc', ticket_reply_type = '$ticket_reply_type', ticket_reply_time_worked = '00:00:00', ticket_reply_by = $ticket_reply_contact, ticket_reply_ticket_id = $ticket_id");
        $reply_id = mysqli_insert_id($mysqli);

        $ticket_dir = "../uploads/tickets/" . $ticket_id . "/";
        mkdirMissing($ticket_dir);

        foreach ($attachments as $attachment) {
            $att_name = $attachment['name'];
            $att_extension = strtolower(pathinfo($att_name, PATHINFO_EXTENSION));

            if (in_array($att_extension, $allowed_extensions)) {
                $att_saved_filename = md5(uniqid(rand(), true)) . '.' . $att_extension;
                $att_saved_path = $ticket_dir . $att_saved_filename;
                file_put_contents($att_saved_path, $attachment['content']);

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
            $row3 = mysqli_fetch_array($ticket_assigned_to_sql);
            $ticket_assigned_to = intval($row3['ticket_assigned_to']);

            if ($ticket_assigned_to) {
                $tech_sql = mysqli_query($mysqli, "SELECT user_email, user_name FROM users WHERE user_id = $ticket_assigned_to LIMIT 1");
                $tech_row = mysqli_fetch_array($tech_sql);
                $tech_email = sanitizeInput($tech_row['user_email']);
                $tech_name = sanitizeInput($tech_row['user_name']);

                $email_subject = "$config_app_name - Ticket updated - [$config_ticket_prefix$ticket_number] $ticket_subject";
                $email_body    = "Hello $tech_name,<br><br>A new reply has been added to the below ticket.<br><br>Client: $client_name<br>Ticket: $config_ticket_prefix$ticket_number<br>Subject: $ticket_subject<br>Link: https://$config_base_url/agent/ticket.php?ticket_id=$ticket_id<br><br>--------------------------------<br>$message_esc";

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
                addToMailQueue($data);
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

/** ------------------------------------------------------------------
 * OAuth helpers + provider guard
 * ------------------------------------------------------------------ */

// returns true if expires_at ('Y-m-d H:i:s') is in the past (or missing)
function tokenExpired(?string $expires_at): bool {
    if (empty($expires_at)) return true;
    $ts = strtotime($expires_at);
    if ($ts === false) return true;
    // refresh a little early (60s) to avoid race
    return ($ts - 60) <= time();
}

// very small form-encoded POST helper using curl
function httpFormPost(string $url, array $fields): array {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields, '', '&'));
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    $raw = curl_exec($ch);
    $err = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['ok' => ($raw !== false && $code >= 200 && $code < 300), 'body' => $raw, 'code' => $code, 'err' => $err];
}

/**
 * Get a valid access token for Google Workspace IMAP via refresh token if needed.
 * Uses settings: config_mail_oauth_client_id / _client_secret / _refresh_token / _access_token / _access_token_expires_at
 * Updates globals if refreshed (so later logging can reflect it if you want to persist).
 */
function getGoogleAccessToken(string $username): ?string {
    // pull from global settings variables you already load
    global $mysqli,
           $config_mail_oauth_client_id,
           $config_mail_oauth_client_secret,
           $config_mail_oauth_refresh_token,
           $config_mail_oauth_access_token,
           $config_mail_oauth_access_token_expires_at;

    // If we have a not-expired token, use it
    if (!empty($config_mail_oauth_access_token) && !tokenExpired($config_mail_oauth_access_token_expires_at)) {
        return $config_mail_oauth_access_token;
    }

    // Need to refresh?
    if (empty($config_mail_oauth_client_id) || empty($config_mail_oauth_client_secret) || empty($config_mail_oauth_refresh_token)) {
        // Nothing we can do
        return null;
    }

    $resp = httpFormPost(
        'https://oauth2.googleapis.com/token',
        [
            'client_id'     => $config_mail_oauth_client_id,
            'client_secret' => $config_mail_oauth_client_secret,
            'refresh_token' => $config_mail_oauth_refresh_token,
            'grant_type'    => 'refresh_token',
        ]
    );

    if (!$resp['ok']) return null;

    $json = json_decode($resp['body'], true);
    if (!is_array($json) || empty($json['access_token'])) return null;

    // Calculate new expiry
    $expires_at = date('Y-m-d H:i:s', time() + (int)($json['expires_in'] ?? 3600));

    // Update in-memory globals (and persist to DB)
    $config_mail_oauth_access_token = $json['access_token'];
    $config_mail_oauth_access_token_expires_at = $expires_at;

    $at_esc  = mysqli_real_escape_string($mysqli, $config_mail_oauth_access_token);
    $exp_esc = mysqli_real_escape_string($mysqli, $config_mail_oauth_access_token_expires_at);
    mysqli_query($mysqli, "UPDATE settings SET
        config_mail_oauth_access_token = '{$at_esc}',
        config_mail_oauth_access_token_expires_at = '{$exp_esc}'
        WHERE company_id = 1
    ");

    return $config_mail_oauth_access_token;
}

/**
 * Get a valid access token for Microsoft 365 IMAP via refresh token if needed.
 * Uses settings: config_mail_oauth_client_id / _client_secret / _tenant_id / _refresh_token / _access_token / _access_token_expires_at
 */
function getMicrosoftAccessToken(string $username): ?string {
    global $mysqli,
           $config_mail_oauth_client_id,
           $config_mail_oauth_client_secret,
           $config_mail_oauth_tenant_id,
           $config_mail_oauth_refresh_token,
           $config_mail_oauth_access_token,
           $config_mail_oauth_access_token_expires_at;

    if (!empty($config_mail_oauth_access_token) && !tokenExpired($config_mail_oauth_access_token_expires_at)) {
        return $config_mail_oauth_access_token;
    }

    if (empty($config_mail_oauth_client_id) || empty($config_mail_oauth_client_secret) || empty($config_mail_oauth_refresh_token) || empty($config_mail_oauth_tenant_id)) {
        return null;
    }

    $url = "https://login.microsoftonline.com/".rawurlencode($config_mail_oauth_tenant_id)."/oauth2/v2.0/token";

    $resp = httpFormPost($url, [
        'client_id'     => $config_mail_oauth_client_id,
        'client_secret' => $config_mail_oauth_client_secret,
        'refresh_token' => $config_mail_oauth_refresh_token,
        'grant_type'    => 'refresh_token',
        // IMAP/SMTP scopes typically included at initial consent; not needed for refresh
    ]);

    if (!$resp['ok']) return null;

    $json = json_decode($resp['body'], true);
    if (!is_array($json) || empty($json['access_token'])) return null;

    $expires_at = date('Y-m-d H:i:s', time() + (int)($json['expires_in'] ?? 3600));

    $config_mail_oauth_access_token = $json['access_token'];
    $config_mail_oauth_access_token_expires_at = $expires_at;

    $at_esc  = mysqli_real_escape_string($mysqli, $config_mail_oauth_access_token);
    $exp_esc = mysqli_real_escape_string($mysqli, $config_mail_oauth_access_token_expires_at);
    mysqli_query($mysqli, "UPDATE settings SET
        config_mail_oauth_access_token = '{$at_esc}',
        config_mail_oauth_access_token_expires_at = '{$exp_esc}'
        WHERE company_id = 1
    ");

    return $config_mail_oauth_access_token;
}

// Provider from settings (may be NULL/empty to disable IMAP polling)
$imap_provider = $config_imap_provider ?? '';
if ($imap_provider === null) $imap_provider = '';

if ($imap_provider === '') {
    // IMAP disabled by admin: exit cleanly
    logApp("Cron-Email-Parser", "info", "IMAP polling skipped: provider not configured.");
    @unlink($lock_file_path);
    exit(0);
}

/** ------------------------------------------------------------------
 * Webklex IMAP setup (supports Standard / Google OAuth / Microsoft OAuth)
 * ------------------------------------------------------------------ */
use Webklex\PHPIMAP\ClientManager;

$validate_cert = true;

// Defaults from settings (standard IMAP)
$host = $config_imap_host;
$port = (int)$config_imap_port;
$encr = !empty($config_imap_encryption) ? $config_imap_encryption : 'notls'; // 'ssl'|'tls'|'notls'
$user = $config_imap_username;
$pass = $config_imap_password;
$auth = null; // 'oauth' for OAuth providers

if ($imap_provider === 'google_oauth') {
    $host = 'imap.gmail.com';
    $port = 993;
    $encr = 'ssl';
    $auth = 'oauth';
    $pass = getGoogleAccessToken($user);
    if (empty($pass)) {
        logApp("Cron-Email-Parser", "error", "Google OAuth: no usable access token (check refresh token/client credentials).");
        @unlink($lock_file_path);
        exit(1);
    }
} elseif ($imap_provider === 'microsoft_oauth') {
    $host = 'outlook.office365.com';
    $port = 993;
    $encr = 'ssl';
    $auth = 'oauth';
    $pass = getMicrosoftAccessToken($user);
    if (empty($pass)) {
        logApp("Cron-Email-Parser", "error", "Microsoft OAuth: no usable access token (check refresh token/client credentials/tenant).");
        @unlink($lock_file_path);
        exit(1);
    }
} else {
    // standard_imap (username/password)
    if (empty($host) || empty($port) || empty($user)) {
        logApp("Cron-Email-Parser", "error", "Standard IMAP: missing host/port/username.");
        @unlink($lock_file_path);
        exit(1);
    }
}

$cm = new ClientManager();

$client = $cm->make(array_filter([
    'host'           => $host,
    'port'           => $port,
    'encryption'     => $encr,            // 'ssl' | 'tls' | null
    'validate_cert'  => (bool)$validate_cert,
    'username'       => $user,            // full mailbox address (OAuth uses user as principal)
    'password'       => $pass,            // access token when $auth === 'oauth'
    'authentication' => $auth,            // 'oauth' or null
    'protocol'       => 'imap',
]));

try {
    $client->connect();
} catch (\Throwable $e) {
    echo "Error connecting to IMAP server: " . $e->getMessage();
    @unlink($lock_file_path);
    exit(1);
}

$inbox = $client->getFolderByPath('INBOX');

$targetFolderPath = 'ITFlow';
try {
    $targetFolder = $client->getFolderByPath($targetFolderPath);
} catch (\Throwable $e) {
    $client->createFolder($targetFolderPath);
    $targetFolder = $client->getFolderByPath($targetFolderPath);
}

// Fetch unseen messages
$messages = $inbox->messages()->leaveUnread()->unseen()->get();

// Counters
$processed_count = 0;
$unprocessed_count = 0;

// Process messages
foreach ($messages as $message) {
    $email_processed = false;

    // Save original message as .eml (getRawMessage() doesn't seem to work properly)
    mkdirMissing('../uploads/tmp/');
    $original_message_file = "processed-eml-" . randomString(200) . ".eml";
    $raw_message = (string)$message->getHeader()->raw . "\r\n\r\n" . ($message->getRawBody() ?? $message->getHTMLBody() ?? $message->getTextBody());
    file_put_contents("../uploads/tmp/{$original_message_file}", $raw_message);

    // From
    $fromCol    = $message->getFrom();
    $fromFirst  = ($fromCol && $fromCol->count()) ? $fromCol->first() : null;
    $from_email = sanitizeInput($fromFirst->mail ?? 'itflow-guest@example.com');
    $from_name  = sanitizeInput($fromFirst->personal ?? 'Unknown');

    $from_domain = explode("@", $from_email);
    $from_domain = sanitizeInput(end($from_domain));

    // Subject
    $subject = sanitizeInput((string)$message->getSubject() ?: 'No Subject');

    // Date (string)
    $dateAttr = $message->getDate();                  // Attribute
    $dateRaw  = $dateAttr ? (string)$dateAttr : '';   // e.g. "Tue, 10 Sep 2025 13:22:05 +0000"
    $ts       = $dateRaw ? strtotime($dateRaw) : false;
    $date     = sanitizeInput($ts !== false ? date('Y-m-d H:i:s', $ts) : date('Y-m-d H:i:s'));

    // Body (prefer HTML)
    $message_body_html = $message->getHTMLBody();
    $message_body_text = $message->getTextBody();
    $message_body = $message_body_html ?: nl2br(htmlspecialchars((string)$message_body_text));

    // Handle attachments (inline vs regular)
    $attachments = [];
    foreach ($message->getAttachments() as $att) {
        $attrs   = $att->getAttributes(); // v6.2: canonical source
        $dispo   = strtolower((string)($attrs['disposition'] ?? ''));
        $cid     = $attrs['id'] ?? null;            // Content-ID
        $content = $attrs['content'] ?? null;       // binary
        $mime    = $att->getMimeType();
        $name    = $att->getName() ?: 'attachment';

        $is_inline = false;
        if ($dispo === 'inline' && $cid && $content !== null) {
            $cid_trim  = trim($cid, '<>');
            $dataUri   = "data:$mime;base64,".base64_encode($content);
            $message_body = str_replace(["cid:$cid_trim", "cid:$cid"], $dataUri, $message_body);
            $is_inline = true;
        }

        if (!$is_inline && $content !== null) {
            $attachments[] = ['name' => $name, 'content' => $content];
        }
    }

    // Decide whether it's a reply to an existing ticket or a new ticket
    if (preg_match("/\[$config_ticket_prefix(\d+)\]/", $subject, $ticket_number_matches)) {
        $ticket_number = intval($ticket_number_matches[1]);
        if (addReply($from_email, $date, $subject, $ticket_number, $message_body, $attachments)) {
            $email_processed = true;
        }
    } else {
        // Known contact?
        $from_email_esc = mysqli_real_escape_string($mysqli, $from_email);
        $any_contact_sql = mysqli_query($mysqli, "SELECT * FROM contacts WHERE contact_email = '$from_email_esc' LIMIT 1");
        $rowc = mysqli_fetch_array($any_contact_sql);

        if ($rowc) {
            $contact_name  = sanitizeInput($rowc['contact_name']);
            $contact_id    = intval($rowc['contact_id']);
            $contact_email = sanitizeInput($rowc['contact_email']);
            $client_id     = intval($rowc['contact_client_id']);

            if (addTicket($contact_id, $contact_name, $contact_email, $client_id, $date, $subject, $message_body, $attachments, $original_message_file)) {
                $email_processed = true;
            }
        } else {
            // Known domain?
            $from_domain_esc = mysqli_real_escape_string($mysqli, $from_domain);
            $domain_sql = mysqli_query($mysqli, "SELECT * FROM domains WHERE domain_name = '$from_domain_esc' LIMIT 1");
            $rowd = mysqli_fetch_assoc($domain_sql);

            if ($rowd && $from_domain == $rowd['domain_name']) {
                $client_id = intval($rowd['domain_client_id']);

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
                // Unknown sender allowed?
                $bad_from_pattern = "/daemon|postmaster/i";
                if (!(preg_match($bad_from_pattern, $from_email))) {
                    if (addTicket(0, $from_name, $from_email, 0, $date, $subject, $message_body, $attachments, $original_message_file)) {
                        $email_processed = true;
                    }
                }
            }
        }
    }

    // Flag/move based on processing result
    if ($email_processed) {
        $processed_count++; // increment first so a move failure doesn't hide the success
        try {
            $message->setFlag('Seen');
            // Move using the Folder object (top-level "ITFlow")
            $message->move($targetFolderPath);
            // optional: logApp("Cron-Email-Parser", "info", "Moved message to ITFlow");
        } catch (\Throwable $e) {
            // >>> Put the extra logging RIGHT HERE
            $subj = (string)$message->getSubject();
            $uid  = method_exists($message, 'getUid') ? $message->getUid() : 'n/a';
            $path = property_exists($targetFolder, 'path') ? $targetFolder->path : 'ITFlow';
            logApp(
                "Cron-Email-Parser",
                "warning",
                "Move failed (subject=\"$subj\", uid=$uid) to [$path]: ".$e->getMessage()
            );
        }
    } else {
        $unprocessed_count++;
        try {
            $message->setFlag('Flagged');
            $message->unsetFlag('Seen');
        } catch (\Throwable $e) {
            logApp("Cron-Email-Parser", "warning", "Flag update failed: ".$e->getMessage());
        }
    }

    // Cleanup temp .eml if still present (e.g., reply path)
    if (isset($original_message_file)) {
        $tmp_path = "../uploads/tmp/{$original_message_file}";
        if (file_exists($tmp_path)) { @unlink($tmp_path); }
    }
}

// Expunge & disconnect
try {
    $client->expunge();
} catch (\Throwable $e) {
    // ignore
}
$client->disconnect();

// Execution timing (optional)
$script_end_time = microtime(true);
$execution_time = $script_end_time - $script_start_time;
$execution_time_formatted = number_format($execution_time, 2);

$processed_info = "Processed: $processed_count email(s), Unprocessed: $unprocessed_count email(s)";
// logAction("Cron-Email-Parser", "Execution", "Cron Email Parser executed in $execution_time_formatted seconds. $processed_info");

// Remove the lock file
unlink($lock_file_path);

// DEBUG
echo "\nLock File Path: $lock_file_path\n";
if (file_exists($lock_file_path)) {
    echo "\nLock is present\n\n";
}
echo "Processed Emails into tickets: $processed_count\n";
echo "Unprocessed Emails: $unprocessed_count\n";
