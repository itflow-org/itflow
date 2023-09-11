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

// Get Certificate Expiry Date for a domain Function
function getCertificateExpiryDate($domain, $port = 443, $timeout = 10) {
    $context = stream_context_create([
        'ssl' => [
            'capture_peer_cert' => true,
            'verify_peer' => false, // We're only capturing the cert details; not verifying if it's valid
            'verify_peer_name' => false,
        ],
    ]);

    $client = @stream_socket_client(
        "ssl://{$domain}:{$port}",
        $errno,
        $errstr,
        $timeout,
        STREAM_CLIENT_CONNECT,
        $context
    );

    if (!$client) {
        return false;
    }

    $contextParams = stream_context_get_params($client);
    
    if (!isset($contextParams['options']['ssl']['peer_certificate'])) {
        return false;
    }

    $cert = $contextParams['options']['ssl']['peer_certificate'];
    $certInfo = openssl_x509_parse($cert);

    if (!isset($certInfo['validTo_time_t'])) {
        return false;
    }

    // Return the expiration date in a human-readable format, e.g., "2023-09-20"
    return date('Y-m-d', $certInfo['validTo_time_t']);
}

/*
 * ###############################################################################################################
 *  UPDATE CERTIFICATE EXPIRY DATE
 * ###############################################################################################################
 */

$sql_certificates = mysqli_query($mysqli, "SELECT certificate_id, certificate_domain FROM certificates WHERE certificate_archived_at IS NULL");

while ($row = mysqli_fetch_array($sql_certificates)) {
    $certificate_id = intval($row['certificate_id']);
    $certificate_domain = santizeInput($row['certificate_domain']);

    $expire_date = getCertificateExpiryDate($certificate_domain);

    // Update the Certificate Expiry date
    mysqli_query($mysqli, "UPDATE certificates SET certificate_expire = '$expire_date' WHERE certificate_id = $certificate_id");

}