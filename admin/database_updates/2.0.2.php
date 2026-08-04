<?php

/*
 * ITFlow - Database update to version 2.0.2 (from 2.0.1)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    // Clean up orphaned data before adding foreign keys

    // Clean up orphaned asset_custom_asset_id rows in asset_custom
    mysqli_query($mysqli, "
        DELETE FROM `asset_custom`
        WHERE `asset_custom_asset_id` NOT IN (SELECT `asset_id` FROM `assets`);
    ");

    // Add foreign key to asset_custom
    mysqli_query($mysqli, "
        ALTER TABLE `asset_custom`
        ADD FOREIGN KEY (`asset_custom_asset_id`) REFERENCES `assets`(`asset_id`) ON DELETE CASCADE
    ");

    // Clean up orphaned asset_id rows in asset_documents
    mysqli_query($mysqli, "
        DELETE FROM `asset_documents`
        WHERE `asset_id` NOT IN (SELECT `asset_id` FROM `assets`);
    ");

    // Clean up orphaned document_id rows in asset_documents
    mysqli_query($mysqli, "
        DELETE FROM `asset_documents`
        WHERE `document_id` NOT IN (SELECT `document_id` FROM `documents`);
    ");

    // Add foreign keys to asset_documents
    mysqli_query($mysqli, "
        ALTER TABLE `asset_documents`
        ADD FOREIGN KEY (`asset_id`) REFERENCES `assets`(`asset_id`) ON DELETE CASCADE,
        ADD FOREIGN KEY (`document_id`) REFERENCES `documents`(`document_id`) ON DELETE CASCADE
    ");

    // Clean up orphaned asset_id rows in asset_files
    mysqli_query($mysqli, "
        DELETE FROM `asset_files`
        WHERE `asset_id` NOT IN (SELECT `asset_id` FROM `assets`);
    ");

    // Clean up orphaned file_id rows in asset_files
    mysqli_query($mysqli, "
        DELETE FROM `asset_files`
        WHERE `file_id` NOT IN (SELECT `file_id` FROM `files`);
    ");

    // Add foreign keys to asset_files
    mysqli_query($mysqli, "
        ALTER TABLE `asset_files`
        ADD FOREIGN KEY (`asset_id`) REFERENCES `assets`(`asset_id`) ON DELETE CASCADE,
        ADD FOREIGN KEY (`file_id`) REFERENCES `files`(`file_id`) ON DELETE CASCADE
    ");

    // Clean up orphaned asset_history_asset_id rows in asset_history
    mysqli_query($mysqli, "
        DELETE FROM `asset_history`
        WHERE `asset_history_asset_id` NOT IN (SELECT `asset_id` FROM `assets`);
    ");

    // Add foreign key to asset_history
    mysqli_query($mysqli, "
        ALTER TABLE `asset_history`
        ADD FOREIGN KEY (`asset_history_asset_id`) REFERENCES `assets`(`asset_id`) ON DELETE CASCADE
    ");

    // Clean up orphaned interface_asset_id rows in asset_interfaces
    mysqli_query($mysqli, "
        DELETE FROM `asset_interfaces`
        WHERE `interface_asset_id` NOT IN (SELECT `asset_id` FROM `assets`);
    ");

    // Add foreign key to asset_interfaces
    mysqli_query($mysqli, "
        ALTER TABLE `asset_interfaces`
        ADD FOREIGN KEY (`interface_asset_id`) REFERENCES `assets`(`asset_id`) ON DELETE CASCADE
    ");

    // Clean up orphaned asset_note_asset_id rows in asset_notes
    mysqli_query($mysqli, "
        DELETE FROM `asset_notes`
        WHERE `asset_note_asset_id` NOT IN (SELECT `asset_id` FROM `assets`);
    ");

    // Add foreign key to asset_notes
    mysqli_query($mysqli, "
        ALTER TABLE `asset_notes`
        ADD FOREIGN KEY (`asset_note_asset_id`) REFERENCES `assets`(`asset_id`) ON DELETE CASCADE
    ");

    // Clean up orphaned contact_id rows in contact_assets
    mysqli_query($mysqli, "
        DELETE FROM `contact_assets`
        WHERE `contact_id` NOT IN (SELECT `contact_id` FROM `contacts`);
    ");

    // Clean up orphaned asset_id rows in contact_assets
    mysqli_query($mysqli, "
        DELETE FROM `contact_assets`
        WHERE `asset_id` NOT IN (SELECT `asset_id` FROM `assets`);
    ");

    // Add foreign keys to contact_assets
    mysqli_query($mysqli, "
        ALTER TABLE `contact_assets`
        ADD FOREIGN KEY (`contact_id`) REFERENCES `contacts`(`contact_id`) ON DELETE CASCADE,
        ADD FOREIGN KEY (`asset_id`) REFERENCES `assets`(`asset_id`) ON DELETE CASCADE
    ");

    // Clean up orphaned service_id rows in service_assets
    mysqli_query($mysqli, "
        DELETE FROM `service_assets`
        WHERE `service_id` NOT IN (SELECT `service_id` FROM `services`);
    ");

    // Clean up orphaned asset_id rows in service_assets
    mysqli_query($mysqli, "
        DELETE FROM `service_assets`
        WHERE `asset_id` NOT IN (SELECT `asset_id` FROM `assets`);
    ");

    // Add foreign keys to service_assets
    mysqli_query($mysqli, "
        ALTER TABLE `service_assets`
        ADD FOREIGN KEY (`service_id`) REFERENCES `services`(`service_id`) ON DELETE CASCADE,
        ADD FOREIGN KEY (`asset_id`) REFERENCES `assets`(`asset_id`) ON DELETE CASCADE
    ");

    // Clean up orphaned software_id rows in software_assets
    mysqli_query($mysqli, "
        DELETE FROM `software_assets`
        WHERE `software_id` NOT IN (SELECT `software_id` FROM `software`);
    ");

    // Clean up orphaned asset_id rows in software_assets
    mysqli_query($mysqli, "
        DELETE FROM `software_assets`
        WHERE `asset_id` NOT IN (SELECT `asset_id` FROM `assets`);
    ");

    // Add foreign keys to software_assets
    mysqli_query($mysqli, "
        ALTER TABLE `software_assets`
        ADD FOREIGN KEY (`software_id`) REFERENCES `software`(`software_id`) ON DELETE CASCADE,
        ADD FOREIGN KEY (`asset_id`) REFERENCES `assets`(`asset_id`) ON DELETE CASCADE
    ");
