<?php

require_once("config.php");
require_once("functions.php");

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

$sql_certificates = mysqli_query($mysqli, "SELECT certificate_id, certificate_domain FROM certificates WHERE certificate_archived_at IS NULL");

while ($row = mysqli_fetch_array($sql_certificates)) {
    $certificate_id = intval($row['certificate_id']);
    $certificate_domain = sanitizeInput($row['certificate_domain']);

    $expire_date = getCertificateExpiryDate($certificate_domain);

    // Update the Certificate Expiry date
    mysqli_query($mysqli, "UPDATE certificates SET certificate_expire = '$expire_date' WHERE certificate_id = $certificate_id");

}