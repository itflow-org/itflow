<?php

/*
 * ITFlow - Database update to version 3.8.1 (from 3.8.0)
 * Included by admin/database_updates.php - do not access directly
 *
 * API / service accounts (functions/service_account.php): an agent login that
 * exists only for API keys to act as. It keeps its role, so its keys work, but
 * it's left out of every agent list and can't sign in to ITFlow.
 *   users.user_service_account  1 = an API / service account
 *
 * Re-runnable: the column is added only where it isn't there yet.
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    $itflow_column = function ($table, $column) use ($mysqli) {
        $result = mysqli_query($mysqli, "SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$table' AND COLUMN_NAME = '$column' LIMIT 1");
        return $result && mysqli_num_rows($result) > 0;
    };

    if (!$itflow_column('users', 'user_service_account')) {
        mysqli_query($mysqli, "ALTER TABLE `users` ADD `user_service_account` tinyint(1) NOT NULL DEFAULT 0 AFTER `user_type`");
    }
