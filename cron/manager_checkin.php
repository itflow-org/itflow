<?php

/*
 * ITFlow - Manager check-in job
 *
 * For an ITFlow managed by another (Admin > Managed By): every minute, tells the manager how
 * this install is doing and takes back what the manager sets - seat limit, white label,
 * updates, active, read-only or suspended - and anything it has asked, like an update. See
 * functions/managed.php. The instance always calls; the manager never calls it.
 *
 * First in the registry, so a change of mode applies to the jobs after it in the same cycle.
 * Stops straight away on an install that isn't managed - which is most of them.
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

$manager_checkin_settings = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT config_enable_cron FROM settings WHERE company_id = 1"));

if (intval($manager_checkin_settings['config_enable_cron'] ?? 0) === 0) {
    cronJobStop("Cron: is not enabled\n");
}

// Files newer than the schema - the column managed mode keeps its state in isn't there yet
if (!settingsColumnExists($mysqli, 'config_managed')) {
    cronJobStop("The database update has not been applied yet\n");
}

if (!managedIsManaged()) {
    cronJobStop("Not managed\n");
}

$manager_checkin = managedCheckin();

// Thrown, so the dispatcher records why against the job and Admin > Managed By can show it
if (!$manager_checkin['ok']) {
    throw new Exception($manager_checkin['error']);
}
