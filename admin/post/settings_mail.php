<?php

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (!defined('MICROSOFT_OAUTH_BASE_URL')) {
    define('MICROSOFT_OAUTH_BASE_URL', 'https://login.microsoftonline.com/');
}

if (isset($_POST['oauth_connect_microsoft_mail'])) {

    validateCSRFToken($_POST['csrf_token']);

    // Save the OAuth credential fields from this form so the auth flow uses the latest inputs
    $config_mail_oauth_client_id     = sanitizeInput($_POST['config_mail_oauth_client_id'] ?? '');
    $config_mail_oauth_client_secret = sanitizeInput($_POST['config_mail_oauth_client_secret'] ?? '');
    $config_mail_oauth_tenant_id     = sanitizeInput($_POST['config_mail_oauth_tenant_id'] ?? $config_mail_oauth_tenant_id);
    $config_mail_oauth_refresh_token = sanitizeInput($_POST['config_mail_oauth_refresh_token'] ?? '');
    $config_mail_oauth_access_token  = sanitizeInput($_POST['config_mail_oauth_access_token'] ?? '');

    mysqli_query($mysqli, "UPDATE settings SET
        config_mail_oauth_client_id     = '$config_mail_oauth_client_id',
        config_mail_oauth_client_secret = '$config_mail_oauth_client_secret',
        config_mail_oauth_tenant_id     = '$config_mail_oauth_tenant_id',
        config_mail_oauth_refresh_token = '$config_mail_oauth_refresh_token',
        config_mail_oauth_access_token  = '$config_mail_oauth_access_token'
        WHERE company_id = 1
    ");

    // Check the SAVED providers (loaded from config at bootstrap), not $_POST —
    // the provider dropdowns live in different forms and are never posted here
    if ($config_imap_provider !== 'microsoft_oauth' && $config_smtp_provider !== 'microsoft_oauth') {
        flash_alert("Please set the SMTP or IMAP Provider to Microsoft 365 (OAuth) and save it before connecting.", 'error');
        redirect();
    }

    if (empty($config_mail_oauth_client_id) || empty($config_mail_oauth_client_secret) || empty($config_mail_oauth_tenant_id)) {
        flash_alert("Missing Microsoft OAuth settings. Please provide Client ID, Client Secret, and Tenant ID first.", 'error');
        redirect();
    }

    if (defined('BASE_URL') && !empty(BASE_URL)) {
        $base_url = rtrim((string) BASE_URL, '/');
    } else {
        $base_url = 'https://' . rtrim((string) $config_base_url, '/');
    }

    $redirect_uri = $base_url . '/admin/oauth_microsoft_mail_callback.php';

    try {
        $state = bin2hex(random_bytes(32));
    } catch (Throwable $e) {
        $state = sha1(uniqid((string) mt_rand(), true));
    }

    $_SESSION['mail_oauth_state'] = $state;
    $_SESSION['mail_oauth_state_expires_at'] = time() + 600;

    $scope = 'offline_access openid profile https://outlook.office.com/IMAP.AccessAsUser.All https://outlook.office.com/SMTP.Send';

    $authorize_url = MICROSOFT_OAUTH_BASE_URL . rawurlencode($config_mail_oauth_tenant_id) . '/oauth2/v2.0/authorize?'
        . http_build_query([
            'client_id' => $config_mail_oauth_client_id,
            'response_type' => 'code',
            'redirect_uri' => $redirect_uri,
            'response_mode' => 'query',
            'scope' => $scope,
            'state' => $state,
            'prompt' => 'consent',
        ], '', '&', PHP_QUERY_RFC3986);

    logAction("Settings", "Edit", "$session_name started Microsoft OAuth connect flow for mail settings");

    redirect($authorize_url);
}

if (isset($_POST['edit_mail_smtp_settings'])) {

    validateCSRFToken($_POST['csrf_token']);

    $config_smtp_provider            = sanitizeInput($_POST['config_smtp_provider']);
    $config_smtp_host       = sanitizeInput($_POST['config_smtp_host'] ?? $config_smtp_host);
    $config_smtp_port       = intval($_POST['config_smtp_port'] ?? $config_smtp_port);
    $config_smtp_encryption = sanitizeInput($_POST['config_smtp_encryption'] ?? $config_smtp_encryption);
    $config_smtp_username   = sanitizeInput($_POST['config_smtp_username'] ?? $config_smtp_username);
    $config_smtp_password   = sanitizeInput($_POST['config_smtp_password'] ?? $config_smtp_password);

    mysqli_query($mysqli, "
        UPDATE settings SET
            config_smtp_provider              = '$config_smtp_provider',
            config_smtp_host                  = '$config_smtp_host',
            config_smtp_port                  = $config_smtp_port,
            config_smtp_encryption            = '$config_smtp_encryption',
            config_smtp_username              = '$config_smtp_username',
            config_smtp_password              = '$config_smtp_password'
        WHERE company_id = 1
    ");

    logAction("Settings", "Edit", "$session_name edited SMTP settings");

    flash_alert("SMTP Mail Settings updated");

    redirect();

}

if (isset($_POST['edit_mail_imap_settings'])) {

    validateCSRFToken($_POST['csrf_token']);

    $config_imap_provider            = sanitizeInput($_POST['config_imap_provider']);
    $config_imap_host       = sanitizeInput($_POST['config_imap_host'] ?? $config_imap_host);
    $config_imap_port       = intval($_POST['config_imap_port'] ?? $config_imap_port);
    $config_imap_encryption = sanitizeInput($_POST['config_imap_encryption'] ?? $config_imap_encryption);
    $config_imap_username   = sanitizeInput($_POST['config_imap_username'] ?? $config_imap_username);
    $config_imap_password   = sanitizeInput($_POST['config_imap_password'] ?? $config_imap_password);

    mysqli_query($mysqli, "
        UPDATE settings SET
            config_imap_provider              = '$config_imap_provider',
            config_imap_host                  = '$config_imap_host',
            config_imap_port                  = $config_imap_port,
            config_imap_encryption            = '$config_imap_encryption',
            config_imap_username              = '$config_imap_username',
            config_imap_password              = '$config_imap_password'
        WHERE company_id = 1
    ");

    logAction("Settings", "Edit", "$session_name edited IMAP settings");

    flash_alert("IMAP Mail Settings updated");

    redirect();

}

if (isset($_POST['edit_mail_oauth_settings'])) {

    validateCSRFToken($_POST['csrf_token']);

    $config_mail_oauth_client_id     = sanitizeInput($_POST['config_mail_oauth_client_id'] ?? '');
    $config_mail_oauth_client_secret = sanitizeInput($_POST['config_mail_oauth_client_secret'] ?? '');
    $config_mail_oauth_tenant_id     = sanitizeInput($_POST['config_mail_oauth_tenant_id'] ?? $config_mail_oauth_tenant_id);
    $config_mail_oauth_refresh_token = sanitizeInput($_POST['config_mail_oauth_refresh_token'] ?? '');
    $config_mail_oauth_access_token  = sanitizeInput($_POST['config_mail_oauth_access_token'] ?? '');

    mysqli_query($mysqli, "UPDATE settings SET
        config_mail_oauth_client_id     = '$config_mail_oauth_client_id',
        config_mail_oauth_client_secret = '$config_mail_oauth_client_secret',
        config_mail_oauth_tenant_id     = '$config_mail_oauth_tenant_id',
        config_mail_oauth_refresh_token = '$config_mail_oauth_refresh_token',
        config_mail_oauth_access_token  = '$config_mail_oauth_access_token'
        WHERE company_id = 1
    ");

    logAction("Settings", "Edit", "$session_name edited mail OAuth settings");
    flash_alert("Mail OAuth Settings updated");
    redirect();
}

if (isset($_POST['edit_mail_from_settings'])) {

    validateCSRFToken($_POST['csrf_token']);

    $config_mail_from_email = sanitizeInput(filter_var($_POST['config_mail_from_email'], FILTER_VALIDATE_EMAIL));
    $config_mail_from_name = sanitizeInput(preg_replace('/[^a-zA-Z0-9\s]/', '', $_POST['config_mail_from_name']));

    $config_invoice_from_email = sanitizeInput(filter_var($_POST['config_invoice_from_email'], FILTER_VALIDATE_EMAIL));
    $config_invoice_from_name = sanitizeInput(preg_replace('/[^a-zA-Z0-9\s]/', '', $_POST['config_invoice_from_name']));

    $config_quote_from_email = sanitizeInput(filter_var($_POST['config_quote_from_email'], FILTER_VALIDATE_EMAIL));
    $config_quote_from_name = sanitizeInput(preg_replace('/[^a-zA-Z0-9\s]/', '', $_POST['config_quote_from_name']));

    $config_ticket_from_email = sanitizeInput(filter_var($_POST['config_ticket_from_email'], FILTER_VALIDATE_EMAIL));
    $config_ticket_from_name = sanitizeInput(preg_replace('/[^a-zA-Z0-9\s]/', '', $_POST['config_ticket_from_name']));

    mysqli_query($mysqli,"UPDATE settings SET config_mail_from_email = '$config_mail_from_email', config_mail_from_name = '$config_mail_from_name', config_invoice_from_email = '$config_invoice_from_email', config_invoice_from_name = '$config_invoice_from_name', config_quote_from_email = '$config_quote_from_email', config_quote_from_name = '$config_quote_from_name', config_ticket_from_email = '$config_ticket_from_email', config_ticket_from_name = '$config_ticket_from_name' WHERE company_id = 1");

    logAction("Settings", "Edit", "$session_name edited mail from settings");

    flash_alert("Mail From Settings updated");

    redirect();

}

if (isset($_POST['test_email_smtp'])) {

    validateCSRFToken($_POST['csrf_token']);

    $test_email = intval($_POST['test_email']);

    if($test_email == 1) {
        $email_from = sanitizeInput($config_mail_from_email);
        $email_from_name = sanitizeInput($config_mail_from_name);
    } elseif ($test_email == 2) {
        $email_from = sanitizeInput($config_invoice_from_email);
        $email_from_name = sanitizeInput($config_invoice_from_name);
    } elseif ($test_email == 3) {
        $email_from = sanitizeInput($config_quote_from_email);
        $email_from_name = sanitizeInput($config_quote_from_name);
    } else {
        $email_from = sanitizeInput($config_ticket_from_email);
        $email_from_name = sanitizeInput($config_ticket_from_name);
    }

    $email_to = sanitizeInput($_POST['email_to']);
    $subject = "Test email from ITFlow";
    $body = "This is a test email from ITFlow. If you are reading this, it worked!";

    $data = [
        [
            'from' => $email_from,
            'from_name' => $email_from_name,
            'recipient' => $email_to,
            'recipient_name' => 'Chap',
            'subject' => $subject,
            'body' => $body
        ]
    ];

    $mail = addToMailQueue($data);

    if ($mail === true) {
        flash_alert("Test email queued! <a class='text-bold text-light' href='mail_queue.php'>Check Admin > Mail queue</a>");
    } else {
        flash_alert("Failed to add test mail to queue", 'error');
    }

    redirect();

}

if (isset($_POST['test_email_imap'])) {

    validateCSRFToken($_POST['csrf_token']);

    $provider = sanitizeInput($config_imap_provider ?? '');

    $host       = $config_imap_host;
    $port       = (int) $config_imap_port;
    $encryption = strtolower(trim($config_imap_encryption)); // e.g. "ssl", "tls", "none"
    $username   = $config_imap_username;
    $password   = $config_imap_password;

    // Shared OAuth fields
    $config_mail_oauth_client_id               = $config_mail_oauth_client_id ?? '';
    $config_mail_oauth_client_secret           = $config_mail_oauth_client_secret ?? '';
    $config_mail_oauth_tenant_id               = $config_mail_oauth_tenant_id ?? '';
    $config_mail_oauth_refresh_token           = $config_mail_oauth_refresh_token ?? '';
    $config_mail_oauth_access_token            = $config_mail_oauth_access_token ?? '';
    $config_mail_oauth_access_token_expires_at = $config_mail_oauth_access_token_expires_at ?? '';

    $is_oauth = ($provider === 'google_oauth' || $provider === 'microsoft_oauth');

    if ($provider === 'google_oauth') {
        if (empty($host)) {
            $host = 'imap.gmail.com';
        }
        if (empty($port)) {
            $port = 993;
        }
        if (empty($encryption)) {
            $encryption = 'ssl';
        }
    } elseif ($provider === 'microsoft_oauth') {
        if (empty($host)) {
            $host = 'outlook.office365.com';
        }
        if (empty($port)) {
            $port = 993;
        }
        if (empty($encryption)) {
            $encryption = 'ssl';
        }
    }

    if (empty($host) || empty($port) || empty($username)) {
        flash_alert("<strong>IMAP connection failed:</strong> Missing host, port, or username.", 'error');
        redirect();
    }

    $token_is_expired = function (?string $expires_at): bool {
        if (empty($expires_at)) {
            return true;
        }

        $ts = strtotime($expires_at);

        if ($ts === false) {
            return true;
        }

        return ($ts - 60) <= time();
    };

    $http_form_post = function (string $url, array $fields): array {
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
    };

    if ($is_oauth) {
        if (!empty($config_mail_oauth_access_token) && !$token_is_expired($config_mail_oauth_access_token_expires_at)) {
            $password = $config_mail_oauth_access_token;
        } else {
            if (empty($config_mail_oauth_client_id) || empty($config_mail_oauth_client_secret) || empty($config_mail_oauth_refresh_token)) {
                flash_alert("<strong>IMAP OAuth failed:</strong> Missing OAuth client credentials or refresh token.", 'error');
                redirect();
            }

            if ($provider === 'google_oauth') {
                $response = $http_form_post('https://oauth2.googleapis.com/token', [
                    'client_id' => $config_mail_oauth_client_id,
                    'client_secret' => $config_mail_oauth_client_secret,
                    'refresh_token' => $config_mail_oauth_refresh_token,
                    'grant_type' => 'refresh_token',
                ]);
            } else {
                if (empty($config_mail_oauth_tenant_id)) {
                    flash_alert("<strong>IMAP OAuth failed:</strong> Microsoft tenant ID is required.", 'error');
                    redirect();
                }

                $token_url = MICROSOFT_OAUTH_BASE_URL . rawurlencode($config_mail_oauth_tenant_id) . "/oauth2/v2.0/token";
                $response = $http_form_post($token_url, [
                    'client_id' => $config_mail_oauth_client_id,
                    'client_secret' => $config_mail_oauth_client_secret,
                    'refresh_token' => $config_mail_oauth_refresh_token,
                    'grant_type' => 'refresh_token',
                ]);
            }

            if (!$response['ok']) {
                flash_alert("<strong>IMAP OAuth failed:</strong> Could not refresh access token.", 'error');
                redirect();
            }

            $json = json_decode($response['body'], true);
            if (!is_array($json) || empty($json['access_token'])) {
                flash_alert("<strong>IMAP OAuth failed:</strong> Token response did not include an access token.", 'error');
                redirect();
            }

            $password = $json['access_token'];
            $expires_at = date('Y-m-d H:i:s', time() + (int)($json['expires_in'] ?? 3600));
            $refresh_token_to_save = $json['refresh_token'] ?? null;

            $token_esc = mysqli_real_escape_string($mysqli, $password);
            $expires_at_esc = mysqli_real_escape_string($mysqli, $expires_at);

            $refresh_sql = '';
            if (!empty($refresh_token_to_save)) {
                $refresh_token_esc = mysqli_real_escape_string($mysqli, $refresh_token_to_save);
                $refresh_sql = ", config_mail_oauth_refresh_token = '{$refresh_token_esc}'";
            }

            mysqli_query($mysqli, "UPDATE settings SET config_mail_oauth_access_token = '{$token_esc}', config_mail_oauth_access_token_expires_at = '{$expires_at_esc}'{$refresh_sql} WHERE company_id = 1");
        }
    }

    // Build remote socket (implicit SSL vs plain TCP)
    $transport = 'tcp';
    if ($encryption === 'ssl') {
        $transport = 'ssl';
    }

    $remote_socket = $transport . '://' . $host . ':' . $port;

    // Stream context (you can tighten these if you want strict validation)
    $context_options = [];
    if (in_array($encryption, ['ssl', 'tls'], true)) {
        $context_options['ssl'] = [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true,
        ];
    }

    $context = stream_context_create($context_options);

    try {
        $errno = 0;
        $errstr = '';

        // 10-second timeout, adjust as needed
        $fp = @stream_socket_client(
            $remote_socket,
            $errno,
            $errstr,
            10,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if (!$fp) {
            throw new Exception("Could not connect to IMAP server: [$errno] $errstr");
        }

        stream_set_timeout($fp, 10);

        // Read server greeting (IMAP servers send something like: * OK Dovecot ready)
        $greeting = fgets($fp, 1024);
        if ($greeting === false || strpos($greeting, '* OK') !== 0) {
            fclose($fp);
            throw new Exception("Invalid IMAP greeting: " . trim((string) $greeting));
        }
        // If you really want STARTTLS for "tls" (port 143), you can do it here
        if ($encryption === 'tls' && stripos($greeting, 'STARTTLS') !== false) {
            fwrite($fp, "A0001 STARTTLS\r\n");
            $line = fgets($fp, 1024);
            if ($line === false || stripos($line, 'A0001 OK') !== 0) {
                fclose($fp);
                throw new Exception("STARTTLS failed: " . trim((string) $line));
            }

            if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                fclose($fp);
                throw new Exception("Unable to enable TLS encryption on IMAP connection.");
            }
        }

        $tag = 'A0002';

        if ($is_oauth) {
            $oauth_b64 = base64_encode("user={$username}\x01auth=Bearer {$password}\x01\x01");
            $auth_cmd = sprintf("%s AUTHENTICATE XOAUTH2 %s\r\n", $tag, $oauth_b64);
            fwrite($fp, $auth_cmd);
        } else {
            $login_cmd = sprintf(
                "%s LOGIN \"%s\" \"%s\"\r\n",
                $tag,
                addcslashes($username, "\\\""),
                addcslashes($password, "\\\"")
            );

            fwrite($fp, $login_cmd);
        }

        $success = false;
        $error_line = '';

        while (!feof($fp)) {
            $line = fgets($fp, 2048);
            if ($line === false) {
                break;
            }

            if (strpos($line, $tag . ' ') === 0) {
                if (stripos($line, $tag . ' OK') === 0) {
                    $success = true;
                } else {
                    $error_line = trim($line);
                }
                break;
            }
        }

        // Always logout / close
        fwrite($fp, "A0003 LOGOUT\r\n");
        fclose($fp);

        if ($success) {
            if ($is_oauth) {
                flash_alert("Connected successfully using OAuth");
            } else {
                flash_alert("Connected successfully");
            }
        } else {
            if (!$error_line) {
                $error_line = 'Unknown IMAP authentication error';
            }
            throw new Exception($error_line);
        }

    } catch (Exception $e) {
        flash_alert("<strong>IMAP connection failed:</strong> " . htmlspecialchars($e->getMessage()), 'error');
    }

    redirect();
}


if (isset($_POST['test_oauth_token_refresh'])) {

    validateCSRFToken($_POST['csrf_token']);

    $provider = sanitizeInput($_POST['oauth_provider'] ?? '');

    if ($provider !== 'google_oauth' && $provider !== 'microsoft_oauth') {
        flash_alert("OAuth token test failed: unsupported provider.", 'error');
        redirect();
    }

    $oauth_client_id = sanitizeInput($config_mail_oauth_client_id ?? '');
    $oauth_client_secret = sanitizeInput($config_mail_oauth_client_secret ?? '');
    $oauth_tenant_id = sanitizeInput($config_mail_oauth_tenant_id ?? '');
    $oauth_refresh_token = sanitizeInput($config_mail_oauth_refresh_token ?? '');

    if (empty($oauth_client_id) || empty($oauth_client_secret) || empty($oauth_refresh_token)) {
        flash_alert("OAuth token test failed: missing client ID, client secret, or refresh token.", 'error');
        redirect();
    }

    if ($provider === 'microsoft_oauth' && empty($oauth_tenant_id)) {
        flash_alert("OAuth token test failed: Microsoft tenant ID is required.", 'error');
        redirect();
    }

    $token_url = 'https://oauth2.googleapis.com/token';
    if ($provider === 'microsoft_oauth') {
        $token_url = MICROSOFT_OAUTH_BASE_URL . rawurlencode($oauth_tenant_id) . "/oauth2/v2.0/token";
    }

    $post_fields = http_build_query([
        'client_id' => $oauth_client_id,
        'client_secret' => $oauth_client_secret,
        'refresh_token' => $oauth_refresh_token,
        'grant_type' => 'refresh_token',
    ]);

    $ch = curl_init($token_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);

    $raw_body = curl_exec($ch);
    $curl_err = curl_error($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($raw_body === false || $http_code < 200 || $http_code >= 300) {
        $err_msg = !empty($curl_err) ? $curl_err : "HTTP $http_code";
        flash_alert("OAuth token test failed: $err_msg", 'error');
        redirect();
    }

    $json = json_decode($raw_body, true);

    if (!is_array($json) || empty($json['access_token'])) {
        flash_alert("OAuth token test failed: access token missing in provider response.", 'error');
        redirect();
    }

    $new_access_token = sanitizeInput($json['access_token']);
    $new_expires_at = date('Y-m-d H:i:s', time() + (int)($json['expires_in'] ?? 3600));
    $new_refresh_token = !empty($json['refresh_token']) ? sanitizeInput($json['refresh_token']) : '';

    $new_access_token_esc = mysqli_real_escape_string($mysqli, $new_access_token);
    $new_expires_at_esc = mysqli_real_escape_string($mysqli, $new_expires_at);

    $refresh_sql = '';
    if (!empty($new_refresh_token)) {
        $new_refresh_token_esc = mysqli_real_escape_string($mysqli, $new_refresh_token);
        $refresh_sql = ", config_mail_oauth_refresh_token = '$new_refresh_token_esc'";
    }

    mysqli_query($mysqli, "UPDATE settings SET config_mail_oauth_access_token = '$new_access_token_esc', config_mail_oauth_access_token_expires_at = '$new_expires_at_esc'$refresh_sql WHERE company_id = 1");

    $provider_label = $provider === 'microsoft_oauth' ? 'Microsoft 365' : 'Google Workspace';
    logAction("Settings", "Edit", "$session_name tested OAuth token refresh for $provider_label mail settings");

    flash_alert("OAuth token refresh successful for $provider_label. Access token expires at $new_expires_at.");
    redirect();
}
