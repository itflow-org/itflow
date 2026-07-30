<?php

/*
 * ITFlow - Database update to version 2.6.2 (from 2.6.1)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    // Two things, both about rows that already existed before 2.6.1 ran.
    //
    // 2.6.1 added the schedule columns but could not set them, so those rows took the column
    // defaults - every minute - which is wrong for three jobs and harmful for the nightly run,
    // whose overdue invoice reminders re-send on every pass. The registry in
    // includes/cron_jobs.php only ever seeds a row it is creating, so it cannot fix them.
    //
    // The domain refresher also moves onto the nightly schedule here, which is why it is
    // matched at either of the intervals it may be sitting on: 1 from the column default, or 5
    // if it was already put back by hand.
    //
    // Only rows still carrying one of those shipped values are touched, so a schedule someone
    // has deliberately changed is left alone. Deliberately hardcoded rather than read from the
    // registry: a migration has to keep meaning the same thing years from now, whatever that
    // file says by then.
    mysqli_query($mysqli, "UPDATE `cron_jobs`
        SET `cron_job_schedule` = 'Daily', `cron_job_daily_at` = '03:00:00'
        WHERE `cron_job_name` = 'nightly_tasks'
        AND `cron_job_schedule` = 'Interval' AND `cron_job_interval_minutes` = 1");

    mysqli_query($mysqli, "UPDATE `cron_jobs`
        SET `cron_job_schedule` = 'Daily', `cron_job_daily_at` = '03:30:00'
        WHERE `cron_job_name` = 'certificate_refresher'
        AND `cron_job_schedule` = 'Interval' AND `cron_job_interval_minutes` = 1");

    mysqli_query($mysqli, "UPDATE `cron_jobs`
        SET `cron_job_schedule` = 'Daily', `cron_job_daily_at` = '04:00:00'
        WHERE `cron_job_name` = 'domain_refresher'
        AND `cron_job_schedule` = 'Interval' AND `cron_job_interval_minutes` IN (1, 5)");
