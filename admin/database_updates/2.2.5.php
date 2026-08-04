<?php

/*
 * ITFlow - Database update to version 2.2.5 (from 2.2.4)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    mysqli_query($mysqli, "ALTER TABLE `settings` ADD `config_theme_dark` TINYINT(1) NOT NULL DEFAULT 0 AFTER `config_theme`");
