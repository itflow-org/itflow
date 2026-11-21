<?php

/*
 * ITFlow - Database update to version 3.7.0 (from 3.6.9)
 * Included by admin/database_updates.php - do not access directly
 *
 * API keys in the address (functions/api_keys.php):
 *   api_keys.api_key_address_at - when a key last came in a call's address (?api_key=), where web
 *     server access logs keep it; Admin > API Keys flags it
 *   settings.config_api_key_in_address - whether the API takes a key there at all (Admin > Settings >
 *     Security). On where a key that still works was here already, so nothing using one breaks;
 *     off on every other install, and on new ones
 *
 * Re-runnable: each column is made only where it isn't there yet, and the setting is only set
 * when its column is made.
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    $itflow_column_exists = mysqli_query($mysqli, "SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'api_keys' AND COLUMN_NAME = 'api_key_address_at' LIMIT 1");
    if (!$itflow_column_exists || mysqli_num_rows($itflow_column_exists) === 0) {
        mysqli_query($mysqli, "ALTER TABLE `api_keys` ADD `api_key_address_at` datetime DEFAULT NULL AFTER `api_key_last_used_at`");
    }

    $itflow_column_exists = mysqli_query($mysqli, "SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'settings' AND COLUMN_NAME = 'config_api_key_in_address' LIMIT 1");
    if (!$itflow_column_exists || mysqli_num_rows($itflow_column_exists) === 0) {
        mysqli_query($mysqli, "ALTER TABLE `settings` ADD `config_api_key_in_address` tinyint(1) NOT NULL DEFAULT 0 AFTER `config_login_alert_failures`");
        mysqli_query($mysqli, "UPDATE `settings` SET `config_api_key_in_address` = 1 WHERE EXISTS (SELECT 1 FROM `api_keys` WHERE `api_key_expire` > CURDATE())");
    }
