<?php

/*
 * Client Portal
 * Checks if the client is logged in or not
 */

require_once __DIR__ . "/../../includes/session_init.php";

if (!isset($_SESSION['client_logged_in']) || !$_SESSION['client_logged_in']) {
    redirect("/login.php");
}

// Set Timezone
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/inc_set_timezone.php';

// User IP & UA
$session_ip = escapeSql(getIP());
$session_user_agent = escapeSql($_SERVER['HTTP_USER_AGENT']);


// Get info from session
$session_client_id = intval($_SESSION['client_id']);
$session_contact_id = intval($_SESSION['contact_id']);
$session_user_id = intval($_SESSION['user_id']);

// Load user session vars
$sql = mysqli_query($mysqli, "SELECT user_archived_at, user_avatar, user_status, user_type FROM users WHERE users.user_id = $session_user_id");

$row = mysqli_fetch_assoc($sql);

$session_avatar = $row['user_avatar'];
$session_user_type = intval($row['user_type']);
$session_user_status = intval($row['user_status']);
$session_user_archived_at = $row['user_archived_at'];

// Check user type is client aka 2
if ($session_user_type !== 2) {
    session_unset();
    session_destroy();
    redirect("/login.php");
}

// Check User is active
if ($session_user_status !== 1) {
    session_unset();
    session_destroy();
    redirect("/login.php");
}

// Check User is archived
if ($session_user_archived_at !== null) {
    session_unset();
    session_destroy();
    redirect("/login.php");
}

// Load company session vars
$sql = mysqli_query($mysqli, "SELECT company_country, company_currency, company_locale, company_logo, company_name FROM companies WHERE company_id = 1");
$row = mysqli_fetch_assoc($sql);

$session_company_name = $row['company_name'];
$session_company_country = $row['company_country'];
$session_company_locale = $row['company_locale'];
$session_company_currency = $row['company_currency'];
$currency_format = numfmt_create($session_company_locale, NumberFormatter::CURRENCY);
$session_company_logo = $row['company_logo'];

// Load contact session vars
$contact_sql = mysqli_query($mysqli, "SELECT contact_billing, contact_email, contact_name, contact_photo, contact_pin, contact_primary,
    contact_technical, contact_title FROM contacts WHERE contact_id = $session_contact_id AND contact_client_id = $session_client_id");
$contact = mysqli_fetch_assoc($contact_sql);

$session_contact_name = escapeSql($contact['contact_name']);
$session_contact_initials = initials($session_contact_name);
$session_contact_title = escapeSql($contact['contact_title']);
$session_contact_email = escapeSql($contact['contact_email']);
$session_contact_photo = escapeSql($contact['contact_photo']);
$session_contact_pin = escapeSql($contact['contact_pin']);
$session_contact_primary = intval($contact['contact_primary']);

$session_contact_is_technical_contact = false;
$session_contact_is_billing_contact = false;
if ($contact['contact_technical'] == 1) {
    $session_contact_is_technical_contact = true;
}
if ($contact['contact_billing'] == 1) {
    $session_contact_is_billing_contact = true;
}

// Load client session vars
$client_sql = mysqli_query($mysqli, "SELECT client_name FROM clients WHERE client_id = $session_client_id");
$client = mysqli_fetch_assoc($client_sql);

$session_client_name = $client['client_name'];
