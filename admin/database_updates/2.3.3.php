<?php

/*
 * ITFlow - Database update to version 2.3.3 (from 2.3.2)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    mysqli_query($mysqli, "ALTER TABLE settings
        ADD `config_imap_provider` ENUM('standard_imap','google_oauth','microsoft_oauth') NULL DEFAULT NULL AFTER `config_mail_from_name`,
        ADD `config_mail_oauth_client_id` VARCHAR(255) NULL AFTER `config_imap_provider`,
        ADD `config_mail_oauth_client_secret` VARCHAR(255) NULL AFTER `config_mail_oauth_client_id`,
        ADD `config_mail_oauth_tenant_id` VARCHAR(255) NULL AFTER `config_mail_oauth_client_secret`,
        ADD `config_mail_oauth_refresh_token` TEXT NULL AFTER `config_mail_oauth_tenant_id`,
        ADD `config_mail_oauth_access_token` TEXT NULL AFTER `config_mail_oauth_refresh_token`,
        ADD `config_mail_oauth_access_token_expires_at` DATETIME NULL AFTER `config_mail_oauth_access_token`
    ");
