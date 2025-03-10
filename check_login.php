<?php

if (!isset($_SESSION)) {
    // HTTP Only cookies
    ini_set("session.cookie_httponly", true);
    if ($config_https_only) {
        // Tell client to only send cookie(s) over HTTPS
        ini_set("session.cookie_secure", true);
    }
    session_start();
}


// Check to see if setup is enabled
if (!isset($config_enable_setup) || $config_enable_setup == 1) {
    header("Location: setup.php");
    exit;
}

// Check user is logged in with a valid session
if (!isset($_SESSION['logged']) || !$_SESSION['logged']) {
    if ($_SERVER["REQUEST_URI"] == "/") {
        header("Location: login.php");
    } else {
        header("Location: login.php?last_visited=" . base64_encode($_SERVER["REQUEST_URI"]) );
    }
    exit;
}

// Set Timezone
require_once "inc_set_timezone.php";


// User Vars and User Settings
$session_ip = sanitizeInput(getIP());
$session_user_agent = sanitizeInput($_SERVER['HTTP_USER_AGENT']);

$session_user_id = intval($_SESSION['user_id']);

$sql = mysqli_query(
    $mysqli,
    "SELECT * FROM users
    LEFT JOIN user_settings ON users.user_id = user_settings.user_id
    LEFT JOIN user_roles ON user_role_id = role_id
    WHERE users.user_id = $session_user_id");

$row = mysqli_fetch_array($sql);
$session_name = sanitizeInput($row['user_name']);
$session_email = $row['user_email'];
$session_avatar = $row['user_avatar'];
$session_token = $row['user_token']; // MFA Token
$session_user_type = intval($row['user_type']);
$session_user_role = intval($row['user_role_id']);
$session_user_role_display = sanitizeInput($row['role_name']);
if (isset($row['role_is_admin']) && $row['role_is_admin'] == 1) {
    $session_is_admin = true;
} else {
    $session_is_admin = false;
}
$session_user_config_force_mfa = intval($row['user_config_force_mfa']);
$user_config_records_per_page = intval($row['user_config_records_per_page']);

// Check user type
if ($session_user_type !== 1) {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit();
}

// Company Vars and Company Settings
$sql = mysqli_query($mysqli, "SELECT * FROM companies, settings WHERE settings.company_id = companies.company_id AND companies.company_id = 1");
$row = mysqli_fetch_array($sql);

$session_company_name = $row['company_name'];
$session_company_country = $row['company_country'];
$session_company_locale = $row['company_locale'];
$session_company_currency = $row['company_currency'];


// Set Currency Format
$currency_format = numfmt_create($session_company_locale, NumberFormatter::CURRENCY);

// Get User Client Access Permissions
$user_client_access_sql = "SELECT client_id FROM user_client_permissions WHERE user_id = $session_user_id";
$user_client_access_result = mysqli_query($mysqli, $user_client_access_sql);

$client_access_array = [];
while ($row = mysqli_fetch_assoc($user_client_access_result)) {
    $client_access_array[] = $row['client_id'];
}

$client_access_string = implode(',', $client_access_array);

// Client access permission check
//  Default allow, if a list of allowed clients is set & the user isn't an admin, restrict them
$access_permission_query = "";
if ($client_access_string && !$session_is_admin) {
    $access_permission_query = "AND clients.client_id IN ($client_access_string)";
}

// Include the settings vars
require_once "get_settings.php";

//Detects if using an Apple device and uses Apple Maps instead of google
$iPod = stripos($_SERVER['HTTP_USER_AGENT'], "iPod");
$iPhone = stripos($_SERVER['HTTP_USER_AGENT'], "iPhone");
$iPad = stripos($_SERVER['HTTP_USER_AGENT'], "iPad");

if ($iPod || $iPhone || $iPad) {
    $session_map_source = "apple";
} else {
    $session_map_source = "google";
}

// Check if mobile device
$session_mobile = isMobile();
