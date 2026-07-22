<?php

/*
 * ITFlow - Database update to version 2.1.9 (from 2.1.8)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    mysqli_query($mysqli, "CREATE TABLE `vendor_templates` (
        `vendor_template_id` INT(11) NOT NULL AUTO_INCREMENT,
        `vendor_template_name` VARCHAR(200) NOT NULL,
        `vendor_template_description` VARCHAR(200) DEFAULT NULL,
        `vendor_template_contact_name` VARCHAR(200) DEFAULT NULL,
        `vendor_template_phone_country_code` VARCHAR(10) DEFAULT NULL,
        `vendor_template_phone` VARCHAR(200) DEFAULT NULL,
        `vendor_template_extension` VARCHAR(200) DEFAULT NULL,
        `vendor_template_email` VARCHAR(200) DEFAULT NULL,
        `vendor_template_website` VARCHAR(200) DEFAULT NULL,
        `vendor_template_hours` VARCHAR(200) DEFAULT NULL,
        `vendor_template_sla` VARCHAR(200) DEFAULT NULL,
        `vendor_template_code` VARCHAR(200) DEFAULT NULL,
        `vendor_template_account_number` VARCHAR(200) DEFAULT NULL,
        `vendor_template_notes` TEXT DEFAULT NULL,
        `vendor_template_created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `vendor_template_updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
        `vendor_template_archived_at` DATETIME NULL DEFAULT NULL,
        PRIMARY KEY (`vendor_template_id`)
    )");

    // Copy Vendor Templates over to new vendor templates table
    mysqli_query($mysqli, "
        INSERT INTO vendor_templates (
            vendor_template_name,
            vendor_template_description,
            vendor_template_contact_name,
            vendor_template_phone_country_code,
            vendor_template_phone,
            vendor_template_extension,
            vendor_template_email,
            vendor_template_website,
            vendor_template_hours,
            vendor_template_sla,
            vendor_template_code,
            vendor_template_account_number,
            vendor_template_notes,
            vendor_template_created_at,
            vendor_template_updated_at,
            vendor_template_archived_at
        )
        SELECT
            vendor_name,
            vendor_description,
            vendor_contact_name,
            vendor_phone_country_code,
            vendor_phone,
            vendor_extension,
            vendor_email,
            vendor_website,
            vendor_hours,
            vendor_sla,
            vendor_code,
            vendor_account_number,
            vendor_notes,
            vendor_created_at,
            vendor_updated_at,
            vendor_archived_at
        FROM
            vendors
        WHERE
            vendor_template = 1
    ");

    mysqli_query($mysqli, "DELETE FROM vendors WHERE vendor_template = 1");

    mysqli_query($mysqli, "ALTER TABLE `vendors` DROP `vendor_template`");
