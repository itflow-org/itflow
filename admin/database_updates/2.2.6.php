<?php

/*
 * ITFlow - Database update to version 2.2.6 (from 2.2.5)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    mysqli_query($mysqli, "ALTER TABLE `assets` ADD `asset_uri_client` VARCHAR(500) NULL DEFAULT NULL AFTER `asset_uri_2`");
