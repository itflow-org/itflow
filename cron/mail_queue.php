<?php
// Set working directory to the directory this cron script lives at.
chdir(dirname(__FILE__));

// Ensure we're running from command line
if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line.\n");
}

require_once "../config.php";
require_once "../includes/inc_set_timezone.php";
require_once "../functions.php";
require_once "../libs/vendor/autoload.php";

// PHP Mailer Libs
require_once "../libs/PHPMailer/src/Exception.php";
require_once "../libs/PHPMailer/src/PHPMailer.php";
require_once "../libs/PHPMailer/src/SMTP.php";
require_once "../libs/PHPMailer/src/OAuthTokenProvider.php";
require_once "../libs/PHPMailer/src/OAuth.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\OAuthTokenProvider;

if (!defined('GOOGLE_OAUTH_TOKEN_URL')) {
    define('GOOGLE_OAUTH_TOKEN_URL', 'https://oauth2.googleapis.com/token');
}

if (!defined('MICROSOFT_OAUTH_BASE_URL')) {
    define('MICROSOFT_OAUTH_BASE_URL', 'https://login.microsoftonline.com/');
}

/** =======================================================================
 *  XOAUTH2 Token Provider for PHPMailer (simple “static” provider)
 * ======================================================================= */
class StaticTokenProvider implements OAuthTokenProvider {
    private string $email;
    private string $accessToken;
    public function __construct(string $email, string $accessToken) {
        $this->email = $email;
        $this->accessToken = $accessToken;
    }
    public function getOauth64(): string {
        $auth = "user={$this->email}\x01auth=Bearer {$this->accessToken}\x01\x01";
        return base64_encode($auth);
    }
}

/** =======================================================================
 *  Load settings
 * ======================================================================= */
$sql_settings = mysqli_query($mysqli, "SELECT * FROM settings WHERE company_id = 1");
$row = mysqli_fetch_assoc($sql_settings);

$config_enable_cron      = intval($row['config_enable_cron']);

// SMTP baseline
$config_smtp_host        = $row['config_smtp_host'];
$config_smtp_username    = $row['config_smtp_username'];
$config_smtp_password    = $row['config_smtp_password'];
$config_smtp_port        = intval($row['config_smtp_port']);
$config_smtp_encryption  = $row['config_smtp_encryption'];

// SMTP provider + shared OAuth fields
$config_smtp_provider                      = $row['config_smtp_provider']; // 'standard_smtp' | 'google_oauth' | 'microsoft_oauth'
$config_mail_oauth_client_id               = $row['config_mail_oauth_client_id'] ?? '';
$config_mail_oauth_client_secret           = $row['config_mail_oauth_client_secret'] ?? '';
$config_mail_oauth_tenant_id               = $row['config_mail_oauth_tenant_id'] ?? '';
$config_mail_oauth_refresh_token           = $row['config_mail_oauth_refresh_token'] ?? '';
$config_mail_oauth_access_token            = $row['config_mail_oauth_access_token'] ?? '';
$config_mail_oauth_access_token_expires_at = $row['config_mail_oauth_access_token_expires_at'] ?? '';

if ($config_enable_cron == 0) {
    logApp("Cron-Mail-Queue", "error", "Cron Mail Queue unable to run - cron not enabled in admin settings.");
    exit("Cron: is not enabled -- Quitting..");
}

if (empty($config_smtp_provider)) {
    logApp("Cron-Mail-Queue", "info", "SMTP sending skipped: provider not configured.");
    exit(0);
}

/** =======================================================================
 *  Lock file
 * ======================================================================= */
$temp_dir = sys_get_temp_dir();
$lock_file_path = "{$temp_dir}/itflow_mail_queue_{$installation_id}.lock";

if (file_exists($lock_file_path)) {
    $file_age = time() - filemtime($lock_file_path);
    if ($file_age > 600) {
        unlink($lock_file_path);
        logApp("Cron-Mail-Queue", "warning", "Cron Mail Queue detected a lock file was present but was over 10 minutes old so it removed it.");
    } else {
        logApp("Cron-Mail-Queue", "info", "Cron Mail Queue attempted to execute but was already executing so instead it terminated.");
        exit("Script is already running. Exiting.");
    }
}

file_put_contents($lock_file_path, "Locked");

/** =======================================================================
 *  Mail OAuth helpers + sender function
 * ======================================================================= */
function tokenIsExpired(?string $expires_at): bool {
    if (empty($expires_at)) {
        return true;
    }

    $ts = strtotime($expires_at);

    if ($ts === false) {
        return true;
    }

    return ($ts - 60) <= time();
}

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

    return [
        'ok' => ($raw !== false && $code >= 200 && $code < 300),
        'body' => $raw,
        'code' => $code,
        'err' => $err,
    ];
}

function persistMailOauthTokens(string $access_token, string $expires_at, ?string $refresh_token = null): void {
    global $mysqli;

    $access_token_esc = mysqli_real_escape_string($mysqli, $access_token);
    $expires_at_esc = mysqli_real_escape_string($mysqli, $expires_at);

    $refresh_sql = '';
    if (!empty($refresh_token)) {
        $refresh_token_esc = mysqli_real_escape_string($mysqli, $refresh_token);
        $refresh_sql = ", config_mail_oauth_refresh_token = '{$refresh_token_esc}'";
    }

    mysqli_query($mysqli, "UPDATE settings SET config_mail_oauth_access_token = '{$access_token_esc}', config_mail_oauth_access_token_expires_at = '{$expires_at_esc}'{$refresh_sql} WHERE company_id = 1");
}

function refreshMailOauthAccessToken(string $provider, string $oauth_client_id, string $oauth_client_secret, string $oauth_tenant_id, string $oauth_refresh_token): ?array {
    $result = null;
    $response = null;

    if (!empty($oauth_client_id) && !empty($oauth_client_secret) && !empty($oauth_refresh_token)) {
        if ($provider === 'google_oauth') {
            $response = httpFormPost(GOOGLE_OAUTH_TOKEN_URL, [
                'client_id' => $oauth_client_id,
                'client_secret' => $oauth_client_secret,
                'refresh_token' => $oauth_refresh_token,
                'grant_type' => 'refresh_token',
            ]);
        } elseif ($provider === 'microsoft_oauth' && !empty($oauth_tenant_id)) {
            $token_url = MICROSOFT_OAUTH_BASE_URL . rawurlencode($oauth_tenant_id) . "/oauth2/v2.0/token";
            $response = httpFormPost($token_url, [
                'client_id' => $oauth_client_id,
                'client_secret' => $oauth_client_secret,
                'refresh_token' => $oauth_refresh_token,
                'grant_type' => 'refresh_token',
            ]);
        }
    }

    if (is_array($response) && !empty($response['ok'])) {
        $json = json_decode($response['body'], true);

        if (is_array($json) && !empty($json['access_token'])) {
            $expires_at = date('Y-m-d H:i:s', time() + (int)($json['expires_in'] ?? 3600));
            $result = [
                'access_token' => $json['access_token'],
                'expires_at' => $expires_at,
                'refresh_token' => $json['refresh_token'] ?? null,
            ];
        }
    }

    return $result;
}

function resolveMailOauthAccessToken(string $provider, string $oauth_client_id, string $oauth_client_secret, string $oauth_tenant_id, string $oauth_refresh_token, string $oauth_access_token, string $oauth_access_token_expires_at): ?string {
    if (!empty($oauth_access_token) && !tokenIsExpired($oauth_access_token_expires_at)) {
        return $oauth_access_token;
    }

    $tokens = refreshMailOauthAccessToken($provider, $oauth_client_id, $oauth_client_secret, $oauth_tenant_id, $oauth_refresh_token);

    if (!is_array($tokens) || empty($tokens['access_token']) || empty($tokens['expires_at'])) {
        return null;
    }

    persistMailOauthTokens($tokens['access_token'], $tokens['expires_at'], $tokens['refresh_token'] ?? null);

    return $tokens['access_token'];
}

function sendQueueEmail(
    string $provider,
    string $host,
    int    $port,
    string $encryption,
    string $username,
    string $password,
    string $from_email,
    string $from_name,
    string $to_email,
    string $to_name,
    string $subject,
    string $html_body,
    string $ics_str,
    string $oauth_client_id,
    string $oauth_client_secret,
    string $oauth_tenant_id,
    string $oauth_refresh_token,
    string $oauth_access_token,
    string $oauth_access_token_expires_at
) {
    // Sensible defaults for OAuth providers if fields were left blank
    if ($provider === 'google_oauth') {
        if (!$host) $host = 'smtp.gmail.com';
        if (!$port) $port = 587;
        if (!$encryption) $encryption = 'tls';
        if (!$username) $username = $from_email;
    } elseif ($provider === 'microsoft_oauth') {
        if (!$host) $host = 'smtp.office365.com';
        if (!$port) $port = 587;
        if (!$encryption) $encryption = 'tls';
        if (!$username) $username = $from_email;
    }

    $mail = new PHPMailer(true);
    $mail->CharSet   = "UTF-8";
    $mail->SMTPDebug = 0;
    $mail->isSMTP();
    $mail->Host = $host;
    $mail->Port = $port;

    $enc = strtolower($encryption);
    if ($enc === '' || $enc === 'none') {
        $mail->SMTPAutoTLS = false;
        $mail->SMTPSecure  = false;
        $mail->SMTPOptions = ['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]];
    } else {
        $mail->SMTPSecure = $enc; // 'tls' | 'ssl'
    }

    if ($provider === 'google_oauth' || $provider === 'microsoft_oauth') {
        // XOAUTH2
        $mail->SMTPAuth = true;
        $mail->AuthType = 'XOAUTH2';
        $mail->Username = $username;

        $access_token = resolveMailOauthAccessToken(
            $provider,
            trim($oauth_client_id),
            trim($oauth_client_secret),
            trim($oauth_tenant_id),
            trim($oauth_refresh_token),
            trim($oauth_access_token),
            trim($oauth_access_token_expires_at)
        );

        if (empty($access_token)) {
            throw new Exception("Missing OAuth access token for XOAUTH2 SMTP.");
        }

        $mail->setOAuth(new StaticTokenProvider($username, $access_token));
    } else {
        // Standard SMTP (with or without auth)
        $mail->SMTPAuth = !empty($username);
        $mail->Username = $username ?: '';
        $mail->Password = $password ?: '';
    }

    // Recipients & content
    $mail->setFrom($from_email, $from_name);
    $mail->addAddress($to_email, $to_name);
    $mail->isHTML(true);
    $mail->Subject = $subject;
    $mail->Body = $html_body;

    if (!empty($ics_str)) {
        $mail->addStringAttachment($ics_str, 'Scheduled_ticket.ics', 'base64', 'text/calendar');
    }

    $mail->send();
    return true;
}

/** =======================================================================
 *  SEND: status = 0 (Queued)
 * ======================================================================= */
$sql_queue = mysqli_query($mysqli, "SELECT * FROM email_queue WHERE email_status = 0 AND email_queued_at <= NOW()");

if (mysqli_num_rows($sql_queue) > 0) {
    while ($rowq = mysqli_fetch_assoc($sql_queue)) {
        $email_id             = (int)$rowq['email_id'];
        $email_from           = $rowq['email_from'];
        $email_from_name      = $rowq['email_from_name'];
        $email_recipient      = $rowq['email_recipient'];
        $email_recipient_name = $rowq['email_recipient_name'];
        $email_subject        = $rowq['email_subject'];
        $email_content        = $rowq['email_content'];
        $email_ics_str        = $rowq['email_cal_str'];

        // Check sender
        if (!filter_var($email_from, FILTER_VALIDATE_EMAIL)) {
            $email_from_logging = sanitizeInput($rowq['email_from']);
            mysqli_query($mysqli, "UPDATE email_queue SET email_status = 2, email_attempts = 99 WHERE email_id = $email_id");
            logApp("Cron-Mail-Queue", "Error", "Failed to send email #$email_id due to invalid sender address: $email_from_logging - check configuration in settings.");
            appNotify("Mail", "Failed to send email #$email_id due to invalid sender address");
            continue;
        }

        mysqli_query($mysqli, "UPDATE email_queue SET email_status = 1 WHERE email_id = $email_id");

        // Basic recipient syntax check
        if (!filter_var($email_recipient, FILTER_VALIDATE_EMAIL)) {
            mysqli_query($mysqli, "UPDATE email_queue SET email_status = 2, email_attempts = 99 WHERE email_id = $email_id");
            $email_to_logging = sanitizeInput($email_recipient);
            $email_subject_logging = sanitizeInput($rowq['email_subject']);
            logApp("Cron-Mail-Queue", "Error", "Failed to send email: $email_id to $email_to_logging due to invalid recipient address. Email subject was: $email_subject_logging");
            appNotify("Mail", "Failed to send email #$email_id to $email_to_logging due to invalid recipient address: Email subject was: $email_subject_logging");
            continue;
        }

        // More intelligent recipient MX check (if not disabled with --no-mx-validation)
        $domain = sanitizeInput(substr($email_recipient, strpos($email_recipient, '@') + 1));
        if (!in_array('--no-mx-validation', $argv) && !checkdnsrr($domain, 'MX')) {
            mysqli_query($mysqli, "UPDATE email_queue SET email_status = 2, email_attempts = 99 WHERE email_id = $email_id");
            $email_to_logging = sanitizeInput($email_recipient);
            $email_subject_logging = sanitizeInput($rowq['email_subject']);
            logApp("Cron-Mail-Queue", "Error", "Failed to send email: $email_id to $email_to_logging due to invalid recipient domain (no MX). Email subject was: $email_subject_logging");
            appNotify("Mail", "Failed to send email #$email_id to $email_to_logging due to invalid recipient domain (no MX): Email subject was: $email_subject_logging");
            continue;
        }

        try {
            sendQueueEmail(
                ($config_smtp_provider ?: 'standard_smtp'),
                $config_smtp_host,
                (int)$config_smtp_port,
                (string)$config_smtp_encryption,
                (string)$config_smtp_username,
                (string)$config_smtp_password,
                (string)$email_from,
                (string)$email_from_name,
                (string)$email_recipient,
                (string)$email_recipient_name,
                (string)$email_subject,
                (string)$email_content,
                (string)$email_ics_str,
                (string)$config_mail_oauth_client_id,
                (string)$config_mail_oauth_client_secret,
                (string)$config_mail_oauth_tenant_id,
                (string)$config_mail_oauth_refresh_token,
                (string)$config_mail_oauth_access_token,
                (string)$config_mail_oauth_access_token_expires_at
            );

            mysqli_query($mysqli, "UPDATE email_queue SET email_status = 3, email_sent_at = NOW(), email_attempts = 1 WHERE email_id = $email_id");

        } catch (Exception $e) {
            mysqli_query($mysqli, "UPDATE email_queue SET email_status = 2, email_failed_at = NOW(), email_attempts = 1 WHERE email_id = $email_id");

            $email_recipient_logging = sanitizeInput($rowq['email_recipient']);
            $email_subject_logging   = sanitizeInput($rowq['email_subject']);
            $err = substr("Mailer Error: " . $e->getMessage(), 0, 100) . "...";

            appNotify("Cron-Mail-Queue", "Failed to send email #$email_id to $email_recipient_logging");
            logApp("Cron-Mail-Queue", "Error", "Failed to send email: $email_id to $email_recipient_logging regarding $email_subject_logging. $err");
        }
    }
}

/** =======================================================================
 *  RETRIES: status = 2 (Failed), attempts < 4, wait 30 min
 *  NOTE: Backoff is `email_failed_at <= NOW() - INTERVAL 30 MINUTE`
 * =======================================================================
 */
$sql_failed_queue = mysqli_query(
    $mysqli,
    "SELECT * FROM email_queue
     WHERE email_status = 2
       AND email_attempts < 4
       AND email_failed_at <= NOW() - INTERVAL 30 MINUTE"
);

if (mysqli_num_rows($sql_failed_queue) > 0) {
    while ($rowf = mysqli_fetch_assoc($sql_failed_queue)) {
        $email_id             = (int)$rowf['email_id'];
        $email_from           = $rowf['email_from'];
        $email_from_name      = $rowf['email_from_name'];
        $email_recipient      = $rowf['email_recipient'];
        $email_recipient_name = $rowf['email_recipient_name'];
        $email_subject        = $rowf['email_subject'];
        $email_content        = $rowf['email_content'];
        $email_ics_str        = $rowf['email_cal_str'];
        $email_attempts       = (int)$rowf['email_attempts'] + 1;

        mysqli_query($mysqli, "UPDATE email_queue SET email_status = 1 WHERE email_id = $email_id");

        if (!filter_var($email_recipient, FILTER_VALIDATE_EMAIL)) {
            mysqli_query($mysqli, "UPDATE email_queue SET email_status = 2, email_attempts = $email_attempts WHERE email_id = $email_id");
            continue;
        }

        try {
            sendQueueEmail(
                ($config_smtp_provider ?: 'standard_smtp'),
                $config_smtp_host,
                (int)$config_smtp_port,
                (string)$config_smtp_encryption,
                (string)$config_smtp_username,
                (string)$config_smtp_password,
                (string)$email_from,
                (string)$email_from_name,
                (string)$email_recipient,
                (string)$email_recipient_name,
                (string)$email_subject,
                (string)$email_content,
                (string)$email_ics_str,
                (string)$config_mail_oauth_client_id,
                (string)$config_mail_oauth_client_secret,
                (string)$config_mail_oauth_tenant_id,
                (string)$config_mail_oauth_refresh_token,
                (string)$config_mail_oauth_access_token,
                (string)$config_mail_oauth_access_token_expires_at
            );

            mysqli_query($mysqli, "UPDATE email_queue SET email_status = 3, email_sent_at = NOW(), email_attempts = $email_attempts WHERE email_id = $email_id");

        } catch (Exception $e) {
            mysqli_query($mysqli, "UPDATE email_queue SET email_status = 2, email_failed_at = NOW(), email_attempts = $email_attempts WHERE email_id = $email_id");

            $email_recipient_logging = sanitizeInput($rowf['email_recipient']);
            $email_subject_logging   = sanitizeInput($rowf['email_subject']);
            $err = substr("Mailer Error: " . $e->getMessage(), 0, 100) . "...";

            logApp("Cron-Mail-Queue", "Error", "Failed to re-send email #$email_id to $email_recipient_logging regarding $email_subject_logging. $err");
        }
    }
}

/** =======================================================================
 *  Unlock
 * ======================================================================= */
unlink($lock_file_path);
