<?php

/*
 * ITFlow - Managed instances job
 *
 * For an ITFlow that manages others (Admin > Managed Instances): notices what the instances
 * don't say themselves. An instance that has stopped checking in gets one notification, until
 * it's back (the check-in clears it - managerCheckin()); a request it took and never answered,
 * or an update that never said it finished, is closed as failed rather than left waiting.
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

$managed_instances_settings = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT config_enable_cron FROM settings WHERE company_id = 1"));

if (intval($managed_instances_settings['config_enable_cron'] ?? 0) === 0) {
    cronJobStop("Cron: is not enabled\n");
}

// Files newer than the schema - the tables arrive with the migration
if (!settingsColumnExists($mysqli, 'config_manager_keys')) {
    cronJobStop("The database update has not been applied yet\n");
}

$managed_instances_count = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM managed_instances
    WHERE managed_instance_archived_at IS NULL AND managed_instance_secret_hash IS NOT NULL"))[0] ?? 0);

if ($managed_instances_count === 0) {
    cronJobStop("No instances connected\n");
}

// Gone quiet - told once, until it checks in again
$managed_instances_quiet = mysqli_query($mysqli, "SELECT managed_instance_id, managed_instance_name, managed_instance_client_id, managed_instance_last_checkin_at FROM managed_instances
    WHERE managed_instance_archived_at IS NULL AND managed_instance_secret_hash IS NOT NULL AND managed_instance_offline_notified_at IS NULL
    AND (managed_instance_last_checkin_at IS NULL OR managed_instance_last_checkin_at < NOW() - INTERVAL " . MANAGED_OFFLINE_MINUTES . " MINUTE)");

while ($managed_instance = mysqli_fetch_assoc($managed_instances_quiet)) {
    $managed_instance_id = intval($managed_instance['managed_instance_id']);
    $managed_instance_last = $managed_instance['managed_instance_last_checkin_at'];

    appNotify("Managed Instance", escapeSql($managed_instance['managed_instance_name'] . " has stopped checking in" . ($managed_instance_last ? " - last " . date('M j, g:i A', strtotime($managed_instance_last)) : '')),
        "/admin/managed_instance.php?id=$managed_instance_id", intval($managed_instance['managed_instance_client_id']), $managed_instance_id);

    mysqli_query($mysqli, "UPDATE managed_instances SET managed_instance_offline_notified_at = NOW() WHERE managed_instance_id = $managed_instance_id");
}

// A request it took and never answered
mysqli_query($mysqli, "UPDATE managed_instance_commands SET managed_command_status = 'failed', managed_command_done_at = NOW(),
    managed_command_result = 'It took the request but never answered - see whether it is still checking in'
    WHERE managed_command_status = 'sent' AND managed_command_sent_at < NOW() - INTERVAL 1 HOUR");

// An update that never said it finished
$managed_instances_stuck = mysqli_query($mysqli, "SELECT managed_command_id, managed_instance_id, managed_instance_name, managed_instance_client_id
    FROM managed_instance_commands JOIN managed_instances ON managed_instance_id = managed_command_instance_id
    WHERE managed_command = 'update_now' AND managed_command_status = 'running' AND managed_command_sent_at < NOW() - INTERVAL 2 HOUR");

while ($managed_instance = mysqli_fetch_assoc($managed_instances_stuck)) {
    mysqli_query($mysqli, "UPDATE managed_instance_commands SET managed_command_status = 'failed', managed_command_done_at = NOW(),
        managed_command_result = 'No word of the update finishing in two hours - look at its Maintenance > Update page'
        WHERE managed_command_id = " . intval($managed_instance['managed_command_id']));

    appNotify("Managed Instance", escapeSql("The update on " . $managed_instance['managed_instance_name'] . " hasn't finished in two hours"),
        "/admin/managed_instance.php?id=" . intval($managed_instance['managed_instance_id']), intval($managed_instance['managed_instance_client_id']), intval($managed_instance['managed_instance_id']));
}
