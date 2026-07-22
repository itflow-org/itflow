<?php

/*
 * ITFlow - Database update to version 2.0.0 (from 1.9.9)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    mysqli_query($mysqli, "RENAME TABLE `logins` TO `credentials`");
    mysqli_query($mysqli, "
        ALTER TABLE `credentials`
        CHANGE COLUMN `login_id` `credential_id` INT(11) NOT NULL AUTO_INCREMENT,
        CHANGE COLUMN `login_name` `credential_name` VARCHAR(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
        CHANGE COLUMN `login_description` `credential_description` VARCHAR(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
        CHANGE COLUMN `login_category` `credential_category` VARCHAR(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
        CHANGE COLUMN `login_uri` `credential_uri` VARCHAR(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
        CHANGE COLUMN `login_uri_2` `credential_uri_2` VARCHAR(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
        CHANGE COLUMN `login_username` `credential_username` VARCHAR(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
        CHANGE COLUMN `login_password` `credential_password` VARBINARY(200) NULL DEFAULT NULL,
        CHANGE COLUMN `login_otp_secret` `credential_otp_secret` VARCHAR(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
        CHANGE COLUMN `login_note` `credential_note` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
        CHANGE COLUMN `login_important` `credential_important` TINYINT(1) NOT NULL DEFAULT '0',
        CHANGE COLUMN `login_created_at` `credential_created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP(),
        CHANGE COLUMN `login_updated_at` `credential_updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP(),
        CHANGE COLUMN `login_archived_at` `credential_archived_at` DATETIME NULL DEFAULT NULL,
        CHANGE COLUMN `login_accessed_at` `credential_accessed_at` DATETIME NULL DEFAULT NULL,
        CHANGE COLUMN `login_password_changed_at` `credential_password_changed_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP(),
        CHANGE COLUMN `login_folder_id` `credential_folder_id` INT(11) NOT NULL DEFAULT '0',
        CHANGE COLUMN `login_contact_id` `credential_contact_id` INT(11) NOT NULL DEFAULT '0',
        CHANGE COLUMN `login_asset_id` `credential_asset_id` INT(11) NOT NULL DEFAULT '0',
        CHANGE COLUMN `login_client_id` `credential_client_id` INT(11) NOT NULL DEFAULT '0'
    ");

    // Rename table contact_logins to contact_credentials
    mysqli_query($mysqli, "RENAME TABLE `contact_logins` TO `contact_credentials`");

    // Alter contact_credentials table and change login_id to credential_id
    mysqli_query($mysqli, "
        ALTER TABLE `contact_credentials`
        CHANGE COLUMN `login_id` `credential_id` INT(11) NOT NULL
    ");

    // Clean up orphaned contact_id rows in contact_credentials
    mysqli_query($mysqli, "
        DELETE FROM `contact_credentials`
        WHERE `contact_id` NOT IN (SELECT `contact_id` FROM `contacts`);
    ");

    // Clean up orphaned credential_id rows in contact_credentials
    mysqli_query($mysqli, "
        DELETE FROM `contact_credentials`
        WHERE `credential_id` NOT IN (SELECT `credential_id` FROM `credentials`);
    ");

    // Add foreign keys to contact_credentials
    mysqli_query($mysqli, "
        ALTER TABLE `contact_credentials`
        ADD FOREIGN KEY (`contact_id`) REFERENCES `contacts`(`contact_id`) ON DELETE CASCADE,
        ADD FOREIGN KEY (`credential_id`) REFERENCES `credentials`(`credential_id`) ON DELETE CASCADE
    ");

    // Rename table service_logins to service_credentials
    mysqli_query($mysqli, "RENAME TABLE `service_logins` TO `service_credentials`");

    // Alter service_credentials table and change login_id to credential_id
    mysqli_query($mysqli, "
        ALTER TABLE `service_credentials`
        CHANGE COLUMN `login_id` `credential_id` INT(11) NOT NULL
    ");

    // Clean up orphaned service_id rows in service_credentials
    mysqli_query($mysqli, "
        DELETE FROM `service_credentials`
        WHERE `service_id` NOT IN (SELECT `service_id` FROM `services`);
    ");

    // Clean up orphaned credential_id rows in service_credentials
    mysqli_query($mysqli, "
        DELETE FROM `service_credentials`
        WHERE `credential_id` NOT IN (SELECT `credential_id` FROM `credentials`);
    ");

    // Add foreign keys to service_credentials
    mysqli_query($mysqli, "
        ALTER TABLE `service_credentials`
        ADD FOREIGN KEY (`service_id`) REFERENCES `services`(`service_id`) ON DELETE CASCADE,
        ADD FOREIGN KEY (`credential_id`) REFERENCES `credentials`(`credential_id`) ON DELETE CASCADE
    ");

    // Rename table software_logins to software_credentials
    mysqli_query($mysqli, "RENAME TABLE `software_logins` TO `software_credentials`");

    // Alter software_credentials table and change login_id to credential_id
    mysqli_query($mysqli, "
        ALTER TABLE `software_credentials`
        CHANGE COLUMN `login_id` `credential_id` INT(11) NOT NULL
    ");

    // Clean up orphaned software_id rows in software_credentials
    mysqli_query($mysqli, "
        DELETE FROM `software_credentials`
        WHERE `software_id` NOT IN (SELECT `software_id` FROM `software`);
    ");

    // Clean up orphaned credential_id rows in software_credentials
    mysqli_query($mysqli, "
        DELETE FROM `software_credentials`
        WHERE `credential_id` NOT IN (SELECT `credential_id` FROM `credentials`);
    ");

    // Add foreign keys to software_credentials
    mysqli_query($mysqli, "
        ALTER TABLE `software_credentials`
        ADD FOREIGN KEY (`software_id`) REFERENCES `software`(`software_id`) ON DELETE CASCADE,
        ADD FOREIGN KEY (`credential_id`) REFERENCES `credentials`(`credential_id`) ON DELETE CASCADE
    ");

    // Rename table vendor_logins to vendor_credentials
    mysqli_query($mysqli, "RENAME TABLE `vendor_logins` TO `vendor_credentials`");

    // Alter vendor_credentials table and change login_id to credential_id
    mysqli_query($mysqli, "
        ALTER TABLE `vendor_credentials`
        CHANGE COLUMN `login_id` `credential_id` INT(11) NOT NULL
    ");

    // Clean up orphaned vendor_id rows in vendor_credentials
    mysqli_query($mysqli, "
        DELETE FROM `vendor_credentials`
        WHERE `vendor_id` NOT IN (SELECT `vendor_id` FROM `vendors`);
    ");

    // Clean up orphaned credential_id rows in vendor_credentials
    mysqli_query($mysqli, "
        DELETE FROM `vendor_credentials`
        WHERE `credential_id` NOT IN (SELECT `credential_id` FROM `credentials`);
    ");

    // Add foreign keys to vendor_credentials
    mysqli_query($mysqli, "
        ALTER TABLE `vendor_credentials`
        ADD FOREIGN KEY (`vendor_id`) REFERENCES `vendors`(`vendor_id`) ON DELETE CASCADE,
        ADD FOREIGN KEY (`credential_id`) REFERENCES `credentials`(`credential_id`) ON DELETE CASCADE
    ");

    // Rename table login_tags to credential_tags
    mysqli_query($mysqli, "RENAME TABLE `login_tags` TO `credential_tags`");

    // Alter credential_tags table and change login_id to credential_id
    mysqli_query($mysqli, "
        ALTER TABLE `credential_tags`
        CHANGE COLUMN `login_id` `credential_id` INT(11) NOT NULL
    ");

    // Clean up orphaned tag_id rows in credential_tags
    mysqli_query($mysqli, "
        DELETE FROM `credential_tags`
        WHERE `tag_id` NOT IN (SELECT `tag_id` FROM `tags`);
    ");

    // Clean up orphaned credential_id rows in credential_tags
    mysqli_query($mysqli, "
        DELETE FROM `credential_tags`
        WHERE `credential_id` NOT IN (SELECT `credential_id` FROM `credentials`);
    ");

    // Add foreign keys to credential_tags
    mysqli_query($mysqli, "
        ALTER TABLE `credential_tags`
        ADD FOREIGN KEY (`tag_id`) REFERENCES `tags`(`tag_id`) ON DELETE CASCADE,
        ADD FOREIGN KEY (`credential_id`) REFERENCES `credentials`(`credential_id`) ON DELETE CASCADE
    ");

    // Create asset_credentials table with foreign keys
    mysqli_query($mysqli, "
        CREATE TABLE `asset_credentials` (
            `credential_id` INT(11) NOT NULL,
            `asset_id` INT(11) NOT NULL,
            PRIMARY KEY (`credential_id`, `asset_id`),
            FOREIGN KEY (`credential_id`) REFERENCES `credentials`(`credential_id`) ON DELETE CASCADE,
            FOREIGN KEY (`asset_id`) REFERENCES `assets`(`asset_id`) ON DELETE CASCADE
        )
    ");
