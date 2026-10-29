<?php

/*
 * ITFlow - Database update to version 3.1.4 (from 3.1.3)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    // Tags on licenses (8), services (9), networks (10), documents (11) and
    // tickets (12) - one link table each, like the other seven kinds, gone
    // with the record or the tag
    $tag_links = [
        'software_tags' => ['software_id', 'software'],
        'service_tags' => ['service_id', 'services'],
        'network_tags' => ['network_id', 'networks'],
        'document_tags' => ['document_id', 'documents'],
        'ticket_tags' => ['ticket_id', 'tickets'],
    ];
    foreach ($tag_links as $tag_link_table => [$tag_link_column, $tag_link_parent]) {
        mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `$tag_link_table` (
            `$tag_link_column` int(11) NOT NULL,
            `tag_id` int(11) NOT NULL,
            PRIMARY KEY (`$tag_link_column`, `tag_id`),
            KEY `tag_id` (`tag_id`),
            CONSTRAINT `{$tag_link_table}_ibfk_1` FOREIGN KEY (`$tag_link_column`) REFERENCES `$tag_link_parent` (`$tag_link_column`) ON DELETE CASCADE,
            CONSTRAINT `{$tag_link_table}_ibfk_2` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`tag_id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    }
