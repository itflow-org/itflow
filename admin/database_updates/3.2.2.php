<?php

/*
 * ITFlow - Database update to version 3.2.2 (from 3.2.1)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    // A canned response for several ticket categories - none is every ticket,
    // as category 0 was. One whose category has since been deleted was offered
    // on every ticket, and still is
    mysqli_query($mysqli, "CREATE TABLE `canned_response_categories` (
        `canned_response_id` int(11) NOT NULL,
        `category_id` int(11) NOT NULL,
        PRIMARY KEY (`canned_response_id`,`category_id`),
        KEY `category_id` (`category_id`),
        CONSTRAINT `canned_response_categories_ibfk_1` FOREIGN KEY (`canned_response_id`) REFERENCES `canned_responses` (`canned_response_id`) ON DELETE CASCADE,
        CONSTRAINT `canned_response_categories_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    mysqli_query($mysqli, "INSERT INTO `canned_response_categories` (`canned_response_id`, `category_id`)
        SELECT `canned_response_id`, `canned_response_category_id` FROM `canned_responses`
        JOIN `categories` ON `category_id` = `canned_response_category_id` AND `category_type` = 'Ticket'
        WHERE `canned_response_category_id` > 0");

    mysqli_query($mysqli, "ALTER TABLE `canned_responses` DROP KEY `canned_response_category_id`, DROP `canned_response_category_id`");

    // Each time one goes into a reply - who, on which ticket and when
    mysqli_query($mysqli, "CREATE TABLE `canned_response_uses` (
        `canned_response_use_id` int(11) NOT NULL AUTO_INCREMENT,
        `canned_response_use_canned_response_id` int(11) NOT NULL,
        `canned_response_use_user_id` int(11) NOT NULL DEFAULT 0,
        `canned_response_use_ticket_id` int(11) NOT NULL DEFAULT 0,
        `canned_response_use_created_at` datetime NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`canned_response_use_id`),
        KEY `canned_response_use_canned_response_id` (`canned_response_use_canned_response_id`,`canned_response_use_created_at`),
        KEY `canned_response_use_user_id` (`canned_response_use_user_id`),
        CONSTRAINT `canned_response_uses_ibfk_1` FOREIGN KEY (`canned_response_use_canned_response_id`) REFERENCES `canned_responses` (`canned_response_id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
