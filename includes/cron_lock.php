<?php

/*
 * ITFlow - Cron runtime: single-run guard and dispatcher support
 *
 * Required by each cron script immediately after its CLI check and before config.php,
 * with the caller setting $cron_lock_script = __FILE__ first.
 *
 * Cron work is not safe to run twice at once: autopay charges cards, the mail queue
 * sends client email, the parser creates tickets. If a run is still going when the next
 * one fires, those actions can happen twice. The lock name is derived from the calling
 * script's full path, so it is unique per script and per install - separate ITFlow
 * instances on one host never block each other, and neither do two different cron
 * scripts belonging to the same install.
 *
 * flock is used rather than a lock file whose presence is checked, because checking for
 * a file and then creating it is not atomic - two runs starting together can both find
 * it absent. flock is also released by the kernel however the process exits, so a killed
 * run leaves nothing stale behind and no age heuristic is needed to clean up after it.
 *
 * Contention is not logged: mail_queue and the email parser run every minute, so hitting
 * a run that is still going is expected, not a fault worth reporting. A lock file that
 * cannot be opened at all is a real misconfiguration and does report loudly.
 *
 * TWO WAYS A CRON SCRIPT RUNS
 *
 * Directly (php cron/mail_queue.php): the guard at the bottom of this file takes the
 * lock and holds it for the life of the process, exactly as it always has.
 *
 * Under the dispatcher (cron/cron.php): the dispatcher takes each job's lock itself,
 * runs the job, and releases it before moving on, so a long job does not hold up the
 * short ones on the next minute's dispatch. The guard below is skipped in that case -
 * the lock is already held for this job, and the jobs share one PHP process, so a lock
 * held for the life of the process would be a lock held for the whole cycle.
 *
 * Because the dispatcher shares one process across jobs, a job must never exit() to end
 * itself early - that would take the rest of the cycle down with it. cronJobStop() is
 * the replacement: it exits when the script was run directly and unwinds back to the
 * dispatcher when it wasn't.
 */

/*
 * Thrown by cronJobStop() when a job ends itself early under the dispatcher. Carries the
 * message and exit code the script would have exited with, so the dispatcher can record
 * why the job stopped.
 */
class CronJobStopped extends Exception
{
}

/*
 * End the current cron job early. Direct runs exit exactly as they did before; dispatched
 * runs unwind to the dispatcher, which records the reason and carries on with the next job.
 */
function cronJobStop(string $message = '', int $exit_code = 0): void
{
    if (defined('ITFLOW_CRON_DISPATCHER')) {
        throw new CronJobStopped($message, $exit_code);
    }

    if ($message !== '') {
        echo $message;
    }

    exit($exit_code);
}

/*
 * Take the single-run lock for a cron script. $script_path must be the script's own
 * __FILE__ (or the same resolved path when the dispatcher takes it on the job's behalf),
 * because that path is what the lock is named after.
 *
 * Returns the open handle on success, or false when another run holds the lock. The
 * handle must stay open for as long as the lock is wanted - closing it releases the lock.
 */
function cronLockAcquire(string $script_path)
{
    $lock_file = sys_get_temp_dir() . '/itflow_cron_' . md5($script_path) . '.lock';

    $lock_handle = fopen($lock_file, 'c');
    if ($lock_handle === false) {
        die("Cannot open the cron lock file at $lock_file - check permissions and open_basedir.\n");
    }

    if (!flock($lock_handle, LOCK_EX | LOCK_NB)) {
        // Closing our own handle does not disturb the lock the other run holds on theirs
        fclose($lock_handle);
        return false;
    }

    return $lock_handle;
}

/*
 * Release a lock taken by cronLockAcquire(). Only the dispatcher needs this - a direct run
 * holds its lock until the process ends and the kernel drops it.
 */
function cronLockRelease($lock_handle): void
{
    if (is_resource($lock_handle)) {
        flock($lock_handle, LOCK_UN);
        fclose($lock_handle);
    }
}

// Single-run guard for scripts run directly. Skipped under the dispatcher, which locks
// each job itself - see the note above.
if (!defined('ITFLOW_CRON_DISPATCHER')) {

    if (!isset($cron_lock_script)) {
        die("Cron scripts must set \$cron_lock_script = __FILE__ before requiring includes/cron_lock.php.\n");
    }

    $cron_lock_handle = cronLockAcquire($cron_lock_script);

    if ($cron_lock_handle === false) {
        // Exit silently. On a per-minute schedule, finding a previous run still going is
        // normal operation rather than an error, and anything written to stdout here would
        // be mailed to the crontab owner every single minute for the length of that run.
        exit(0);
    }

    // The handle is deliberately left open: the lock is held for the life of the process.
}
