<?php

require_once "config.php";

// Set Timezone
require_once "inc_set_timezone.php";

require_once "functions.php";


$sql_settings = mysqli_query($mysqli, "SELECT * FROM settings WHERE settings.company_id = 1");

$row = mysqli_fetch_array($sql_settings);

// Company Settings
$config_enable_cron = intval($row['config_enable_cron']);
$config_cron_key = $row['config_cron_key'];

$argv = $_SERVER['argv'];

// Check cron is enabled
if ($config_enable_cron == 0) {
    exit("Cron: is not enabled -- Quitting..");
}

// Check Cron Key
if ( $argv[1] !== $config_cron_key ) {
    exit("Cron Key invalid  -- Quitting..");
}

/*
 * ###############################################################################################################
 *  UPDATE CERTIFICATE EXPIRY DATE
 * ###############################################################################################################
 */

$sql_certificates = mysqli_query($mysqli, "SELECT * FROM certificates WHERE certificate_archived_at IS NULL");

while ($row = mysqli_fetch_array($sql_certificates)) {
    $certificate_id = intval($row['certificate_id']);
    $domain = sanitizeInput($row['certificate_domain']);
    
    $certificate = getSSL($domain);

    $expire = sanitizeInput($certificate['expire']);
    $issued_by = sanitizeInput($certificate['issued_by']);
    $public_key = sanitizeInput($certificate['public_key']);

    if (empty($expire)) {
        $expire = "NULL";
    } else {
        $expire = "'" . $expire . "'";
    }

    echo "\n$domain\n";
    echo "$issued_by\n";
    echo "$expire\n";
    echo "$public_key\n\n";

    mysqli_query($mysqli,"UPDATE certificates SET certificate_issued_by = '$issued_by', certificate_expire = $expire, certificate_public_key = '$public_key' WHERE certificate_id = $certificate_id");

}