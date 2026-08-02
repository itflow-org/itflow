<?php

/*
 * ITFlow - Database update to version 2.6.1 (from 2.6.0)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    // The cron dispatcher's schedule moves out of code and into the database so it can be
    // managed from Maintenance > Cron. The registry in includes/cron_jobs.php still decides
    // which scripts exist and seeds these columns the first time it meets a job; from then
    // on the row is what runs. Nothing here can name a script - a row whose job is not in
    // the registry is ignored.
    mysqli_query($mysqli, "ALTER TABLE `cron_jobs`
        ADD COLUMN `cron_job_enabled` tinyint(1) NOT NULL DEFAULT 1 AFTER `cron_job_name`,
        ADD COLUMN `cron_job_schedule` varchar(200) NOT NULL DEFAULT 'Interval' AFTER `cron_job_enabled`,
        ADD COLUMN `cron_job_interval_minutes` int(11) NOT NULL DEFAULT 1 AFTER `cron_job_schedule`,
        ADD COLUMN `cron_job_daily_at` time DEFAULT NULL AFTER `cron_job_interval_minutes`,
        ADD COLUMN `cron_job_run_now` tinyint(1) NOT NULL DEFAULT 0 AFTER `cron_job_daily_at`");

    // Duration is here to make a job that is quietly getting slower visible before it starts
    // overrunning its own interval.
    mysqli_query($mysqli, "ALTER TABLE `cron_jobs`
        ADD COLUMN `cron_job_last_duration` decimal(10,2) DEFAULT NULL AFTER `cron_job_last_finished_at`,
        ADD COLUMN `cron_job_last_error` text DEFAULT NULL AFTER `cron_job_last_status`,
        ADD COLUMN `cron_job_last_error_at` datetime DEFAULT NULL AFTER `cron_job_last_error`");

    // Written by the dispatcher every minute before it runs anything, so the admin page can
    // tell "no job happened to be due" apart from "the crontab entry is missing".
    mysqli_query($mysqli, "ALTER TABLE `settings`
        ADD COLUMN `config_cron_last_dispatch_at` datetime DEFAULT NULL");
