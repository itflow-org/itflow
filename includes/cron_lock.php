<?php

/*
 * ITFlow - Single-run guard for cron entry points
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
 * The handle is deliberately left open: the lock is held for the life of the process.
 */

$cron_lock_file = sys_get_temp_dir() . '/itflow_cron_' . md5($cron_lock_script) . '.lock';
$cron_lock_handle = fopen($cron_lock_file, 'c');
if ($cron_lock_handle === false) {
    die("Cannot open the cron lock file at $cron_lock_file - check permissions and open_basedir.\n");
}
if (!flock($cron_lock_handle, LOCK_EX | LOCK_NB)) {
    // Exit silently. On a per-minute schedule, finding a previous run still going is
    // normal operation rather than an error, and anything written to stdout here would
    // be mailed to the crontab owner every single minute for the length of that run.
    exit(0);
}
