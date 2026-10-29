<?php

/*
 * ITFlow - Database update to version 2.8.7 (from 2.8.6)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    // Services: retired ones are archived rather than deleted, and each can have a
    // review cadence - when it was last reviewed, and every how many months
    mysqli_query($mysqli, "ALTER TABLE `services` ADD COLUMN `service_archived_at` datetime DEFAULT NULL AFTER `service_accessed_at`");
    mysqli_query($mysqli, "ALTER TABLE `services` ADD COLUMN `service_reviewed_at` datetime DEFAULT NULL AFTER `service_review_due`");
    mysqli_query($mysqli, "ALTER TABLE `services` ADD COLUMN `service_review_interval` int(11) NOT NULL DEFAULT 0 AFTER `service_reviewed_at`");

    // Room for categories like "Line of business", and for how it is backed up and brought back; description, category and notes may be left empty
    mysqli_query($mysqli, "ALTER TABLE `services` MODIFY `service_backup` varchar(500) DEFAULT NULL");
    mysqli_query($mysqli, "ALTER TABLE `services` MODIFY `service_category` varchar(50) NOT NULL DEFAULT ''");
    mysqli_query($mysqli, "ALTER TABLE `services` MODIFY `service_description` varchar(200) NOT NULL DEFAULT ''");
    mysqli_query($mysqli, "ALTER TABLE `services` MODIFY `service_importance` varchar(10) NOT NULL DEFAULT 'Medium'");
    mysqli_query($mysqli, "ALTER TABLE `services` MODIFY `service_notes` mediumtext DEFAULT NULL");

    // A service's links to domains had no foreign keys - clear out what points at nothing first
    mysqli_query($mysqli, "DELETE `service_domains` FROM `service_domains` LEFT JOIN `services` ON `services`.`service_id` = `service_domains`.`service_id`
        LEFT JOIN `domains` ON `domains`.`domain_id` = `service_domains`.`domain_id` WHERE `services`.`service_id` IS NULL OR `domains`.`domain_id` IS NULL");

    // The service link tables let the same pair in twice - keep one of each, then make the pair the key
    $service_link_tables = [
        'service_assets' => 'asset_id',
        'service_certificates' => 'certificate_id',
        'service_contacts' => 'contact_id',
        'service_credentials' => 'credential_id',
        'service_documents' => 'document_id',
        'service_domains' => 'domain_id',
        'service_vendors' => 'vendor_id',
    ];
    foreach ($service_link_tables as $table => $column) {
        mysqli_query($mysqli, "CREATE TEMPORARY TABLE `{$table}_once` SELECT DISTINCT `service_id`, `$column` FROM `$table`");
        mysqli_query($mysqli, "DELETE FROM `$table`");
        mysqli_query($mysqli, "INSERT INTO `$table` (`service_id`, `$column`) SELECT `service_id`, `$column` FROM `{$table}_once`");
        mysqli_query($mysqli, "DROP TEMPORARY TABLE `{$table}_once`");
        mysqli_query($mysqli, "ALTER TABLE `$table` ADD PRIMARY KEY (`service_id`, `$column`)");
    }

    mysqli_query($mysqli, "ALTER TABLE `service_domains` ADD CONSTRAINT `service_domains_ibfk_1` FOREIGN KEY (`service_id`) REFERENCES `services` (`service_id`) ON DELETE CASCADE");
    mysqli_query($mysqli, "ALTER TABLE `service_domains` ADD CONSTRAINT `service_domains_ibfk_2` FOREIGN KEY (`domain_id`) REFERENCES `domains` (`domain_id`) ON DELETE CASCADE");

    // Tickets raised about a service - an outage, a change - whether or not they name one of its assets
    mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `service_tickets` (
        `service_id` int(11) NOT NULL,
        `ticket_id` int(11) NOT NULL,
        PRIMARY KEY (`service_id`, `ticket_id`),
        KEY `ticket_id` (`ticket_id`),
        CONSTRAINT `service_tickets_ibfk_1` FOREIGN KEY (`service_id`) REFERENCES `services` (`service_id`) ON DELETE CASCADE,
        CONSTRAINT `service_tickets_ibfk_2` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`ticket_id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    // The review date was there all along but nothing used it - the lists filter and sort by it now
    mysqli_query($mysqli, "ALTER TABLE `services` ADD INDEX `service_review_due` (`service_review_due`)");
