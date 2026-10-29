<?php

/*
 * ITFlow - Database update to version 3.2.4 (from 3.2.3)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    // An agent's own canned responses - offered to them alone; 0 is shared,
    // offered to everyone. Who made each, either way
    mysqli_query($mysqli, "ALTER TABLE `canned_responses`
        ADD `canned_response_user_id` int(11) NOT NULL DEFAULT 0 AFTER `canned_response_minutes`,
        ADD `canned_response_created_by` int(11) NOT NULL DEFAULT 0 AFTER `canned_response_user_id`,
        ADD KEY `canned_response_user_id` (`canned_response_user_id`)");

    // Files that go out with a canned response - kept in uploads/canned_responses/<id>/
    // and copied onto the ticket of each reply it goes into
    mysqli_query($mysqli, "CREATE TABLE `canned_response_files` (
        `canned_response_file_id` int(11) NOT NULL AUTO_INCREMENT,
        `canned_response_file_canned_response_id` int(11) NOT NULL,
        `canned_response_file_name` varchar(255) NOT NULL,
        `canned_response_file_reference_name` varchar(255) NOT NULL,
        `canned_response_file_size` int(11) NOT NULL DEFAULT 0,
        `canned_response_file_created_at` datetime NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`canned_response_file_id`),
        KEY `canned_response_file_canned_response_id` (`canned_response_file_canned_response_id`),
        CONSTRAINT `canned_response_files_ibfk_1` FOREIGN KEY (`canned_response_file_canned_response_id`) REFERENCES `canned_responses` (`canned_response_id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
