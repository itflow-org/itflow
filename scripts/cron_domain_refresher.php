<?php

// Set working directory to the directory this cron script lives at.
chdir(dirname(__FILE__));

require_once "../config.php";

// Set Timezone
require_once "../inc_set_timezone.php";

require_once "../functions.php";


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
 *  REFRESH DATA
 * ###############################################################################################################
 */

// REFRESH DOMAIN WHOIS DATA (1 a day/run)
//  Get the oldest updated domain (MariaDB shows NULLs first when ordering by default)
$row = mysqli_fetch_array(mysqli_query($mysqli, "SELECT domain_id, domain_name, domain_expire FROM `domains` ORDER BY domain_updated_at LIMIT 1"));

if ($row) {

    // Get current data in database
    $domain_id = intval($row['domain_id']);
    $domain_name = sanitizeInput($row['domain_name']);
    $current_expire = sanitizeInput($row['domain_expire']);

    // Touch the record we're refreshing to ensure we don't loop
    mysqli_query($mysqli, "UPDATE domains SET domain_updated_at = NOW() WHERE domain_id = $domain_id");

    // Lookup fresh info
    $expire = getDomainExpirationDate($domain_name);
    $records = getDomainRecords($domain_name);
    $a = sanitizeInput($records['a']);
    $ns = sanitizeInput($records['ns']);
    $mx = sanitizeInput($records['mx']);
    $txt = sanitizeInput($records['txt']);
    $whois = sanitizeInput($records['whois']);

    // Handle expiry date
    if (strtotime($expire)) {
        $expire = "'" . $expire . "'"; // Valid
    } elseif (!strtotime($expire) && strtotime($current_expire)) {
        // New expiry date is invalid, but old one is OK - reverting back
        $expire = "'" . $current_expire . "'";
    } else {
        // Neither are valid, setting expiry to NULL
        $expire = 'NULL';
    }


    // Update the domain
    mysqli_query($mysqli, "UPDATE domains SET domain_name = '$domain_name',  domain_expire = $expire, domain_ip = '$a', domain_name_servers = '$ns', domain_mail_servers = '$mx', domain_txt = '$txt', domain_raw_whois = '$whois' WHERE domain_id = $domain_id");
}
