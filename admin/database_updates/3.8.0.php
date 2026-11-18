<?php

/*
 * ITFlow - Database update to version 3.8.0 (from 3.7.9)
 * Included by admin/database_updates.php - do not access directly
 *
 * Labor costs (functions/labor_cost.php): what an hour of each agent costs the
 * company - wage, payroll taxes and benefits - from a date on. A new rate is a
 * new row, so a past period keeps the rate it had. Client Profitability costs
 * the hours on each client's tickets at them.
 *   user_cost_rates
 *   settings.config_labor_cost_default  for agents with no rate - NULL leaves their time uncosted
 *
 * Re-runnable: the table and the column are added only where they aren't there yet.
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    $itflow_column = function ($table, $column) use ($mysqli) {
        $result = mysqli_query($mysqli, "SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$table' AND COLUMN_NAME = '$column' LIMIT 1");
        return $result && mysqli_num_rows($result) > 0;
    };

    mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `user_cost_rates` (
        `user_cost_rate_id` int(11) NOT NULL AUTO_INCREMENT,
        `user_cost_rate_user_id` int(11) NOT NULL,
        `user_cost_rate_from` date NOT NULL,
        `user_cost_rate_hourly` decimal(15,2) NOT NULL DEFAULT 0.00,
        `user_cost_rate_created_by` int(11) NOT NULL DEFAULT 0,
        `user_cost_rate_created_at` datetime NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`user_cost_rate_id`),
        UNIQUE KEY `user_cost_rate_user_from` (`user_cost_rate_user_id`, `user_cost_rate_from`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    if (!$itflow_column('settings', 'config_labor_cost_default')) {
        mysqli_query($mysqli, "ALTER TABLE `settings` ADD `config_labor_cost_default` decimal(15,2) DEFAULT NULL AFTER `config_default_hourly_rate`");
    }
