<?php

/*
 * ITFlow - Database update to version 3.3.2 (from 3.3.1)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    // Signing in, watched (functions/sign_in.php).
    //
    // 1. auth_logs never had anything written to it - sign ins are in logs -
    //    and nightly only ever emptied it.
    mysqli_query($mysqli, "DROP TABLE IF EXISTS `auth_logs`");

    // 2. When the admins are told about a run of failed sign-ins: after this
    //    many in 10 minutes from one IP address or against one account, 0 for
    //    never. Checked first, so the step can run again.
    $itflow_column = mysqli_query($mysqli, "SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'settings' AND COLUMN_NAME = 'config_login_alert_failures' LIMIT 1");
    if (!$itflow_column || mysqli_num_rows($itflow_column) === 0) {
        mysqli_query($mysqli, "ALTER TABLE `settings` ADD COLUMN `config_login_alert_failures` int(11) NOT NULL DEFAULT 10");
    }

    // 3. What each API key is used for - its calls a day to each endpoint,
    //    and when and from where the last one came. One row a key, day and
    //    endpoint, counted up as calls come in; nightly drops the days older
    //    than the log retention.
    mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `api_key_usage` (
        `api_key_usage_key_id` int(11) NOT NULL,
        `api_key_usage_date` date NOT NULL,
        `api_key_usage_endpoint` varchar(100) NOT NULL DEFAULT '',
        `api_key_usage_calls` int(11) NOT NULL DEFAULT 0,
        `api_key_usage_last_at` datetime NOT NULL DEFAULT current_timestamp(),
        `api_key_usage_last_ip` varchar(200) DEFAULT NULL,
        PRIMARY KEY (`api_key_usage_key_id`, `api_key_usage_date`, `api_key_usage_endpoint`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    unset($itflow_column);
