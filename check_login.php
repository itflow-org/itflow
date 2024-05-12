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


// User IP & UA
$session_ip = sanitizeInput(getIP());
$session_user_agent = sanitizeInput($_SERVER['HTTP_USER_AGENT']);

$session_user_id = intval($_SESSION['user_id']);

$sql = mysqli_query($mysqli, "SELECT * FROM users, user_settings WHERE users.user_id = user_settings.user_id AND users.user_id = $session_user_id");
$row = mysqli_fetch_array($sql);
$session_name = sanitizeInput($row['user_name']);
$session_email = $row['user_email'];
$session_avatar = $row['user_avatar'];
$session_token = $row['user_token'];
$session_user_role = intval($row['user_role']);
if ($session_user_role == 3) {
    $session_user_role_display = "Administrator";
} elseif ($session_user_role == 2) {
    $session_user_role_display = "Technician";
} else {
    $session_user_role_display = "Accountant";
}
$session_user_config_force_mfa = intval($row['user_config_force_mfa']);
$user_config_records_per_page = intval($row['user_config_records_per_page']);

$sql = mysqli_query($mysqli, "SELECT * FROM companies, settings WHERE settings.company_id = companies.company_id AND companies.company_id = 1");
$row = mysqli_fetch_array($sql);

$session_company_name = $row['company_name'];
$session_company_country = $row['company_country'];
$session_company_locale = $row['company_locale'];
$session_company_currency = $row['company_currency'];


// Set Currency Format
$currency_format = numfmt_create($session_company_locale, NumberFormatter::CURRENCY);


try {
    // Get User Client Access Permissions
    $user_client_access_sql = "SELECT client_id FROM user_permissions WHERE user_id = $session_user_id";
    $user_client_access_result = mysqli_query($mysqli, $user_client_access_sql);

    $client_access_array = [];
    while ($row = mysqli_fetch_assoc($user_client_access_result)) {
        $client_access_array[] = $row['client_id'];
    }

    $client_access_string = implode(',', $client_access_array);

    // Role / Client Access Permission Check
    if ($session_user_role < 3 && !empty($client_access_string)) {
        $access_permission_query = "AND client_id IN ($client_access_string)";
    } else {
        $access_permission_query = "";
    }
} catch (Exception $e) {
    // Handle exception
    error_log('MySQL error: ' . $e->getMessage());
    $access_permission_query = ""; // Ensure safe default if query fails
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


// Get Notification Count for the badge on the top nav
$row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('notification_id') AS num FROM notifications WHERE (notification_user_id = $session_user_id OR notification_user_id = 0) AND notification_dismissed_at IS NULL"));
$num_notifications = $row['num'];


// FORCE MFA Setup
//if ($session_user_config_force_mfa == 1 && $session_token == NULL) {
//    header("Location: force_mfa.php");
//}
