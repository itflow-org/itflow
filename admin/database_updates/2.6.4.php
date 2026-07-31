<?php

/*
 * ITFlow - Database update to version 2.6.4 (from 2.6.3)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    // Backup catalogue - one row per archive produced, so the app knows what exists
    // without trusting a directory listing

    mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `backups` (
        `backup_id` int(11) NOT NULL AUTO_INCREMENT,
        `backup_type` varchar(20) NOT NULL DEFAULT 'full',
        `backup_file_name` varchar(255) NOT NULL,
        `backup_size` bigint(20) NOT NULL DEFAULT 0,
        `backup_sha256` varchar(64) DEFAULT NULL,
        `backup_status` varchar(20) NOT NULL DEFAULT 'Pending',
        `backup_error` text DEFAULT NULL,
        `backup_source` varchar(20) NOT NULL DEFAULT 'Manual',
        `backup_created_by` varchar(200) DEFAULT NULL,
        `backup_created_at` datetime NOT NULL DEFAULT current_timestamp(),
        `backup_completed_at` datetime DEFAULT NULL,
        `backup_downloaded_at` datetime DEFAULT NULL,
        PRIMARY KEY (`backup_id`),
        KEY `backup_status_created` (`backup_status`, `backup_created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    // Retention and what the scheduled backup produces
    mysqli_query($mysqli, "ALTER TABLE settings ADD COLUMN IF NOT EXISTS `config_backup_retention_days` int(11) NOT NULL DEFAULT 30");
    mysqli_query($mysqli, "ALTER TABLE settings ADD COLUMN IF NOT EXISTS `config_backup_retention_count` int(11) NOT NULL DEFAULT 5");
    mysqli_query($mysqli, "ALTER TABLE settings ADD COLUMN IF NOT EXISTS `config_backup_cron_type` varchar(20) NOT NULL DEFAULT 'full'");

    // Seed the scheduled backup job. The dispatcher would create this row itself the first
    // time it sees the job, but seeding it here means the schedule is right on an install
    // that already has cron_jobs rows - the every-minute default bit us once already.
    mysqli_query($mysqli, "INSERT IGNORE INTO cron_jobs SET cron_job_name = 'backup', cron_job_enabled = 0, cron_job_schedule = 'Daily', cron_job_daily_at = '02:00'");
