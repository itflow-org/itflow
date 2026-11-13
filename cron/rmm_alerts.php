<?php

/*
 * ITFlow - RMM Alerts job
 *
 * Every minute, for each RMM provider whose alerts open tickets (Admin > RMM Providers): a new
 * alert on a device of a linked client opens a ticket on it - or goes on the ticket the same
 * thing already has - and an alert that has cleared resolves its ticket, unless someone has
 * worked on it (rmmAlertsPoll() in functions/rmm.php).
 *
 * Safe every minute: a provider still being looked at from the last run is left to it. With
 * no provider's alerts turned on, a run is a query or two and nothing else.
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

$rmm_alerts_settings = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT config_enable_cron, config_module_enable_ticketing FROM settings WHERE company_id = 1"));

if (intval($rmm_alerts_settings['config_enable_cron'] ?? 0) == 0) {
    cronJobStop("Cron: is not enabled\n");
}

if (intval($rmm_alerts_settings['config_module_enable_ticketing'] ?? 0) == 0) {
    cronJobStop("Ticketing is turned off\n");
}

$rmm_alerts_results = rmmAlertsPollAll();

if (!$rmm_alerts_results) {
    cronJobStop("No RMM provider has alerts opening tickets\n");
}

$rmm_alerts_failed = [];
foreach ($rmm_alerts_results as $rmm_alerts_name => $rmm_alerts_result) {
    if ($rmm_alerts_result['ok']) {
        echo "$rmm_alerts_name: " . $rmm_alerts_result['note'] . "\n";
    } else {
        $rmm_alerts_failed[] = "$rmm_alerts_name: " . $rmm_alerts_result['note'];
    }
}

if ($rmm_alerts_failed) {
    // Says why on Maintenance > Cron - and in App Logs, which rmmAlertsPoll() wrote to when it started failing
    cronJobStop(implode("\n", $rmm_alerts_failed) . "\n", 1);
}
