<?php

/*
 * ITFlow - Database update to version 2.3.6 (from 2.3.5)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    mysqli_query($mysqli, "ALTER TABLE `settings` CHANGE `config_smtp_provider` `config_smtp_provider` VARCHAR(200) DEFAULT NULL");
    mysqli_query($mysqli, "ALTER TABLE `settings` CHANGE `config_imap_provider` `config_imap_provider` VARCHAR(200) DEFAULT NULL");
