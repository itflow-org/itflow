<?php

/*
 * ITFlow - Database update to version 2.1.2 (from 2.1.1)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    mysqli_query($mysqli, "ALTER TABLE `settings` DROP `config_phone_mask`");
