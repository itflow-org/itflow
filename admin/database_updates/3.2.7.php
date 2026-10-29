<?php

/*
 * ITFlow - Database update to version 3.2.7 (from 3.2.6)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    // Who should have a document from a template - 0 no one in particular, 1
    // every client, 2 the clients with one of its tags - and how often the
    // documents made from it are reviewed, in months (0 never)
    mysqli_query($mysqli, "ALTER TABLE `document_templates`
        ADD `document_template_required` tinyint(1) NOT NULL DEFAULT 0 AFTER `document_template_content`,
        ADD `document_template_review_months` int(11) NOT NULL DEFAULT 0 AFTER `document_template_required`");

    mysqli_query($mysqli, "CREATE TABLE `document_template_required_tags` (
        `document_template_id` int(11) NOT NULL,
        `tag_id` int(11) NOT NULL,
        PRIMARY KEY (`document_template_id`,`tag_id`),
        KEY `tag_id` (`tag_id`),
        CONSTRAINT `document_template_required_tags_ibfk_1` FOREIGN KEY (`document_template_id`) REFERENCES `document_templates` (`document_template_id`) ON DELETE CASCADE,
        CONSTRAINT `document_template_required_tags_ibfk_2` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`tag_id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    // When a document was last marked as reviewed, and by whom - an edit counts as a review too
    mysqli_query($mysqli, "ALTER TABLE `documents`
        ADD `document_reviewed_at` datetime DEFAULT NULL AFTER `document_accessed_at`,
        ADD `document_reviewed_by` int(11) NOT NULL DEFAULT 0 AFTER `document_updated_by`");
