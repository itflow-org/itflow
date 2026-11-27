<?php

/*
 * ITFlow - Database update to version 4.1.9 (from 4.1.8)
 * Included by admin/database_updates.php - do not access directly
 *
 * A managed instance's history (functions/managed.php):
 *   managed_instance_stats - one row per instance per day, by the manager's own date, kept up
 *     at each check-in: its agents, invites waiting and seat limit, clients, uploads and
 *     database size, the errors of the last 24 hours and their categories (JSON), whether a
 *     backup was made ('ok', 'failed' or ''), its version, how many times it checked in, and
 *     the gaps between check-ins that ended that day (JSON, each from and to)
 *
 * Re-runnable: the table is made only where it isn't there yet.
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `managed_instance_stats` (
        `managed_stat_id` int(11) NOT NULL AUTO_INCREMENT,
        `managed_stat_instance_id` int(11) NOT NULL,
        `managed_stat_date` date NOT NULL,
        `managed_stat_agents` int(11) NOT NULL DEFAULT 0,
        `managed_stat_invites` int(11) NOT NULL DEFAULT 0,
        `managed_stat_seat_limit` int(11) NOT NULL DEFAULT 0,
        `managed_stat_clients` int(11) NOT NULL DEFAULT 0,
        `managed_stat_uploads_mb` decimal(12,1) NOT NULL DEFAULT 0.0,
        `managed_stat_db_mb` decimal(12,1) NOT NULL DEFAULT 0.0,
        `managed_stat_errors` int(11) NOT NULL DEFAULT 0,
        `managed_stat_error_categories` text DEFAULT NULL,
        `managed_stat_backup` varchar(10) NOT NULL DEFAULT '',
        `managed_stat_version` varchar(20) NOT NULL DEFAULT '',
        `managed_stat_checkins` int(11) NOT NULL DEFAULT 0,
        `managed_stat_outages` text DEFAULT NULL,
        PRIMARY KEY (`managed_stat_id`),
        UNIQUE KEY `managed_stat_day` (`managed_stat_instance_id`, `managed_stat_date`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
