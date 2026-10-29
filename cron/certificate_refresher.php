<?php

// Set working directory to the directory this cron script lives at.
chdir(dirname(__FILE__));

// Ensure we're running from command line
if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line.\n");
}

// Prevent overlapping runs of this script
$cron_lock_script = __FILE__;
require_once "includes/cron_lock.php";

require_once "../config.php";

// Set Timezone
require_once "../includes/inc_set_timezone.php";
require_once "../functions.php";


$sql_settings = mysqli_query($mysqli, "SELECT config_enable_cron FROM settings WHERE settings.company_id = 1");

$row = mysqli_fetch_assoc($sql_settings);

// Company Settings
$config_enable_cron = intval($row['config_enable_cron']);

// Check cron is enabled
if ($config_enable_cron == 0) {
    logApp("Cron-Certificate-Refresher", "error", "Cron Certificate Refresh unable to run - cron not enabled in admin settings.");
    cronJobStop("Cron: is not enabled -- Quitting..");
}

/*
 * ###############################################################################################################
 *  CHECK CERTIFICATES
 * ###############################################################################################################
 */

// Every certificate with a host, once a day - connect, take what it serves,
// and save it with what the check found (certificateCheckAndSave()). Never
// checked first, then least recently checked; as many as fit in ten minutes,
// the rest first in line tomorrow. A host that doesn't answer is recorded as
// unreachable and keeps the certificate on file. Certificates with no host
// (pasted, or typed by hand) have nothing to check and are left alone.
$started = time();
$tally = array_fill_keys(array_keys(CERTIFICATE_CHECK_STATES), 0);
$sql = mysqli_query($mysqli, "SELECT certificate_id, certificate_name, certificate_domain, certificate_check_status, certificate_client_id, client_name FROM certificates
    LEFT JOIN clients ON client_id = certificate_client_id
    WHERE certificate_archived_at IS NULL AND client_archived_at IS NULL
    AND certificate_domain IS NOT NULL AND certificate_domain <> ''
    AND (certificate_checked_at IS NULL OR certificate_checked_at < NOW() - INTERVAL 20 HOUR)
    ORDER BY certificate_checked_at IS NOT NULL, certificate_checked_at ASC
    LIMIT 1000");

while ($row = mysqli_fetch_assoc($sql)) {

    if (time() - $started > 600) {
        break;
    }

    $check = certificateCheckAndSave($row['certificate_id']);
    if ($check === null) {
        // Not a host it can connect to - leave it be, but out of the queue until tomorrow
        mysqli_query($mysqli, "UPDATE certificates SET certificate_checked_at = NOW(), certificate_check_status = NULL WHERE certificate_id = " . intval($row['certificate_id']));
        continue;
    }
    $status = empty($check['reachable']) ? 'unreachable' : $check['status'];
    $tally[$status]++;
    echo "{$row['certificate_domain']}: $status\n";

    // Was fine yesterday, isn't today - someone installed the wrong one, or it lapsed.
    // (A host not answering is left to the list - that is as often a blip as a fault.)
    if ($row['certificate_check_status'] === 'ok' && in_array($status, ['mismatch', 'untrusted', 'expired'], true)) {
        $certificate_id = intval($row['certificate_id']);
        $client_id = intval($row['certificate_client_id']);
        appNotify("Certificate Problem", escapeSql("Certificate {$row['certificate_name']} for {$row['client_name']}: " . CERTIFICATE_CHECK_STATES[$status]['text']), "/agent/certificates.php?client_id=$client_id&open=$certificate_id", $client_id);
    }
}

$checked = array_sum($tally);
if ($checked) {
    $parts = [];
    foreach ($tally as $status => $count) {
        if ($count) {
            $parts[] = "$count $status";
        }
    }
    logApp("Cron-Certificate-Refresher", "info", "Checked $checked certificate(s): " . implode(', ', $parts) . '.');
}
