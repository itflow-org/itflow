<?php

/*
 * ITFlow - Database update to version 4.0.7 (from 4.0.6)
 * Included by admin/database_updates.php - do not access directly
 *
 * The weekly summary email to the owners (functions/weekly_summary.php), set in
 * Admin > Settings > Notifications:
 *   settings.config_weekly_summary - on or off (off to start with)
 *   settings.config_weekly_summary_day - the day it goes, 1 Monday to 7 Sunday
 *   settings.config_weekly_summary_to - who it goes to, NULL for every admin
 *   settings.config_weekly_summary_sent_at - the day it last went, so it goes once
 *
 * Re-runnable: each column is only added where it isn't there yet.
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    $itflow_column = function ($table, $column) use ($mysqli) {
        $table = mysqli_real_escape_string($mysqli, $table);
        $column = mysqli_real_escape_string($mysqli, $column);
        return mysqli_num_rows(mysqli_query($mysqli, "SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$table' AND COLUMN_NAME = '$column'")) > 0;
    };

    if (!$itflow_column('settings', 'config_weekly_summary')) {
        mysqli_query($mysqli, "ALTER TABLE `settings` ADD `config_weekly_summary` tinyint(1) NOT NULL DEFAULT 0 AFTER `config_notify_helper_replies`");
    }
    if (!$itflow_column('settings', 'config_weekly_summary_day')) {
        mysqli_query($mysqli, "ALTER TABLE `settings` ADD `config_weekly_summary_day` tinyint(1) NOT NULL DEFAULT 1 AFTER `config_weekly_summary`");
    }
    if (!$itflow_column('settings', 'config_weekly_summary_to')) {
        mysqli_query($mysqli, "ALTER TABLE `settings` ADD `config_weekly_summary_to` varchar(500) DEFAULT NULL AFTER `config_weekly_summary_day`");
    }
    if (!$itflow_column('settings', 'config_weekly_summary_sent_at')) {
        mysqli_query($mysqli, "ALTER TABLE `settings` ADD `config_weekly_summary_sent_at` date DEFAULT NULL AFTER `config_weekly_summary_to`");
    }
