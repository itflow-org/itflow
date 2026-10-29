<?php

/*
 * ITFlow - Database update to version 2.8.2 (from 2.8.1)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    // Vendor tags (tag type 6) - ISP, Telecom, Software, Distributor and so on,
    // the same as clients, locations, contacts, credentials and assets have
    mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `vendor_tags` (
        `vendor_id` int(11) NOT NULL,
        `tag_id` int(11) NOT NULL,
        PRIMARY KEY (`vendor_id`, `tag_id`),
        KEY `tag_id` (`tag_id`),
        CONSTRAINT `vendor_tags_ibfk_1` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`vendor_id`) ON DELETE CASCADE,
        CONSTRAINT `vendor_tags_ibfk_2` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`tag_id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    // The account manager's direct line and email, beside their name
    mysqli_query($mysqli, "ALTER TABLE `vendors`
        ADD COLUMN `vendor_contact_phone_country_code` varchar(10) DEFAULT NULL AFTER `vendor_contact_name`,
        ADD COLUMN `vendor_contact_phone` varchar(200) DEFAULT NULL AFTER `vendor_contact_phone_country_code`,
        ADD COLUMN `vendor_contact_email` varchar(200) DEFAULT NULL AFTER `vendor_contact_phone`
    ");

    // Vendors deleted before deletes cleared their references left assets,
    // licenses, tickets and domains pointing at an id that no longer exists -
    // point those at no vendor
    mysqli_query($mysqli, "UPDATE `assets` LEFT JOIN `vendors` ON `vendor_id` = `asset_vendor_id` SET `asset_vendor_id` = 0 WHERE `asset_vendor_id` > 0 AND `vendor_id` IS NULL");
    mysqli_query($mysqli, "UPDATE `software` LEFT JOIN `vendors` ON `vendor_id` = `software_vendor_id` SET `software_vendor_id` = 0 WHERE `software_vendor_id` > 0 AND `vendor_id` IS NULL");
    mysqli_query($mysqli, "UPDATE `tickets` LEFT JOIN `vendors` ON `vendor_id` = `ticket_vendor_id` SET `ticket_vendor_id` = 0 WHERE `ticket_vendor_id` > 0 AND `vendor_id` IS NULL");
    foreach (['domain_registrar', 'domain_webhost', 'domain_dnshost', 'domain_mailhost'] as $domain_vendor_column) {
        mysqli_query($mysqli, "UPDATE `domains` LEFT JOIN `vendors` ON `vendor_id` = `$domain_vendor_column` SET `$domain_vendor_column` = 0 WHERE `$domain_vendor_column` > 0 AND `vendor_id` IS NULL");
    }
