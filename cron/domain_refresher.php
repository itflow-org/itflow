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
    logApp("Cron-Domain-Refresher", "error", "Cron Domain Refresh unable to run - cron not enabled in admin settings.");
    cronJobStop("Cron: is not enabled -- Quitting..");
}

/*
 * ###############################################################################################################
 *  REFRESH DATA
 * ###############################################################################################################
 */

// A batch a run - as many as fit in a few minutes, up to 50. Due are the ones
// never looked up, not looked up in a week, or expiring within 60 days and not
// looked up today. Least recently tried first: a domain that answers nothing is
// touched anyway, so it goes to the back rather than taking a slot every run.
// A lookup that finds nothing changes nothing (domainApplyLookup()).
$started = time();
$checked = $found = 0;
$sql = mysqli_query($mysqli, "SELECT domain_id, domain_name FROM domains
    LEFT JOIN clients ON client_id = domain_client_id
    WHERE domain_archived_at IS NULL AND client_archived_at IS NULL
    AND (domain_refreshed_at IS NULL OR domain_refreshed_at < NOW() - INTERVAL 7 DAY
        OR (domain_expire IS NOT NULL AND domain_expire <= CURDATE() + INTERVAL 60 DAY AND domain_refreshed_at < NOW() - INTERVAL 1 DAY))
    ORDER BY domain_updated_at ASC
    LIMIT 50");

while ($row = mysqli_fetch_assoc($sql)) {

    if (time() - $started > 240) {
        break;
    }

    $domain_id = intval($row['domain_id']);
    $checked++;

    // Touch the record we're refreshing to ensure we don't loop
    mysqli_query($mysqli, "UPDATE domains SET domain_updated_at = NOW() WHERE domain_id = $domain_id");

    $before = domainHistorySnapshot($domain_id);
    if (domainApplyLookup($domain_id, domainLookup($row['domain_name']))) {
        domainWriteHistory($domain_id, $before, domainHistorySnapshot($domain_id));
        $found++;
        echo "Updated {$row['domain_name']}.\n";
    } else {
        echo "Nothing answered for {$row['domain_name']} - left as it was.\n";
    }

    // Be gentle on the registries
    sleep(1);
}

if ($checked) {
    logApp("Cron-Domain-Refresher", "info", "Looked up $checked domain(s), $found answered.");
}
