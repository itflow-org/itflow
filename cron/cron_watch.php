<?php

/*
 * ITFlow - Cron Watch
 *
 * Emails the admins when a cron job fails, gets stuck or falls behind - once
 * when it starts, once a day while it lasts, and when it's fixed
 * (cronHealthWatch() in functions/cron_health.php). Run by cron/cron.php every
 * 5 minutes; can also be run directly at any time.
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
require_once "../includes/inc_set_timezone.php";
require_once "../functions.php";

// Read the master switch here rather than trusting a global - see the cron rules in CONTRIBUTING.md
$row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT config_enable_cron FROM settings WHERE company_id = 1"));
if (intval($row['config_enable_cron']) === 0) {
    cronJobStop("Cron: is not enabled -- Quitting..");
}

if (!cronHealthReady()) {
    cronJobStop("Waiting for the database update (Maintenance > Update)");
}

foreach (cronHealthWatch() as $cron_watch_line) {
    echo "$cron_watch_line\n";
}
