<?php

/*
 * ITFlow - Database update to version 2.1.4 (from 2.1.3)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    mysqli_query($mysqli, "ALTER TABLE `client_stripe` ADD `stripe_pm_details` VARCHAR(200) DEFAULT NULL AFTER `stripe_pm`");
    mysqli_query($mysqli, "ALTER TABLE `client_stripe` ADD `stripe_pm_created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `stripe_pm_details`");
