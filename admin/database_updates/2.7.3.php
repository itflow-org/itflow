<?php

/*
 * ITFlow - Database update to version 2.7.3 (from 2.7.2)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    // Maintenance > Update no longer runs git itself. cron/update_check.php does the fetch
    // on its own schedule and parks the answer here; the page and the nightly notification
    // both read these rather than shelling out in a web request.
    //
    // config_update_checked_at is written only after a check SUCCEEDS, so "last checked"
    // never claims freshness for a reading that a failed fetch left stale. The cron_jobs row
    // holds the attempt time and the error.

    mysqli_query($mysqli, "ALTER TABLE `settings`
        ADD COLUMN IF NOT EXISTS `config_update_latest_commit` varchar(40) DEFAULT NULL,
        ADD COLUMN IF NOT EXISTS `config_update_pending_commits` text DEFAULT NULL,
        ADD COLUMN IF NOT EXISTS `config_update_checked_at` datetime DEFAULT NULL");

    // Seeded here as well as by the dispatcher, which only creates rows on its next pass -
    // without this, a Check Now pressed in the minutes after an upgrade would have no row to
    // set cron_job_run_now on and would wait for that pass instead of starting.
    mysqli_query($mysqli, "INSERT IGNORE INTO cron_jobs SET cron_job_name = 'update_check', cron_job_enabled = 1, cron_job_schedule = 'Daily', cron_job_daily_at = '02:30'");
