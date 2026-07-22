<?php

/*
 * ITFlow - Database update to version 2.1.3 (from 2.1.2)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    // Update country_code to NULL for `contacts` table
    mysqli_query($mysqli, "ALTER TABLE `contacts` MODIFY `contact_phone_country_code` VARCHAR(10) DEFAULT NULL");
    mysqli_query($mysqli, "ALTER TABLE `contacts` MODIFY `contact_mobile_country_code` VARCHAR(10) DEFAULT NULL");

    // Update country_code to NULL for `locations` table
    mysqli_query($mysqli, "ALTER TABLE `locations` MODIFY `location_phone_country_code` VARCHAR(10) DEFAULT NULL");
    mysqli_query($mysqli, "ALTER TABLE `locations` MODIFY `location_fax_country_code` VARCHAR(10) DEFAULT NULL");

    // Update country_code to NULL for `vendors` table
    mysqli_query($mysqli, "ALTER TABLE `vendors` MODIFY `vendor_phone_country_code` VARCHAR(10) DEFAULT NULL");

    // Update country_code to NULL for `companies` table
    mysqli_query($mysqli, "ALTER TABLE `companies` MODIFY `company_phone_country_code` VARCHAR(10) DEFAULT NULL");

    // Set country_code to NULL for `contacts` table
    mysqli_query($mysqli, "UPDATE `contacts` SET `contact_phone_country_code` = NULL");
    mysqli_query($mysqli, "UPDATE `contacts` SET `contact_mobile_country_code` = NULL");

    // Set country_code to NULL for `locations` table
    mysqli_query($mysqli, "UPDATE `locations` SET `location_phone_country_code` = NULL");
    mysqli_query($mysqli, "UPDATE `locations` SET `location_fax_country_code` = NULL");

    // Set country_code to NULL for `vendors` table
    mysqli_query($mysqli, "UPDATE `vendors` SET `vendor_phone_country_code` = NULL");

    // Set country_code to NULL for `companies` table
    mysqli_query($mysqli, "UPDATE `companies` SET `company_phone_country_code` = NULL");
