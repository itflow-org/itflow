<?php

/*
 * ITFlow - Database update to version 3.3.0 (from 3.2.9)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    // Who should have a license from a template - 0 no one in particular, 1 every client, 2 the
    // clients with one of its tags
    mysqli_query($mysqli, "ALTER TABLE `software_templates`
        ADD `software_template_required` tinyint(1) NOT NULL DEFAULT 0 AFTER `software_template_product_id`");

    mysqli_query($mysqli, "CREATE TABLE `software_template_required_tags` (
        `software_template_id` int(11) NOT NULL,
        `tag_id` int(11) NOT NULL,
        PRIMARY KEY (`software_template_id`,`tag_id`),
        KEY `tag_id` (`tag_id`),
        CONSTRAINT `software_template_required_tags_ibfk_1` FOREIGN KEY (`software_template_id`) REFERENCES `software_templates` (`software_template_id`) ON DELETE CASCADE,
        CONSTRAINT `software_template_required_tags_ibfk_2` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`tag_id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
