<?php

/*
 * ITFlow - Database update to version 2.2.0 (from 2.1.9)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    mysqli_query($mysqli, "ALTER TABLE `companies` MODIFY `company_currency` VARCHAR(200) DEFAULT 'USD'");
