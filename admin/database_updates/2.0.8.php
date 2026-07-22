<?php

/*
 * ITFlow - Database update to version 2.0.8 (from 2.0.7)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    mysqli_query($mysqli, "ALTER TABLE `files` DROP `file_hash`");
