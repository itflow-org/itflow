<?php

/*
 * ITFlow - Database update to version 3.9.7 (from 3.9.6)
 * Included by admin/database_updates.php - do not access directly
 *
 * Keeping the Knowledge Base useful (functions/kb.php):
 *   kb_articles.kb_article_views - how many times it was read outside ITFlow,
 *     in the client portal or the public help center, once a visit.
 *   kb_article_feedback - "Was this helpful?" from those readers: yes or no,
 *     what was missing, and who - a contact and their client, or someone on
 *     the public help center (with their IP address, to stop a flood) - and
 *     when an agent dealt with it.
 *   kb_search_misses - what readers searched Help or the help center for and
 *     found nothing: the words, where, and how many times.
 *
 * Re-runnable: each table and column is added only where it isn't there yet.
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    $itflow_column = function ($table, $column) use ($mysqli) {
        $result = mysqli_query($mysqli, "SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$table' AND COLUMN_NAME = '$column' LIMIT 1");
        return $result && mysqli_num_rows($result) > 0;
    };

    if (!$itflow_column('kb_articles', 'kb_article_views')) {
        mysqli_query($mysqli, "ALTER TABLE `kb_articles` ADD `kb_article_views` int(11) NOT NULL DEFAULT 0 AFTER `kb_article_reviewed_by`");
    }

    mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `kb_article_feedback` (
        `kb_article_feedback_id` int(11) NOT NULL AUTO_INCREMENT,
        `kb_article_feedback_helpful` tinyint(1) NOT NULL,
        `kb_article_feedback_comment` varchar(500) DEFAULT NULL,
        `kb_article_feedback_source` varchar(10) NOT NULL,
        `kb_article_feedback_contact_id` int(11) NOT NULL DEFAULT 0,
        `kb_article_feedback_client_id` int(11) NOT NULL DEFAULT 0,
        `kb_article_feedback_ip` varchar(45) DEFAULT NULL,
        `kb_article_feedback_created_at` datetime NOT NULL DEFAULT current_timestamp(),
        `kb_article_feedback_dealt_at` datetime DEFAULT NULL,
        `kb_article_feedback_dealt_by` int(11) NOT NULL DEFAULT 0,
        `kb_article_feedback_kb_article_id` int(11) NOT NULL,
        PRIMARY KEY (`kb_article_feedback_id`),
        KEY `kb_article_feedback_kb_article_id` (`kb_article_feedback_kb_article_id`),
        KEY `kb_article_feedback_ip` (`kb_article_feedback_ip`, `kb_article_feedback_created_at`),
        CONSTRAINT `kb_article_feedback_ibfk_1` FOREIGN KEY (`kb_article_feedback_kb_article_id`) REFERENCES `kb_articles` (`kb_article_id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `kb_search_misses` (
        `kb_search_miss_id` int(11) NOT NULL AUTO_INCREMENT,
        `kb_search_miss_query` varchar(100) NOT NULL,
        `kb_search_miss_source` varchar(10) NOT NULL,
        `kb_search_miss_client_id` int(11) NOT NULL DEFAULT 0,
        `kb_search_miss_searches` int(11) NOT NULL DEFAULT 1,
        `kb_search_miss_first_at` datetime NOT NULL DEFAULT current_timestamp(),
        `kb_search_miss_last_at` datetime NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`kb_search_miss_id`),
        UNIQUE KEY `kb_search_miss_query` (`kb_search_miss_query`, `kb_search_miss_source`, `kb_search_miss_client_id`),
        KEY `kb_search_miss_last_at` (`kb_search_miss_last_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
