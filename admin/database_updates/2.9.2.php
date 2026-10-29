<?php

/*
 * ITFlow - Database update to version 2.9.2 (from 2.9.1)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    // How ticket time is billed (Admin > Settings > Invoice, billingTimeRules()):
    // the step it rounds to in minutes, to the nearest step or always up, the
    // least a ticket with time on it bills, and the invoice line's name. The
    // defaults are how it has always been - the nearest 15 minutes, no minimum.
    mysqli_query($mysqli, "ALTER TABLE `settings`
        ADD COLUMN `config_time_increment` int(11) NOT NULL DEFAULT 15 AFTER `config_default_hourly_rate`,
        ADD COLUMN `config_time_rounding` varchar(10) NOT NULL DEFAULT 'nearest' AFTER `config_time_increment`,
        ADD COLUMN `config_time_minimum` int(11) NOT NULL DEFAULT 0 AFTER `config_time_rounding`,
        ADD COLUMN `config_time_item_name` varchar(200) NOT NULL DEFAULT 'Support [Hourly]' AFTER `config_time_minimum`");
