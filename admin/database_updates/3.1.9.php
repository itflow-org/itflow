<?php

/*
 * ITFlow - Database update to version 3.1.9 (from 3.1.8)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    // A ticket template sets up the whole ticket: its category, priority,
    // billing, who it goes to and when it is due, and its tags. NULL priority
    // or billable, 0 category, agent or due: the template leaves that alone.
    mysqli_query($mysqli, "ALTER TABLE `ticket_templates`
        ADD `ticket_template_category_id` int(11) NOT NULL DEFAULT 0 AFTER `ticket_template_details`,
        ADD `ticket_template_priority` varchar(20) DEFAULT NULL AFTER `ticket_template_category_id`,
        ADD `ticket_template_billable` tinyint(1) DEFAULT NULL AFTER `ticket_template_priority`,
        ADD `ticket_template_assigned_to` int(11) NOT NULL DEFAULT 0 AFTER `ticket_template_billable`,
        ADD `ticket_template_due_hours` int(11) NOT NULL DEFAULT 0 AFTER `ticket_template_assigned_to`");

    mysqli_query($mysqli, "CREATE TABLE `ticket_template_tags` (
        `ticket_template_id` int(11) NOT NULL,
        `tag_id` int(11) NOT NULL,
        PRIMARY KEY (`ticket_template_id`,`tag_id`),
        KEY `tag_id` (`tag_id`),
        CONSTRAINT `ticket_template_tags_ibfk_1` FOREIGN KEY (`ticket_template_id`) REFERENCES `ticket_templates` (`ticket_template_id`) ON DELETE CASCADE,
        CONSTRAINT `ticket_template_tags_ibfk_2` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`tag_id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    // Which template a ticket was raised from - for the template's use, from now on
    mysqli_query($mysqli, "ALTER TABLE `tickets` ADD `ticket_template_id` int(11) NOT NULL DEFAULT 0 AFTER `ticket_recurring_ticket_id`, ADD KEY `ticket_template_id` (`ticket_template_id`)");
