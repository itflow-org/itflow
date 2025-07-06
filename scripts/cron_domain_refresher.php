<?php

// Set working directory to the directory this cron script lives at.
chdir(dirname(__FILE__));

// Ensure we're running from command line
if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line.\n");
}

require_once "../config.php";

// Set Timezone
require_once "../includes/inc_set_timezone.php";
require_once "../functions.php";

$sql_settings = mysqli_query($mysqli, "SELECT * FROM settings WHERE settings.company_id = 1");

$row = mysqli_fetch_array($sql_settings);

// Company Settings
$config_enable_cron = intval($row['config_enable_cron']);

// Check cron is enabled
if ($config_enable_cron == 0) {
    logApp("Cron-Domain-Refresher", "error", "Cron Domain Refresh unable to run - cron not enabled in admin settings.");
    exit("Cron: is not enabled -- Quitting..");
}

/*
 * ###############################################################################################################
 *  REFRESH DATA
 * ###############################################################################################################
 */

// REFRESH DOMAIN WHOIS DATA (1 a day/run)
//  Get the oldest updated domain (MariaDB shows NULLs first when ordering by default)
$row = mysqli_fetch_array(mysqli_query($mysqli, "SELECT domain_id, domain_name, domain_expire FROM `domains` WHERE domain_archived_at IS NULL ORDER BY domain_updated_at LIMIT 1"));

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

    // Current domain info
    $original_domain_info = mysqli_fetch_assoc(mysqli_query($mysqli,"
        SELECT
            domains.*,
            registrar.vendor_name AS registrar_name,
            dnshost.vendor_name AS dnshost_name,
            mailhost.vendor_name AS mailhost_name,
            webhost.vendor_name AS webhost_name
        FROM domains
        LEFT JOIN vendors AS registrar ON domains.domain_registrar = registrar.vendor_id
        LEFT JOIN vendors AS dnshost ON domains.domain_dnshost = dnshost.vendor_id
        LEFT JOIN vendors AS mailhost ON domains.domain_mailhost = mailhost.vendor_id
        LEFT JOIN vendors AS webhost ON domains.domain_webhost = webhost.vendor_id
        WHERE domain_id = $domain_id
    "));

    // Update the domain
    mysqli_query($mysqli, "UPDATE domains SET domain_name = '$domain_name',  domain_expire = $expire, domain_ip = '$a', domain_name_servers = '$ns', domain_mail_servers = '$mx', domain_txt = '$txt', domain_raw_whois = '$whois' WHERE domain_id = $domain_id");
    echo "Updated $domain_name.";

    // Fetch updated info
    $new_domain_info = mysqli_fetch_assoc(mysqli_query($mysqli,"
        SELECT
            domains.*,
            registrar.vendor_name AS registrar_name,
            dnshost.vendor_name AS dnshost_name,
            mailhost.vendor_name AS mailhost_name,
            webhost.vendor_name AS webhost_name
        FROM domains
        LEFT JOIN vendors AS registrar ON domains.domain_registrar = registrar.vendor_id
        LEFT JOIN vendors AS dnshost ON domains.domain_dnshost = dnshost.vendor_id
        LEFT JOIN vendors AS mailhost ON domains.domain_mailhost = mailhost.vendor_id
        LEFT JOIN vendors AS webhost ON domains.domain_webhost = webhost.vendor_id
        WHERE domain_id = $domain_id
    "));

    // Compare/log changes
    $ignored_columns = ["domain_updated_at", "domain_accessed_at", "domain_registrar", "domain_webhost", "domain_dnshost", "domain_mailhost"];
    foreach ($original_domain_info as $column => $old_value) {
        $new_value = $new_domain_info[$column];
        if ($old_value != $new_value && !in_array($column, $ignored_columns)) {
            $column = sanitizeInput($column);
            $old_value = sanitizeInput($old_value);
            $new_value = sanitizeInput($new_value);
            mysqli_query($mysqli,"INSERT INTO domain_history SET domain_history_column = '$column', domain_history_old_value = '$old_value', domain_history_new_value = '$new_value', domain_history_domain_id = $domain_id");
        }
    }

}
