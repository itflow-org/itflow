<?php

// Unified login (Agent + Client) using one email & password

header("Content-Security-Policy: default-src 'self'");

if (!file_exists('config.php')) {
    header("Location: /setup");
    exit();
}

require_once "config.php";
require_once "functions.php";
require_once "libs/totp/totp.php";

if (session_status() === PHP_SESSION_NONE) {
    ini_set("session.cookie_httponly", true);

    if ($config_https_only || !isset($config_https_only)) {
        ini_set("session.cookie_secure", true);
    }

    session_start();
}

if (!isset($config_enable_setup) || $config_enable_setup == 1) {
    header("Location: /setup");
    exit();
}

if (
    $config_https_only
    && (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on')
    && (!isset($_SERVER['HTTP_X_FORWARDED_PROTO']) || $_SERVER['HTTP_X_FORWARDED_PROTO'] !== 'https')
) {
    echo "Login is restricted as ITFlow defaults to HTTPS-only for enhanced security. To login using HTTP, modify the config.php file by setting config_https_only to false. However, this is strongly discouraged, especially when accessing from potentially unsafe networks like the internet.";
    exit;
}

require_once "includes/inc_set_timezone.php";

$session_ip = sanitizeInput(getIP());
$session_user_agent = sanitizeInput($_SERVER['HTTP_USER_AGENT'] ?? '');

// IMPORTANT (Option B support): ensure this exists in this scope so logAction() can use it
$session_user_id = intval($_SESSION['user_id'] ?? 0);

$row = mysqli_fetch_assoc(mysqli_query(
    $mysqli,
    "SELECT COUNT(log_id) AS failed_login_count
     FROM logs
     WHERE log_ip = '$session_ip'
       AND log_type = 'Login'
       AND log_action = 'Failed'
       AND log_created_at > (NOW() - INTERVAL 10 MINUTE)"
));
$failed_login_count = intval($row['failed_login_count']);

if ($failed_login_count >= 15) {
    // Make sure global session_user_id is not required here (will be 0 anyway)
    logAction("Login", "Blocked", "$session_ip was blocked access to login due to IP lockout");
    header("HTTP/1.1 429 Too Many Requests");
    exit("<h2>$config_app_name</h2>Your IP address has been blocked due to repeated failed login attempts. Please try again later. <br><br>This action has been logged.");
}

// Settings
$sql_settings = mysqli_query($mysqli, "
    SELECT settings.*, companies.company_name, companies.company_logo
    FROM settings
    LEFT JOIN companies ON settings.company_id = companies.company_id
    WHERE settings.company_id = 1
");
$row = mysqli_fetch_assoc($sql_settings);

$company_name          = $row['company_name'];
$company_logo          = $row['company_logo'];
$config_start_page     = escapeHtml($row['config_start_page']);
$config_login_message  = escapeHtml($row['config_login_message']);

$config_smtp_provider       = $row['config_smtp_provider'];
$config_smtp_host       = $row['config_smtp_host'];
$config_smtp_port       = intval($row['config_smtp_port']);
$config_smtp_encryption = $row['config_smtp_encryption'];
$config_smtp_username   = $row['config_smtp_username'];
$config_smtp_password   = $row['config_smtp_password'];
$config_mail_from_email = sanitizeInput($row['config_mail_from_email']);
$config_mail_from_name  = sanitizeInput($row['config_mail_from_name']);

$config_client_portal_enable     = intval($row['config_client_portal_enable']);
$config_login_remember_me_expire = intval($row['config_login_remember_me_expire']);
$config_whitelabel_enabled       = intval($row['config_whitelabel_enabled']);

$config_login_key_required = $row['config_login_key_required'];
$config_login_key_secret   = $row['config_login_key_secret'];

$azure_client_id = $row['config_azure_client_id'] ?? null;

$response         = null;
$token_field      = null;
$show_role_choice = false;

$email    = '';
$password = ''; // only ever used in the initial POST request

// Helpers
function pendingExpired($sess, $ttl_seconds = 120) {
    return !$sess || empty($sess['created']) || (time() - intval($sess['created']) > $ttl_seconds);
}

// POST handling
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['login']) || isset($_POST['role_choice']) || isset($_POST['mfa_login']))) {

    $role_choice = $_POST['role_choice'] ?? null;

    $is_login_step = isset($_POST['login']);
    $is_role_step  = isset($_POST['role_choice']) && !$is_login_step && !isset($_POST['mfa_login']);
    $is_mfa_step   = isset($_POST['mfa_login']);

    // -----------------------------------
    // STEP 2: ROLE CHOICE (no email/pass)
    // -----------------------------------
    if ($is_role_step) {

        $posted_token = $_POST['pending_login_token'] ?? '';
        $sess = $_SESSION['pending_dual_login'] ?? null;

        if (pendingExpired($sess) || empty($posted_token) || empty($sess['token']) || !hash_equals($sess['token'], $posted_token)) {
            unset($_SESSION['pending_dual_login']);
            header("HTTP/1.1 401 Unauthorized");
            $response = "
              <div class='alert alert-danger'>
                Your login session expired. Please sign in again.
              </div>";
        } else {
            $email = sanitizeInput($sess['email'] ?? '');
        }
    }

    // -----------------------------------
    // STEP 3: MFA SUBMIT (no email/pass)
    // -----------------------------------
    if ($is_mfa_step && empty($response)) {

        $posted_token = $_POST['pending_mfa_token'] ?? '';
        $sess = $_SESSION['pending_mfa_login'] ?? null;

        if (pendingExpired($sess) || empty($posted_token) || empty($sess['token']) || !hash_equals($sess['token'], $posted_token)) {
            unset($_SESSION['pending_mfa_login']);
            header("HTTP/1.1 401 Unauthorized");
            $response = "
              <div class='alert alert-danger'>
                Your MFA session expired. Please sign in again.
              </div>";
        } else {
            $email = sanitizeInput($sess['email'] ?? '');
            $role_choice = 'agent';
        }
    }

    // -----------------------------------
    // STEP 1: INITIAL CREDENTIALS
    // -----------------------------------
    if ($is_login_step && empty($response)) {
        $email    = sanitizeInput($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            header("HTTP/1.1 401 Unauthorized");
            $response = "
              <div class='alert alert-danger'>
                Incorrect username or password.
              </div>";
        }
    }

    // Continue only if no response error
    if (empty($response)) {

        // Query all possible matches for that email
        $sql = mysqli_query($mysqli, "
            SELECT users.*,
                   user_settings.*,
                   contacts.*,
                   clients.*
            FROM users
            LEFT JOIN user_settings ON users.user_id = user_settings.user_id
            LEFT JOIN contacts       ON users.user_id = contacts.contact_user_id
            LEFT JOIN clients        ON contacts.contact_client_id = clients.client_id
            WHERE user_email = '$email'
              AND user_archived_at IS NULL
              AND user_status = 1
              AND (
                    user_type = 1
                    OR (user_type = 2 AND client_archived_at IS NULL)
                  )
        ");

        $agentRow  = null;
        $clientRow = null;

        // Step 1: verify password. Step 2/3: use stored allowed ids.
        $allowed_agent_id  = null;
        $allowed_client_id = null;

        if ($is_role_step) {
            $sess = $_SESSION['pending_dual_login'] ?? null;
            $allowed_agent_id  = isset($sess['agent_user_id']) ? intval($sess['agent_user_id']) : null;
            $allowed_client_id = isset($sess['client_user_id']) ? intval($sess['client_user_id']) : null;
        }

        if ($is_mfa_step) {
            $sess = $_SESSION['pending_mfa_login'] ?? null;
            $allowed_agent_id = isset($sess['agent_user_id']) ? intval($sess['agent_user_id']) : null;
        }

        while ($r = mysqli_fetch_assoc($sql)) {

            $ut = intval($r['user_type']);

            if ($is_login_step) {
                // Only Step 1 checks password
                if (!password_verify($password, $r['user_password'])) {
                    continue;
                }
            } else {
                // Step 2/3: restrict to ids we previously verified
                if ($ut === 1 && $allowed_agent_id !== null && intval($r['user_id']) !== $allowed_agent_id) {
                    continue;
                }
                if ($ut === 2 && $allowed_client_id !== null && intval($r['user_id']) !== $allowed_client_id) {
                    continue;
                }
            }

            if ($ut === 1 && $agentRow === null) {
                $agentRow = $r;
            }
            if ($ut === 2 && $clientRow === null) {
                $clientRow = $r;
            }
        }

        if ($agentRow === null && $clientRow === null) {
            header("HTTP/1.1 401 Unauthorized");

            // Option B not possible here (we don't know user_id reliably)
            logAction("Login", "Failed", "Failed login attempt using $email");

            $response = "
              <div class='alert alert-danger'>
                Incorrect username or password.
              </div>";
        } else {

            $selectedRow  = null;
            $selectedType = null; // 1 agent, 2 client

            // Dual role
            if ($agentRow !== null && $clientRow !== null) {

                if ($role_choice === 'agent') {
                    $selectedRow  = $agentRow;
                    $selectedType = 1;
                } elseif ($role_choice === 'client') {
                    $selectedRow  = $clientRow;
                    $selectedType = 2;
                } else {
                    // Show role choice screen
                    $show_role_choice = true;

                    // If this is the first time (Step 1), we need to stash allowed ids and (optional) decrypted agent encryption key
                    // WITHOUT storing password.
                    if ($is_login_step) {

                        $pending_token = bin2hex(random_bytes(32));

                        // If agent has user-specific encryption ciphertext, decrypt it NOW while password is present.
                        $agent_master_key = null;
                        $agent_cipher = $agentRow['user_specific_encryption_ciphertext'] ?? null;
                        if (!empty($agent_cipher)) {
                            $agent_master_key = decryptUserSpecificKey($agent_cipher, $password);
                        }

                        $_SESSION['pending_dual_login'] = [
                            'email'            => $email,
                            'agent_user_id'    => intval($agentRow['user_id']),
                            'client_user_id'   => intval($clientRow['user_id']),
                            'agent_master_key' => $agent_master_key, // may be null
                            'token'            => $pending_token,
                            'created'          => time()
                        ];
                    }
                }

            } else {
                // Single role
                if ($agentRow !== null) {
                    $selectedRow  = $agentRow;
                    $selectedType = 1;
                } else {
                    $selectedRow  = $clientRow;
                    $selectedType = 2;
                }
            }

            // Proceed if selected
            if ($selectedRow !== null && $selectedType !== null) {

                // Cache pending sessions BEFORE unsetting anything (needed for role->MFA and MFA success)
                $pending_dual = $_SESSION['pending_dual_login'] ?? null;
                $pending_mfa  = $_SESSION['pending_mfa_login'] ?? null;

                // NOTE: do NOT unset pending_dual_login here anymore; we may still need agent_master_key for MFA or role-step agent login

                $user_id    = intval($selectedRow['user_id']);
                $user_email = sanitizeInput($selectedRow['user_email']);

                // =========================
                // AGENT FLOW
                // =========================
                if ($selectedType === 1) {

                    if ($config_login_key_required) {
                        if (!isset($_GET['key']) || $_GET['key'] !== $config_login_key_secret) {
                            redirect();
                        }
                    }

                    $user_name                  = sanitizeInput($selectedRow['user_name']);
                    $token                      = sanitizeInput($selectedRow['user_token']);
                    $force_mfa                  = intval($selectedRow['user_config_force_mfa']);
                    $user_encryption_ciphertext = $selectedRow['user_specific_encryption_ciphertext'];

                    $current_code = 0;
                    if (isset($_POST['current_code'])) {
                        $current_code = intval($_POST['current_code']);
                    }

                    $mfa_is_complete = false;
                    $extended_log    = '';

                    if (empty($token)) {
                        $mfa_is_complete = true; // no MFA configured
                    }

                    // remember-me cookie allows bypass
                    if (isset($_COOKIE['rememberme'])) {
                        $remember_tokens = mysqli_query($mysqli, "
                            SELECT remember_token_token
                            FROM remember_tokens
                            WHERE remember_token_user_id = $user_id
                              AND remember_token_created_at > (NOW() - INTERVAL $config_login_remember_me_expire DAY)
                        ");
                        while ($remember_row = mysqli_fetch_assoc($remember_tokens)) {
                            if (hash_equals($remember_row['remember_token_token'], $_COOKIE['rememberme'])) {
                                $mfa_is_complete = true;
                                $extended_log    = 'with 2FA remember-me cookie';
                                break;
                            }
                        }
                    }

                    // Validate MFA code
                    if (!empty($current_code) && TokenAuth6238::verify($token, $current_code)) {
                        $mfa_is_complete = true;
                        $extended_log    = 'with MFA';
                    }

                    if ($mfa_is_complete) {

                        // Remember me token creation
                        if (isset($_POST['remember_me'])) {
                            $newRememberToken = bin2hex(random_bytes(64));
                            setcookie('rememberme', $newRememberToken, time() + 86400 * $config_login_remember_me_expire, "/", null, true, true);

                            mysqli_query($mysqli, "
                                INSERT INTO remember_tokens
                                SET remember_token_user_id = $user_id,
                                    remember_token_token   = '$newRememberToken'
                            ");
                            $extended_log .= ", generated a new remember-me token";
                        }

                        // Suspicious login checks / email notify
                        $sql_ip_prev_logins = mysqli_fetch_assoc(mysqli_query($mysqli, "
                            SELECT COUNT(log_id) AS ip_previous_logins
                            FROM logs
                            WHERE log_type = 'Login'
                              AND log_action = 'Success'
                              AND log_ip = '$session_ip'
                              AND log_user_id = $user_id
                        "));
                        $ip_previous_logins = sanitizeInput($sql_ip_prev_logins['ip_previous_logins']);

                        $sql_ua_prev_logins = mysqli_fetch_assoc(mysqli_query($mysqli, "
                            SELECT COUNT(log_id) AS ua_previous_logins
                            FROM logs
                            WHERE log_type = 'Login'
                              AND log_action = 'Success'
                              AND log_user_agent = '$session_user_agent'
                              AND log_user_id = $user_id
                        "));
                        $ua_prev_logins = sanitizeInput($sql_ua_prev_logins['ua_previous_logins']);

                        if (!empty($config_smtp_provider) && $ip_previous_logins == 0 && $ua_prev_logins == 0) {
                            $subject = "$config_app_name new login for $user_name";
                            $body    = "Hi $user_name, <br><br>A recent successful login to your $config_app_name account was considered a little unusual. If this was you, you can safely ignore this email!<br><br>IP Address: $session_ip<br> User Agent: $session_user_agent <br><br>If you did not perform this login, your credentials may be compromised. <br><br>Thanks, <br>ITFlow";

                            $data = [[
                                'from'           => $config_mail_from_email,
                                'from_name'      => $config_mail_from_name,
                                'recipient'      => $user_email,
                                'recipient_name' => $user_name,
                                'subject'        => $subject,
                                'body'           => $body
                            ]];
                            addToMailQueue($data);
                        }

                        // Option B: set session_user_id BEFORE logAction()
                        $session_user_id = $user_id;
                        logAction("Login", "Success", "$user_name successfully logged in $extended_log", 0, $user_id);

                        $_SESSION['user_id']    = $user_id;
                        $_SESSION['csrf_token'] = randomString(32);
                        $_SESSION['logged']     = true;

                        if ($force_mfa == 1 && $token == NULL) {
                            $config_start_page = "user/mfa_enforcement.php";
                        }

                        // Setup encryption session key WITHOUT PASSWORD IN SESSION
                        $site_encryption_master_key = null;

                        if ($is_mfa_step) {
                            // Step 3: MFA submit
                            $sess = $pending_mfa ?? ($_SESSION['pending_mfa_login'] ?? null);
                            if ($sess && !empty($sess['agent_master_key'])) {
                                $site_encryption_master_key = $sess['agent_master_key'];
                            }

                        } elseif ($is_role_step) {
                            // Step 2: role choice (no password available)
                            $sess = $pending_dual ?? ($_SESSION['pending_dual_login'] ?? null);
                            if ($sess && !empty($sess['agent_master_key'])) {
                                $site_encryption_master_key = $sess['agent_master_key'];
                            }

                        } else {
                            // Step 1: initial login (password available in this request)
                            if (!empty($user_encryption_ciphertext)) {
                                $site_encryption_master_key = decryptUserSpecificKey($user_encryption_ciphertext, $password);
                            }
                        }

                        if (!empty($site_encryption_master_key)) {
                            generateUserSessionKey($site_encryption_master_key);
                        }

                        // NOW safe to clear pending sessions AFTER we used them
                        unset($_SESSION['pending_mfa_login']);
                        unset($_SESSION['pending_dual_login']);

                        // Redirect
                        if (isset($_GET['last_visited']) && (str_starts_with(base64_decode($_GET['last_visited']), '/agent') || str_starts_with(base64_decode($_GET['last_visited']), '/admin'))) {
                            redirect($_SERVER["REQUEST_SCHEME"] . "://" . $config_base_url . base64_decode($_GET['last_visited']));
                        } else {
                            redirect("agent/$config_start_page");
                        }

                    } else {

                        // MFA required — store *only what we need*, not password
                        $pending_mfa_token = bin2hex(random_bytes(32));

                        // If we arrived here from role-choice step, the agent master key may be in cached pending_dual
                        // If we arrived from initial login step, decrypt now (password in memory) and store master key.
                        $agent_master_key = null;

                        if ($is_role_step) {
                            $sess = $pending_dual ?? ($_SESSION['pending_dual_login'] ?? null);
                            if ($sess && array_key_exists('agent_master_key', $sess)) {
                                $agent_master_key = $sess['agent_master_key'];
                            }
                        } else {
                            if (!empty($user_encryption_ciphertext)) {
                                $agent_master_key = decryptUserSpecificKey($user_encryption_ciphertext, $password);
                            }
                        }

                        $_SESSION['pending_mfa_login'] = [
                            'email'            => $user_email,
                            'agent_user_id'    => $user_id,
                            'agent_master_key' => $agent_master_key, // may be null
                            'token'            => $pending_mfa_token,
                            'created'          => time()
                        ];

                        // Now that we've transferred what we need, it's safe to clear the dual-role pending session.
                        unset($_SESSION['pending_dual_login']);

                        $token_field = "
                            <div class='input-group mb-3'>
                                <input type='text' inputmode='numeric' pattern='[0-9]*' maxlength='6'
                                       class='form-control' placeholder='Verify your 2FA code'
                                       name='current_code' required autofocus>
                                <div class='input-group-append'>
                                  <div class='input-group-text'>
                                    <span class='fas fa-key'></span>
                                  </div>
                                </div>
                            </div>";

                        if ($current_code !== 0) {

                            // Option B: set session_user_id BEFORE logAction()
                            $session_user_id = $user_id;
                            logAction("Login", "MFA Failed", "$user_email failed MFA", 0, $user_id);

                            if (!empty($config_smtp_provider)) {
                                $subject = "Important: $config_app_name failed 2FA login attempt for $user_name";
                                $body    = "Hi $user_name, <br><br>A recent login to your $config_app_name account was unsuccessful due to an incorrect 2FA code. If you did not attempt this login, your credentials may be compromised. <br><br>Thanks, <br>ITFlow";
                                $data    = [[
                                    'from'           => $config_mail_from_email,
                                    'from_name'      => $config_mail_from_name,
                                    'recipient'      => $user_email,
                                    'recipient_name' => $user_name,
                                    'subject'        => $subject,
                                    'body'           => $body
                                ]];
                                addToMailQueue($data);
                            }

                            $response = "
                                  <div class='alert alert-danger'>
                                    Please enter a valid 2FA code.
                                  </div>";
                        }
                    }

                // =========================
                // CLIENT FLOW
                // =========================
                } elseif ($selectedType === 2) {

                    if ($config_client_portal_enable != 1) {
                        header("HTTP/1.1 401 Unauthorized");

                        logAction("Client Login", "Failed", "Client portal disabled; login attempt using $email");

                        $response = "
                          <div class='alert alert-danger'>
                            Incorrect username or password.
                          </div>";
                    } else {

                        // Client login user id can be clobbered by SELECT users.*, contacts.*, clients.* collisions.
                        // Prefer contact_user_id (ties to the portal user), fallback to user_id if present.
                        $user_id = intval($selectedRow['contact_user_id'] ?? 0);
                        if ($user_id === 0) {
                            $user_id = intval($selectedRow['user_id'] ?? 0);
                        }

                        $client_id        = intval($selectedRow['contact_client_id'] ?? 0);
                        $contact_id       = intval($selectedRow['contact_id'] ?? 0);
                        $user_auth_method = sanitizeInput($selectedRow['user_auth_method'] ?? '');

                        if ($client_id && $contact_id && $user_auth_method === 'local') {

                            $_SESSION['client_logged_in'] = true;
                            $_SESSION['client_id']        = $client_id;
                            $_SESSION['user_id']          = $user_id;
                            $_SESSION['user_type']        = 2;
                            $_SESSION['contact_id']       = $contact_id;
                            $_SESSION['login_method']     = "local";

                            // Keep consistent with agent flow (helps any shared session checks)
                            $_SESSION['logged']     = true;
                            $_SESSION['csrf_token'] = randomString(32);

                            // Option B: set session_user_id BEFORE logAction()
                            $session_user_id = $user_id;
                            logAction("Client Login", "Success", "Client contact $user_email successfully logged in locally", $client_id, $user_id);

                            // Clear any pending sessions (avoid stale dual-role/MFA state)
                            unset($_SESSION['pending_dual_login']);
                            unset($_SESSION['pending_mfa_login']);

                            header("Location: client/index.php");
                            exit();

                        } else {

                            // if we have a users.user_id, log it
                            $session_user_id = $user_id ?: 0;
                            logAction(
                                "Client Login",
                                "Failed",
                                "Failed client portal login attempt using $email (invalid auth method or missing contact/client)",
                                $client_id ?? 0,
                                $user_id
                            );

                            header("HTTP/1.1 401 Unauthorized");
                            $response = "
                              <div class='alert alert-danger'>
                                Incorrect username or password.
                              </div>";
                        }
                    }
                }
            }
        }
    }
}

// Form state
$show_mfa_form   = (isset($token_field) && !empty($token_field));
$show_login_form = (!$show_role_choice && !$show_mfa_form);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title><?php echo escapeHtml($company_name); ?> | Login</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">

    <link rel="stylesheet" href="libs/fontawesome-free/css/all.min.css">

    <?php if(file_exists('uploads/favicon.ico')) { ?>
        <link rel="icon" type="image/x-icon" href="/uploads/favicon.ico">
    <?php } ?>

    <link rel="stylesheet" href="libs/adminlte/css/adminlte.min.css">
</head>
<body class="hold-transition login-page">

<div class="login-box">
    <div class="login-logo">
        <?php if (!empty($company_logo)) { ?>
            <img alt="<?=escapeHtml($company_name)?> logo" height="110" width="380" class="img-fluid" src="<?php echo "uploads/settings/$company_logo"; ?>">
        <?php } else { ?>
            <span class="text-primary text-bold"><i class="fas fa-paper-plane mr-2"></i>IT</span>Flow
        <?php } ?>
    </div>

    <div class="card">
        <div class="card-body login-card-body">

            <?php if (!empty($config_login_message)){ ?>
                <p class="login-box-msg px-0"><?php echo nl2br($config_login_message); ?></p>
            <?php } ?>

            <?php if (isset($response)) { ?>
                <p><?php echo $response; ?></p>
            <?php } ?>

            <form method="post">

                <?php if ($show_login_form): ?>
                    <!-- STEP 1: Email + Password -->
                    <div class="input-group mb-3">
                        <input type="email" class="form-control"
                            placeholder="<?php if ($config_login_key_required) { if (!isset($_GET['key']) || $_GET['key'] !== $config_login_key_secret) { echo "Client "; } } echo "Email"; ?>"
                            name="email"
                            value="<?php echo htmlspecialchars($email ?? '', ENT_QUOTES); ?>"
                            required autofocus
                        >
                        <div class="input-group-append">
                            <div class="input-group-text">
                                <span class="fas fa-envelope"></span>
                            </div>
                        </div>
                    </div>

                    <div class="input-group mb-3">
                        <input type="password" class="form-control" placeholder="Password" name="password" required>
                        <div class="input-group-append">
                            <div class="input-group-text">
                                <span class="fas fa-lock"></span>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block mb-3" name="login">Sign In</button>
                <?php endif; ?>

                <?php if ($show_role_choice): ?>
                    <!-- STEP 2: Role choice only -->
                    <input type="hidden" name="pending_login_token"
                           value="<?php echo htmlspecialchars($_SESSION['pending_dual_login']['token'] ?? '', ENT_QUOTES); ?>">

                    <div class="mb-2 text-center">
                        <button type="submit" class="btn btn-dark btn-block mb-2" name="role_choice" value="agent">
                            Log in as Agent
                        </button>
                        <button type="submit" class="btn btn-light btn-block" name="role_choice" value="client">
                            Log in as Client
                        </button>
                    </div>
                <?php endif; ?>

                <?php if ($show_mfa_form): ?>
                    <!-- STEP 3: MFA only -->
                    <?php echo $token_field; ?>

                    <input type="hidden" name="pending_mfa_token"
                           value="<?php echo htmlspecialchars($_SESSION['pending_mfa_login']['token'] ?? '', ENT_QUOTES); ?>">

                    <div class="form-group mb-3">
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input" id="remember_me" name="remember_me">
                            <label class="custom-control-label" for="remember_me">Remember Me</label>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-dark btn-block mb-3" name="mfa_login">Verify & Sign In</button>
                <?php endif; ?>

            </form>

            <?php if($config_client_portal_enable == 1){ ?>
                <hr>
                <?php if (!empty($config_smtp_provider)) { ?>
                    <a href="client/login_reset.php">Forgot password?</a>
                <?php } ?>
                <?php if (!empty($azure_client_id)) { ?>
                    <div class="col text-center mt-2">
                        <a href="client/login_microsoft.php">
                            <button type="button" class="btn btn-secondary">Login with Microsoft Entra</button>
                        </a>
                    </div>
                <?php } ?>
            <?php } ?>

        </div>
    </div>
</div>

<?php
if (!$config_whitelabel_enabled) {
    echo '<small class="text-muted">Powered by ITFlow</small>';
}
?>

<script src="libs/jquery/jquery.min.js"></script>
<script src="libs/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="libs/adminlte/js/adminlte.min.js"></script>
<script src="js/login_prevent_resubmit.js"></script>

</body>
</html>
