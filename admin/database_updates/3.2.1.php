<?php

/*
 * ITFlow - Database update to version 3.2.1 (from 3.2.0)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    // A ticket template offered in the client portal as a request form: what
    // the client sees it as (a title, a line about it and an icon), who may
    // use it (everyone with portal access, or the primary and technical
    // contacts only), and which clients see it (all, or the ones in
    // ticket_template_portal_clients)
    mysqli_query($mysqli, "ALTER TABLE `ticket_templates`
        ADD `ticket_template_portal` tinyint(1) NOT NULL DEFAULT 0 AFTER `ticket_template_due_hours`,
        ADD `ticket_template_portal_title` varchar(200) DEFAULT NULL AFTER `ticket_template_portal`,
        ADD `ticket_template_portal_description` varchar(500) DEFAULT NULL AFTER `ticket_template_portal_title`,
        ADD `ticket_template_portal_icon` varchar(50) DEFAULT NULL AFTER `ticket_template_portal_description`,
        ADD `ticket_template_portal_who` varchar(20) NOT NULL DEFAULT 'everyone' AFTER `ticket_template_portal_icon`,
        ADD `ticket_template_portal_all_clients` tinyint(1) NOT NULL DEFAULT 1 AFTER `ticket_template_portal_who`");

    mysqli_query($mysqli, "CREATE TABLE `ticket_template_portal_clients` (
        `ticket_template_id` int(11) NOT NULL,
        `client_id` int(11) NOT NULL,
        PRIMARY KEY (`ticket_template_id`,`client_id`),
        KEY `client_id` (`client_id`),
        CONSTRAINT `ticket_template_portal_clients_ibfk_1` FOREIGN KEY (`ticket_template_id`) REFERENCES `ticket_templates` (`ticket_template_id`) ON DELETE CASCADE,
        CONSTRAINT `ticket_template_portal_clients_ibfk_2` FOREIGN KEY (`client_id`) REFERENCES `clients` (`client_id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    // Whether the client is asked a fill-in on the portal form and sees the
    // answer on their ticket - off, it is the agents' to answer
    mysqli_query($mysqli, "ALTER TABLE `ticket_template_fill_ins` ADD `fill_in_client` tinyint(1) NOT NULL DEFAULT 1 AFTER `fill_in_required`");
    mysqli_query($mysqli, "ALTER TABLE `ticket_fill_ins` ADD `ticket_fill_in_client` tinyint(1) NOT NULL DEFAULT 1 AFTER `ticket_fill_in_required`");
    mysqli_query($mysqli, "ALTER TABLE `recurring_ticket_fill_ins` ADD `recurring_ticket_fill_in_client` tinyint(1) NOT NULL DEFAULT 1 AFTER `recurring_ticket_fill_in_required`");
