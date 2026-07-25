<?php

/*
 * ITFlow - Database update to version 2.4.7 (from 2.4.6)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    // API keys now run as a user and inherit that user's RBAC (module / operation / client).
    // Existing keys predate this and can't be safely mapped to a user, so they are removed
    // and must be recreated with an owning user.
    mysqli_query($mysqli, "DELETE FROM api_keys");

    // Tie keys to a user; client access now derives from that user, so the old per-key
    // client scope is removed.
    mysqli_query($mysqli, "ALTER TABLE `api_keys` ADD COLUMN `api_key_user_id` INT(11) NOT NULL DEFAULT 0");
    mysqli_query($mysqli, "ALTER TABLE `api_keys` DROP COLUMN `api_key_client_id`");
