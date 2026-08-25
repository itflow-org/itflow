<?php

/*
 * ITFlow - Database update to version 2.7.2 (from 2.7.1)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    // Documented IP addresses, hanging off the network (subnet) they belong to.
    // Addresses are stored canonicalised (inet_ntop form) so the UNIQUE key
    // below actually catches the same address entered two different ways.
    //
    // The foreign key means deleting a network takes its addresses with it -
    // agent/post/network.php's delete handler needs no change.
    mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `network_ips` (
        `ip_id` int(11) NOT NULL AUTO_INCREMENT,
        `ip_address` varchar(45) NOT NULL,
        `ip_hostname` varchar(200) DEFAULT NULL,
        `ip_description` varchar(200) DEFAULT NULL,
        `ip_created_at` datetime NOT NULL DEFAULT current_timestamp(),
        `ip_updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
        `ip_network_id` int(11) NOT NULL,
        PRIMARY KEY (`ip_id`),
        UNIQUE KEY `ip_network_address` (`ip_network_id`,`ip_address`),
        CONSTRAINT `network_ips_ibfk_1` FOREIGN KEY (`ip_network_id`) REFERENCES `networks` (`network_id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
