<?php

/*
 * Client Portal
 * Checks if the client is logged in or not
 */

if (!isset($_SESSION)) {
    // HTTP Only cookies
    ini_set("session.cookie_httponly", true);
    if ($config_https_only) {
        // Tell client to only send cookie(s) over HTTPS
        ini_set("session.cookie_secure", true);
    }
    session_start();
}

if (!isset($_SESSION['client_logged_in']) || !$_SESSION['client_logged_in']) {
    header("Location: login.php");
    die;
}

// Check user type
if ($_SESSION['user_type'] !== 2) {
    header("Location: login.php");
    exit();
}

// Set Timezone
require_once "../includes/inc_set_timezone.php";

// User IP & UA
$session_ip = sanitizeInput(getIP());
$session_user_agent = sanitizeInput($_SERVER['HTTP_USER_AGENT']);


// Get info from session
$session_client_id = intval($_SESSION['client_id']);
$session_contact_id = intval($_SESSION['contact_id']);
$session_user_id = intval($_SESSION['user_id']);


// Get company info from database
$sql = mysqli_query($mysqli, "SELECT * FROM companies WHERE company_id = 1");
$row = mysqli_fetch_array($sql);

$session_company_name = $row['company_name'];
$session_company_country = $row['company_country'];
$session_company_locale = $row['company_locale'];
$session_company_currency = $row['company_currency'];
$currency_format = numfmt_create($session_company_locale, NumberFormatter::CURRENCY);


// Get contact info
$contact_sql = mysqli_query($mysqli, "SELECT * FROM contacts WHERE contact_id = $session_contact_id AND contact_client_id = $session_client_id");
$contact = mysqli_fetch_array($contact_sql);

$session_contact_name = sanitizeInput($contact['contact_name']);
$session_contact_initials = initials($session_contact_name);
$session_contact_title = sanitizeInput($contact['contact_title']);
$session_contact_email = sanitizeInput($contact['contact_email']);
$session_contact_photo = sanitizeInput($contact['contact_photo']);
$session_contact_pin = sanitizeInput($contact['contact_pin']);
$session_contact_primary = intval($contact['contact_primary']);

$session_contact_is_technical_contact = false;
$session_contact_is_billing_contact = false;
if ($contact['contact_technical'] == 1) {
    $session_contact_is_technical_contact = true;
}
if ($contact['contact_billing'] == 1) {
    $session_contact_is_billing_contact = true;
}

// Get client info
$client_sql = mysqli_query($mysqli, "SELECT * FROM clients WHERE client_id = $session_client_id");
$client = mysqli_fetch_array($client_sql);

$session_client_name = $client['client_name'];
