<?php

/*
 * ITFlow - Database update to version 2.1.7 (from 2.1.6)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    mysqli_query($mysqli, "CREATE TABLE `document_templates` (
        `document_template_id` INT(11) NOT NULL AUTO_INCREMENT,
        `document_template_name` VARCHAR(200) NOT NULL,
        `document_template_description` TEXT DEFAULT NULL,
        `document_template_content` LONGTEXT NOT NULL,
        `document_template_created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `document_template_updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
        `document_template_archived_at` DATETIME NULL DEFAULT NULL,
        `document_template_created_by` INT(11) NOT NULL DEFAULT 0,
        `document_template_updated_by` INT(11) NOT NULL DEFAULT 0,
        PRIMARY KEY (`document_template_id`)
    )");

    // Copy Document Templates over to new document templates table
    mysqli_query($mysqli, "
        INSERT INTO document_templates (
            document_template_name,
            document_template_description,
            document_template_content,
            document_template_created_at,
            document_template_updated_at,
            document_template_archived_at,
            document_template_created_by,
            document_template_updated_by
        )
        SELECT
            document_name,
            document_description,
            document_content,
            document_created_at,
            document_updated_at,
            document_archived_at,
            document_created_by,
            document_updated_by
        FROM
            documents
        WHERE
            document_template = 1
    ");

    mysqli_query($mysqli, "DELETE FROM documents WHERE document_template = 1");

    mysqli_query($mysqli, "ALTER TABLE `documents` DROP `document_template`");
