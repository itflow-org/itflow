<?php

/*
 * ITFlow - Database update to version 2.1.1 (from 2.1.0)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    mysqli_query($mysqli, "ALTER TABLE `user_settings` ADD `user_config_signature` TEXT DEFAULT NULL AFTER `user_config_calendar_first_day`");
