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
    logApp("Cron-Certificate-Refresher", "error", "Cron Certificate Refresh unable to run - cron not enabled in admin settings.");
    exit("Cron: is not enabled -- Quitting..");
}

/*
 * ###############################################################################################################
 *  UPDATE CERTIFICATE EXPIRY DATE
 * ###############################################################################################################
 */

$sql_certificates = mysqli_query(
    $mysqli,
    "SELECT * FROM certificates
        LEFT JOIN clients ON certificates.certificate_client_id = clients.client_id
        WHERE certificate_archived_at IS NULL
        AND client_archived_at IS NULL"
);

while ($row = mysqli_fetch_array($sql_certificates)) {
    $certificate_id = intval($row['certificate_id']);
    $domain = sanitizeInput($row['certificate_domain']);

    $certificate = getSSL($domain);

    $expire = sanitizeInput($certificate['expire']);
    $issued_by = sanitizeInput($certificate['issued_by']);
    $public_key = sanitizeInput($certificate['public_key']);

    if (!empty($expire)) {

        echo "\n$domain\n";
        echo "$issued_by\n";
        echo "$expire\n";
        echo "$public_key\n\n";

        $expire = "'" . $expire . "'";

        // Get current certificate info
        $original_certificate_info = mysqli_fetch_assoc(mysqli_query($mysqli,"
            SELECT
                certificates.*,
                domains.domain_name
            FROM certificates
            LEFT JOIN domains ON certificate_domain_id = domain_id
            WHERE certificate_id = $certificate_id
        "));

        // Update
        mysqli_query($mysqli,"UPDATE certificates SET certificate_issued_by = '$issued_by', certificate_expire = $expire, certificate_public_key = '$public_key' WHERE certificate_id = $certificate_id");

        // Fetch the updated info
        $new_certificate_info = mysqli_fetch_assoc(mysqli_query($mysqli,"
            SELECT
                certificates.*,
                domains.domain_name
            FROM certificates
            LEFT JOIN domains ON certificate_domain_id = domain_id
            WHERE certificate_id = $certificate_id
        "));

        // Compare/log changes between old/new info
        $ignored_columns = ["certificate_public_key", "certificate_updated_at", "certificate_accessed_at", "certificate_domain_id"];
        foreach ($original_certificate_info as $column => $old_value) {
            $new_value = $new_certificate_info[$column];
            if ($old_value != $new_value && !in_array($column, $ignored_columns)) {
                $column = sanitizeInput($column);
                $old_value = sanitizeInput($old_value);
                $new_value = sanitizeInput($new_value);
                mysqli_query($mysqli,"INSERT INTO certificate_history SET certificate_history_column = '$column', certificate_history_old_value = '$old_value', certificate_history_new_value = '$new_value', certificate_history_certificate_id = $certificate_id");
            }
        }

    } else {
        logApp("Cron-Certificate-Refresher", "error", "Cron Certificate Refresh - error updating Error updating $domain.");
        error_log("Certificate Cron Error - Error updating $domain");
    }

}
