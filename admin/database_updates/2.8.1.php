<?php

/*
 * ITFlow - Database update to version 2.8.1 (from 2.8.0)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    // Documents and files linked to a location - the site runbook, the floor
    // plan, photos of the comms cupboard - the same as assets, contacts and
    // licenses have. Both go when the location or the document / file does.
    mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `location_documents` (
        `location_id` int(11) NOT NULL,
        `document_id` int(11) NOT NULL,
        PRIMARY KEY (`location_id`, `document_id`),
        KEY `document_id` (`document_id`),
        CONSTRAINT `location_documents_ibfk_1` FOREIGN KEY (`location_id`) REFERENCES `locations` (`location_id`) ON DELETE CASCADE,
        CONSTRAINT `location_documents_ibfk_2` FOREIGN KEY (`document_id`) REFERENCES `documents` (`document_id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `location_files` (
        `location_id` int(11) NOT NULL,
        `file_id` int(11) NOT NULL,
        PRIMARY KEY (`location_id`, `file_id`),
        KEY `file_id` (`file_id`),
        CONSTRAINT `location_files_ibfk_1` FOREIGN KEY (`location_id`) REFERENCES `locations` (`location_id`) ON DELETE CASCADE,
        CONSTRAINT `location_files_ibfk_2` FOREIGN KEY (`file_id`) REFERENCES `files` (`file_id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
