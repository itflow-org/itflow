<?php

// Check if timezone and offset are set in session
if (!isset($_SESSION['session_timezone']) || !isset($_SESSION['session_utc_offset'])) {
    $result = mysqli_query($mysqli, "SELECT config_timezone FROM settings WHERE company_id = 1");
    $row = mysqli_fetch_array($result);
    $_SESSION['session_timezone'] = $row['config_timezone'];

    // Set PHP timezone
    date_default_timezone_set($_SESSION['session_timezone']);

    // Calculate UTC offset and store it in session
    $session_datetime = new DateTime('now', new DateTimeZone($_SESSION['session_timezone']));
    $_SESSION['session_utc_offset'] = $session_datetime->format('P');
}

// Use the stored timezone and offset
$session_timezone = $_SESSION['session_timezone'];
date_default_timezone_set($session_timezone);

// Set MySQL session time zone
mysqli_query($mysqli, "SET time_zone = '{$_SESSION['session_utc_offset']}'");
