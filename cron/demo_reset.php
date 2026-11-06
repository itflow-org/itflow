<?php

/*
 * ITFlow - Demo Reset job
 *
 * For the demo - an ITFlow config.php turns demo mode on for ($config_demo_mode, see
 * functions/demo.php). Puts it back to fresh demo data: every table made again from db.sql,
 * then the demo accounts, all of Starter Content, the demo clients and a client portal
 * sign-in, with the uploads folder emptied. The company's details, the timezone and the cron
 * schedules carry over. Everyone signed in is signed out.
 *
 * Listed in the registry only in the demo, daily at 2:00 AM. Run it now with:
 *
 *   php cron/demo_reset.php
 *
 * Anywhere else it stops straight away - it never empties an ITFlow that isn't the demo.
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

// Set Timezone - unless a reset that failed part way left no settings to read it from; this one
// puts them back, and sets it then
try {
    require_once "../includes/inc_set_timezone.php";
} catch (Throwable $e) {
}
require_once "../functions.php";

if (!demoModeOn()) {
    cronJobStop("Not the demo - config.php doesn't turn on \$config_demo_mode, so nothing was reset");
}

// A reset that failed part way leaves no settings to read - this is what puts them back
try {
    $demo_reset_settings = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT config_enable_cron FROM settings WHERE company_id = 1"));
} catch (Throwable $e) {
    $demo_reset_settings = ['config_enable_cron' => 1];
}
if (intval($demo_reset_settings['config_enable_cron'] ?? 1) === 0) {
    cronJobStop("Cron: is not enabled -- Quitting..");
}

$demo_reset_started = microtime(true);
$demo_reset = demoModeReset($mysqli);
echo "Demo reset in " . number_format(microtime(true) - $demo_reset_started, 1) . "s - {$demo_reset['clients']} clients, {$demo_reset['users']} demo accounts"
    . ($demo_reset['portal'] !== '' ? ", portal sign-in {$demo_reset['portal']}" : ', no portal sign-in') . "\n";

// The jobs after this one in the cycle wait for the next minute, against the fresh database
$cron_dispatch_stop_cycle = true;
