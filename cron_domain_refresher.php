<?php

require_once "config.php";

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
 *  REFRESH DATA
 * ###############################################################################################################
 */
// 2023-02-20 JQ Commenting this code out as its intermitently breaking cron executions, investigating
// ERROR
// php cron.php
// PHP Fatal error:  Uncaught TypeError: mysqli_fetch_array(): Argument #1 ($result) must be of type mysqli_result, bool given in cron.php:141
// Stack trace:
//#0 cron.php(141): mysqli_fetch_array()
//#1 {main}
//  thrown in cron.php on line 141
// END ERROR
// REFRESH DOMAIN WHOIS DATA (1 a day)
//  Get the oldest updated domain (MariaDB shows NULLs first when ordering by default)
$row = mysqli_fetch_array(mysqli_query(
    $mysqli,
    "SELECT domain_id, domain_name FROM `domains`
    ORDER BY domain_updated_at LIMIT 1"));

if ($row) {
    $domain_id = intval($row['domain_id']);
    $domain_name = sanitizeInput($row['domain_name']);

    $expire = getDomainExpirationDate($domain_name);
    $records = getDomainRecords($domain_name);
    $a = sanitizeInput($records['a']);
    $ns = sanitizeInput($records['ns']);
    $mx = sanitizeInput($records['mx']);
    $txt = sanitizeInput($records['txt']);
    $whois = sanitizeInput($records['whois']);

    if (
        $expire === 'NULL'
        && $row['domain_expire'] !== null
        && (new DateTime($row['domain_expire'])) >= (new DateTime())
    ) {
        $expire = $row['domain_expire'];
    }

    // Update the domain
    mysqli_query(
        $mysqli,
        "UPDATE domains SET
        domain_name = '$domain_name',
        domain_expire = '$expire',
        domain_ip = '$a',
        domain_name_servers = '$ns',
        domain_mail_servers = '$mx',
        domain_txt = '$txt',
        domain_raw_whois = '$whois'
        WHERE domain_id = $domain_id");
}

