<?php

/*
 * ITFlow - Database update to version 2.8.3 (from 2.8.2)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    // Domain tags (tag type 7) - Primary, Parked, Client-owned, Managed DNS and so on
    mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `domain_tags` (
        `domain_id` int(11) NOT NULL,
        `tag_id` int(11) NOT NULL,
        PRIMARY KEY (`domain_id`, `tag_id`),
        KEY `tag_id` (`tag_id`),
        CONSTRAINT `domain_tags_ibfk_1` FOREIGN KEY (`domain_id`) REFERENCES `domains` (`domain_id`) ON DELETE CASCADE,
        CONSTRAINT `domain_tags_ibfk_2` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`tag_id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    // Whether it renews by itself at the registrar, its DMARC record and the
    // DKIM selectors found, and when the lookups last came back with something
    mysqli_query($mysqli, "ALTER TABLE `domains`
        ADD COLUMN `domain_auto_renew` tinyint(1) NOT NULL DEFAULT 0 AFTER `domain_expire`,
        ADD COLUMN `domain_dmarc` text DEFAULT NULL AFTER `domain_txt`,
        ADD COLUMN `domain_dkim` varchar(255) DEFAULT NULL AFTER `domain_dmarc`,
        ADD COLUMN `domain_refreshed_at` datetime DEFAULT NULL AFTER `domain_accessed_at`
    ");

    // Domains deleted before deletes cleared their references left services and
    // certificates pointing at an id that no longer exists
    mysqli_query($mysqli, "DELETE `service_domains` FROM `service_domains` LEFT JOIN `domains` ON `domains`.`domain_id` = `service_domains`.`domain_id` WHERE `domains`.`domain_id` IS NULL");
    mysqli_query($mysqli, "UPDATE `certificates` LEFT JOIN `domains` ON `domain_id` = `certificate_domain_id` SET `certificate_domain_id` = 0 WHERE `certificate_domain_id` > 0 AND `domain_id` IS NULL");
