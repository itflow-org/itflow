<?php

/*
 * ITFlow - Database update to version 3.1.8 (from 3.1.7)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    // The built-in Accountant role gets Full on Financial, so deleting an
    // expense, income, transfer or payment entered by mistake is theirs to do.
    // Only where it is still as it shipped - named Accountant, not an admin
    // role, Clients, Support, Sales and Reports at Read, Financial at Modify
    // and no Credentials. A role an admin has changed is left as they made it.
    $accountant = mysqli_fetch_row(mysqli_query($mysqli, "SELECT role_id FROM user_roles WHERE role_id = 1 AND role_name = 'Accountant' AND role_is_admin = 0"));
    if ($accountant) {
        $accountant_levels = [];
        $sql_accountant = mysqli_query($mysqli, "SELECT module_name, user_role_permission_level FROM user_role_permissions
            JOIN modules ON modules.module_id = user_role_permissions.module_id WHERE user_role_id = 1");
        while ($accountant_row = mysqli_fetch_row($sql_accountant)) {
            $accountant_levels[$accountant_row[0]] = intval($accountant_row[1]);
        }
        ksort($accountant_levels);
        if ($accountant_levels === ['module_client' => 1, 'module_financial' => 2, 'module_reporting' => 1, 'module_sales' => 1, 'module_support' => 1]) {
            mysqli_query($mysqli, "UPDATE user_role_permissions SET user_role_permission_level = 3
                WHERE user_role_id = 1 AND module_id = (SELECT module_id FROM modules WHERE module_name = 'module_financial')");
        }
    }
