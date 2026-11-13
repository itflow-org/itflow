<?php

/*
 * ITFlow - RMM Sync job
 *
 * Brings in the devices of the clients linked in Admin > RMM Providers - each one an asset,
 * matched to the one already there or made - and keeps up what each RMM says about them:
 * online or not, last seen, who is signed in, what needs attention (rmmSyncAll() in
 * functions/rmm.php). Up to two minutes a run across every provider that is on; each picks up
 * next run where it stopped, the clients brought in longest ago first.
 *
 * Safe on an interval: a provider whose sync is still going from the last run is left to it.
 * With no RMM set up, a run is one query and nothing else.
 */

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

$rmm_sync_settings = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT config_enable_cron FROM settings WHERE company_id = 1"));

if (intval($rmm_sync_settings['config_enable_cron'] ?? 0) == 0) {
    cronJobStop("Cron: is not enabled\n");
}

$rmm_sync_results = rmmSyncAll();

if (!$rmm_sync_results) {
    cronJobStop("No RMM provider is set up and on\n");
}

$rmm_sync_failed = [];
foreach ($rmm_sync_results as $rmm_sync_name => $rmm_sync_result) {
    if ($rmm_sync_result['ok']) {
        echo "$rmm_sync_name: " . $rmm_sync_result['note'] . "\n";
    } else {
        $rmm_sync_failed[] = "$rmm_sync_name: " . $rmm_sync_result['note'];
    }
}

if ($rmm_sync_failed) {
    // Says why on Maintenance > Cron - and in App Logs, which rmmSync() wrote to
    cronJobStop(implode("\n", $rmm_sync_failed) . "\n", 1);
}
