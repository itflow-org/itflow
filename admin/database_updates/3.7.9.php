<?php

/*
 * ITFlow - Database update to version 3.7.9 (from 3.7.8)
 * Included by admin/database_updates.php - do not access directly
 *
 * Cron health (functions/cron_health.php): the alerts set on Maintenance >
 * Cron, the key in the health address an uptime monitor checks, and what the
 * Cron Watch job last emailed about.
 *   settings.config_cron_alert_email  1 = email the alerts (the default)
 *   settings.config_cron_alert_to     addresses; empty = every active admin
 *   settings.config_cron_health_key   the health address's key, made when first needed
 *   settings.config_cron_alert_state  JSON - problems emailed, jobs waiting for a first run
 *
 * Re-runnable: each column is added only where it isn't there yet.
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    $itflow_column = function ($table, $column) use ($mysqli) {
        $result = mysqli_query($mysqli, "SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$table' AND COLUMN_NAME = '$column' LIMIT 1");
        return $result && mysqli_num_rows($result) > 0;
    };

    if (!$itflow_column('settings', 'config_cron_alert_email')) {
        mysqli_query($mysqli, "ALTER TABLE `settings` ADD `config_cron_alert_email` tinyint(1) NOT NULL DEFAULT 1 AFTER `config_cron_last_dispatch_at`");
    }

    if (!$itflow_column('settings', 'config_cron_alert_to')) {
        mysqli_query($mysqli, "ALTER TABLE `settings` ADD `config_cron_alert_to` varchar(500) DEFAULT NULL AFTER `config_cron_alert_email`");
    }

    if (!$itflow_column('settings', 'config_cron_health_key')) {
        mysqli_query($mysqli, "ALTER TABLE `settings` ADD `config_cron_health_key` varchar(200) DEFAULT NULL AFTER `config_cron_alert_to`");
    }

    if (!$itflow_column('settings', 'config_cron_alert_state')) {
        mysqli_query($mysqli, "ALTER TABLE `settings` ADD `config_cron_alert_state` text DEFAULT NULL AFTER `config_cron_health_key`");
    }
