<?php

/*
 * ITFlow - Cron dispatcher
 *
 * The only entry that belongs in the crontab:
 *
 *   * * * * * php /path/to/itflow/cron/cron.php >/dev/null
 *
 * It wakes once a minute, works out which jobs are due, and runs them. Adding a job is a
 * new script in cron/ and a line in the table below - the crontab never has to change again.
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

// Tells includes/cron_lock.php and the jobs themselves that they are running under the
// dispatcher rather than being executed directly. Must be defined before anything else
// is loaded.
define('ITFLOW_CRON_DISPATCHER', true);

require_once "../includes/cron_lock.php";
require_once "../config.php";

// Set Timezone
require_once "../includes/inc_set_timezone.php";
require_once "../functions.php";

/*
 * The jobs, in the order they run.
 *
 *   'every'    => n         run every n minutes
 *   'daily_at' => 'HH:MM'   run once a day, at or after this time
 *
 * Order matters: the every-minute jobs come first so a nightly run cannot delay them, and
 * mail_queue comes before the jobs that queue mail so nothing sits in the queue for a
 * minute longer than it has to.
 *
 * Each job still checks its own settings (cron enabled, email parsing enabled, and so on)
 * and stops itself when it has nothing to do, so a disabled feature costs a require and a
 * settings read.
 */
$cron_dispatch_jobs = [
    ['name' => 'mail_queue',            'script' => 'mail_queue.php',            'every' => 1],
    ['name' => 'ticket_email_parser',   'script' => 'ticket_email_parser.php',   'every' => 1],
    ['name' => 'ticket_sla',            'script' => 'ticket_sla.php',            'every' => 1],
    ['name' => 'domain_refresher',      'script' => 'domain_refresher.php',      'every' => 5],
    ['name' => 'nightly_tasks',         'script' => 'nightly_tasks.php',         'daily_at' => '03:00'],
    ['name' => 'certificate_refresher', 'script' => 'certificate_refresher.php', 'daily_at' => '03:30'],
];

/*
 * Claim a job if it is due. The UPDATE is the claim: two dispatchers racing for the same
 * job both run it against the same row and the loser matches nothing, which also holds
 * across two web servers sharing one database, where the file lock would not.
 *
 * Every comparison is made against PHP's clock, not the database's, so the schedule follows
 * the timezone ITFlow is configured for however the database server is set up.
 */
function cronJobClaim($mysqli, array $job): bool
{
    $name = escapeSql($job['name']);
    $now = date('Y-m-d H:i:s');

    // Register the job the first time it is seen - adding a job needs no migration
    mysqli_query($mysqli, "INSERT IGNORE INTO cron_jobs SET cron_job_name = '$name'");

    if (isset($job['daily_at'])) {
        // Due once today's scheduled time has passed, unless we have already run since it
        $threshold = date('Y-m-d') . ' ' . $job['daily_at'] . ':00';
        if ($now < $threshold) {
            return false;
        }
    } else {
        // Interval jobs get 30 seconds of slack. cron fires on the minute but a run can
        // start a second or two late, and an exact n-minute comparison would then find the
        // job 'not due yet' and skip every other cycle.
        $every = max(1, intval($job['every'] ?? 1));
        $threshold = date('Y-m-d H:i:s', time() - (($every * 60) - 30));
    }

    mysqli_query($mysqli, "UPDATE cron_jobs SET
        cron_job_last_run_at = '$now',
        cron_job_last_status = 'Running'
        WHERE cron_job_name = '$name'
        AND (cron_job_last_run_at IS NULL OR cron_job_last_run_at < '$threshold')");

    return mysqli_affected_rows($mysqli) === 1;
}

/*
 * Record how a job ended. Only ever cosmetic - nothing schedules off the result - but it is
 * the difference between "cron is broken" and knowing which job broke and when.
 */
function cronJobFinished($mysqli, string $job_name, string $status): void
{
    $name = escapeSql($job_name);
    $status = escapeSql(substr($status, 0, 200));
    $finished_at = date('Y-m-d H:i:s');

    mysqli_query($mysqli, "UPDATE cron_jobs SET
        cron_job_last_finished_at = '$finished_at',
        cron_job_last_status = '$status'
        WHERE cron_job_name = '$name'");
}

// A fatal error inside a job cannot be caught, and it takes the rest of the cycle with it.
// Recording which job was running at the time is the only trace of that left behind.
$cron_dispatch_running = null;
register_shutdown_function(function () use (&$cron_dispatch_running, $mysqli) {
    if ($cron_dispatch_running === null) {
        return;
    }

    $error = error_get_last();
    $reason = $error['message'] ?? 'ended unexpectedly';

    cronJobFinished($mysqli, $cron_dispatch_running, "Failed: $reason");
});

foreach ($cron_dispatch_jobs as $cron_dispatch_job) {

    $cron_dispatch_path = realpath(__DIR__ . '/' . $cron_dispatch_job['script']);

    if ($cron_dispatch_path === false) {
        // A job listed above with no script behind it is a mistake worth hearing about
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

    try {
        require_once $cron_dispatch_path;
        cronJobFinished($mysqli, $cron_dispatch_job['name'], 'Completed');
    } catch (CronJobStopped $e) {
        // The job ended itself early - disabled in settings, nothing configured, nothing to do
        $reason = $e->getMessage();
        cronJobFinished($mysqli, $cron_dispatch_job['name'], $reason === '' ? 'Stopped' : "Stopped: $reason");
    } catch (Throwable $e) {
        // One job throwing is not a reason to skip the rest of the cycle
        logApp("Cron", "error", "Cron job {$cron_dispatch_job['name']} failed: " . $e->getMessage());
        cronJobFinished($mysqli, $cron_dispatch_job['name'], 'Failed: ' . $e->getMessage());
    }

    $cron_dispatch_running = null;

    cronLockRelease($cron_dispatch_lock);
}
