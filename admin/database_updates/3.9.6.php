<?php

/*
 * ITFlow - Database update to version 3.9.6 (from 3.9.5)
 * Included by admin/database_updates.php - do not access directly
 *
 * Who can read a Knowledge Base article (functions/kb.php):
 *   kb_articles.kb_article_visibility - 'agents' (only agents), 'clients' (in
 *     the client portal, of the clients below) or 'public' (every client's
 *     portal, and the public help center when that is on).
 *   kb_articles.kb_article_all_clients - for 'clients': every client.
 *   kb_article_client_tags - for 'clients': the clients with these client tags.
 *   kb_article_clients - for 'clients': these clients.
 *   settings.config_kb_public_enable - the public help center, off.
 *
 * Re-runnable: each table and column is added only where it isn't there yet.
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    $itflow_column = function ($table, $column) use ($mysqli) {
        $result = mysqli_query($mysqli, "SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$table' AND COLUMN_NAME = '$column' LIMIT 1");
        return $result && mysqli_num_rows($result) > 0;
    };

    if (!$itflow_column('kb_articles', 'kb_article_visibility')) {
        mysqli_query($mysqli, "ALTER TABLE `kb_articles` ADD `kb_article_visibility` varchar(20) NOT NULL DEFAULT 'agents' AFTER `kb_article_published_at`");
    }
    if (!$itflow_column('kb_articles', 'kb_article_all_clients')) {
        mysqli_query($mysqli, "ALTER TABLE `kb_articles` ADD `kb_article_all_clients` tinyint(1) NOT NULL DEFAULT 0 AFTER `kb_article_visibility`");
    }

    mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `kb_article_client_tags` (
        `kb_article_id` int(11) NOT NULL,
        `tag_id` int(11) NOT NULL,
        PRIMARY KEY (`kb_article_id`, `tag_id`),
        KEY `tag_id` (`tag_id`),
        CONSTRAINT `kb_article_client_tags_ibfk_1` FOREIGN KEY (`kb_article_id`) REFERENCES `kb_articles` (`kb_article_id`) ON DELETE CASCADE,
        CONSTRAINT `kb_article_client_tags_ibfk_2` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`tag_id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `kb_article_clients` (
        `kb_article_id` int(11) NOT NULL,
        `client_id` int(11) NOT NULL,
        PRIMARY KEY (`kb_article_id`, `client_id`),
        KEY `client_id` (`client_id`),
        CONSTRAINT `kb_article_clients_ibfk_1` FOREIGN KEY (`kb_article_id`) REFERENCES `kb_articles` (`kb_article_id`) ON DELETE CASCADE,
        CONSTRAINT `kb_article_clients_ibfk_2` FOREIGN KEY (`client_id`) REFERENCES `clients` (`client_id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    if (!$itflow_column('settings', 'config_kb_public_enable')) {
        mysqli_query($mysqli, "ALTER TABLE `settings` ADD `config_kb_public_enable` tinyint(1) NOT NULL DEFAULT 0 AFTER `config_module_enable_kb`");
    }
