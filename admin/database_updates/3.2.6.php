<?php

/*
 * ITFlow - Database update to version 3.2.6 (from 3.2.5)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    // What a document template asks for when a document is made from it - its
    // fill-ins, each answer going in wherever its [Name] is
    mysqli_query($mysqli, "CREATE TABLE `document_template_fill_ins` (
        `document_fill_in_id` int(11) NOT NULL AUTO_INCREMENT,
        `document_fill_in_document_template_id` int(11) NOT NULL,
        `document_fill_in_name` varchar(50) NOT NULL,
        `document_fill_in_type` varchar(10) NOT NULL DEFAULT 'text',
        `document_fill_in_options` text DEFAULT NULL,
        `document_fill_in_required` tinyint(1) NOT NULL DEFAULT 0,
        `document_fill_in_help` varchar(255) DEFAULT NULL,
        `document_fill_in_order` int(11) NOT NULL DEFAULT 0,
        PRIMARY KEY (`document_fill_in_id`),
        UNIQUE KEY `document_fill_in_template_name` (`document_fill_in_document_template_id`,`document_fill_in_name`),
        CONSTRAINT `document_template_fill_ins_ibfk_1` FOREIGN KEY (`document_fill_in_document_template_id`) REFERENCES `document_templates` (`document_template_id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
