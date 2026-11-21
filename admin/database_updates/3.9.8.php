<?php

/*
 * ITFlow - Database update to version 3.9.8 (from 3.9.7)
 * Included by admin/database_updates.php - do not access directly
 *
 *   kb_article_files - files that go with a Knowledge Base article: the name
 *     it was uploaded as, the unguessable name it is kept under in
 *     uploads/kb/<article>/files/, its size, and who added it when
 *     (functions/kb.php).
 *   documents.document_review_months - how often a document is reviewed, set
 *     on the document: none (the default) goes by its template, 0 is never
 *     (functions/document_template.php).
 *
 * Re-runnable: each table and column is added only where it isn't there yet.
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    $itflow_column = function ($table, $column) use ($mysqli) {
        $result = mysqli_query($mysqli, "SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$table' AND COLUMN_NAME = '$column' LIMIT 1");
        return $result && mysqli_num_rows($result) > 0;
    };

    mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `kb_article_files` (
        `kb_article_file_id` int(11) NOT NULL AUTO_INCREMENT,
        `kb_article_file_name` varchar(255) NOT NULL,
        `kb_article_file_reference_name` varchar(255) NOT NULL,
        `kb_article_file_size` bigint(20) unsigned NOT NULL DEFAULT 0,
        `kb_article_file_created_at` datetime NOT NULL DEFAULT current_timestamp(),
        `kb_article_file_created_by` int(11) NOT NULL DEFAULT 0,
        `kb_article_file_kb_article_id` int(11) NOT NULL,
        PRIMARY KEY (`kb_article_file_id`),
        KEY `kb_article_file_kb_article_id` (`kb_article_file_kb_article_id`),
        CONSTRAINT `kb_article_files_ibfk_1` FOREIGN KEY (`kb_article_file_kb_article_id`) REFERENCES `kb_articles` (`kb_article_id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    if (!$itflow_column('documents', 'document_review_months')) {
        mysqli_query($mysqli, "ALTER TABLE `documents` ADD `document_review_months` int(11) DEFAULT NULL AFTER `document_reviewed_at`");
    }
