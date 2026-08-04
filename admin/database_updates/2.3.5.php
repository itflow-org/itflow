<?php

/*
 * ITFlow - Database update to version 2.3.5 (from 2.3.4)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    // Add Software Keys
    mysqli_query($mysqli, "CREATE TABLE `software_keys` (
        `software_key_id` INT(11) NOT NULL AUTO_INCREMENT,
        `software_key` VARCHAR(400) NOT NULL,
        `software_key_software_id` INT(11) NOT NULL,
        PRIMARY KEY (`software_key_id`),
        FOREIGN KEY (`software_key_software_id`) REFERENCES `software`(`software_id`) ON DELETE CASCADE
    )");

    // Software Key Assignments to Contacts
    mysqli_query($mysqli, "CREATE TABLE `software_key_contact_assignments` (
        `software_key_id` INT(11) NOT NULL,
        `contact_id` INT(11) NOT NULL,
        `software_key_assigned_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`software_key_id`, `contact_id`),
        FOREIGN KEY (`software_key_id`) REFERENCES `software_keys`(`software_key_id`) ON DELETE CASCADE,
        FOREIGN KEY (`contact_id`) REFERENCES `contacts`(`contact_id`) ON DELETE CASCADE
    )");

    // Software Key Assignments to Assets
    mysqli_query($mysqli, "CREATE TABLE `software_key_asset_assignments` (
        `software_key_id` INT(11) NOT NULL,
        `asset_id` INT(11) NOT NULL,
        `software_key_assigned_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`software_key_id`, `asset_id`),
        FOREIGN KEY (`software_key_id`) REFERENCES `software_keys`(`software_key_id`) ON DELETE CASCADE,
        FOREIGN KEY (`asset_id`) REFERENCES `assets`(`asset_id`) ON DELETE CASCADE
    )");
