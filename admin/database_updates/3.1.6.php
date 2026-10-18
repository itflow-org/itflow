<?php

/*
 * ITFlow - Database update to version 3.1.6 (from 3.1.5)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    // A role's level for each module - one row each. Rows left behind by a
    // role or module that is gone, or of no access, are dropped; a module a
    // role has twice keeps its highest level, held to 1 to 3. Then a primary
    // key, so it can only have one.
    mysqli_query($mysqli, "DELETE FROM user_role_permissions
        WHERE user_role_permission_level <= 0
        OR user_role_id NOT IN (SELECT role_id FROM user_roles)
        OR module_id NOT IN (SELECT module_id FROM modules)");
    mysqli_query($mysqli, "CREATE TEMPORARY TABLE role_permissions_kept AS
        SELECT user_role_id, module_id, LEAST(MAX(user_role_permission_level), 3) AS kept_level
        FROM user_role_permissions GROUP BY user_role_id, module_id");
    mysqli_query($mysqli, "DELETE FROM user_role_permissions");
    mysqli_query($mysqli, "INSERT INTO user_role_permissions (user_role_id, module_id, user_role_permission_level)
        SELECT user_role_id, module_id, kept_level FROM role_permissions_kept");
    mysqli_query($mysqli, "DROP TEMPORARY TABLE role_permissions_kept");
    mysqli_query($mysqli, "ALTER TABLE `user_role_permissions` ADD PRIMARY KEY (`user_role_id`, `module_id`)");
