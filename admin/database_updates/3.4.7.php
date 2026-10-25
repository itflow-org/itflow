<?php

/*
 * ITFlow - Database update to version 3.4.7 (from 3.4.6)
 * Included by admin/database_updates.php - do not access directly
 *
 * Get started, the walk through what to set up after the install
 * (functions/getting_started.php). settings.config_getting_started puts it in
 * front of the first admin to sign in, and on the dashboard until it is done
 * or dismissed - on for a new install (db.sql), off for one already running,
 * as this update leaves it. config_getting_started_skipped is the steps put
 * off for now, and config_getting_started_opened_at when it was first opened,
 * so it opens by itself once.
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    $itflow_column = function ($column) use ($mysqli) {
        $result = mysqli_query($mysqli, "SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'settings' AND COLUMN_NAME = '$column' LIMIT 1");
        return $result && mysqli_num_rows($result) > 0;
    };

    if (!$itflow_column('config_getting_started')) {
        mysqli_query($mysqli, "ALTER TABLE `settings` ADD `config_getting_started` tinyint(1) NOT NULL DEFAULT 1 AFTER `config_client_portal_signup`");
        // An install already running has been set up - it isn't walked through it again
        mysqli_query($mysqli, "UPDATE `settings` SET `config_getting_started` = 0");
    }
    if (!$itflow_column('config_getting_started_skipped')) {
        mysqli_query($mysqli, "ALTER TABLE `settings` ADD `config_getting_started_skipped` varchar(255) NOT NULL DEFAULT '' AFTER `config_getting_started`");
    }
    if (!$itflow_column('config_getting_started_opened_at')) {
        mysqli_query($mysqli, "ALTER TABLE `settings` ADD `config_getting_started_opened_at` datetime DEFAULT NULL AFTER `config_getting_started_skipped`");
    }

    unset($itflow_column);
