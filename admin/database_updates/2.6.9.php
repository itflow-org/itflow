<?php

/*
 * ITFlow - Database update to version 2.6.9 (from 2.6.8)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    // Canned responses for ticket replies, managed from Admin > Canned Responses and
    // picked from a dropdown on the ticket reply form.
    //
    // canned_response_category_id is a ticket category (categories.category_id where
    // category_type = 'Ticket'), or 0 for one that offers itself on every ticket
    // whatever its category.

    mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `canned_responses` (
        `canned_response_id` int(11) NOT NULL AUTO_INCREMENT,
        `canned_response_name` varchar(200) NOT NULL,
        `canned_response_body` longtext DEFAULT NULL,
        `canned_response_category_id` int(11) NOT NULL DEFAULT 0,
        `canned_response_created_at` datetime NOT NULL DEFAULT current_timestamp(),
        `canned_response_updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
        `canned_response_archived_at` datetime DEFAULT NULL,
        PRIMARY KEY (`canned_response_id`),
        KEY `canned_response_category_id` (`canned_response_category_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
