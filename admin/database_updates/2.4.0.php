<?php

/*
 * ITFlow - Database update to version 2.4.0 (from 2.3.9)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    mysqli_query($mysqli, "ALTER TABLE `clients` ADD `client_favorite` TINYINT(1) NOT NULL DEFAULT '0' AFTER `client_notes`");

    mysqli_query($mysqli, "ALTER TABLE `locations` ADD `location_favorite` TINYINT(1) NOT NULL DEFAULT '0' AFTER `location_notes`");

    mysqli_query($mysqli, "ALTER TABLE `vendors` ADD `vendor_favorite` TINYINT(1) NOT NULL DEFAULT '0' AFTER `vendor_notes`");

    mysqli_query($mysqli, "ALTER TABLE `software` ADD `software_favorite` TINYINT(1) NOT NULL DEFAULT '0' AFTER `software_notes`");

    mysqli_query(
        $mysqli,
        "ALTER TABLE `credentials`
         CHANGE `credential_important` `credential_favorite`
         TINYINT(1) NOT NULL DEFAULT 0
         AFTER `credential_note`"
    );

    mysqli_query($mysqli, "ALTER TABLE `assets` DROP `asset_important`");
    mysqli_query($mysqli, "ALTER TABLE `assets` ADD `asset_favorite` TINYINT(1) NOT NULL DEFAULT '0' AFTER `asset_notes`");

    mysqli_query($mysqli, "ALTER TABLE `documents` DROP `document_important`");
    mysqli_query($mysqli, "ALTER TABLE `documents` ADD `document_favorite` TINYINT(1) NOT NULL DEFAULT '0' AFTER `document_client_visible`");

    mysqli_query($mysqli, "ALTER TABLE `racks` ADD `rack_favorite` TINYINT(1) NOT NULL DEFAULT '0' AFTER `rack_notes`");

    mysqli_query($mysqli, "ALTER TABLE `files` DROP `file_important`");
    mysqli_query($mysqli, "ALTER TABLE `files` ADD `file_favorite` TINYINT(1) NOT NULL DEFAULT '0' AFTER `file_mime_type`");

    mysqli_query($mysqli, "ALTER TABLE `networks` ADD `network_favorite` TINYINT(1) NOT NULL DEFAULT '0' AFTER `network_notes`");

    mysqli_query($mysqli, "ALTER TABLE `domains` ADD `domain_favorite` TINYINT(1) NOT NULL DEFAULT '0' AFTER `domain_notes`");

    mysqli_query($mysqli, "ALTER TABLE `certificates` ADD `certificate_favorite` TINYINT(1) NOT NULL DEFAULT '0' AFTER `certificate_notes`");

    mysqli_query($mysqli, "ALTER TABLE `services` ADD `service_favorite` TINYINT(1) NOT NULL DEFAULT '0' AFTER `service_notes`");
