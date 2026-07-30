<?php

/*
 * ITFlow - Database update to version 2.6.0 (from 2.5.9)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    // cron/cron.php is now a dispatcher: it runs every minute and decides which of the
    // scripts in cron/ are due, so the crontab only needs one line. That decision needs
    // somewhere durable to record when each job last ran - a job whose minute was missed
    // has to be picked up at the next opportunity rather than skipped, and one that is
    // half way through must not be started again.
    //
    // Rows are created by the dispatcher the first time it sees a job, so adding a job
    // later needs a line in cron.php and nothing here.
    mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `cron_jobs` (
        `cron_job_id` int(11) NOT NULL AUTO_INCREMENT,
        `cron_job_name` varchar(200) NOT NULL,
        `cron_job_last_run_at` datetime DEFAULT NULL,
        `cron_job_last_finished_at` datetime DEFAULT NULL,
        `cron_job_last_status` varchar(200) DEFAULT NULL,
        PRIMARY KEY (`cron_job_id`),
        UNIQUE KEY `cron_job_name` (`cron_job_name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
