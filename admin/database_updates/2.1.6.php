<?php

/*
 * ITFlow - Database update to version 2.1.6 (from 2.1.5)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    mysqli_query($mysqli, "CREATE TABLE `document_versions` (
        `document_version_id` INT(11) NOT NULL AUTO_INCREMENT,
        `document_version_name` VARCHAR(200) NOT NULL,
        `document_version_description` TEXT DEFAULT NULL,
        `document_version_content` LONGTEXT NOT NULL,
        `document_version_created_by` INT(11) DEFAULT 0,
        `document_version_created_at` DATETIME NOT NULL,
        `document_version_document_id` INT(11) NOT NULL,
        PRIMARY KEY (`document_version_id`)
    )");

    // Delete all Current Document Versions
    mysqli_query($mysqli, "
        DELETE FROM `documents`
        WHERE `document_parent` > 0 AND `document_parent` != `document_id`
    ");

    mysqli_query($mysqli, "ALTER TABLE `documents` DROP `document_parent`");
