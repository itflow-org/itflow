<?php

/*
 * ITFlow - Database update to version 2.7.4 (from 2.7.3)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    // Closure days for the SLA business calendar. A date listed here yields no
    // business minutes at all, exactly like a weekday that is not a business
    // day - holidays are a calendar concept, not a per-ticket pause, so they
    // never touch sla_history.
    //
    // holiday_date is UNIQUE so the US federal holiday generator can be run
    // repeatedly (and over a year that was partly entered by hand) without
    // creating duplicates.

    mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `business_holidays` (
        `holiday_id` int(11) NOT NULL AUTO_INCREMENT,
        `holiday_date` date NOT NULL,
        `holiday_name` varchar(200) NOT NULL,
        `holiday_created_at` datetime NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`holiday_id`),
        UNIQUE KEY `holiday_date` (`holiday_date`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
