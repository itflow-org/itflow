<?php

/*
 * ITFlow - Database update to version 2.3.4 (from 2.3.3)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    mysqli_query($mysqli, "ALTER TABLE settings
        ADD `config_smtp_provider` ENUM('standard_smtp','google_oauth','microsoft_oauth') NULL DEFAULT NULL AFTER `config_start_page`
    ");
