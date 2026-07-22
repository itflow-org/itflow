<?php

/*
 * ITFlow - Database update to version 2.1.8 (from 2.1.7)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    mysqli_query($mysqli, "CREATE TABLE `software_templates` (
        `software_template_id` INT(11) NOT NULL AUTO_INCREMENT,
        `software_template_name` VARCHAR(200) NOT NULL,
        `software_template_description` TEXT DEFAULT NULL,
        `software_template_version` VARCHAR(200) DEFAULT NULL,
        `software_template_type` VARCHAR(200) NOT NULL,
        `software_template_license_type` VARCHAR(200) DEFAULT NULL,
        `software_template_notes` TEXT DEFAULT NULL,
        `software_template_created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `software_template_updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
        `software_template_archived_at` DATETIME NULL DEFAULT NULL,
        PRIMARY KEY (`software_template_id`)
    )");

    // Copy software Templates over to new software templates table
    mysqli_query($mysqli, "
        INSERT INTO software_templates (
            software_template_name,
            software_template_description,
            software_template_version,
            software_template_type,
            software_template_license_type,
            software_template_notes,
            software_template_created_at,
            software_template_updated_at,
            software_template_archived_at
        )
        SELECT
            software_name,
            software_description,
            software_version,
            software_type,
            software_license_type,
            software_notes,
            software_created_at,
            software_updated_at,
            software_archived_at
        FROM
            software
        WHERE
            software_template = 1
    ");

    mysqli_query($mysqli, "DELETE FROM software WHERE software_template = 1");

    mysqli_query($mysqli, "ALTER TABLE `software` DROP `software_template`");

    mysqli_query($mysqli, "ALTER TABLE `software` DROP `software_template_id`");
