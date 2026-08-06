<?php

/*
 * ITFlow - Cron dispatcher
 *
 * The only entry that belongs in the crontab:
 *
 *   * * * * * php /path/to/itflow/cron/cron.php >/dev/null
 *
 * It wakes once a minute, works out which jobs are due, and runs them. The jobs themselves
 * are listed in includes/cron_jobs.php; when and whether each one runs is held in the
 * cron_jobs table and edited from Maintenance > Cron.
 *
 * WHAT THE JOBS INHERIT
 *
 * Jobs are require'd into this process, so config.php, the timezone and functions.php are
 * already loaded by the time a job's own require_once lines run and those lines become
 * no-ops. That is fine for the bootstrap, which is what every job needs anyway, but it
 * means two things for job code:
 *
 *   - A job must end itself with cronJobStop(), never exit(). exit() ends the whole cycle
 *     and every job after it in the list.
 *   - Jobs share one global scope. A job must set the variables it reads rather than
 *     assuming the state a fresh process would have given it.
 *
 * SCHEDULING
 *
 * Due-ness is tracked in the cron_jobs table rather than by matching the clock, so a job
 * whose minute was missed - machine down, previous run still going, dispatch running a
 * second or two late - runs at the next opportunity instead of being skipped for the day.
 * A job is claimed before it runs, not after, so a run that dies half way through is not
 * repeated: nightly_tasks generates invoices and charges cards.
 *
 * Each job is also locked individually for the length of its own run, so a long job (the
 * nightly run, a slow mailbox) never holds up the every-minute jobs - the next minute's
 * dispatch picks those up in a second process while the long one is still going.
 */

// Set working directory to the directory this cron script lives at.
chdir(dirname(__FILE__));

// Ensure we're running from command line
if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line.\n");
}

// Tells cron/includes/cron_lock.php and the jobs themselves that they are running under the
// dispatcher rather than being executed directly. Must be defined before anything else
// is loaded.
define('ITFLOW_CRON_DISPATCHER', true);

require_once "includes/cron_lock.php";
require_once "../config.php";

// Set Timezone
require_once "../includes/inc_set_timezone.php";
require_once "../functions.php";
require_once "../includes/cron_jobs.php";

/*
 * Claim a job if it is due. The UPDATE is the claim: two dispatchers racing for the same
 * job both run it against the same row and the loser matches nothing, which also holds
 * across two web servers sharing one database, where the file lock would not. The same
 * statement consumes a Run Now request, so a button press can only ever produce one run.
 *
 * Every comparison is made against PHP's clock, not the database's, so the schedule follows
 * the timezone ITFlow is configured for however the database server is set up.
 */
function cronJobClaim($mysqli, array $job): bool
{
    $name = escapeSql($job['name']);
    $now = date('Y-m-d H:i:s');

    // Register the job the first time it is seen, seeded with the schedule it ships with.
    // From here on the row is what runs - Maintenance > Cron writes to it.
    $default_schedule = escapeSql($job['schedule']);
    $default_interval = intval($job['interval_minutes'] ?? 1);
    $default_daily_at = isset($job['daily_at']) ? "'" . escapeSql($job['daily_at']) . ":00'" : 'NULL';

    $default_enabled = isset($job['enabled']) ? intval($job['enabled']) : 1;

    mysqli_query($mysqli, "INSERT IGNORE INTO cron_jobs SET
        cron_job_name = '$name',
        cron_job_enabled = $default_enabled,
        cron_job_schedule = '$default_schedule',
        cron_job_interval_minutes = $default_interval,
        cron_job_daily_at = $default_daily_at");

    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT cron_job_daily_at, cron_job_enabled, cron_job_interval_minutes, cron_job_schedule FROM cron_jobs WHERE cron_job_name = '$name' LIMIT 1"));

    if (!$row) {
        return false;
    }

    // A Run Now request runs the job whatever its schedule says, and whether or not it is
    // enabled - turning the schedule off and running it by hand is a legitimate way to work.
    $due_clause = "cron_job_run_now = 1";

    if (!empty($row['cron_job_enabled'])) {

        if ($row['cron_job_schedule'] === 'Daily') {
            // Due once the most recent scheduled time has passed, unless we already ran since
            // it. Before today's time, that most recent occurrence was yesterday's - without
            // this, a dispatcher only invoked before the scheduled time (the old single
            // "0 2 * * * cron.php" crontab line against a 03:00 job) would never find the job
            // due and it would silently never run again.
            $threshold = date('Y-m-d') . ' ' . substr((string)$row['cron_job_daily_at'], 0, 5) . ':00';
            if ($now < $threshold) {
                $threshold = date('Y-m-d', strtotime('-1 day')) . ' ' . substr((string)$row['cron_job_daily_at'], 0, 5) . ':00';
            }
            $scheduled = true;
        } else {
            // Interval jobs get 30 seconds of slack. cron fires on the minute but a run can
            // start a second or two late, and an exact n-minute comparison would then find the
            // job 'not due yet' and skip every other cycle.
            $interval = max(1, intval($row['cron_job_interval_minutes']));

            // A job the registry marks interval-unsafe never runs more often than daily,
            // whatever its row says - a leftover row from before the schedule was locked
            // must not revive the every-minute failure. See includes/cron_jobs.php.
            if (($job['interval_safe'] ?? true) === false) {
                $interval = max($interval, 1440);
            }
            $threshold = date('Y-m-d H:i:s', time() - (($interval * 60) - 30));
            $scheduled = true;
        }

        if ($scheduled) {
            $threshold = escapeSql($threshold);
            $due_clause .= " OR (cron_job_enabled = 1 AND (cron_job_last_run_at IS NULL OR cron_job_last_run_at < '$threshold'))";
        }
    }

    mysqli_query($mysqli, "UPDATE cron_jobs SET
        cron_job_last_run_at = '$now',
        cron_job_last_status = 'Running',
        cron_job_run_now = 0
        WHERE cron_job_name = '$name'
        AND ($due_clause)");

    return mysqli_affected_rows($mysqli) === 1;
}

/*
 * Record how a job ended. The status is the outcome of the run that just happened; the error
 * is sticky and survives later successes, because the run that failed is usually long gone by
 * the time anyone goes looking. Maintenance > Cron clears it.
 */
function cronJobFinished($mysqli, string $job_name, string $status, ?float $duration = null, ?string $error = null): void
{
    $name = escapeSql($job_name);
    $status = escapeSql(substr($status, 0, 200));
    $finished_at = date('Y-m-d H:i:s');
    $duration_sql = $duration === null ? 'NULL' : "'" . number_format($duration, 2, '.', '') . "'";

    $error_sql = '';
    if ($error !== null) {
        $error_text = escapeSql(substr($error, 0, 1000));
        $error_sql = ", cron_job_last_error = '$error_text', cron_job_last_error_at = '$finished_at'";
    }

    // This is the failure path. A job that killed the database connection - a long backup
    // whose idle connection was closed, a server restart mid-cycle - must not have its
    // bookkeeping throw on top, or an uncaught mysqli_sql_exception ends the dispatch and
    // no record of the original failure survives anywhere.
    if (function_exists('backupDbEnsure')) {
        $mysqli = backupDbEnsure($mysqli);
    }

    try {
        mysqli_query($mysqli, "UPDATE cron_jobs SET
            cron_job_last_finished_at = '$finished_at',
            cron_job_last_status = '$status',
            cron_job_last_duration = $duration_sql
            $error_sql
            WHERE cron_job_name = '$name'");
    } catch (Throwable $e) {
        echo "Cron: could not record the outcome of '$job_name' - " . $e->getMessage() . "\n";
    }
}

// Proof the crontab is firing, recorded before any job runs. Maintenance > Cron reads it to tell
// "no job was due" apart from "nothing has run this since the server was rebuilt".
mysqli_query($mysqli, "UPDATE settings SET config_cron_last_dispatch_at = '" . date('Y-m-d H:i:s') . "' WHERE company_id = 1");

// Best effort: ask the server not to hang up while a long job is quiet.
if (function_exists('backupDbHoldOpen')) {
    backupDbHoldOpen($mysqli);
}

// A fatal error inside a job cannot be caught, and it takes the rest of the cycle with it.
// Recording which job was running at the time is the only trace of that left behind.
$cron_dispatch_running = null;
$cron_dispatch_started = null;
register_shutdown_function(function () use (&$cron_dispatch_running, &$cron_dispatch_started, $mysqli) {
    if ($cron_dispatch_running === null) {
        return;
    }

    $error = error_get_last();
    $reason = $error['message'] ?? 'ended unexpectedly';
    $duration = $cron_dispatch_started === null ? null : microtime(true) - $cron_dispatch_started;

    cronJobFinished($mysqli, $cron_dispatch_running, 'Failed', $duration, $reason);
});

foreach (cronJobRegistry() as $cron_dispatch_job) {

    $cron_dispatch_path = realpath(__DIR__ . '/' . $cron_dispatch_job['script']);

    if ($cron_dispatch_path === false) {
        // A job listed in the registry with no script behind it is a mistake worth hearing about
        echo "Cron: job '{$cron_dispatch_job['name']}' points at {$cron_dispatch_job['script']}, which does not exist.\n";
        continue;
    }

    // Locked before the schedule is consulted: if the previous run of this job is still
    // going there is nothing to decide, and its claim already stands.
    $cron_dispatch_lock = cronLockAcquire($cron_dispatch_path);
    if ($cron_dispatch_lock === false) {
        continue;
    }

    if (!cronJobClaim($mysqli, $cron_dispatch_job)) {
        cronLockRelease($cron_dispatch_lock);
        continue;
    }

    $cron_dispatch_running = $cron_dispatch_job['name'];
    $cron_dispatch_started = microtime(true);

    // Every job runs in this one process on this one connection, and a job that spends
    // minutes on network or file work without querying leaves it idle long enough for a
    // server with a short wait_timeout to close it. The next job then dies on a connection
    // it never touched. Re-establish before each one so a quiet job cannot poison the rest
    // of the cycle.
    if (function_exists('backupDbEnsure')) {
        $mysqli = backupDbEnsure($mysqli);
    }

    try {
        require_once $cron_dispatch_path;
        cronJobFinished($mysqli, $cron_dispatch_job['name'], 'Completed', microtime(true) - $cron_dispatch_started);
    } catch (CronJobStopped $e) {
        // The job ended itself early - disabled in settings, nothing configured, nothing to do
        $reason = $e->getMessage();
        cronJobFinished($mysqli, $cron_dispatch_job['name'], $reason === '' ? 'Stopped' : "Stopped: $reason", microtime(true) - $cron_dispatch_started);
    } catch (Throwable $e) {
        // One job throwing is not a reason to skip the rest of the cycle
        echo "Cron: job '{$cron_dispatch_job['name']}' failed - " . $e->getMessage() . "\n";
        try {
            logApp("Cron", "error", "Cron job {$cron_dispatch_job['name']} failed: " . $e->getMessage());
        } catch (Throwable $log_e) {
            // Logging the failure must never become a second, fatal failure
        }
        cronJobFinished($mysqli, $cron_dispatch_job['name'], 'Failed', microtime(true) - $cron_dispatch_started, $e->getMessage());
    }

    $cron_dispatch_running = null;
    $cron_dispatch_started = null;

    cronLockRelease($cron_dispatch_lock);
}
