<?php

/*
 * ITFlow - Database update to version 2.0.5 (from 2.0.4)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    // Clean up orphaned history
    mysqli_query($mysqli, "
        DELETE FROM `client_notes`
        WHERE `client_note_client_id` NOT IN (SELECT `client_id` FROM `clients`);
    ");

    // Add foreign key
    mysqli_query($mysqli, "
        ALTER TABLE `client_notes`
        ADD FOREIGN KEY (`client_note_client_id`) REFERENCES `clients`(`client_id`) ON DELETE CASCADE
    ");

    // Clean up orphaned history
    mysqli_query($mysqli, "
        DELETE FROM `client_tags`
        WHERE `client_id` NOT IN (SELECT `client_id` FROM `clients`);
    ");

    // Clean up orphaned history
    mysqli_query($mysqli, "
        DELETE FROM `client_tags`
        WHERE `tag_id` NOT IN (SELECT `tag_id` FROM `tags`);
    ");

    // Add foreign key
    mysqli_query($mysqli, "
        ALTER TABLE `client_tags`
        ADD FOREIGN KEY (`client_id`) REFERENCES `clients`(`client_id`) ON DELETE CASCADE,
        ADD FOREIGN KEY (`tag_id`) REFERENCES `tags`(`tag_id`) ON DELETE CASCADE
    ");

    //Contact Assets
    // Clean up orphaned history
    mysqli_query($mysqli, "
        DELETE FROM `contact_assets`
        WHERE `contact_id` NOT IN (SELECT `contact_id` FROM `contacts`);
    ");

    mysqli_query($mysqli, "
        DELETE FROM `contact_assets`
        WHERE `asset_id` NOT IN (SELECT `asset_id` FROM `assets`);
    ");

    // Add foreign key
    mysqli_query($mysqli, "
        ALTER TABLE `contact_assets`
        ADD FOREIGN KEY (`contact_id`) REFERENCES `contacts`(`contact_id`) ON DELETE CASCADE,
        ADD FOREIGN KEY (`asset_id`) REFERENCES `assets`(`asset_id`) ON DELETE CASCADE
    ");

    // Contact Documents
    // Clean up orphaned history
    mysqli_query($mysqli, "
        DELETE FROM `contact_documents`
        WHERE `contact_id` NOT IN (SELECT `contact_id` FROM `contacts`);
    ");

    mysqli_query($mysqli, "
        DELETE FROM `contact_documents`
        WHERE `document_id` NOT IN (SELECT `document_id` FROM `documents`);
    ");

    // Add foreign key
    mysqli_query($mysqli, "
        ALTER TABLE `contact_documents`
        ADD FOREIGN KEY (`contact_id`) REFERENCES `contacts`(`contact_id`) ON DELETE CASCADE,
        ADD FOREIGN KEY (`document_id`) REFERENCES `documents`(`document_id`) ON DELETE CASCADE
    ");

    // contact_files
    // Clean up orphaned rows
    mysqli_query($mysqli, "
        DELETE FROM `contact_files`
        WHERE `contact_id` NOT IN (SELECT `contact_id` FROM `contacts`);
    ");

    mysqli_query($mysqli, "
        DELETE FROM `contact_files`
        WHERE `file_id` NOT IN (SELECT `file_id` FROM `files`);
    ");

    // Add foreign key
    mysqli_query($mysqli, "
        ALTER TABLE `contact_files`
        ADD FOREIGN KEY (`contact_id`) REFERENCES `contacts`(`contact_id`) ON DELETE CASCADE,
        ADD FOREIGN KEY (`file_id`) REFERENCES `files`(`file_id`) ON DELETE CASCADE
    ");

    // contact_notes
    // Clean up orphaned rows
    mysqli_query($mysqli, "
        DELETE FROM `contact_notes`
        WHERE `contact_note_contact_id` NOT IN (SELECT `contact_id` FROM `contacts`);
    ");

    // Add foreign key
    mysqli_query($mysqli, "
        ALTER TABLE `contact_notes`
        ADD FOREIGN KEY (`contact_note_contact_id`) REFERENCES `contacts`(`contact_id`) ON DELETE CASCADE
    ");

    // contact_tags
    // Clean up orphaned rows
    mysqli_query($mysqli, "
        DELETE FROM `contact_tags`
        WHERE `contact_id` NOT IN (SELECT `contact_id` FROM `contacts`);
    ");

    mysqli_query($mysqli, "
        DELETE FROM `contact_tags`
        WHERE `tag_id` NOT IN (SELECT `tag_id` FROM `tags`);
    ");

    // Add foreign key
    mysqli_query($mysqli, "
        ALTER TABLE `contact_tags`
        ADD FOREIGN KEY (`contact_id`) REFERENCES `contacts`(`contact_id`) ON DELETE CASCADE,
        ADD FOREIGN KEY (`tag_id`) REFERENCES `tags`(`tag_id`) ON DELETE CASCADE
    ");

    // document_files
    // Clean up orphaned rows
    mysqli_query($mysqli, "
        DELETE FROM `document_files`
        WHERE `document_id` NOT IN (SELECT `document_id` FROM `documents`);
    ");

    mysqli_query($mysqli, "
        DELETE FROM `document_files`
        WHERE `file_id` NOT IN (SELECT `file_id` FROM `files`);
    ");

    // Add foreign key
    mysqli_query($mysqli, "
        ALTER TABLE `document_files`
        ADD FOREIGN KEY (`document_id`) REFERENCES `documents`(`document_id`) ON DELETE CASCADE,
        ADD FOREIGN KEY (`file_id`) REFERENCES `files`(`file_id`) ON DELETE CASCADE
    ");

    // domain_history
    // Clean up orphaned rows
    mysqli_query($mysqli, "
        DELETE FROM `domain_history`
        WHERE `domain_history_domain_id` NOT IN (SELECT `domain_id` FROM `domains`);
    ");

    // Add foreign key
    mysqli_query($mysqli, "
        ALTER TABLE `domain_history`
        ADD FOREIGN KEY (`domain_history_domain_id`) REFERENCES `domains`(`domain_id`) ON DELETE CASCADE
    ");

    // location_tags
    // Clean up orphaned rows
    mysqli_query($mysqli, "
        DELETE FROM `location_tags`
        WHERE `location_id` NOT IN (SELECT `location_id` FROM `locations`);
    ");
    mysqli_query($mysqli, "
        DELETE FROM `location_tags`
        WHERE `tag_id` NOT IN (SELECT `tag_id` FROM `tags`);
    ");
    // Add foreign key
    mysqli_query($mysqli, "
        ALTER TABLE `location_tags`
        ADD FOREIGN KEY (`location_id`) REFERENCES `locations`(`location_id`) ON DELETE CASCADE,
        ADD FOREIGN KEY (`tag_id`) REFERENCES `tags`(`tag_id`) ON DELETE CASCADE
    ");

    // quote_files
    // Clean up orphaned rows
    mysqli_query($mysqli, "
        DELETE FROM `quote_files`
        WHERE `quote_id` NOT IN (SELECT `quote_id` FROM `quotes`);
    ");
    mysqli_query($mysqli, "
        DELETE FROM `quote_files`
        WHERE `file_id` NOT IN (SELECT `file_id` FROM `files`);
    ");
    // Add foreign key
    mysqli_query($mysqli, "
        ALTER TABLE `quote_files`
        ADD FOREIGN KEY (`quote_id`) REFERENCES `quotes`(`quote_id`) ON DELETE CASCADE,
        ADD FOREIGN KEY (`file_id`) REFERENCES `files`(`file_id`) ON DELETE CASCADE
    ");

    // service_certificates
    // Clean up orphaned rows
    mysqli_query($mysqli, "
        DELETE FROM `service_certificates`
        WHERE `service_id` NOT IN (SELECT `service_id` FROM `services`);
    ");
    mysqli_query($mysqli, "
        DELETE FROM `service_certificates`
        WHERE `certificate_id` NOT IN (SELECT `certificate_id` FROM `certificates`);
    ");
    // Add foreign key
    mysqli_query($mysqli, "
        ALTER TABLE `service_certificates`
        ADD FOREIGN KEY (`service_id`) REFERENCES `services`(`service_id`) ON DELETE CASCADE,
        ADD FOREIGN KEY (`certificate_id`) REFERENCES `certificates`(`certificate_id`) ON DELETE CASCADE
    ");

    // service_contacts
    // Clean up orphaned rows
    mysqli_query($mysqli, "
        DELETE FROM `service_contacts`
        WHERE `service_id` NOT IN (SELECT `service_id` FROM `services`);
    ");
    mysqli_query($mysqli, "
        DELETE FROM `service_contacts`
        WHERE `contact_id` NOT IN (SELECT `contact_id` FROM `contacts`);
    ");
    // Add foreign key
    mysqli_query($mysqli, "
        ALTER TABLE `service_contacts`
        ADD FOREIGN KEY (`service_id`) REFERENCES `services`(`service_id`) ON DELETE CASCADE,
        ADD FOREIGN KEY (`contact_id`) REFERENCES `contacts`(`contact_id`) ON DELETE CASCADE
    ");

    // service_documents
    // Clean up orphaned rows
    mysqli_query($mysqli, "
        DELETE FROM `service_documents`
        WHERE `service_id` NOT IN (SELECT `service_id` FROM `services`);
    ");
    mysqli_query($mysqli, "
        DELETE FROM `service_documents`
        WHERE `document_id` NOT IN (SELECT `document_id` FROM `documents`);
    ");
    // Add foreign key
    mysqli_query($mysqli, "
        ALTER TABLE `service_documents`
        ADD FOREIGN KEY (`service_id`) REFERENCES `services`(`service_id`) ON DELETE CASCADE,
        ADD FOREIGN KEY (`document_id`) REFERENCES `documents`(`document_id`) ON DELETE CASCADE
    ");

    // service_domains
    // Clean up orphaned rows
    mysqli_query($mysqli, "
        DELETE FROM `service_domains`
        WHERE `service_id` NOT IN (SELECT `service_id` FROM `services`);
    ");
    mysqli_query($mysqli, "
        DELETE FROM `service_domains`
        WHERE `domain_id` NOT IN (SELECT `domain_id` FROM `domains`);
    ");
    // Add foreign key
    mysqli_query($mysqli, "
        ALTER TABLE `service_domains`
        ADD FOREIGN KEY (`service_id`) REFERENCES `services`(`service_id`) ON DELETE CASCADE,
        ADD FOREIGN KEY (`domain_id`) REFERENCES `domains`(`domain_id`) ON DELETE CASCADE
    ");

    // service_vendors
    // Clean up orphaned rows
    mysqli_query($mysqli, "
        DELETE FROM `service_vendors`
        WHERE `service_id` NOT IN (SELECT `service_id` FROM `services`);
    ");
    mysqli_query($mysqli, "
        DELETE FROM `service_vendors`
        WHERE `vendor_id` NOT IN (SELECT `vendor_id` FROM `vendors`);
    ");
    // Add foreign key
    mysqli_query($mysqli, "
        ALTER TABLE `service_vendors`
        ADD FOREIGN KEY (`service_id`) REFERENCES `services`(`service_id`) ON DELETE CASCADE,
        ADD FOREIGN KEY (`vendor_id`) REFERENCES `vendors`(`vendor_id`) ON DELETE CASCADE
    ");

    // software_contacts
    // Clean up orphaned rows
    mysqli_query($mysqli, "
        DELETE FROM `software_contacts`
        WHERE `software_id` NOT IN (SELECT `software_id` FROM `software`);
    ");
    mysqli_query($mysqli, "
        DELETE FROM `software_contacts`
        WHERE `contact_id` NOT IN (SELECT `contact_id` FROM `contacts`);
    ");
    // Add foreign key
    mysqli_query($mysqli, "
        ALTER TABLE `software_contacts`
        ADD FOREIGN KEY (`software_id`) REFERENCES `software`(`software_id`) ON DELETE CASCADE,
        ADD FOREIGN KEY (`contact_id`) REFERENCES `contacts`(`contact_id`) ON DELETE CASCADE
    ");

    // software_documents
    // Clean up orphaned rows
    mysqli_query($mysqli, "
        DELETE FROM `software_documents`
        WHERE `software_id` NOT IN (SELECT `software_id` FROM `software`);
    ");
    mysqli_query($mysqli, "
        DELETE FROM `software_documents`
        WHERE `document_id` NOT IN (SELECT `document_id` FROM `documents`);
    ");
    // Add foreign key
    mysqli_query($mysqli, "
        ALTER TABLE `software_documents`
        ADD FOREIGN KEY (`software_id`) REFERENCES `software`(`software_id`) ON DELETE CASCADE,
        ADD FOREIGN KEY (`document_id`) REFERENCES `documents`(`document_id`) ON DELETE CASCADE
    ");

    // software_files
    // Clean up orphaned rows
    mysqli_query($mysqli, "
        DELETE FROM `software_files`
        WHERE `software_id` NOT IN (SELECT `software_id` FROM `software`);
    ");
    mysqli_query($mysqli, "
        DELETE FROM `software_files`
        WHERE `file_id` NOT IN (SELECT `file_id` FROM `files`);
    ");
    // Add foreign key
    mysqli_query($mysqli, "
        ALTER TABLE `software_files`
        ADD FOREIGN KEY (`software_id`) REFERENCES `software`(`software_id`) ON DELETE CASCADE,
        ADD FOREIGN KEY (`file_id`) REFERENCES `files`(`file_id`) ON DELETE CASCADE
    ");

    // vendor_documents
    // Clean up orphaned rows
    mysqli_query($mysqli, "
        DELETE FROM `vendor_documents`
        WHERE `vendor_id` NOT IN (SELECT `vendor_id` FROM `vendors`);
    ");
    mysqli_query($mysqli, "
        DELETE FROM `vendor_documents`
        WHERE `document_id` NOT IN (SELECT `document_id` FROM `documents`);
    ");
    // Add foreign key
    mysqli_query($mysqli, "
        ALTER TABLE `vendor_documents`
        ADD FOREIGN KEY (`vendor_id`) REFERENCES `vendors`(`vendor_id`) ON DELETE CASCADE,
        ADD FOREIGN KEY (`document_id`) REFERENCES `documents`(`document_id`) ON DELETE CASCADE
    ");

    // vendor_files
    // Clean up orphaned rows
    mysqli_query($mysqli, "
        DELETE FROM `vendor_files`
        WHERE `vendor_id` NOT IN (SELECT `vendor_id` FROM `vendors`);
    ");
    mysqli_query($mysqli, "
        DELETE FROM `vendor_files`
        WHERE `file_id` NOT IN (SELECT `file_id` FROM `files`);
    ");
    // Add foreign key
    mysqli_query($mysqli, "
        ALTER TABLE `vendor_files`
        ADD FOREIGN KEY (`vendor_id`) REFERENCES `vendors`(`vendor_id`) ON DELETE CASCADE,
        ADD FOREIGN KEY (`file_id`) REFERENCES `files`(`file_id`) ON DELETE CASCADE
    ");
