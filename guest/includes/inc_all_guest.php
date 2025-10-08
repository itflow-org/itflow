<?php
// Configuration & core
require_once $_SERVER['DOCUMENT_ROOT'] . '/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/functions.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/load_global_settings.php';

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/session_init.php';

// Set Timezone
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/inc_set_timezone.php';

$ip = sanitizeInput(getIP());
$user_agent = sanitizeInput($_SERVER['HTTP_USER_AGENT']);
$os = sanitizeInput(getOS($user_agent));
$browser = sanitizeInput(getWebBrowser($user_agent));

// Get Company Name
$sql = mysqli_query($mysqli, "SELECT company_name FROM companies WHERE company_id = 1");
$row = mysqli_fetch_array($sql);

$session_company_name = $row['company_name'];

// Page setup
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/page_title.php';

// Layout UI
require_once $_SERVER['DOCUMENT_ROOT'] . '/guest/includes/guest_header.php';

// Wrapper & alerts
require_once $_SERVER['DOCUMENT_ROOT'] . '/guest/includes/inc_wrapper.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/inc_alert_feedback.php';
//require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/filter_header.php';
