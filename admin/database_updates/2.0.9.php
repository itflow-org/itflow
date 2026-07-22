<?php

/*
 * ITFlow - Database update to version 2.0.9 (from 2.0.8)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    mysqli_query($mysqli, "ALTER TABLE `files` DROP `file_has_thumbnail`");
    mysqli_query($mysqli, "ALTER TABLE `files` DROP `file_has_preview`");
    mysqli_query($mysqli, "ALTER TABLE `files` DROP `file_asset_id`");
