<?php

/*
 * Admin > Settings > Mail (admin/settings_mail.php). Each tab's form saves on
 * its own and comes back to its tab; what changed is in the audit log, from
 * what to what - passwords, the OAuth secret and the refresh token only as
 * changed. Those are never sent to the page: left empty, they stay as saved.
 * A server that isn't a name, a port that isn't one, an address that isn't an
 * email address comes back to the tab saying so, and nothing is saved. Then
 * the tests, the previews, and connecting Microsoft 365
 * (admin/oauth_microsoft_mail_callback.php finishes that).
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

// Saves and tests return to the tab they were submitted from (see settings_mail.php)
$mail_tabs = ['sending', 'receiving', 'oauth', 'from', 'tests', 'previews'];
$mail_tab_redirect = 'settings_mail.php';
if (isset($_POST['tab']) && in_array($_POST['tab'], $mail_tabs, true)) {
    $mail_tab_redirect .= '?tab=' . $_POST['tab'];
}

if (!defined('MICROSOFT_OAUTH_BASE_URL')) {
    define('MICROSOFT_OAUTH_BASE_URL', 'https://login.microsoftonline.com/');
}

/*
 * Sending (smtp) or Receiving (imap), checked - [settings after, errors by
 * field]. Off keeps the server and sign-in as they were, for turning it back
 * on. A server takes its name, port and encryption, and a username and
 * password - no username, no password (an SMTP server that takes email
 * without signing in). Google and Microsoft have one server each, so what
 * was saved for one is cleared, the password with it - they sign in with the
 * OAuth app.
 */
$mail_server_check = function ($server, array $before) {
    $p = "config_{$server}_";
    $standard = "standard_$server";
    $example = $server === 'smtp' ? 'smtp.example.com' : 'imap.example.com';
    $ports = $server === 'smtp' ? '587, 465 or 25' : '993 or 143';
    $provider = (string) ($_POST[$p . 'provider'] ?? '');
    $username = mb_substr(trim((string) ($_POST[$p . 'username'] ?? $before[$p . 'username'])), 0, 200);
    $errors = [];
    $after = $before;
    $after[$p . 'provider'] = $provider;

    if (!in_array($provider, ['', $standard, 'google_oauth', 'microsoft_oauth'], true)) {
        $errors[$p . 'provider'] = 'Pick one of these.';

    } elseif ($provider === $standard) {
        $host = trim((string) ($_POST[$p . 'host'] ?? ''));
        $port = trim((string) ($_POST[$p . 'port'] ?? ''));
        $encryption = (string) ($_POST[$p . 'encryption'] ?? '');
        $password = (string) ($_POST[$p . 'password'] ?? '');

        if ($host === '') {
            $errors[$p . 'host'] = "The server's name - $example, or the like.";
        } elseif (mb_strlen($host) > 200 || !(preg_match('/^[A-Za-z0-9]([A-Za-z0-9._-]*[A-Za-z0-9])?$/', $host) || filter_var(trim($host, '[]'), FILTER_VALIDATE_IP))) {
            $errors[$p . 'host'] = "Only the server's name or IP address - $example. The port and encryption have their own fields.";
        }
        if (!ctype_digit($port) || intval($port) < 1 || intval($port) > 65535) {
            $errors[$p . 'port'] = "A port number - $ports.";
        }
        if (!in_array($encryption, ['', 'tls', 'ssl'], true) && $encryption !== (string) $before[$p . 'encryption']) {
            $errors[$p . 'encryption'] = 'Pick one of these.';
        }
        if (mb_strlen($password) > 200) {
            $errors[$p . 'password'] = 'Up to 200 characters.';
        }
        $password = $username === '' ? '' : ($password !== '' ? $password : (string) $before[$p . 'password']);
        if ($server === 'imap' && $username === '') {
            $errors[$p . 'username'] = 'The mailbox to read - usually its address.';
        } elseif ($username !== '' && $password === '' && !isset($errors[$p . 'password'])) {
            $errors[$p . 'password'] = $server === 'smtp' ? 'And its password - or no username, if the server takes email without signing in.' : 'And its password.';
        }
        $after[$p . 'host'] = $host;
        $after[$p . 'port'] = intval($port);
        $after[$p . 'encryption'] = $encryption;
        $after[$p . 'username'] = $username;
        $after[$p . 'password'] = $password;

    } elseif ($provider !== '') {
        if ($server === 'imap' && $username === '') {
            $errors[$p . 'username'] = 'The mailbox to read - its address.';
        }
        $after[$p . 'host'] = '';
        $after[$p . 'port'] = null;
        $after[$p . 'encryption'] = '';
        $after[$p . 'username'] = $username;
        $after[$p . 'password'] = '';
    }
    return [$after, $errors];
};

if (isset($_POST['edit_mail_smtp_settings']) || isset($_POST['edit_mail_imap_settings'])) {

    validateCSRFToken();

    $server = isset($_POST['edit_mail_smtp_settings']) ? 'smtp' : 'imap';
    $before = settingsCurrent(array_map(function ($column) use ($server) {
        return "config_{$server}_$column";
    }, ['provider', 'host', 'port', 'encryption', 'username', 'password']));

    [$after, $errors] = $mail_server_check($server, $before);
    if ($errors) {
        settingsRefuse('mail', $errors, $mail_tab_redirect);
    }

    settingsSave('mail', $before, $after);

    redirect($mail_tab_redirect);

}

/*
 * The OAuth app - its ID, secret and tenant (Microsoft only; kept as saved
 * while Microsoft isn't picked), and the refresh token. No ID forgets the app:
 * its secret and token go too. A changed app or token makes what ITFlow last
 * signed in with useless, so that is fetched afresh when next needed.
 * Connect Microsoft 365 saves the same way, then goes to Microsoft to sign in.
 */
if (isset($_POST['edit_mail_oauth_settings']) || isset($_POST['oauth_connect_microsoft_mail'])) {

    validateCSRFToken();

    $before = settingsCurrent(['config_mail_oauth_client_id', 'config_mail_oauth_client_secret', 'config_mail_oauth_tenant_id', 'config_mail_oauth_refresh_token',
        'config_mail_oauth_access_token', 'config_mail_oauth_access_token_expires_at']);

    $client_id = trim((string) ($_POST['config_mail_oauth_client_id'] ?? $before['config_mail_oauth_client_id']));
    $secret = trim((string) ($_POST['config_mail_oauth_client_secret'] ?? ''));
    $tenant = trim((string) ($_POST['config_mail_oauth_tenant_id'] ?? $before['config_mail_oauth_tenant_id']));
    // A token pasted in with line breaks is still one token
    $refresh_token = preg_replace('/\s+/', '', (string) ($_POST['config_mail_oauth_refresh_token'] ?? ''));

    $errors = [];
    if (preg_match('/\s/', $client_id)) {
        $errors['config_mail_oauth_client_id'] = 'No spaces in it - copy it again from the app.';
    } elseif (mb_strlen($client_id) > 255) {
        $errors['config_mail_oauth_client_id'] = 'Up to 255 characters.';
    }
    if (mb_strlen($secret) > 255) {
        $errors['config_mail_oauth_client_secret'] = 'Up to 255 characters.';
    }
    if ($tenant !== '' && (mb_strlen($tenant) > 255 || !preg_match('/^[A-Za-z0-9.-]+$/', $tenant))) {
        $errors['config_mail_oauth_tenant_id'] = 'The Directory (tenant) ID, as 00000000-0000-0000-0000-000000000000 - or your domain, as example.onmicrosoft.com.';
    } elseif ($tenant === '' && $client_id !== '' && isset($_POST['config_mail_oauth_tenant_id'])) {
        // Only asked while Sending or Receiving is on Microsoft 365
        $errors['config_mail_oauth_tenant_id'] = 'Microsoft 365 needs it - the Directory (tenant) ID, from the app registration\'s Overview.';
    }
    if ($errors) {
        settingsRefuse('mail', $errors, $mail_tab_redirect);
    }

    $after = [
        'config_mail_oauth_client_id' => $client_id,
        'config_mail_oauth_client_secret' => $client_id === '' ? '' : ($secret !== '' ? $secret : (string) $before['config_mail_oauth_client_secret']),
        'config_mail_oauth_tenant_id' => $tenant,
        'config_mail_oauth_refresh_token' => $client_id === '' ? '' : ($refresh_token !== '' ? $refresh_token : (string) $before['config_mail_oauth_refresh_token']),
    ];
    foreach ($after as $column => $value) {
        if (!settingsSame($before[$column], $value)) {
            $after['config_mail_oauth_access_token'] = '';
            $after['config_mail_oauth_access_token_expires_at'] = null;
            break;
        }
    }

    $changed = settingsWrite('settings', $before, $after);
    // What it signed in with is only ever fetched - not something anyone changed
    unset($changed['config_mail_oauth_access_token'], $changed['config_mail_oauth_access_token_expires_at']);
    $lines = settingsChangeLines($before, $changed);

    if (isset($_POST['edit_mail_oauth_settings'])) {
        settingsSaid('mail', $lines);
        redirect($mail_tab_redirect);
    }

    // ---- Connect Microsoft 365 ----
    if ($lines) {
        settingsSaid('mail', $lines);
    }

    // The providers as saved - they are on the other tabs' forms
    if ($config_imap_provider !== 'microsoft_oauth' && $config_smtp_provider !== 'microsoft_oauth') {
        flashAlert("Set Sending or Receiving to Microsoft 365 and save it, then connect.", 'error');
        redirect($mail_tab_redirect);
    }

    $config_mail_oauth_client_id = $after['config_mail_oauth_client_id'];
    $config_mail_oauth_client_secret = $after['config_mail_oauth_client_secret'];
    $config_mail_oauth_tenant_id = $after['config_mail_oauth_tenant_id'];
    if ($config_mail_oauth_client_id === '' || $config_mail_oauth_client_secret === '' || $config_mail_oauth_tenant_id === '') {
        flashAlert("The app's client ID, secret and tenant ID first - then connect.", 'error');
        redirect($mail_tab_redirect);
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

    logAudit("Settings", "Edit", "$session_name started connecting Microsoft 365 for mail");

    redirect($authorize_url);
}

/*
 * The addresses and names emails come from. An address that isn't one comes
 * back saying so; a name is kept as typed, bar line breaks and the like.
 */
if (isset($_POST['edit_mail_from_settings'])) {

    validateCSRFToken();

    $boxes = ['mail', 'invoice', 'quote', 'ticket'];
    $columns = [];
    foreach ($boxes as $box) {
        $columns[] = "config_{$box}_from_email";
        $columns[] = "config_{$box}_from_name";
    }
    $before = settingsCurrent($columns);

    $after = [];
    $errors = [];
    foreach ($boxes as $box) {
        $email_key = "config_{$box}_from_email";
        $name_key = "config_{$box}_from_name";
        $email = trim((string) ($_POST[$email_key] ?? $before[$email_key]));
        $name = trim(preg_replace('/\s+/u', ' ', preg_replace('/[\x00-\x1F\x7F]/u', ' ', (string) ($_POST[$name_key] ?? $before[$name_key]))));
        if (($problem = settingsEmailProblem($email)) !== '') {
            $errors[$email_key] = $problem;
        } elseif (mb_strlen($email) > 200) {
            $errors[$email_key] = 'Up to 200 characters.';
        }
        if (mb_strlen($name) > 200) {
            $errors[$name_key] = 'Up to 200 characters.';
        }
        $after[$email_key] = $email;
        $after[$name_key] = $name;
    }
    if ($errors) {
        settingsRefuse('mail', $errors, $mail_tab_redirect);
    }

    settingsSave('mail', $before, $after);

    redirect($mail_tab_redirect);

}

// A copy of one of the previews (Admin > Mail > Previews), to the admin asking
if (isset($_POST['send_email_preview'])) {

    validateCSRFToken();

    $key = (string) ($_POST['email'] ?? '');
    $catalog = emailPreviewCatalog();
    if (!isset($catalog[$key])) {
        flashAlert('There is no such email', 'error');
        redirect('settings_mail.php?tab=previews');
    }
    if (!emailReady()) {
        flashAlert('Set up how mail is sent first, then send yourself a copy', 'error');
        redirect('settings_mail.php?tab=previews&email=' . rawurlencode($key));
    }
    if (emailPreviewSendCopy($key, $session_email, stripslashes($session_name))) {
        logAudit("Settings", "Edit", "$session_name sent themselves a preview of the " . escapeSql($catalog[$key]['group'] . ' - ' . $catalog[$key]['label']) . " email");
        flashAlert('A copy of <strong>' . escapeHtml($catalog[$key]['label']) . '</strong> is queued to ' . escapeHtml($session_email));
    } else {
        flashAlert('The copy could not be sent - check your own email address in Your account', 'error');
    }
    redirect('settings_mail.php?tab=previews&email=' . rawurlencode($key));

}

if (isset($_POST['test_email_smtp'])) {

    validateCSRFToken();

    $test_email = intval($_POST['test_email']);

    // From the mailbox picked - the system's, invoices', quotes' or tickets' (emailTestSend(), functions/email.php)
    $mailbox = [1 => 'system', 2 => 'invoice', 3 => 'quote'][$test_email] ?? 'ticket';
    $to = trim((string) ($_POST['email_to'] ?? ''));

    if (!emailReady()) {
        flashAlert('Set up how mail is sent first, then send the test', 'error');
        redirect($mail_tab_redirect);
    }

    $sent = emailTestSend($to, $mailbox);

    if ($sent) {
        flashAlert('Test email queued - it goes within a minute. Watch it go in <strong>Maintenance &gt; Mail Queue</strong>');
    } else {
        flashAlert(escapeHtml($to) . " isn't an email address - nothing was queued", 'error');
    }

    redirect($mail_tab_redirect);

}

if (isset($_POST['test_email_imap'])) {

    validateCSRFToken();

    $provider = escapeSql($config_imap_provider ?? '');

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

    // Override, don't default - a leftover standard-IMAP host from before the
    // switch to OAuth would otherwise make this test fail against the old server
    // while the cron parser (which overrides unconditionally) connects fine.
    if ($provider === 'google_oauth') {
        $host       = 'imap.gmail.com';
        $port       = 993;
        $encryption = 'ssl';
    } elseif ($provider === 'microsoft_oauth') {
        $host       = 'outlook.office365.com';
        $port       = 993;
        $encryption = 'ssl';
    }

    if (empty($host) || empty($port) || empty($username)) {
        flashAlert("<strong>IMAP connection failed:</strong> Missing host, port, or username.", 'error');
        redirect($mail_tab_redirect);
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
                flashAlert("<strong>IMAP OAuth failed:</strong> Missing OAuth client credentials or refresh token.", 'error');
                redirect($mail_tab_redirect);
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
                    flashAlert("<strong>IMAP OAuth failed:</strong> Microsoft tenant ID is required.", 'error');
                    redirect($mail_tab_redirect);
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
                flashAlert("<strong>IMAP OAuth failed:</strong> Could not refresh access token.", 'error');
                redirect($mail_tab_redirect);
            }

            $json = json_decode($response['body'], true);
            if (!is_array($json) || empty($json['access_token'])) {
                flashAlert("<strong>IMAP OAuth failed:</strong> Token response did not include an access token.", 'error');
                redirect($mail_tab_redirect);
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
    require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/vendor/autoload.php'; // ImapEngine (composer)

    // Map the stored encryption value to an ImapEngine transport (matches the cron sync)
    $imap_transport = match ($encryption) {
        'ssl'      => 'ssl',       // implicit TLS (993)
        'tls'      => 'starttls',  // STARTTLS upgrade (143) - Webklex semantics
        'starttls' => 'starttls',
        default    => '',          // 'none' / plain TCP
    };

    try {
        // Same ImapEngine client the cron sync uses, so a passing test predicts a
        // working sync. Typed errors instead of raw banners; host validated at save.
        $mailbox = new \DirectoryTree\ImapEngine\Mailbox([
            'host'           => $host,
            'port'           => $port,
            'encryption'     => $imap_transport,
            'validate_cert'  => true,
            'username'       => $username,
            'password'       => $password,          // access token when OAuth
            'authentication' => $is_oauth ? 'oauth' : 'plain',
        ]);

        $mailbox->connect();
        $mailbox->inbox(); // confirm auth + mailbox access, like the sync does

        flashAlert($is_oauth ? "Connected successfully using OAuth" : "Connected successfully");
    } catch (\Throwable $e) {
        flashAlert("<strong>IMAP connection failed.</strong> Check the host, port, encryption, and credentials.", 'error');
    }

    redirect($mail_tab_redirect);
}


if (isset($_POST['test_oauth_token_refresh'])) {

    validateCSRFToken();

    $provider = escapeSql($_POST['oauth_provider'] ?? '');

    if ($provider !== 'google_oauth' && $provider !== 'microsoft_oauth') {
        flashAlert("OAuth token test failed: unsupported provider.", 'error');
        redirect($mail_tab_redirect);
    }

    $oauth_client_id = escapeSql($config_mail_oauth_client_id ?? '');
    $oauth_client_secret = escapeSql($config_mail_oauth_client_secret ?? '');
    $oauth_tenant_id = escapeSql($config_mail_oauth_tenant_id ?? '');
    $oauth_refresh_token = escapeSql($config_mail_oauth_refresh_token ?? '');

    if (empty($oauth_client_id) || empty($oauth_client_secret) || empty($oauth_refresh_token)) {
        flashAlert("OAuth token test failed: missing client ID, client secret, or refresh token.", 'error');
        redirect($mail_tab_redirect);
    }

    if ($provider === 'microsoft_oauth' && empty($oauth_tenant_id)) {
        flashAlert("OAuth token test failed: Microsoft tenant ID is required.", 'error');
        redirect($mail_tab_redirect);
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
        flashAlert("OAuth token test failed: $err_msg", 'error');
        redirect($mail_tab_redirect);
    }

    $json = json_decode($raw_body, true);

    if (!is_array($json) || empty($json['access_token'])) {
        flashAlert("OAuth token test failed: access token missing in provider response.", 'error');
        redirect($mail_tab_redirect);
    }

    $new_access_token = escapeSql($json['access_token']);
    $new_expires_at = date('Y-m-d H:i:s', time() + (int)($json['expires_in'] ?? 3600));
    $new_refresh_token = !empty($json['refresh_token']) ? escapeSql($json['refresh_token']) : '';

    $new_access_token_esc = mysqli_real_escape_string($mysqli, $new_access_token);
    $new_expires_at_esc = mysqli_real_escape_string($mysqli, $new_expires_at);

    $refresh_sql = '';
    if (!empty($new_refresh_token)) {
        $new_refresh_token_esc = mysqli_real_escape_string($mysqli, $new_refresh_token);
        $refresh_sql = ", config_mail_oauth_refresh_token = '$new_refresh_token_esc'";
    }

    mysqli_query($mysqli, "UPDATE settings SET config_mail_oauth_access_token = '$new_access_token_esc', config_mail_oauth_access_token_expires_at = '$new_expires_at_esc'$refresh_sql WHERE company_id = 1");

    $provider_label = $provider === 'microsoft_oauth' ? 'Microsoft 365' : 'Google Workspace';
    logAudit("Settings", "Edit", "$session_name tested OAuth token refresh for $provider_label mail settings");

    flashAlert("OAuth token refresh successful for $provider_label. Access token expires at $new_expires_at.");
    redirect($mail_tab_redirect);
}
