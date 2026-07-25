<?php

/*
 * ITFlow - Database update to version 2.4.6 (from 2.4.5)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    // Add Allow Deny Client Access to the Enforce Client Permissions
    mysqli_query($mysqli, "ALTER TABLE `user_client_permissions` ADD COLUMN `permission_type` ENUM('allow','deny') NOT NULL DEFAULT 'allow'");
