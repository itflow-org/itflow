<?php

/*
 * ITFlow - Database update to version 2.6.8 (from 2.6.7)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    // Maintenance > Update can now hand an update to cron rather than running it inside the
    // request: the page stamps this column and cron/app_update.php takes it and runs
    // scripts/update_cli.php in its own process. NULL means nothing is queued, which is why
    // the job cannot update an install that did not ask for one.

    mysqli_query($mysqli, "ALTER TABLE `settings` ADD COLUMN IF NOT EXISTS `config_update_queued_at` datetime DEFAULT NULL");

    // Seeded here as well as by the dispatcher, which only creates rows on its next pass -
    // without this, an update queued in the minutes after an upgrade would have no row to
    // set cron_job_run_now on and would wait for that pass instead of starting.
    mysqli_query($mysqli, "INSERT IGNORE INTO cron_jobs SET cron_job_name = 'app_update', cron_job_enabled = 0, cron_job_schedule = 'Daily', cron_job_daily_at = '05:00'");
