<?php

/*
 * ITFlow - Update check job
 *
 * Asks the git remote whether a newer release exists and stores the answer. Nothing in the
 * web tier shells out any more, so this is the only place the question gets asked: the
 * Update page and the nightly notification both read what this job wrote.
 *
 * Check Now on Maintenance > Update sets cron_job_run_now on this job rather than checking
 * inside the request - same mechanism as Queue Update, and the page shows "Checking" until
 * the dispatcher consumes the flag.
 *
 * Safe to run twice: it reads and overwrites, and changes nothing outside the three settings
 * columns it owns.
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

$update_check_settings = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT config_enable_cron FROM settings WHERE company_id = 1"));

$config_enable_cron = intval($update_check_settings['config_enable_cron'] ?? 0);

if ($config_enable_cron == 0) {
    cronJobStop("Cron: is not enabled\n");
}

// An install whose files are newer than its schema reaches this job before the migration
// that adds the columns has run. Asking for them would throw
if (!settingsColumnExists($mysqli, 'config_update_latest_commit')) {
    cronJobStop("The database update has not been applied yet\n");
}

// A zip-drop install has no remote to ask. Not a failure - there is simply nothing to check
if (!file_exists("../.git")) {
    cronJobStop("Not a git checkout - there is no remote to check against\n");
}

if (!shellCommandsAvailable()) {
    cronJobStop("PHP on the command line cannot run git (exec is disabled), so the check cannot run\n");
}

$updates = checkForUpdates();

/*
 * Thrown rather than stopped: a fetch that fails is something to look at, and the dispatcher
 * records a thrown message against the job so it shows on both Maintenance > Cron and the
 * Update page. The last three lines, not the last one - git's final line is often the
 * suggested fix rather than the problem.
 */
if ($updates->result !== 0) {
    $update_check_error = trim(implode(' | ', array_slice($updates->output, -3)));
    throw new Exception("git fetch failed: " . ($update_check_error === '' ? 'no output' : $update_check_error));
}

$update_check_latest = escapeSql($updates->latest_version);
$update_check_commits = escapeSql((string) json_encode($updates->pending_commits));

mysqli_query($mysqli, "UPDATE settings SET
    config_update_latest_commit = '$update_check_latest',
    config_update_pending_commits = '$update_check_commits',
    config_update_checked_at = '" . date('Y-m-d H:i:s') . "'
    WHERE company_id = 1");

echo count($updates->pending_commits) . " commit(s) behind the remote\n";
