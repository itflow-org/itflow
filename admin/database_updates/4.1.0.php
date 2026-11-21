<?php

/*
 * ITFlow - Database update to version 4.1.0 (from 4.0.9)
 * Included by admin/database_updates.php - do not access directly
 *
 * The audit log calls each kind of record what the rest of ITFlow calls it:
 * logs.log_type Software becomes License, Software Template License Template,
 * Revenue Income (the other income records were already logged as Income) and
 * Account Transfer Transfer (the API already logged transfers as Transfer).
 * Nothing else in an entry changes.
 *
 * Re-runnable: an entry that already has its new name is left alone.
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    foreach (['Software' => 'License', 'Software Template' => 'License Template', 'Revenue' => 'Income', 'Account Transfer' => 'Transfer'] as $old_type => $new_type) {
        mysqli_query($mysqli, "UPDATE `logs` SET `log_type` = '$new_type' WHERE `log_type` = '$old_type'");
    }
