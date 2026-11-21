<?php

/*
 * ITFlow - Database update to version 3.9.5 (from 3.9.4)
 * Included by admin/database_updates.php - do not access directly
 *
 * The Knowledge Base - articles written once for every client, not one client's
 * documents (functions/kb.php):
 *   kb_articles - an article: its title, a line saying what it is for, its
 *     content (and the plain text search runs over), its category (categories
 *     of the 'kb' kind), when it was published (none: a draft), how often it
 *     is reviewed and when it last was, and who made and changed it.
 *   kb_article_versions - what an article said before each edit.
 *   kb_article_tags - its tags (tag_type 13), gone with the article or the tag.
 *   settings.config_module_enable_kb - the Knowledge Base module switch, on.
 *
 * Re-runnable: each table and column is added only where it isn't there yet.
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `kb_articles` (
        `kb_article_id` int(11) NOT NULL AUTO_INCREMENT,
        `kb_article_title` varchar(200) NOT NULL,
        `kb_article_summary` varchar(255) DEFAULT NULL,
        `kb_article_content` longtext NOT NULL,
        `kb_article_content_raw` longtext NOT NULL,
        `kb_article_category_id` int(11) NOT NULL DEFAULT 0,
        `kb_article_published_at` datetime DEFAULT NULL,
        `kb_article_review_months` int(11) NOT NULL DEFAULT 0,
        `kb_article_reviewed_at` datetime DEFAULT NULL,
        `kb_article_reviewed_by` int(11) NOT NULL DEFAULT 0,
        `kb_article_created_at` datetime NOT NULL DEFAULT current_timestamp(),
        `kb_article_created_by` int(11) NOT NULL DEFAULT 0,
        `kb_article_updated_at` datetime DEFAULT NULL,
        `kb_article_updated_by` int(11) NOT NULL DEFAULT 0,
        `kb_article_archived_at` datetime DEFAULT NULL,
        PRIMARY KEY (`kb_article_id`),
        KEY `kb_article_category_id` (`kb_article_category_id`),
        FULLTEXT KEY `kb_article_content_raw` (`kb_article_content_raw`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `kb_article_versions` (
        `kb_article_version_id` int(11) NOT NULL AUTO_INCREMENT,
        `kb_article_version_title` varchar(200) NOT NULL,
        `kb_article_version_summary` varchar(255) DEFAULT NULL,
        `kb_article_version_content` longtext NOT NULL,
        `kb_article_version_created_at` datetime NOT NULL DEFAULT current_timestamp(),
        `kb_article_version_created_by` int(11) NOT NULL DEFAULT 0,
        `kb_article_version_kb_article_id` int(11) NOT NULL,
        PRIMARY KEY (`kb_article_version_id`),
        KEY `kb_article_version_kb_article_id` (`kb_article_version_kb_article_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `kb_article_tags` (
        `kb_article_id` int(11) NOT NULL,
        `tag_id` int(11) NOT NULL,
        PRIMARY KEY (`kb_article_id`, `tag_id`),
        KEY `tag_id` (`tag_id`),
        CONSTRAINT `kb_article_tags_ibfk_1` FOREIGN KEY (`kb_article_id`) REFERENCES `kb_articles` (`kb_article_id`) ON DELETE CASCADE,
        CONSTRAINT `kb_article_tags_ibfk_2` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`tag_id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    $exists = mysqli_query($mysqli, "SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'settings' AND COLUMN_NAME = 'config_module_enable_kb' LIMIT 1");
    if (!$exists || !mysqli_num_rows($exists)) {
        mysqli_query($mysqli, "ALTER TABLE `settings` ADD `config_module_enable_kb` tinyint(1) NOT NULL DEFAULT 1 AFTER `config_module_enable_calendar`");
    }
