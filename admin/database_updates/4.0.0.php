<?php

/*
 * ITFlow - Database update to version 4.0.0 (from 3.9.9)
 * Included by admin/database_updates.php - do not access directly
 *
 * user_settings.user_config_compact_rows - Your account > Preferences > Compact rows: lists drawn
 * with tighter rows, so more fit on screen (the itflow-compact class on <body>). Off to start.
 *
 * Re-runnable: the column is added only where it isn't there yet.
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    $itflow_compact_column = mysqli_query($mysqli, "SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_settings' AND COLUMN_NAME = 'user_config_compact_rows' LIMIT 1");

    if ($itflow_compact_column && mysqli_num_rows($itflow_compact_column) === 0) {
        mysqli_query($mysqli, "ALTER TABLE `user_settings` ADD `user_config_compact_rows` tinyint(1) NOT NULL DEFAULT 0 AFTER `user_config_theme_dark`");
    }
