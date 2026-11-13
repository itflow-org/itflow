<?php

/*
 * ITFlow - The cron job registry
 *
 * The list of jobs cron/cron.php knows how to run, and the schedule each one ships with.
 * Adding a job is a script in cron/ and an entry here - the crontab never changes.
 *
 * Schedules here are DEFAULTS. They seed the job's row in the cron_jobs table the first
 * time the dispatcher sees it, and from then on the row is what runs: Maintenance > Cron
 * writes to it. Changing a default in this file therefore only affects installs that have
 * not met the job yet.
 *
 * This file is the only thing that decides which scripts can be run. The database holds
 * when and whether, never what - a row naming a script that is not listed here is ignored,
 * so nothing that reaches the database can point the dispatcher at an arbitrary file.
 *
 * Loaded from both sides: cron/cron.php requires it on the command line under system cron,
 * and Maintenance > Cron requires it in a web request. Nothing in here may touch $_SERVER,
 * $_SESSION or any other superglobal - there is no DOCUMENT_ROOT, no session and no request
 * when cron runs it.
 *
 * That shared use is why this sits here rather than in cron/includes/ with the lock, which
 * only cron loads: the admin pages would otherwise be reaching into the cron directory.
 *
 * 'icon' is the job's Font Awesome icon on Maintenance > Cron, and nothing to the dispatcher.
 *
 * 'enabled' => 0 ships a job switched off - the row is seeded disabled and stays that way
 * until somebody turns it on in Maintenance > Cron. Used for work an install should opt into
 * rather than inherit from an upgrade, like the backup job filling a disk overnight.
 *
 * 'interval_safe' => false marks a job whose work repeats if the day repeats - nightly's
 * late fees and overdue reminders fire again on a second run of the same day. Maintenance >
 * Cron only offers the daily schedule for such a job, and the dispatcher refuses to run
 * one on an interval whatever its row says.
 *
 * Every job here checks config_enable_cron in its own header and stops itself when that
 * switch is off. It is not a dispatcher-level gate - a new job has to make the check
 * itself, and a job that skips it will keep running on an install that thinks cron is off.
 *
 * Managed mode's two jobs - manager_checkin and managed_instances - are only listed on an
 * install config.php turns that side on for (managedEnabled(), managerEnabled() in
 * functions/managed.php). Off, they aren't run or shown, and their rows wait untouched.
 *
 * Demo mode (functions/demo.php) adds demo_reset at the top and leaves out the domain and
 * certificate refreshers - the demo's are made up, and the refreshers would mark them all
 * unreachable a night after the reset made them.
 *
 * Order matters: the dispatcher works down this list. manager_checkin goes first, so what a
 * manager sets - read-only or suspended pause most jobs (MANAGED_KEEP_JOBS) - applies to the
 * rest of the same cycle. app_update replaces the files every job is loaded from, so it stays
 * at the end and ends the cycle behind itself.
 */

function cronJobRegistry(): array
{
    $jobs = [
        [
            'name' => 'demo_reset',
            'label' => 'Demo Reset',
            'icon' => 'fa-undo',
            'script' => 'demo_reset.php',
            'description' => 'For the demo (config.php\'s $config_demo_mode): puts it back to fresh demo data - the demo accounts, Starter Content and the demo clients - and signs everyone out. Locked in the demo; php cron/demo_reset.php runs it now.',
            'schedule' => 'Daily',
            'daily_at' => '02:00',
            'interval_safe' => false,
        ],
        [
            'name' => 'manager_checkin',
            'label' => 'Manager Check-in',
            'icon' => 'fa-network-wired',
            'script' => 'manager_checkin.php',
            'description' => 'For an ITFlow managed by another (Admin > Managed By): reports how it is doing and takes what its manager sets. Stops straight away when it isn\'t managed.',
            'schedule' => 'Interval',
            'interval_minutes' => 1,
        ],
        [
            'name' => 'mail_queue',
            'label' => 'Mail Queue',
            'icon' => 'fa-envelope',
            'script' => 'mail_queue.php',
            'description' => 'Sends everything ITFlow has queued - invoices, quotes, ticket replies, notifications.',
            'schedule' => 'Interval',
            'interval_minutes' => 1,
        ],
        [
            'name' => 'sms_queue',
            'label' => 'Text Messages',
            'icon' => 'fa-comment-dots',
            'script' => 'sms_queue.php',
            'description' => 'Sends the texts waiting to go, a second apart, texts reminders of visits and calls coming up, and tidies the text log - once texting is set up in Admin > Text Messaging.',
            'schedule' => 'Interval',
            'interval_minutes' => 1,
        ],
        [
            'name' => 'rmm_sync',
            'label' => 'RMM Sync',
            'icon' => 'fa-laptop-medical',
            'script' => 'rmm_sync.php',
            'description' => 'Brings in the devices of the RMM clients linked in Admin > RMM Providers as assets, and keeps up whether each is online - once an RMM is set up there.',
            'schedule' => 'Interval',
            'interval_minutes' => 15,
        ],
        [
            'name' => 'ticket_email_parser',
            'label' => 'Ticket Email Parser',
            'icon' => 'fa-inbox',
            'script' => 'ticket_email_parser.php',
            'description' => 'Reads the support mailbox and turns incoming mail into tickets and replies.',
            'schedule' => 'Interval',
            'interval_minutes' => 1,
        ],
        [
            'name' => 'ticket_sla',
            'label' => 'Ticket SLA Monitor',
            'icon' => 'fa-stopwatch',
            'script' => 'ticket_sla.php',
            'description' => 'Moves tickets through their SLA warning and breach stages and sends the alerts.',
            'schedule' => 'Interval',
            'interval_minutes' => 1,
        ],
        [
            'name' => 'ai_triage',
            'label' => 'AI Triage',
            'icon' => 'fa-robot',
            'script' => 'ai_triage.php',
            'description' => 'Gives new tickets from email, the portal and the API a category and priority by AI, when Settings > Tickets says to. Up to 5 a run.',
            'schedule' => 'Interval',
            'interval_minutes' => 1,
        ],
        [
            'name' => 'managed_instances',
            'label' => 'Managed Instances',
            'icon' => 'fa-server',
            'script' => 'managed_instances.php',
            'description' => 'For an ITFlow that manages others (Admin > Managed Instances): notifies when one stops checking in, and closes requests that were never answered.',
            'schedule' => 'Interval',
            'interval_minutes' => 5,
        ],
        [
            'name' => 'payment_provider_fees',
            'label' => 'Payment Provider Fees',
            'icon' => 'fa-credit-card',
            'script' => 'payment_provider_fees.php',
            'description' => 'Records what Stripe charged on each card payment as an expense, once Stripe has settled it - while Admin > Payment Providers records its fees.',
            'schedule' => 'Interval',
            'interval_minutes' => 15,
        ],
        [
            'name' => 'domain_refresher',
            'label' => 'Domain Refresher',
            'icon' => 'fa-globe',
            'script' => 'domain_refresher.php',
            'description' => 'Looks up DNS, registration and expiry for domains that are due - never looked up, not in a week, or expiring within 60 days. Up to 50 a run.',
            'schedule' => 'Daily',
            'daily_at' => '04:00',
        ],
        [
            'name' => 'nightly_tasks',
            'label' => 'Nightly Tasks',
            'icon' => 'fa-moon',
            'script' => 'nightly_tasks.php',
            'description' => 'The daily run: recurring invoices and tickets, overdue reminders, autopay, late fees, clean-up, update check.',
            'schedule' => 'Daily',
            'daily_at' => '03:00',
            'interval_safe' => false,
        ],
        [
            'name' => 'backup',
            'label' => 'Backup',
            'icon' => 'fa-cloud-upload-alt',
            'script' => 'backup.php',
            'description' => 'Builds the scheduled backup and anything queued from Maintenance > Backup. Off by default.',
            'schedule' => 'Daily',
            'daily_at' => '02:00',
            'enabled' => 0,
            'interval_safe' => false,
        ],
        [
            'name' => 'certificate_refresher',
            'label' => 'Certificate Refresher',
            'icon' => 'fa-lock',
            'script' => 'certificate_refresher.php',
            'description' => 'Checks the host of every certificate once a day - what it serves now, its expiry and issuer, and whether it is OK, for another name, untrusted, expired or unreachable.',
            'schedule' => 'Daily',
            'daily_at' => '03:30',
        ],
        [
            'name' => 'update_check',
            'label' => 'Update Check',
            'icon' => 'fa-sync-alt',
            'script' => 'update_check.php',
            'description' => 'Asks the git remote whether a newer release exists and stores the answer for Maintenance > Update.',
            'schedule' => 'Daily',
            'daily_at' => '02:30',
        ],
        [
            'name' => 'app_update',
            'label' => 'Application Update',
            'icon' => 'fa-download',
            'script' => 'app_update.php',
            'description' => 'Runs an update queued from Maintenance > Update. Does nothing unless one is queued.',
            'schedule' => 'Daily',
            'daily_at' => '05:00',
            'enabled' => 0,
            'interval_safe' => false,
        ],
    ];

    // Managed mode's jobs, only where config.php turns their side on; the demo's job only in the
    // demo, and its made-up domains and certificates left alone there
    $demo = function_exists('demoModeOn') && demoModeOn();
    $listed = [
        'manager_checkin' => function_exists('managedEnabled') && managedEnabled(),
        'managed_instances' => function_exists('managerEnabled') && managerEnabled(),
        'demo_reset' => $demo,
        'domain_refresher' => !$demo,
        'certificate_refresher' => !$demo,
    ];

    return array_values(array_filter($jobs, function ($job) use ($listed) {
        return $listed[$job['name']] ?? true;
    }));
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
