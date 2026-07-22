<?php

/*
 * ITFlow - Database update to version 2.1.0 (from 2.0.9)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    mysqli_query($mysqli, "ALTER TABLE `contacts` ADD `contact_phone_country_code` VARCHAR(10) DEFAULT 1 AFTER `contact_email`");
    mysqli_query($mysqli, "ALTER TABLE `contacts` ADD `contact_mobile_country_code` VARCHAR(10) DEFAULT 1 AFTER `contact_extension`");

    mysqli_query($mysqli, "ALTER TABLE `locations` ADD `location_phone_country_code` VARCHAR(10) DEFAULT 1 AFTER `location_zip`");
    mysqli_query($mysqli, "ALTER TABLE `locations` ADD `location_phone_extension` VARCHAR(10) DEFAULT NULL AFTER `location_phone`");
    mysqli_query($mysqli, "ALTER TABLE `locations` ADD `location_fax_country_code` VARCHAR(10) DEFAULT 1 AFTER `location_phone_extension`");

    mysqli_query($mysqli, "ALTER TABLE `vendors` ADD `vendor_phone_country_code` VARCHAR(10) DEFAULT 1 AFTER `vendor_contact_name`");

    mysqli_query($mysqli, "ALTER TABLE `companies` ADD `company_phone_country_code` VARCHAR(10) DEFAULT 1 AFTER `company_country`");
