<?php

/*
 * ITFlow - POST request handler for Admin > Debug
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['repair_database_collation'])) {

    validateCSRFToken();

    // Give every table whose collation isn't db.sql's the one db.sql has
    // (functions/database_collation.php). Each table changed is rebuilt, and a very large
    // one can outlast the browser or a proxy in front of it - so the work is not tied to
    // the request, and what is left if it is cut off is left whole: a table is changed
    // entirely or not at all, and Repair again carries on with the rest.
    ignore_user_abort(true);
    set_time_limit(0);

    $repair = databaseCollationRepair();
    $changed = count($repair['changed']);
    $failed = count($repair['failed']);

    foreach ($repair['failed'] as $table => $why) {
        logApp('Database', 'warning', "Repair collations left the table $table with the collation it had: $why");
    }

    if ($changed) {
        logAudit("Database", "Repair", "$session_name repaired the collation of " . ($changed === 1 ? '1 table' : "$changed tables") . ": " . implode(', ', $repair['changed']));
    }

    if ($failed) {
        flashAlert(($changed ? ($changed === 1 ? '1 table' : "$changed tables") . ' repaired. ' : '')
            . ($failed === 1 ? '1 table' : "$failed tables") . " could not be changed and " . ($failed === 1 ? 'is' : 'are') . " as before - App Logs has why.", 'error');
    } elseif ($changed) {
        flashAlert(($changed === 1 ? '1 table now has' : "$changed tables now have") . " the collation db.sql has for " . ($changed === 1 ? 'it' : 'them') . ".");
    } else {
        flashAlert("Every table already has the collation db.sql has for it.");
    }

    redirect("debug.php#debug-database");
}
