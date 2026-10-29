<?php

/*
 * ITFlow - Database update to version 3.1.7 (from 3.1.6)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    // When each API key was last used - Admin > API Keys, to find the ones nothing uses
    mysqli_query($mysqli, "ALTER TABLE `api_keys` ADD `api_key_last_used_at` DATETIME NULL DEFAULT NULL AFTER `api_key_user_id`");
