<?php

/*
 * ITFlow - Application update job
 *
 * Runs an update queued from Maintenance > Update. It never updates an install on its own:
 * the only trigger is config_update_queued_at, which the Update page sets and this job takes
 * before it starts anything, so a schedule, a Run Now or a crashed run cannot produce an
 * unasked-for update.
 *
 * The update itself is scripts/update_cli.php in its OWN process. That script replaces the
 * files this dispatcher is running from and then applies the database migrations that came
 * with them, so it has to be a separate process - see the comment in that file. This job
 * only starts it, waits, and records what happened.
 *
 * Because the files on disk change under the dispatcher, this job ends the dispatch cycle
 * after a successful update rather than letting the jobs behind it be loaded half from the
 * old release and half from the new one.
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

$app_update_settings = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT config_enable_cron FROM settings WHERE company_id = 1"));

$config_enable_cron = intval($app_update_settings['config_enable_cron'] ?? 0);

if ($config_enable_cron == 0) {
    cronJobStop("Cron: is not enabled\n");
}

// An install whose files are newer than its schema reaches this job before the migration
// that adds the column has run. Nothing can have been queued yet, and asking for it throws
if (!settingsColumnExists($mysqli, 'config_update_queued_at')) {
    cronJobStop("The database update has not been applied yet\n");
}

$config_update_queued_at = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT config_update_queued_at FROM settings WHERE company_id = 1"))['config_update_queued_at'] ?? null;

if (empty($config_update_queued_at)) {
    // Nothing was asked for. Reached by a Run Now from Maintenance > Cron, or by somebody
    // putting this job on a schedule - neither is on its own a reason to update an install.
    cronJobStop("No update queued\n");
}

/*
 * Taken before the update runs, not after. An update that dies half way through - a failed
 * migration, a machine losing power mid-checkout - must be looked at rather than retried
 * unattended a minute later.
 */
mysqli_query($mysqli, "UPDATE settings SET config_update_queued_at = NULL WHERE company_id = 1");

$app_update_script = realpath(__DIR__ . "/../scripts/update_cli.php");

if ($app_update_script === false) {
    throw new Exception("scripts/update_cli.php is missing - the update cannot be run.");
}

if (!function_exists('exec')) {
    throw new Exception("PHP on the command line cannot start other processes (exec is disabled), so the update cannot be run.");
}

// Recorded either side of the run so that "did the files actually change" is a commit
// comparison rather than a match on wording. Empty on an install that is not a git checkout,
// where update_cli.php updates the database only and nothing moves underneath us.
$app_update_head_before = trim((string) exec("git rev-parse HEAD 2>/dev/null"));

echo "Running $app_update_script\n";

$app_update_output = [];
exec(escapeshellarg(PHP_BINARY) . " " . escapeshellarg($app_update_script) . " 2>&1", $app_update_output, $app_update_return);

$app_update_text = trim(implode("\n", $app_update_output));

echo $app_update_text . "\n";

$app_update_head_after = trim((string) exec("git rev-parse HEAD 2>/dev/null"));

$app_update_changed = $app_update_head_before !== ''
    && $app_update_head_after !== ''
    && $app_update_head_before !== $app_update_head_after;

/*
 * Read by cron/cron.php. The files behind every job after this one have just been replaced,
 * and this process is still running the release from before, so the cycle ends here and the
 * next minute's dispatch runs the rest against one version of the code.
 */
if ($app_update_changed) {
    $cron_dispatch_stop_cycle = true;
}

// The tail is what says why it stopped, and the whole output can run to dozens of lines.
// The very last line alone is often the suggested fix rather than the problem itself
// ("You could try sudo -u ..."), so keep a few of them.
$app_update_lines = array_values(array_filter(array_map('trim', $app_update_output), 'strlen'));
$app_update_summary = implode(" | ", array_slice($app_update_lines, -3));

if ($app_update_return !== 0) {
    appNotify("Update", "The queued update failed - $app_update_summary", "/admin/update.php");
    logAudit("App", "Update", "Cron ran a queued update which failed: $app_update_summary");
    throw new Exception("update_cli.php exited $app_update_return - $app_update_summary");
}

appNotify("Update", "The queued update finished", "/admin/update.php");
logAudit("App", "Update", "Cron applied a queued update");
