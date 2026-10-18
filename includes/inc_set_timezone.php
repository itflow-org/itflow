<?php

/*
 * Sets both clocks - PHP's and this MySQL connection's - to ITFlow's timezone
 * (Admin > Settings > Localization), so NOW(), CURRENT_TIMESTAMP defaults and
 * date() all agree with the pages. Every entry point requires it: the pages,
 * cron and the API (api/v1/validate_api_key.php).
 *
 * Sets $session_timezone and $session_utc_offset, and puts them in the
 * session when one is open - cron and the API have none.
 */

$timezone_row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT config_timezone FROM settings WHERE company_id = 1"));
$session_timezone = (string) ($timezone_row['config_timezone'] ?? '');

// Set PHP timezone
date_default_timezone_set($session_timezone);

// Calculate UTC offset
$session_datetime = new DateTime('now', new DateTimeZone($session_timezone));
$session_utc_offset = $session_datetime->format('P');

if (session_status() === PHP_SESSION_ACTIVE) {
    $_SESSION['session_timezone'] = $session_timezone;
    $_SESSION['session_utc_offset'] = $session_utc_offset;
}

// Set MySQL session time zone
mysqli_query($mysqli, "SET time_zone = '$session_utc_offset'");
