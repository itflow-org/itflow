<?php

/*
 * ITFlow - Database update to version 3.9.2 (from 3.9.1)
 * Included by admin/database_updates.php - do not access directly
 *
 * Microsoft 365 mail through Microsoft Graph (functions/mail_graph.php) - what
 * Graph last said about the mailbox connected on Admin > Settings > Mail > OAuth:
 *   settings.config_mail_graph_token - the Graph token, NULL when there is none.
 *   settings.config_mail_graph_token_expires_at - when it runs out, or when an
 *     app that couldn't use Graph is asked again; NULL - not asked yet.
 *   settings.config_mail_graph_scopes - the Graph permissions it carries:
 *     Mail.Send sends through Graph, Mail.ReadWrite reads the mailbox through it.
 *
 * Re-runnable: each column is added only where it isn't there yet.
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    $itflow_column = function ($table, $column) use ($mysqli) {
        $result = mysqli_query($mysqli, "SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$table' AND COLUMN_NAME = '$column' LIMIT 1");
        return $result && mysqli_num_rows($result) > 0;
    };

    if (!$itflow_column('settings', 'config_mail_graph_token')) {
        mysqli_query($mysqli, "ALTER TABLE `settings` ADD `config_mail_graph_token` text DEFAULT NULL AFTER `config_mail_oauth_access_token_expires_at`");
    }
    if (!$itflow_column('settings', 'config_mail_graph_token_expires_at')) {
        mysqli_query($mysqli, "ALTER TABLE `settings` ADD `config_mail_graph_token_expires_at` datetime DEFAULT NULL AFTER `config_mail_graph_token`");
    }
    if (!$itflow_column('settings', 'config_mail_graph_scopes')) {
        mysqli_query($mysqli, "ALTER TABLE `settings` ADD `config_mail_graph_scopes` text DEFAULT NULL AFTER `config_mail_graph_token_expires_at`");
    }
