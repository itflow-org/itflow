<?php

/*
 * ITFlow - The cron job registry
 *
 * The list of jobs cron/cron.php knows how to run, and the schedule each one ships with.
 * Adding a job is a script in cron/ and an entry here - the crontab never changes.
 *
 * Schedules here are DEFAULTS. They seed the job's row in the cron_jobs table the first
 * time the dispatcher sees it, and from then on the row is what runs: Settings > Cron
 * writes to it. Changing a default in this file therefore only affects installs that have
 * not met the job yet.
 *
 * This file is the only thing that decides which scripts can be run. The database holds
 * when and whether, never what - a row naming a script that is not listed here is ignored,
 * so nothing that reaches the database can point the dispatcher at an arbitrary file.
 *
 * Loaded from both sides: cron/cron.php requires it on the command line under system cron,
 * and Settings > Cron requires it in a web request. Nothing in here may touch $_SERVER,
 * $_SESSION or any other superglobal - there is no DOCUMENT_ROOT, no session and no request
 * when cron runs it.
 *
 * That shared use is why this sits here rather than in cron/includes/ with the lock, which
 * only cron loads: the admin pages would otherwise be reaching into the cron directory.
 *
 * 'interval_safe' => false marks a job whose work repeats if the day repeats - nightly's
 * late fees and overdue reminders fire again on a second run of the same day. Settings >
 * Cron only offers the daily schedule for such a job, and the dispatcher refuses to run
 * one on an interval whatever its row says.
 */

function cronJobRegistry(): array
{
    return [
        [
            'name' => 'mail_queue',
            'label' => 'Mail Queue',
            'script' => 'mail_queue.php',
            'description' => 'Sends everything ITFlow has queued - invoices, quotes, ticket replies, notifications.',
            'schedule' => 'Interval',
            'interval_minutes' => 1,
        ],
        [
            'name' => 'ticket_email_parser',
            'label' => 'Ticket Email Parser',
            'script' => 'ticket_email_parser.php',
            'description' => 'Reads the support mailbox and turns incoming mail into tickets and replies.',
            'schedule' => 'Interval',
            'interval_minutes' => 1,
        ],
        [
            'name' => 'ticket_sla',
            'label' => 'Ticket SLA Monitor',
            'script' => 'ticket_sla.php',
            'description' => 'Moves tickets through their SLA warning and breach stages and sends the alerts.',
            'schedule' => 'Interval',
            'interval_minutes' => 1,
        ],
        [
            'name' => 'domain_refresher',
            'label' => 'Domain Refresher',
            'script' => 'domain_refresher.php',
            'description' => 'Refreshes WHOIS and DNS for the domain that was checked longest ago. One domain per run.',
            'schedule' => 'Daily',
            'daily_at' => '04:00',
        ],
        [
            'name' => 'nightly_tasks',
            'label' => 'Nightly Tasks',
            'script' => 'nightly_tasks.php',
            'description' => 'The daily run: recurring invoices and tickets, overdue reminders, autopay, late fees, clean-up, update check.',
            'schedule' => 'Daily',
            'daily_at' => '03:00',
            'interval_safe' => false,
        ],
        [
            'name' => 'certificate_refresher',
            'label' => 'Certificate Refresher',
            'script' => 'certificate_refresher.php',
            'description' => 'Re-reads the expiry date and issuer of every SSL certificate on file.',
            'schedule' => 'Daily',
            'daily_at' => '03:30',
        ],
    ];
}

/*
 * The registry keyed by job name, for looking up what a cron_jobs row belongs to.
 */
function cronJobRegistryByName(): array
{
    $jobs = [];

    foreach (cronJobRegistry() as $job) {
        $jobs[$job['name']] = $job;
    }

    return $jobs;
}

/*
 * How a schedule reads in the admin UI: "Every minute", "Every 5 minutes", "Daily at 03:00".
 */
function cronJobScheduleDescription(string $schedule, int $interval_minutes, ?string $daily_at): string
{
    if ($schedule === 'Daily') {
        return 'Daily at ' . substr((string)$daily_at, 0, 5);
    }

    if ($interval_minutes === 1) {
        return 'Every minute';
    }

    if ($interval_minutes === 60) {
        return 'Hourly';
    }

    return "Every $interval_minutes minutes";
}

/*
 * When a job is next expected to run, or null when it is disabled or nothing is scheduled.
 * Interval jobs that are already overdue read as due now rather than as a time in the past.
 */
function cronJobNextRun(array $row): ?string
{
    if (empty($row['cron_job_enabled'])) {
        return null;
    }

    $last_run = $row['cron_job_last_run_at'];

    if ($row['cron_job_schedule'] === 'Daily') {
        // The most recent scheduled occurrence: today's, or yesterday's if today's has not
        // arrived yet - matching how the dispatcher decides due-ness
        $occurrence = date('Y-m-d') . ' ' . substr((string)$row['cron_job_daily_at'], 0, 5) . ':00';
        if ($occurrence > date('Y-m-d H:i:s')) {
            $occurrence = date('Y-m-d', strtotime('-1 day')) . ' ' . substr((string)$row['cron_job_daily_at'], 0, 5) . ':00';
        }

        if ($last_run === null || $last_run < $occurrence) {
            return date('Y-m-d H:i:s');
        }

        return date('Y-m-d H:i:s', strtotime($occurrence) + 86400);
    }

    if ($last_run === null) {
        return date('Y-m-d H:i:s');
    }

    $next = strtotime($last_run) + (max(1, intval($row['cron_job_interval_minutes'])) * 60);

    return date('Y-m-d H:i:s', max($next, time()));
}

/*
 * "4 minutes ago" / "in 2 minutes" for the admin page. Anything older than a day reads as a
 * date, because "27,000 minutes ago" tells nobody anything.
 */
function cronJobTimeAgo(?string $datetime): string
{
    if (empty($datetime)) {
        return 'Never';
    }

    $seconds = time() - strtotime($datetime);
    $ahead = $seconds < 0;
    $seconds = abs($seconds);

    if ($seconds < 60) {
        $text = 'less than a minute';
    } elseif ($seconds < 3600) {
        $minutes = floor($seconds / 60);
        $text = $minutes . ' minute' . ($minutes == 1 ? '' : 's');
    } elseif ($seconds < 86400) {
        $hours = floor($seconds / 3600);
        $text = $hours . ' hour' . ($hours == 1 ? '' : 's');
    } else {
        return date('M j, g:i A', strtotime($datetime));
    }

    return $ahead ? "in $text" : "$text ago";
}
