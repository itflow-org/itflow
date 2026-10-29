<?php

/*
 * Client Portal
 * Checks if the client is logged in or not
 */

require_once __DIR__ . "/../../includes/session_init.php";

/*
 * View as contact: an agent, signed in to ITFlow in this browser, looking at
 * the portal as one of a client's contacts - read only (functions/portal.php).
 * Checked again on every page, so the agent's access going away ends it.
 */
$portal_view_as = null;
if (empty($_SESSION['client_logged_in']) && !empty($_SESSION['portal_view_as']) && !empty($_SESSION['logged'])) {
    $portal_view_as = portalViewAsAccess(intval($_SESSION['user_id'] ?? 0), intval($_SESSION['portal_view_as']['contact_id'] ?? 0));
    if (!$portal_view_as) {
        unset($_SESSION['portal_view_as']);
        redirect("/agent/");
    }
} elseif (!isset($_SESSION['client_logged_in']) || !$_SESSION['client_logged_in']) {
    redirect("/login.php");
}

// Set Timezone
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/inc_set_timezone.php';

// User IP & UA
$session_ip = escapeSql(getIP());
$session_user_agent = escapeSql($_SERVER['HTTP_USER_AGENT'] ?? '');

// Get info from session
if ($portal_view_as) {
    $session_client_id = intval($portal_view_as['client_id']);
    $session_contact_id = intval($portal_view_as['contact_id']);
    $session_user_id = intval($portal_view_as['user_id']); // their portal user, 0 when they have none
} else {
    $session_client_id = intval($_SESSION['client_id']);
    $session_contact_id = intval($_SESSION['contact_id']);
    $session_user_id = intval($_SESSION['user_id']);
}

// Load user session vars
$row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT user_archived_at, user_auth_method, user_avatar, user_status, user_token, user_type FROM users WHERE users.user_id = $session_user_id"));

$session_avatar = $row['user_avatar'] ?? '';
$session_auth_method = (string) ($row['user_auth_method'] ?? '');
$session_mfa_on = trim((string) ($row['user_token'] ?? '')) !== '';

if (!$portal_view_as) {

    // Portal access turned off (no sign-in method) - by an agent or the client's
    // primary contact - ends a session that is already open, too
    if (!$row || !in_array($row['user_auth_method'], ['local', 'azure'], true)) {
        session_unset();
        session_destroy();
        redirect("/login.php");
    }

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
}

// Two-step sign-in required for password accounts (Admin > Client Portal) and
// not set up yet: Your account until it is, and nothing else
$portal_mfa_setup_needed = !$portal_view_as && intval($config_client_portal_require_mfa ?? 0) === 1 && $session_auth_method === 'local' && !$session_mfa_on;
if ($portal_mfa_setup_needed && !in_array(basename($_SERVER['PHP_SELF']), ['profile.php', 'post.php'], true)) {
    redirect("/client/profile.php?mfa=setup");
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
